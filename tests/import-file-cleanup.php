<?php
/** Standalone filesystem regression checks: php tests/import-file-cleanup.php */
require_once dirname(__DIR__) . '/classes/ImportLogger.php';
require_once dirname(__DIR__) . '/classes/ImportProgress.php';
require_once dirname(__DIR__) . '/classes/ImportMedia.php';
require_once dirname(__DIR__) . '/classes/ImportLegacyAttachmentCleanup.php';
require_once dirname(__DIR__) . '/classes/ImportTermCleanup.php';
require_once dirname(__DIR__) . '/classes/ImportFileCleanup.php';
require_once dirname(__DIR__) . '/classes/Import.php';
require_once dirname(__DIR__) . '/classes/ManualCleanup.php';

$temporary = sys_get_temp_dir() . '/casawp-file-cleanup-' . uniqid();
mkdir($temporary, 0700, true);
ini_set('error_log', $temporary . '/php-errors.log');
define('CASASYNC_CUR_UPLOAD_BASEDIR', $temporary . '/integration');
define('CASASYNC_CUR_UPLOAD_BASEURL', 'https://site.example/uploads');
$options = $metadata = $types = $owned = $mime_types = $scheduled = [];
$batch_limit = 3;
$fixed_date = null;

function get_date_from_gmt($date, $format) {
    return $GLOBALS['fixed_date'] === null ? gmdate($format) : (new DateTimeImmutable($GLOBALS['fixed_date']))->format($format);
}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function __($message, $domain) { return !empty($GLOBALS['localized_ui']) ? 'DE: ' . $message : $message; }
function number_format_i18n($number, $decimals = 0) { return !empty($GLOBALS['localized_ui']) ? number_format($number, $decimals, ',', '.') : number_format($number, $decimals, '.', ''); }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['metadata'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) {
    if (($GLOBALS['options']['simulate_failed_meta_key'] ?? '') === $key) { return false; }
    $GLOBALS['metadata'][$id][$key] = $value;
    return true;
}
function delete_post_meta($id, $key) { unset($GLOBALS['metadata'][$id][$key]); return true; }
function wp_get_post_parent_id($id) { return !empty($GLOBALS['owned'][$id]) ? 900 : 0; }
function get_post_type($id) { return $id === 900 ? 'casawp_property' : 'post'; }
function has_term($term, $taxonomy, $id) { return ($GLOBALS['types'][$id] ?? '') === $term; }
function wp_attachment_is_image($id) { return strpos($GLOBALS['mime_types'][$id] ?? '', 'image/') === 0; }
function apply_filters($name, $default, ...$args) { return $name === 'casawp_file_cleanup_batch_size' ? $GLOBALS['batch_limit'] : $default; }
function get_posts($args) { return []; }
function get_taxonomies($args = [], $output = 'names') { return []; }
function flush_rewrite_rules() {}
function as_unschedule_all_actions(...$args) {}
function as_schedule_single_action($time, $hook, $args, $group) { $GLOBALS['scheduled'][] = $args; return 1; }
function casawp_release_lock() {}
function casawp_has_pending() { return false; }
function do_action(...$args) {}
function get_term_by($field, $value, $taxonomy) { return (object) ['term_id' => 50]; }
function get_term($id, $taxonomy = '') { return (object) ['term_id' => $id]; }
function sanitize_text_field($value) { return strip_tags($value); }
function wp_basename($value) { return basename($value); }
function sanitize_title($value) { return strtolower(str_replace(' ', '-', $value)); }
function wp_check_filetype($file, $mimes = null) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return ['type' => ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'][$extension] ?? false];
}
function is_wp_error($value) { return false; }
function wp_insert_attachment($data, $file, $parent, $return_error) {
    check($file === false, 'CDN imports must not attach a downloaded local file');
    $GLOBALS['inserted_attachment'] = $data;
    return 700;
}
function wp_set_post_terms($id, $terms, $taxonomy, $append = false) {}

