<?php
/** Standalone feed safety checks: php tests/import-feed-validation.php */

namespace casawp {
    // Keep the real gateway entry points in scope without making network requests.
    function curl_init() { return new \stdClass(); }
    function curl_setopt($handle, $option, $value) { return true; }
    function curl_exec($handle) { return $GLOBALS['gateway_feed']; }
    function curl_getinfo($handle, $option) {
        return $option === CURLINFO_HTTP_CODE ? 200 : 'application/xml';
    }
    function curl_errno($handle) { return 0; }
    function curl_error($handle) { return ''; }
    function curl_close($handle) {}
}

namespace {
    require_once dirname(__DIR__) . '/classes/ImportLogger.php';
    require_once dirname(__DIR__) . '/classes/Import.php';
    require_once dirname(__DIR__) . '/classes/ManualCleanup.php';

    // The PHP cURL extension is unnecessary because the transport is replaced above.
    if (!function_exists('curl_version')) { function curl_version() { return []; } }
    foreach (['CURLOPT_URL', 'CURLOPT_RETURNTRANSFER', 'CURLOPT_IPRESOLVE', 'CURL_IPRESOLVE_V4', 'CURLINFO_HTTP_CODE', 'CURLINFO_CONTENT_TYPE'] as $index => $name) {
        if (!defined($name)) { define($name, $index + 1); }
    }

    $temporary = sys_get_temp_dir() . '/casawp-feed-validation-' . uniqid();
    mkdir($temporary . '/casawp/import/attachment/externalsync/existing', 0700, true);
    define('CASASYNC_CUR_UPLOAD_BASEDIR', $temporary);
    ini_set('error_log', $temporary . '/php-error.log');
    $media_file = $temporary . '/casawp/import/attachment/externalsync/existing/brochure.pdf';
    $options = $postmeta = $posts = $scheduled = $events = [];
    $cache_suspended = false;
    $gateway_feed = '';

    function get_date_from_gmt($date, $format) { return gmdate($format); }
    function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
    function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
    function delete_option($key) { unset($GLOBALS['options'][$key]); return true; }
    function update_post_meta($id, $key, $value) {
        $GLOBALS['events'][] = 'metadata';
        $GLOBALS['postmeta'][$id][$key] = $value;
        return true;
    }
    function get_post_meta($id, $key, $single = false) { return $GLOBALS['postmeta'][$id][$key] ?? ''; }
    function get_posts($args) {
        // Every fixture begins with one published property from an earlier import.
        return ($args['post_type'] ?? '') === 'attachment' ? array_keys($GLOBALS['attachments']) : array_keys($GLOBALS['posts']);
    }
    function wp_delete_attachment($id, $force = false) {
        $GLOBALS['events'][] = 'attachment deletion';
        unset($GLOBALS['attachments'][$id], $GLOBALS['postmeta'][$id]);
        if (is_file($GLOBALS['media_file'])) { unlink($GLOBALS['media_file']); }
        return (object) ['ID' => $id];
    }
    function wp_delete_post($id, $force = false) {
        $GLOBALS['events'][] = 'property deletion';
        unset($GLOBALS['posts'][$id], $GLOBALS['postmeta'][$id]);
        return (object) ['ID' => $id];
    }
    function is_wp_error($value) { return false; }
    function flush_rewrite_rules() {}
    function do_action(...$args) {}
    function casawp_has_pending() { return false; }
    function wp_suspend_cache_addition($suspend) { $GLOBALS['cache_suspended'] = $suspend; }
    function apply_filters($hook, $default, ...$args) { return $default; }
    function as_next_scheduled_action(...$args) { return false; }
    function as_unschedule_all_actions(...$args) {}
    function as_schedule_single_action($time, $hook, $args, $group) {
        $GLOBALS['scheduled'][] = compact('time', 'hook', 'args', 'group');
        return count($GLOBALS['scheduled']);
    }
    function casawp_acquire_lock() {
        if (get_option('casawp_import_lock')) { return false; }
        update_option('casawp_import_lock', time());
        return true;
    }
    function casawp_release_lock() { delete_option('casawp_import_lock'); }
    function casawp_set_pending() { update_option('casawp_import_pending', true); }
    function casawp_defer_import_during_cleanup() { return false; }

    class WP_Query {
        public array $posts;
        public function __construct($args) { $this->posts = array_keys($GLOBALS['posts']); }
    }

