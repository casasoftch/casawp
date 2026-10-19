<?php
/** Standalone regression checks: php tests/import-logging.php */

require_once dirname(__DIR__) . '/classes/ImportLogger.php';
require_once dirname(__DIR__) . '/classes/Import.php';

$mode = $argv[1] ?? '';
$directory = $argv[2] ?? sys_get_temp_dir() . '/casawp-logging-' . uniqid();
if (!is_dir($directory)) {
    mkdir($directory, 0700, true);
}
define('CASASYNC_CUR_UPLOAD_BASEDIR', $directory);
ini_set('error_log', $directory . '/php-error.log');
$options = ['casawp_current_run_id' => 12345, 'casawp_batch_size_override' => 2];
$scheduled = [];
$schedule_result = 73;
$cache_suspended = false;
$log_level = 'info';

function get_date_from_gmt($date, $format) { return gmdate($format); }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function wp_suspend_cache_addition($suspend) { $GLOBALS['cache_suspended'] = $suspend; }
function as_next_scheduled_action(...$args) { return false; }
function as_schedule_single_action($time, $hook, $args, $group) {
    $GLOBALS['scheduled'][] = compact('time', 'hook', 'args', 'group');
    return $GLOBALS['schedule_result'];
}
function wp_reset_postdata() {}
function wp_update_post($data, $return_error = false) { return $data['ID']; }
function is_wp_error($value) { return false; }
function apply_filters($hook, $default, ...$args) {
    if ($hook === 'casawp_import_log_level') { return $GLOBALS['log_level']; }
    return $default;
}

class WP_Query {
    public function __construct($args) {}
    public function have_posts() { return true; }
    public function the_post() { $GLOBALS['post'] = (object) ['ID' => 91]; }
}

$wpdb = new class {
    public $postmeta = 'wp_postmeta';
    public function prepare($sql, ...$args) { return $sql; }
    public function get_var($sql) { return 0; }
};

class LoggingTestImport extends \casawp\Import {
    public function getMainLang() { return 'de'; }
    public function hasWPML() { return false; }
    public function reactivate_properties($ids) {}
    public function finalize_import_cleanup(): bool { return true; }
    public function updateInsertWPMLconnection($post, string $lang, string $identifier): void {}
    public function property2Array($property) {
        if ((string) $property['id'] === 'conversion-error') {
            throw new RuntimeException('Invalid property date');
        }
        return [
            'exportproperty_id' => (string) $property['id'],
            'address' => ['locality' => 'Test'],
            'offers' => [['lang' => 'de']],
        ];
    }
    public function updateOffer($id, $position, $property, $offer, $post) {
        if ($property['exportproperty_id'] === 'bad-offer') {
            throw new RuntimeException('Simulated offer failure');
        }
        $this->addToLog('Offer saved for regression test.');
    }
}

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function entries() {
    $path = CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/logs/' . gmdate('Ym') . '.log';
    if (!is_file($path)) { return []; }
    return array_map(static function ($line) {
        $entry = json_decode($line, true);
        check(is_array($entry), 'Every log entry must be valid JSON');
        return $entry;
    }, file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
}
function eventEntries($event) {
    return array_values(array_filter(entries(), static function ($entry) use ($event) {
        return ($entry['_meta']['event'] ?? '') === $event;
    }));
}
function writeFeed($xml) {
    $path = CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import';
    if (!is_dir($path)) { mkdir($path, 0700, true); }
    file_put_contents($path . '/data.xml', $xml);
}
function child($mode) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($mode) . ' ' . escapeshellarg(CASASYNC_CUR_UPLOAD_BASEDIR);
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($process), 'Could not start test subprocess');
    foreach ($pipes as $pipe) { fclose($pipe); }
    return $process;
}
function removeTree($path) {
    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') { continue; }
        $child = $path . '/' . $name;
        is_dir($child) ? removeTree($child) : unlink($child);
    }
    rmdir($path);
}