$wpdb = new class {
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $terms = 'wp_terms';
    public $term_taxonomy = 'wp_term_taxonomy';
    public $last_error = '';
    public $fail = false;
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function prepare($query, ...$args) { return $args; }
    public function get_var($query) { return 50; }
    public function get_results($query) {
        $this->last_error = $this->fail ? 'Simulated database failure' : '';
        return [];
    }
    public function get_col($patterns) {
        $this->last_error = $this->fail ? 'Simulated database failure' : '';
        if ($this->fail) { return []; }
        $ids = [];
        foreach ($GLOBALS['metadata'] as $id => $meta) {
            foreach ($patterns as $pattern) {
                $prefix = stripslashes(substr($pattern, 0, -1));
                if (isset($meta['_wp_attached_file']) && strpos($meta['_wp_attached_file'], $prefix) === 0) {
                    $ids[] = $id;
                    break;
                }
            }
        }
        return $ids;
    }
};

function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function put($path, $content = 'test file') {
    if (!is_dir(dirname($path))) { mkdir(dirname($path), 0700, true); }
    file_put_contents($path, $content);
    return $path;
}
function scenario($name, $cdn = false) {
    global $temporary, $options, $metadata, $types, $owned, $mime_types, $scheduled, $wpdb;
    $base = $temporary . '/' . $name;
    put($base . '/casawp/import/data.xml', '<root><properties><property id="active"/></properties></root>');
    put($base . '/casawp/import/data-done.xml');
    put($base . '/casawp/import/data-error.xml');
    $options = [
        'casawp_current_run_id' => 111, 'casawp_use_casagateway_cdn' => $cdn,
        'casawp_import_feed_fingerprint' => ['run_id' => 111, 'sha256' => hash_file('sha256', $base . '/casawp/import/data.xml')],
    ];
    $metadata = $types = $owned = $mime_types = $scheduled = [];
    $wpdb->fail = false;
    return $base;
}
function attachment($id, $base, $file, $type, $imported = true, $origin = 'https://cdn.casasoft.com/media/photo.jpg') {
    global $metadata, $types, $owned, $mime_types;
    $metadata[$id] = [
        '_wp_attached_file' => 'casawp/import/attachment/' . $file,
        '_origin' => $origin, '_casawp_import' => $imported ? 1 : 0,
        '_wp_attachment_metadata' => ['width' => 1200, 'height' => 800],
        '_wp_attachment_image_alt' => 'Preserved description',
    ];
    $types[$id] = $type;
    $owned[$id] = $imported;
    $mime_types[$id] = preg_match('/\.(?:jpg|png)$/', $file) ? 'image/jpeg' : 'application/pdf';
    return put($base . '/casawp/import/attachment/' . $file);
}
function runCleanup($base, $ids = ['active']) {
    $logger = new \casawp\ImportLogger(static function () { return 111; });
    for ($i = 0; $i < 100; $i++) {
        // Recreate the worker to exercise persisted traversal and shared-file protection.
        if ((new \casawp\ImportFileCleanup($base, $logger))->runBatch(111, $ids)) { return $i + 1; }
    }
    throw new RuntimeException('Cleanup did not finish within bounded batches');
}
function removeTree($path) {
    if (is_link($path) || !is_dir($path)) { unlink($path); return; }
    foreach (scandir($path) as $name) {
        if ($name !== '.' && $name !== '..') { removeTree($path . '/' . $name); }
    }
    rmdir($path);
}
function cleanupTotals($run_id) {
    $totals = [];
    foreach (glob(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/logs/*.log') as $file) {
        if (!is_file($file) || is_link($file)) { continue; }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $entry = json_decode($line, true);
            if (($entry['_meta']['event'] ?? '') === 'cleanup.total' && ($entry['_meta']['run_id'] ?? 0) === $run_id) {
                $totals[] = $entry;
            }
        }
    }
    return $totals;
}

try {
    foreach (\casawp\ImportMedia::TYPES as $type) {
        check(\casawp\ImportMedia::usesRemote($type, 'https://cdn.casasoft.com/media/provider-8553/property-1311752/test.pdf', true), 'Every supported attachment type must support CDN imports');
        check(!\casawp\ImportMedia::usesRemote($type, 'https://cdn.casasoft.com/media/test.pdf', false), 'CDN-off imports must stay local');
        check(!\casawp\ImportMedia::usesRemote($type, 'relative-file.pdf', true), 'Local file sources must stay local');
    }
    check(!\casawp\ImportMedia::usesRemote('document', 'https://cdn.casasoft.com.example.org/media/test.pdf', true), 'CDN policy must reject lookalike hosts');
    check(!\casawp\ImportMedia::usesRemote('document', 'https://cdn.casasoft.com/other/test.pdf', true), 'Only gateway media URLs are remote-only for documents');
    $base = scenario('remote-import', true);
    foreach (['image' => 'jpg', 'document' => 'pdf', 'plan' => 'pdf', 'offer-logo' => 'png', 'sales-brochure' => 'pdf'] as $type => $extension) {
        $id = (new \casawp\Import())->casawpUploadAttachment(['type' => $type, 'url' => 'https://cdn.casasoft.com/media/provider-8553/property-1311752/file.' . $extension], 900, 'active');
        check($id === 700 && get_post_meta(700, '_is_remote', true) === 1, 'Importer must create remote-only records for all CDN attachment types');
        check($GLOBALS['inserted_attachment']['post_mime_type'] === ($extension === 'pdf' ? 'application/pdf' : 'image/' . ($extension === 'jpg' ? 'jpeg' : 'png')), 'Remote attachments must retain their correct MIME type');
    }
    $base = scenario('local');
    $obsolete = put($base . '/casawp/import/attachment/externalsync/gone/obsolete.pdf');
    $legacy = put($base . '/casawp/import/attachment/legacy/obsolete.txt');
    $photo = attachment(1, $base, 'externalsync/active/photo.jpg', 'image');
    $document = attachment(2, $base, 'externalsync/active/document.pdf', 'document');
    $manual = attachment(3, $base, 'externalsync/gone-but-referenced/manual.pdf', 'document', false);
    $other_xml = put($base . '/casawp/import/manual.xml');
    $outside = put($temporary . '/outside/keep.jpg');
    symlink(dirname($outside), $base . '/casawp/import/attachment/externalsync/link');
    symlink($outside, $base . '/casawp/import/attachment/externalsync/active/link.jpg');
    check(runCleanup($base) > 1, 'Cleanup must resume over several bounded batches');
    check(!file_exists($obsolete) && !is_dir(dirname($obsolete)) && !is_dir(dirname($legacy)), 'Unreferenced obsolete property files and directories must be removed');
    check(is_file($photo) && is_file($document) && is_file($manual), 'CDN-off and remaining WordPress consumers must retain their local files');
    check(is_file($base . '/casawp/import/data.xml') && is_file($other_xml), 'Current feed and unrelated XML must be retained');
    check(!file_exists($base . '/casawp/import/data-done.xml') && !file_exists($base . '/casawp/import/data-error.xml'), 'Known historical XML files must be removed');
    check(is_file($outside) && is_link($base . '/casawp/import/attachment/externalsync/link'), 'Cleanup must not traverse or remove symbolic links');
    check(is_dir($base . '/casawp/import/attachment/externalsync'), 'A nonempty externalsync folder must be retained');

    $base = scenario('empty-externalsync');
    $external = $base . '/casawp/import/attachment/externalsync';
    mkdir($external, 0700, true);
    runCleanup($base);
    check(!is_dir($external) && !is_dir(dirname($external)), 'An initially empty externalsync folder and its empty attachment parent must be removed');

    $base = scenario('emptied-externalsync');
    $external = $base . '/casawp/import/attachment/externalsync';
    put($external . '/gone/nested/obsolete.pdf');
    check(runCleanup($base) > 1, 'Removing nested obsolete folders must resume across cleanup batches');
    check(!is_dir($external) && !is_dir(dirname($external)), 'Cleanup must remove externalsync and attachment after their last property folder is deleted');
    check(is_file($base . '/casawp/import/data.xml'), 'Removing the empty attachment root must retain the import folder and current feed');

    $base = scenario('finder-metadata');
    $external = $base . '/casawp/import/attachment/externalsync';
    put($external . '/.DS_Store');
    put(dirname($external) . '/.DS_Store');
    runCleanup($base);
    check(!is_dir($external) && !is_dir(dirname($external)), 'Finder metadata must not keep otherwise empty externalsync and attachment folders alive');

    $base = scenario('empty-attachment');
    $attachment_root = $base . '/casawp/import/attachment';
    mkdir($attachment_root, 0700, true);
    runCleanup($base);
    check(!is_dir($attachment_root), 'An initially empty attachment root must be removed');

    $base = scenario('nonempty-attachment');
    $external = $base . '/casawp/import/attachment/externalsync';
    mkdir($external, 0700, true);
    $retained = put(dirname($external) . '/keep.txt');
    runCleanup($base);
    check(!is_dir($external) && is_file($retained), 'An empty externalsync folder must be removed while retaining a nonempty attachment parent');

    $base = scenario('cdn', true);
    $photo = attachment(10, $base, 'externalsync/active/photo.jpg', 'image');
    attachment(11, $base, 'externalsync/active/photo.jpg', 'image'); // A translated offer shares the file.
    foreach ([10, 11] as $id) {
        $metadata[$id]['_wp_attachment_metadata']['sizes'] = ['thumbnail' => ['file' => 'photo-150x150.jpg']];
        $metadata[$id]['_wp_attachment_metadata']['original_image'] = 'photo-original.jpg';
        $metadata[$id]['_wp_attachment_backup_sizes'] = ['full-orig' => ['file' => 'photo-backup.jpg']];
    }
    $thumb = put(dirname($photo) . '/photo-150x150.jpg');
    $original = put(dirname($photo) . '/photo-original.jpg');
    $backup = put(dirname($photo) . '/photo-backup.jpg');
    $orphan = put(dirname($photo) . '/old-photo.webp');
    $plan = attachment(12, $base, 'externalsync/active/plan.png', 'plan');
    $plan_thumb = put(dirname($plan) . '/plan-thumbnail.png');
    $metadata[12]['_wp_attachment_metadata']['sizes'] = ['thumbnail' => ['file' => 'plan-thumbnail.png']];
    $logo = attachment(13, $base, 'externalsync/active/logo.jpg', 'offer-logo');
    $document = attachment(14, $base, 'externalsync/active/document.pdf', 'document');
    $brochure = attachment(18, $base, 'externalsync/active/brochure.pdf', 'sales-brochure');
    $local_plan = attachment(22, $base, 'externalsync/active/local-plan.png', 'plan', true, 'local-plan.png');
    $external_document = attachment(23, $base, 'externalsync/active/external.pdf', 'document', true, 'https://example.org/document.pdf');
    $shared_document = attachment(24, $base, 'externalsync/active/shared.pdf', 'document');
    attachment(25, $base, 'externalsync/active/shared.pdf', 'document', false);
    $shared = attachment(15, $base, 'externalsync/active/shared.jpg', 'image');
    attachment(16, $base, 'externalsync/active/shared.jpg', 'image', false);
    $local_source = attachment(17, $base, 'externalsync/active/local-source.jpg', 'image', true, 'https://site.example/uploads/casawp/import/attachment/externalsync/active/local-source.jpg');
    $unknown = attachment(26, $base, 'externalsync/active/unknown.bin', '', false);
    $root_file = put($base . '/casawp/import/attachment/externalsync/cache.dat');
    $nested = put($base . '/casawp/import/attachment/externalsync/active/deep/nested/anything.xyz');
    $outside_cache = attachment(27, $base, 'custom/local.pdf', 'document', false, 'local.pdf');
    runCleanup($base);
    check(!file_exists($photo) && !file_exists($thumb) && !file_exists($original) && !file_exists($backup) && !file_exists($orphan), 'CDN photos, old thumbnails, backups and orphan image files must be deleted');
    check(!file_exists($plan) && !file_exists($plan_thumb) && !file_exists($logo) && !file_exists($document) && !file_exists($brochure), 'CDN documents, plans, logos and brochures must lose their obsolete local copies');
    foreach ([$local_plan, $external_document, $shared_document, $shared, $local_source, $unknown, $root_file, $nested] as $file) {
        check(!is_file($file), 'Every regular externalsync file must be removed with CDN on, regardless of type, metadata, active property or shared consumers');
    }
    check(!is_dir($base . '/casawp/import/attachment/externalsync'), 'The complete externalsync cache must disappear after its last file');
    check(is_file($outside_cache), 'Local consumers outside externalsync must retain their files');
    foreach ([10, 11, 12, 13, 14, 15, 16, 18, 24, 25, 26] as $id) {
        check(isset($metadata[$id]) && $metadata[$id]['_is_remote'] === 1 && !isset($metadata[$id]['_wp_attached_file']), 'CDN attachment IDs must remain while obsolete local file pointers are cleared');
        check($metadata[$id]['_remote_width'] === 1200 && $metadata[$id]['_remote_height'] === 800 && $metadata[$id]['_wp_attachment_image_alt'] === 'Preserved description', 'CDN dimensions and descriptive metadata must survive cleanup');
    }
    check(isset($metadata[17]['_wp_attached_file']) && isset($metadata[22]['_wp_attached_file']) && isset($metadata[23]['_wp_attached_file']), 'Records without a usable remote source must not be falsely marked as remote');

    foreach (['casawp_import_failed', 'casawp_import_canceled', 'casawp_file_cleanup_failed_run', 'casawp_current_run_id'] as $guard) {
        $base = scenario('guard-' . $guard, true);
        $orphan = put($base . '/casawp/import/attachment/externalsync/gone/photo.jpg');
        $options[$guard] = $guard === 'casawp_current_run_id' ? 222 : ($guard === 'casawp_file_cleanup_failed_run' ? 111 : true);
        runCleanup($base);
        check(is_file($orphan) && is_file($base . '/casawp/import/data-error.xml'), 'Failed, canceled and stale runs must not delete any import files');
    }
    $base = scenario('changed-feed', true);
    put($base . '/casawp/import/data.xml', '<different/>');
    runCleanup($base);
    check(is_file($base . '/casawp/import/data-error.xml'), 'A changed feed must prevent cleanup');

    $base = scenario('changed-mode', false);
    $photo = attachment(19, $base, 'externalsync/active/photo.jpg', 'image');
    $worker = new \casawp\ImportFileCleanup($base, new \casawp\ImportLogger(static function () { return 111; }));
    check(!$worker->runBatch(111, ['active']), 'First small batch should leave a resumable cursor');
    $options['casawp_use_casagateway_cdn'] = true;
    check($worker->runBatch(111, ['active']) && is_file($photo) && isset($metadata[19]['_wp_attached_file']), 'A CDN mode change during cleanup must stop deletion without migrating attachments');

    $base = scenario('linked-attachments', true);
    $outside_directory = $temporary . '/outside';
    symlink($outside_directory, $base . '/casawp/import/attachment');
    runCleanup($base);
    check(is_file($outside_directory . '/keep.jpg'), 'A linked attachment root must never be traversed');

    $base = scenario('metadata-failure', true);
    $photo = attachment(21, $base, 'externalsync/active/photo.jpg', 'image');
    $options['simulate_failed_meta_key'] = '_is_remote';
    try { runCleanup($base); check(false, 'Failed remote conversion must stop deletion'); }
    catch (RuntimeException $e) { check(strpos($e->getMessage(), 'Cannot mark the CDN attachment as remote') === 0, 'Expected remote metadata failure'); }
    check(is_file($photo) && isset($metadata[21]['_wp_attached_file']), 'A failed metadata migration must preserve the local original');
    check(get_option('casawp_import_file_cleanup_totals')['stats']['bytes'] === 18, 'Partial failures must retain the bytes actually freed before the error');

    $base = scenario('database-failure', true);
    $photo = attachment(20, $base, 'externalsync/active/photo.jpg', 'image');
    $wpdb->fail = true;
    try { runCleanup($base); check(false, 'Database error must stop deletion'); }
    catch (RuntimeException $e) { check(strpos($e->getMessage(), 'Simulated database failure') !== false, 'Expected reference-check failure'); }
    check(is_file($photo) && isset($metadata[20]['_wp_attached_file']), 'Reference query failures must preserve local photos and metadata');
    $wpdb->fail = false;

    $localized_ui = true;
    $base = scenario('integration', true);
    $photo = attachment(30, $base, 'externalsync/active/photo.jpg', 'image');
    file_put_contents($photo, str_repeat('x', 2 * 1048576));
    $fixed_date = '2026-10-19 14:21:00+02:00';
    $current_log = put($base . '/casawp/logs/202610.log', '');
    $previous_log = put($base . '/casawp/logs/202609.log', '');
    $old_log = put($base . '/casawp/logs/202608.log', str_repeat('x', 3 * 1048576));
    $batch = 1;
    do {
        $import = new \casawp\Import();
        $import->handle_finalize_import_cleanup_batch($batch++, 111);
        check(get_option('casawp_import_cleanup_progress')['batch_number'] === $batch - 1,
            'Each executed import cleanup batch must publish its actual number, including the last batch');
        if (get_option('casawp_current_run_id')) {
            check(strpos(\casawp\ImportProgress::data()['message'], '(batch ' . ($batch - 1) . ')') !== false,
                'Live import cleanup progress must display the batch actually executed by the worker');
        }
        check($batch < 100, 'Integrated cleanup must complete');
    } while ((int) get_option('casawp_cleanup_done_for_run', 0) !== 111);
    check(!file_exists($photo) && !file_exists($base . '/casawp/import/data-done.xml') && !get_option('casawp_current_run_id'), 'Final import cleanup must finish file batches before releasing the run');
    check(count($scheduled) > 0 && $scheduled[0]['run_id'] === 111, 'Incomplete file cleanup must schedule another final cleanup batch');
    check(is_file($current_log) && is_file($previous_log) && !file_exists($old_log), 'Successful finalization must retain current/previous calendar months and delete older logs without cron');
    $totals = cleanupTotals(111);
    check(count($totals) === 1, 'Finalization must log exactly one combined total after all cleanup batches');
    check($totals[0]['_meta']['freed_bytes'] === 5 * 1048576 + 18
        && $totals[0]['_meta']['attachment_xml_bytes'] === 2 * 1048576 + 18
        && $totals[0]['_meta']['old_log_bytes'] === 3 * 1048576, 'Final total must add exact cumulative attachment/XML and log bytes without double counting');
    check(strpos(reset($totals[0]), 'Import cleanup total: 5.00 MB freed') === 0, 'Final total must be readable at the default log level');
    check(!get_option('casawp_import_file_cleanup_totals'), 'Completed run totals must be cleared after logging');
    $summary = get_option('casawp_import_cleanup_progress');
    check($summary['run_id'] === 111 && $summary['freed_bytes'] === 5 * 1048576 + 18
        && $summary['stats']['files'] === 3, 'The settings summary must preserve this run’s file counts and total bytes after live totals are cleared');
    $cleanup_entries = [];
    foreach (file($current_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $entry = json_decode($line, true);
        if (is_array($entry) && ($entry['_meta']['run_id'] ?? 0) === 111) { $cleanup_entries[] = $entry; }
    }
    $phases = array_values(array_map(static function ($entry) { return $entry['_meta']['cleanup_phase']; },
        array_filter($cleanup_entries, static function ($entry) { return ($entry['_meta']['event'] ?? '') === 'cleanup.phase_started'; })));
    check($phases === ['attachments', 'files', 'terms'], 'Cleanup must announce database, filesystem and taxonomy transitions once, in order');
    $events = array_column(array_column($cleanup_entries, '_meta'), 'event');
    $batch_finishes = array_keys($events, 'final_cleanup.finished', true);
    check(max($batch_finishes) < array_search('cleanup.total', $events, true)
        && array_search('cleanup.total', $events, true) < array_search('cleanup.summary', $events, true)
        && end($events) === 'import.separator.end'
        && array_search('cleanup.separator', $events, true) === array_search('cleanup.summary', $events, true) + 1
        && array_search('import.separator.end', $events, true) === array_search('cleanup.separator', $events, true) + 1
        && count(array_keys($events, 'cleanup.separator', true)) === 1, 'The last batch must finish before totals and summary, followed by cleanup and import end separators');
    check(count(array_keys($events, 'cleanup.separator.start', true)) === 1
        && array_search('cleanup.separator.start', $events, true) < array_search('cleanup.phase_started', $events, true),
        'Import cleanup must write one start separator across separate batch requests before its first phase');
    $summaries = array_values(array_filter($cleanup_entries, static function ($entry) { return ($entry['_meta']['event'] ?? '') === 'cleanup.summary'; }));
    check(count($summaries) === 1 && $summaries[0]['_meta']['deleted_files'] === 3
        && $summaries[0]['_meta']['freed_bytes'] === 5 * 1048576 + 18
        && strpos(reset($summaries[0]), 'Import and cleanup completed. Removed:') === 0
        && strpos(reset($summaries[0]), 'local import files (3)') !== false
        && strpos(reset($summaries[0]), '5.00 MB freed.') !== false, 'Import log summaries must use English and English number formatting even on translated sites');
    $localized_ui = false;
    check(array_search('files.completed', $events, true) < array_search('terms.completed', $events, true)
        && array_search('terms.completed', $events, true) < array_search('log_cleanup.started', $events, true), 'Each phase must finish before the next phase is reported');
    check(!in_array('scheduler.scheduled', $events, true) && !in_array('final_cleanup.started', $events, true), 'Normal cleanup logs must omit scheduled and started batch entries');

    $log_dir = $base . '/casawp/logs';
    foreach ([
        ['2026-01-01 00:00:00+01:00', '202601', '202512', '202511'],
        ['2024-03-31 23:59:59+02:00', '202403', '202402', '202401'],
        ['2026-10-01 00:00:00+02:00', '202610', '202609', '202608'],
    ] as [$date, $current, $previous, $old]) {
        $fixed_date = $date;
        put($log_dir . '/' . $current . '.log');
        put($log_dir . '/' . $previous . '.log');
        put($log_dir . '/' . $old . '.log');
        $custom = put($log_dir . '/custom-debug.log');
        $invalid = put($log_dir . '/202613.log');
        $outside = put($temporary . '/outside-log.log');
        if (!is_link($log_dir . '/200001.log')) { symlink($outside, $log_dir . '/200001.log'); }
        (new \casawp\Import())->cleanup_log_files();
        check(is_file($log_dir . '/' . $current . '.log') && is_file($log_dir . '/' . $previous . '.log'), 'Both protected months must survive year changes, leap years, month-end dates and site timezone boundaries');
        check(!file_exists($log_dir . '/' . $old . '.log'), 'All other standard monthly logs must be removed regardless of file modification time');
        check(is_file($custom) && is_file($invalid) && is_link($log_dir . '/200001.log') && is_file($outside), 'Non-monthly names, invalid months and symbolic links must be retained');
    }
    $fixed_date = '2026-10-20 12:00:00+02:00';
    $legacy_old_names = ['2020 Aug.log', '2020 August.log', '2020-08.log', '2020_8.log', '2021 AUG.LOG', '2020 Aug..log', '2020 Sept.log'];
    $legacy_keep_names = ['2026 Oct.log', '2026 October.log', '2026-10.log', '2026 Sep.log', '2026 September.log', '2026_9.LOG'];
    $unrecognized_names = ['2020 Aug backup.log', '2020 Unknown.log', '2020-00.log', '2020-13.log', '2020 August.txt'];
    foreach (array_merge($legacy_old_names, $legacy_keep_names, $unrecognized_names) as $name) { put($log_dir . '/' . $name); }
    mkdir($log_dir . '/2019 Aug.log');
    symlink($outside, $log_dir . '/2018 Aug.log');
    $legacy_cleanup = new \casawp\Import();
    $legacy_cleanup->cleanup_log_files();
    foreach ($legacy_old_names as $name) { check(!file_exists($log_dir . '/' . $name), 'Old monthly log variant must be removed: ' . $name); }
    foreach (array_merge($legacy_keep_names, $unrecognized_names) as $name) { check(is_file($log_dir . '/' . $name), 'Protected months and unrecognized names must survive: ' . $name); }
    check(is_dir($log_dir . '/2019 Aug.log') && is_link($log_dir . '/2018 Aug.log') && is_file($outside), 'Legacy-name directories and symbolic links must not be deleted');
    check($legacy_cleanup->getLogCleanupFreedBytes() === count($legacy_old_names) * strlen('test file'), 'Legacy monthly files must contribute their exact bytes to the cleanup total');
    $fixed_date = null;

    // A structurally invalid feed must not be interpreted as permission to sweep folders.
    put($base . '/casawp/import/data.xml', '<root/>');
    put($base . '/casawp/import/data-error.xml');
    $options['casawp_current_run_id'] = 222;
    $options['casawp_import_file_cleanup_totals'] = ['run_id' => 111, 'stats' => ['bytes' => 99999999]];
    $import->handle_finalize_import_cleanup_batch(1, 222);
    check(is_file($base . '/casawp/import/data-error.xml'), 'Missing properties node must preserve import files during finalization');
    $totals = cleanupTotals(222);
    check(count($totals) === 1 && $totals[0]['_meta']['freed_bytes'] === 0, 'Skipped cleanup must report zero and exclude totals belonging to an earlier run');

    echo "Import file cleanup checks passed.\n";
} finally {
    removeTree($temporary);
}