    // Load the actual one-shot wrapper without bootstrapping WordPress and unrelated services.
    $plugin_source = file_get_contents(dirname(__DIR__) . '/casawp.php');
    if (!preg_match('/^function casawp_start_single_request_import\b[\s\S]*?^}/m', $plugin_source, $wrapper)) {
        throw new \RuntimeException('Could not load the single-request import entry point.');
    }
    eval($wrapper[0]);

    class FeedValidationImport extends \casawp\Import {
        public array $converted = [];
        public array $processed = [];
        public int $cleanup_calls = 0;

        public function property2Array($property) {
            $this->converted[] = (string) $property['id'];
            return ['exportproperty_id' => (string) $property['id']];
        }

        public function updateOffers($properties) {
            $this->processed = array_column($properties, 'exportproperty_id');
            $GLOBALS['events'][] = 'offers';
        }

        public function finalize_import_cleanup(): bool {
            $this->cleanup_calls++;
            return true;
        }
    }

    function check($condition, $message): void {
        if (!$condition) { throw new \RuntimeException($message); }
    }

    function resetFixture(string $feed): array {
        $GLOBALS['options'] = [
            'casawp_current_run_id' => 222,
            'casawp_batch_size_override' => 2,
            'casawp_api_key' => 'fixture-public-key',
            'casawp_private_key' => 'fixture-private-key',
        ];
        $GLOBALS['posts'] = [91 => ['post_status' => 'publish', 'post_title' => 'Existing property']];
        $GLOBALS['attachments'] = [191 => ['post_parent' => 91]];
        $GLOBALS['postmeta'] = [
            91 => ['is_active' => true, 'last_processed_run' => 111, 'exportproperty_id' => 'existing'],
            191 => ['_wp_attached_file' => 'casawp/import/attachment/externalsync/existing/brochure.pdf'],
        ];
        $GLOBALS['scheduled'] = $GLOBALS['events'] = [];
        $GLOBALS['cache_suspended'] = false;
        $GLOBALS['gateway_feed'] = $feed;
        file_put_contents(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/data.xml', $feed);
        file_put_contents($GLOBALS['media_file'], 'Existing brochure content');
        return [$GLOBALS['posts'], $GLOBALS['postmeta'], hash_file('sha256', $GLOBALS['media_file'])];
    }

    function checkPreserved(array $before): void {
        check($GLOBALS['posts'] === $before[0], 'Rejected feeds must preserve published property records.');
        check($GLOBALS['postmeta'] === $before[1], 'Rejected feeds must preserve active and imported property metadata.');
        check(is_file($GLOBALS['media_file']) && hash_file('sha256', $GLOBALS['media_file']) === $before[2],
            'Rejected feeds must preserve existing attachment files.');
        check(!$GLOBALS['cache_suspended'], 'Rejected feeds must restore WordPress cache-addition state.');
    }

    function invokeImport(string $mode, \casawp\Import $import): ?\Throwable {
        try {
            if ($mode === 'batch') { $import->handle_properties_import_batch(1, 222); }
            elseif ($mode === 'single') { $import->init_single_run(222); $import->handle_single_request_import(); }
            elseif ($mode === 'gateway') { $import->updateImportFileThroughCasaGateway(); }
            elseif ($mode === 'cleanup') { (new \casawp\Import())->handle_finalize_import_cleanup_batch(1, 222); }
            elseif ($mode === 'outdated') { (new \casawp\Import())->delete_outdated_properties(1, 222); }
            else { casawp_start_single_request_import('Feed validation fixture'); }
        } catch (\Throwable $error) { return $error; }
        return null;
    }

    function removeTree(string $directory): void {
        foreach (scandir($directory) as $name) {
            if ($name === '.' || $name === '..') { continue; }
            $path = $directory . '/' . $name;
            if (is_dir($path)) { removeTree($path); }
            else { unlink($path); }
        }
        rmdir($directory);
    }

    $invalid = [
        'error response' => '<response><error>Gateway temporarily unavailable</error></response>',
        'missing properties' => '<root/>',
        'nested properties' => '<root><wrapper><properties/></wrapper></root>',
        'wrong properties node' => '<root><property id="existing"/></root>',
        'duplicate properties nodes' => '<root><properties/><properties/></root>',
        'text-only properties' => '<root><properties>Gateway temporarily unavailable</properties></root>',
        'unexpected properties child' => '<root><properties><error>Gateway temporarily unavailable</error></properties></root>',
        'namespaced unexpected child' => '<root><properties><x:error xmlns:x="urn:test"/></properties></root>',
        'unsupported namespaced property' => '<root><properties><x:property xmlns:x="urn:test" id="existing"/></properties></root>',
        'missing ID after valid property' => '<root><properties><property id="new"/><property/></properties></root>',
        'empty ID after valid property' => '<root><properties><property id="new"/><property id=""/></properties></root>',
        'blank ID after valid property' => '<root><properties><property id="new"/><property id=" &#x9; "/></properties></root>',
        'malformed XML' => '<root><properties><property id="existing"></properties></root>',
    ];
    $failures = [];
    $checks = 0;
    try {
        foreach (['batch', 'single', 'gateway', 'single wrapper', 'cleanup', 'outdated'] as $mode) {
            foreach ($invalid as $name => $feed) {
                try {
                    $queued_deletion = in_array($mode, ['cleanup', 'outdated'], true);
                    $before = resetFixture($queued_deletion
                        ? '<root><properties><property id="existing"/></properties></root>' : $feed);
                    if ($queued_deletion) {
                        // A queued worker must revalidate data.xml if it changed since the import started.
                        $path = CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/data.xml';
                        $options['casawp_import_feed_fingerprint'] = ['run_id' => 222, 'sha256' => hash_file('sha256', $path)];
                        file_put_contents($path, $feed);
                    }
                    if (in_array($mode, ['batch', 'single', 'cleanup', 'outdated'], true)) {
                        update_option('casawp_import_lock', time());
                    }
                    $import = new FeedValidationImport();
                    $error = invokeImport($mode, $import);
                    checkPreserved($before);
                    check($import->converted === [] && $import->processed === [], 'Validate every property before converting or updating any item.');
                    check($import->cleanup_calls === 0, 'Rejected feeds must never request property cleanup.');
                    check($scheduled === [], 'Rejected feeds must not queue import or deletion actions.');
                    check($error !== null || get_option('casawp_import_failed'), 'Rejected feeds must report a controlled failure.');
                    check(get_option('casawp_import_failed') && !get_option('casawp_current_run_id') && !get_option('casawp_import_lock'),
                        'A rejected feed must mark its own run failed and release its run and lock.');
                    // Batch and gateway handlers may capture errors internally; the one-shot API can throw.
                    if ($error) {
                        check($error instanceof \RuntimeException || $error instanceof \Exception,
                            'Validation must fail with a controlled exception, not a missing stub or PHP engine error.');
                    }
                    $checks++;
                } catch (\Throwable $error) { $failures[] = $mode . ' / ' . $name . ': ' . $error->getMessage(); }
            }
        }

        foreach (['batch', 'single'] as $mode) {
            try {
                resetFixture('<root><properties/></root>');
                $import = new FeedValidationImport();
                check(invokeImport($mode, $import) === null, 'An intentionally empty properties list must remain valid.');
                check($import->converted === [] && $import->processed === [] && $import->cleanup_calls === 1,
                    'An intentionally empty feed must still request full-unpublish cleanup.');
                check(!$cache_suspended, 'Empty-feed imports must restore cache-addition state.');
                $checks++;
            } catch (\Throwable $error) { $failures[] = $mode . ' / intentional empty feed: ' . $error->getMessage(); }

            try {
                resetFixture('<root><properties><property id="existing"/><property id="new"/></properties></root>');
                $import = new FeedValidationImport();
                check(invokeImport($mode, $import) === null, 'A normal populated feed must remain valid.');
                check($import->converted === ['existing', 'new'] && $import->processed === ['existing', 'new'],
                    'A valid feed must preserve property IDs and processing order.');
                check($import->cleanup_calls === 1 && (int) get_option('casawp_completed_batches') === 1,
                    'A successful populated feed must retain completion and cleanup behavior.');
                check(!$cache_suspended, 'Populated-feed imports must restore cache-addition state.');
                $checks++;
            } catch (\Throwable $error) { $failures[] = $mode . ' / populated feed: ' . $error->getMessage(); }

            try {
                resetFixture('<root><properties><property id="0"/></properties></root>');
                $import = new FeedValidationImport();
                check(invokeImport($mode, $import) === null, 'The nonblank property ID zero must remain valid.');
                check($import->converted === ['0'] && $import->processed === ['0'] && $import->cleanup_calls === 1,
                    'Property ID zero must retain normal import behavior.');
                $checks++;
            } catch (\Throwable $error) { $failures[] = $mode . ' / property ID zero: ' . $error->getMessage(); }

            try {
                resetFixture('<root xmlns="urn:casawp-fixture"><properties><property id="existing"/></properties></root>');
                $import = new FeedValidationImport();
                check(invokeImport($mode, $import) === null, 'Default-namespace feeds supported by SimpleXML must remain valid.');
                check($import->converted === ['existing'] && $import->processed === ['existing'] && $import->cleanup_calls === 1,
                    'A supported default namespace must preserve normal property import behavior.');
                $checks++;
            } catch (\Throwable $error) { $failures[] = $mode . ' / default namespace: ' . $error->getMessage(); }
        }

        foreach (['batch', 'cleanup', 'outdated'] as $mode) {
            try {
                $before = resetFixture('<root><properties><property/></properties></root>');
                $options['casawp_current_run_id'] = 333;
                $options['casawp_import_lock'] = $newer_lock = time() - 5;
                $import = new FeedValidationImport();
                invokeImport($mode, $import);
                checkPreserved($before);
                check((int) get_option('casawp_current_run_id') === 333 && (int) get_option('casawp_import_lock') === $newer_lock,
                    'An invalid stale callback must never release a newer import run or its lock.');
                check(!get_option('casawp_import_failed') && !get_option('casawp_file_cleanup_failed_run'),
                    'An invalid stale callback must never mark a newer import as failed.');
                check($import->converted === [] && $import->processed === [] && $scheduled === [],
                    'An invalid stale callback must not continue import or cleanup work.');
                $checks++;
            } catch (\Throwable $error) { $failures[] = $mode . ' / stale invalid callback: ' . $error->getMessage(); }
        }

        try {
            $before = resetFixture('<root><properties><property/></properties></root>');
            delete_option('casawp_current_run_id');
            $options['casawp_import_lock'] = $manual_lock = time() - 5;
            $options['casawp_manual_cleanup'] = $manual_state = [
                'run_id' => 444, 'status' => 'running', 'lock' => $manual_lock, 'phase' => 'attachments',
            ];
            (new \casawp\Import())->delete_outdated_properties();
            checkPreserved($before);
            check((int) get_option('casawp_import_lock') === $manual_lock && get_option('casawp_manual_cleanup') === $manual_state,
                'A no-run outdated callback must preserve the lock and state of active manual cleanup.');
            check(!get_option('casawp_import_failed') && !get_option('casawp_file_cleanup_failed_run') && $scheduled === [],
                'A no-run outdated callback must not create an import failure or schedule work.');
            $checks++;
        } catch (\Throwable $error) { $failures[] = 'outdated / active manual cleanup without import run: ' . $error->getMessage(); }

        foreach ([false, true] as $already_locked) {
            try {
                $before = resetFixture('<root><properties><property/></properties></root>');
                delete_option('casawp_current_run_id');
                if ($already_locked) { update_option('casawp_import_lock', time()); }
                (new \casawp\Import())->updateImportFileThroughCasaGateway($already_locked);
                checkPreserved($before);
                check(get_option('casawp_import_failed') && !get_option('casawp_import_lock') && !get_option('casawp_current_run_id'),
                    'An invalid gateway feed must release its own acquired lock even before a run ID exists.');
                check($scheduled === [], 'An invalid first gateway feed must never schedule import or cleanup work.');
                $checks++;
            } catch (\Throwable $error) {
                $failures[] = 'gateway / invalid feed before run ID (' . ($already_locked ? 'prelocked' : 'direct') . '): ' . $error->getMessage();
            }
        }

        try {
            $before = resetFixture('<root><properties><property id="0"/></properties></root>');
            $postmeta[91]['exportproperty_id'] = '0';
            $before[1] = $postmeta;
            check(invokeImport('outdated', new FeedValidationImport()) === null, 'The outdated pass must accept property ID zero.');
            checkPreserved($before);
            $checks++;
        } catch (\Throwable $error) { $failures[] = 'outdated / property ID zero: ' . $error->getMessage(); }

        foreach (['gateway', 'single wrapper'] as $mode) {
            try {
                resetFixture('<root><properties/></root>');
                $import = new FeedValidationImport();
                check(invokeImport($mode, $import) === null, 'Intentional empty feeds must pass through public import entry points.');
                $hooks = array_column($scheduled, 'hook');
                $expected_hook = $mode === 'gateway' ? 'casawp_batch_import' : 'casawp_finalize_import_cleanup_batch';
                check(in_array($expected_hook, $hooks, true), 'A valid empty feed must still start its intended import or cleanup work.');
                check(!$cache_suspended, 'Public empty-feed imports must restore cache-addition state.');
                $checks++;
            } catch (\Throwable $error) { $failures[] = $mode . ' / intentional empty feed: ' . $error->getMessage(); }
        }
    } finally { removeTree($temporary); }

    if ($failures) {
        fwrite(STDERR, implode("\n", $failures) . "\n");
        exit(1);
    }
    echo 'Import feed validation checks passed (' . $checks . " scenarios).\n";
}