$logger = new \casawp\ImportLogger(static function () { return 12345; });
if ($mode === 'append') {
    for ($i = 0; $i < 50; $i++) { $logger->write('Concurrent entry.', ['event' => 'test.concurrent']); }
    exit;
}
if ($mode === 'fatal' || $mode === 'memory-fatal') {
    $logger->run('properties_batch', ['batch_number' => 9], function () use ($logger, $mode) {
        $logger->setStage('offer_media', ['property_id' => 'fatal-property']);
        if ($mode === 'memory-fatal') {
            ini_set('memory_limit', '24M');
            $blocks = [];
            while (true) { $blocks[] = str_repeat('x', 1048576); }
        }
        trigger_error('Simulated fatal import error', E_USER_ERROR);
    });
    exit;
}
if ($mode === 'unrelated-fatal') {
    $logger->run('finished_import', [], static function () {});
    trigger_error('Fatal after import ended', E_USER_ERROR);
    exit;
}

try {
    $legacy = ['old' => 'transcript'];
    $logger->write($legacy, ['token' => 'secret-token']);
    $entry = entries()[0];
    check(reset($entry) === $legacy, 'Legacy timestamp/transcript payload must be preserved');
    check(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', array_key_first($entry)), 'Readable local timestamps must retain second precision');
    check($entry['_meta']['run_id'] === 12345 && $entry['_meta']['token'] === '[redacted]', 'Run context and key redaction');
    check(!isset($entry['_meta']['memory_mb']) && !isset($entry['_meta']['request_id']), 'Successful entries must omit repeated diagnostic metadata');

    $logger->run('batch', ['batch_number' => 4], function () use ($logger) {
        try {
            $logger->run('offer', ['property_id' => 'p1', 'language' => 'de'], static function () {
                throw new RuntimeException('Failure at https://user:password@example.test/file.jpg?token=secret-token#private');
            });
        } catch (RuntimeException $e) {}
        $logger->write('Continuing batch.', ['event' => 'test.context_restored']);
    });
    $failed = eventEntries('offer.failed')[0]['_meta'];
    check(preg_match('/T\d{2}:\d{2}:\d{2}Z$/', $failed['timestamp']), 'Error UTC timestamp must include seconds');
    check($failed['stage'] === 'offer' && $failed['property_id'] === 'p1' && $failed['batch_number'] === 4, 'Exception must include stage, property and batch');
    check(isset($failed['file'], $failed['line'], $failed['trace']) && !isset($failed['trace'][0]['args']), 'Exception location and trace without argument values');
    check(!isset(eventEntries('test.context_restored')[0]['_meta']['property_id']), 'Offer context must not leak into following entries');
    check(eventEntries('batch.finished')[0]['_meta']['level'] === 'warning', 'Batch containing an offer failure must finish with a warning');
    $raw = file_get_contents($directory . '/casawp/logs/' . gmdate('Ym') . '.log');
    check(strpos($raw, 'secret-token') === false && strpos($raw, 'user:password') === false, 'Log output must redact credentials and URL query values');
    $logger->write("Invalid UTF-8: \xB1\x31");
    $entry = $logger->write("Message\n[forged]");
    check(strpos($entry, "\n") === false, 'JSON must escape newlines to keep one record per line');
    entries();
    $entries_before = count(entries());
    $logger->run('volume_batch', [], function () use ($logger) {
        for ($i = 0; $i < 1000; $i++) {
            $logger->write('Routine attachment update.', ['event' => 'media.updated'], 'debug');
        }
    });
    check(count(entries()) - $entries_before === 2, 'Normal logging must summarize routine volume without per-record writes');
    $volume_summary = eventEntries('volume_batch.finished')[0];
    check(strpos(reset($volume_summary), '1000 attachments updated, 0 errors.') !== false, 'Suppressed detail must remain visible as readable summary counts');

    $milestones = new \casawp\ImportLogger(static function () { return 1792411575; });
    $milestones->run('properties_batch', ['batch_number' => 31], function () use ($milestones) {
        $milestones->setStage('archive_statistics', [
            'feed_file' => '/test/data.xml', 'feed_sha256' => str_repeat('a', 64),
            'feed_bytes' => 4307574, 'property_id' => null,
        ]);
        $entry = json_decode($milestones->write('Import action scheduled.', [
            'event' => 'scheduler.scheduled', 'action_id' => 867, 'hook' => 'casawp_batch_import',
            'scheduled_batch_number' => 32, 'delay_seconds' => 13,
        ]), true);
        check(reset($entry) === 'Import batch 32 scheduled in 13s (action 867).', 'Scheduled action message must expose the target batch, delay and action ID');
        check(array_keys($entry['_meta']) === ['run_id', 'batch_number', 'event'], 'Successful scheduling must omit inherited feed, stage, memory and null fields');
        foreach (['offer.finished' => 16, 'offer.unchanged' => 12, 'offer.meta_updated' => 4, 'media.updated' => 39] as $event => $count) {
            for ($i = 0; $i < $count; $i++) {
                $milestones->write('Routine detail.', ['event' => $event], 'debug');
            }
        }
    });
    $milestone_summaries = eventEntries('properties_batch.finished');
    $milestone_summary = end($milestone_summaries);
    check(strpos(reset($milestone_summary), 'Import batch 31 finished in ') === 0 && strpos(reset($milestone_summary), '16 offers processed, 12 unchanged offers, 4 metadata updates, 39 attachments updated, 0 errors.') !== false, 'Batch summaries must expose counts in a consistent readable order');
    check(!isset($milestone_summary['_meta']['counts']) && !isset($milestone_summary['_meta']['feed_file']), 'Successful summaries must not duplicate counts or feed diagnostics in metadata');
    $milestones->run('properties_batch', ['batch_number' => 32], function () use ($milestones) {
        $entry = json_decode($milestones->write('Processing import batch.', [
            'event' => 'batch.processing', 'batch_size' => 4, 'total_batches' => 164,
            'property_ids' => ['1781010', '1781011', '1781012', '1739179'],
        ]), true);
        check(reset($entry) === 'Import batch 32/164: processing 4 properties (1781010, 1781011, 1781012, 1739179).', 'Processing message must expose batch progress and property IDs');
        check(!isset($entry['_meta']['property_ids']), 'Property list must not be repeated in metadata');
    });

    $import = new LoggingTestImport();
    writeFeed("<root>\n<properties><property></properties>\n</root>");
    $previous = libxml_use_internal_errors(false);
    $import->handle_properties_import_batch(1, 12345);
    check(libxml_use_internal_errors() === false, 'XML parser must restore the previous libxml error mode');
    libxml_use_internal_errors($previous);
    $xml_error = eventEntries('feed.parse_errors')[0]['_meta'];
    check($xml_error['xml_errors'][0]['line'] === 2 && $xml_error['xml_errors'][0]['column'] > 0, 'XML errors must contain line and column');
    check(isset($xml_error['feed_bytes'], $xml_error['feed_sha256']), 'Feed size and fingerprint must identify the input');
    check(!$cache_suspended && empty($scheduled), 'Malformed feed must restore cache state and schedule no subsequent batch');

    writeFeed('<root><properties><property id="conversion-error"/></properties></root>');
    $import->handle_properties_import_batch(1, 12345);
    $batch_errors = eventEntries('batch.failed');
    $conversion = end($batch_errors)['_meta'];
    check($conversion['stage'] === 'property_conversion' && $conversion['property_id'] === 'conversion-error', 'Conversion error must identify the source property');

    writeFeed('<root><properties><property id="bad-offer"/><property id="good-offer"/></properties></root>');
    $import->handle_properties_import_batch(1, 12345);
    check($import->transcript['bad-offerde']['action'] === 'error', 'Failed offer remains in the transcript');
    $offer_errors = eventEntries('offer.failed');
    $offer_error = end($offer_errors)['_meta'];
    check($offer_error['casawp_id'] === 'bad-offerde' && $offer_error['post_id'] === 91 && $offer_error['language'] === 'de', 'Offer failure must include IDs and language');
    check(count(eventEntries('offer.finished')) === 0, 'Successful offer detail is suppressed in normal mode');
    $batch_summaries = eventEntries('properties_batch.finished');
    $summary = end($batch_summaries)['_meta'];
    check($summary['counts']['offer.failed'] === 1 && $summary['counts']['offer.finished'] === 1, 'Batch summary must count failed and processed offers');
    check((int) $options['casawp_completed_batches'] === 1, 'Existing progress behavior is preserved');

    $log_level = 'debug';
    $debug_start = count(entries());
    $import->handle_properties_import_batch(1, 12345);
    $debug_entries = array_slice(entries(), $debug_start);
    $headers = array_values(array_filter($debug_entries, static function ($entry) {
        return strpos((string) reset($entry), 'Property good-offer (de) | run 12345, batch 1') === 0;
    }));
    check(count($headers) === 1 && !isset($headers[0]['_meta']), 'Debug property headings must retain identity without repeated metadata');
    check(count(eventEntries('offer.finished')) === 0, 'Successful debug offers must not add redundant finish entries');

    $logger->run('properties_batch', ['batch_number' => 8], function () use ($logger) {
        $logger->setStage('offer_media', ['property_id' => 'compact-property', 'language' => 'de']);
        $entry = json_decode($logger->write('updated casawp attachment 42 origin: https://example.test/image.jpg?token=secret-token', ['event' => 'media.updated'], 'debug'), true);
        check(count($entry) === 1 && !isset($entry['_meta']), 'Routine attachment debug lines must use the original compact JSON shape');
        check(strpos(reset($entry), 'updated casawp attachment 42 origin: https://example.test/image.jpg?[redacted]') === 0, 'Compact debug lines must retain attachment ID and a redacted source');
        $warning = json_decode($logger->write('Attachment download deferred.', ['event' => 'media.cooldown'], 'warning'), true);
        check($warning['_meta']['property_id'] === 'compact-property' && $warning['_meta']['stage'] === 'offer_media', 'Warnings must retain full context while debug logging is enabled');
    });
    $compact_summaries = eventEntries('properties_batch.finished');
    $compact_summary = end($compact_summaries);
    check(strpos(reset($compact_summary), '1 attachment updated, 0 errors.') !== false, 'Compact debug details must still contribute to readable batch counts');
    $log_level = 'info';

    $schedule_result = 0;
    $import->start_import();
    $scheduler_error = eventEntries('scheduler.failed')[0]['_meta'];
    check($scheduler_error['hook'] === 'casawp_batch_import' && $scheduler_error['level'] === 'error', 'Failed scheduling must be visible');

    $workers = [];
    for ($i = 0; $i < 4; $i++) { $workers[] = child('append'); }
    foreach ($workers as $worker) { check(proc_close($worker) === 0, 'Concurrent logging worker failed'); }
    check(count(eventEntries('test.concurrent')) === 200, 'Concurrent log writes must retain all JSON records');

    check(proc_close(child('fatal')) !== 0, 'Fatal test process should fail');
    $fatal = eventEntries('import.fatal');
    check(count($fatal) === 1 && $fatal[0]['_meta']['stage'] === 'offer_media' && $fatal[0]['_meta']['property_id'] === 'fatal-property', 'Fatal error must retain last active import stage and property');
    proc_close(child('unrelated-fatal'));
    check(count(eventEntries('import.fatal')) === 1, 'Unrelated errors after import must not be logged as import failures');
    check(proc_close(child('memory-fatal')) !== 0, 'Memory exhaustion process should fail');
    $fatals = eventEntries('import.fatal');
    check(count($fatals) === 2 && strpos($fatals[1]['_meta']['error'], 'Allowed memory size') !== false, 'Memory exhaustion must leave a diagnostic entry');

    rename($directory . '/casawp/logs', $directory . '/saved-logs');
    file_put_contents($directory . '/casawp/logs', 'blocked directory');
    $logger->write('Fallback preserves context.', ['event' => 'test.fallback']);
    check(strpos(file_get_contents($directory . '/php-error.log'), 'Fallback preserves context.') !== false, 'Unwritable log must fall back to PHP error log');

    echo "Import logging checks passed.\n";
} finally {
    removeTree($directory);
}
