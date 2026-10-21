<?php
/** Settings progress regression checks: php tests/import-progress.php */
require_once dirname(__DIR__) . '/classes/ImportProgress.php';
$options = [];
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; }
function __($message, $domain) { return $message; }
function number_format_i18n($number, $decimals = 0) { return number_format($number, $decimals); }
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function progress() { return \casawp\ImportProgress::data(); }
check(progress()['status'] === 'idle', 'An unused site must stay idle.');
$options = ['casawp_current_run_id' => 123, 'casawp_import_progress_run_id' => 123,
    'casawp_total_batches' => 10, 'casawp_completed_batches' => 5];
check(progress()['progress'] === 49.5 && progress()['status'] === 'running', 'Reserve the last percent for cleanup.');
$options['casawp_completed_batches'] = 10;
check(progress()['progress'] === 99 && progress()['status'] === 'cleanup', 'Finished property batches must not finish the import.');
$options['casawp_import_file_cleanup'] = ['run_id' => 122, 'logged_phase' => 'terms'];
check(strpos(progress()['message'], 'Outdated properties') !== false, 'Ignore cleanup state belonging to an older run.');
$options['casawp_import_file_cleanup'] = ['run_id' => 123, 'logged_phase' => 'attachments'];
$options['casawp_import_file_cleanup_totals'] = ['run_id' => 123, 'stats' => ['bytes' => 2097152]];
check(strpos(progress()['message'], 'Database attachments. 2.00 MB') !== false, 'Show the actual phase and cumulative bytes.');
$options['casawp_import_cleanup_progress'] = ['run_id' => 122, 'phase' => 'logs', 'batch_number' => 999];
check(strpos(progress()['message'], '(batch') === false, 'An old run must not supply the cleanup batch number.');
$options['casawp_import_cleanup_progress'] = ['run_id' => 123, 'phase' => 'attachments', 'batch_number' => 121];
check(progress()['message'] === 'Cleaning up: Database attachments (batch 121). 2.00 MB freed.', 'Import cleanup must show the current batch alongside its phase and freed bytes.');
$options['casawp_import_cleanup_progress']['phase'] = 'logs';
check(strpos(progress()['message'], 'Old logs') !== false && progress()['progress'] === 99, 'Log cleanup is still active work.');
check(strpos(progress()['message'], '(batch 121)') !== false, 'The batch number must remain visible during log cleanup.');
$options['casawp_cleanup_done_for_run'] = 123;
check(progress()['progress'] === 99, 'Keep monitoring until the current run is released.');
unset($options['casawp_current_run_id']);
check(progress()['progress'] === 100 && progress()['status'] === 'completed', 'Only a finished cleanup completes the run.');
$options['casawp_import_cleanup_progress']['finished_at'] = time() - 60;
check(progress()['notice_seconds'] >= 539 && progress()['notice_seconds'] <= 540, 'Reloading settings must preserve only the remaining part of the ten-minute window.');
$options['casawp_import_cleanup_progress']['finished_at'] = time() - 601;
check(progress()['notice_seconds'] === 0, 'Expired summaries must stay hidden.');
check(\casawp\ImportProgress::completionNoticeSeconds(0) === 0, 'Older results without a finish timestamp must stay hidden.');
$options['casawp_import_cleanup_progress'] += ['stats' => ['legacy_deleted' => 3, 'files' => 7, 'terms_deleted' => 4], 'freed_bytes' => 2097152];
check(strpos(progress()['message'], 'unused attachments (3), local import files (7), empty taxonomy terms (4). 2.00 MB freed.') !== false, 'Completion must retain counts and combined freed MB after live totals are cleared.');
$options['casawp_import_cleanup_progress']['finished_at'] = time();
\casawp\ImportProgress::dismissCompletionNotice();
check(progress()['notice_seconds'] === 0 && progress()['status'] === 'completed'
    && $options['casawp_import_cleanup_progress']['stats']['files'] === 7, 'Starting manual cleanup permanently dismisses the import notice without erasing its results.');
$options['casawp_import_cleanup_progress']['run_id'] = 122;
check(progress()['notice_seconds'] === 0, 'A different run cannot provide a completion notice.');
check(strpos(progress()['message'], 'unused attachments') === false, 'Do not display results from an older cleanup.');
$options['casawp_import_cleanup_progress']['run_id'] = 123;
check(strpos(\casawp\ImportProgress::cleanupSummary([], 0), 'unused attachments (0), local import files (0), empty taxonomy terms (0). 0.00 MB freed.') !== false, 'Report zero for categories where nothing was removed.');
$options['casawp_cleanup_done_for_run'] = 122;
check(progress()['status'] === 'stopped' && progress()['progress'] < 100, 'An interrupted cleanup must not report completion.');
$options['casawp_import_canceled'] = true;
check(progress()['status'] === 'stopped', 'Canceled imports must not report completion.');
unset($options['casawp_import_canceled']);
$options['casawp_current_run_id'] = 123;
$options['casawp_import_failed'] = true;
check(progress()['status'] === 'stopped', 'Failed imports must not report active work.');
unset($options['casawp_import_failed']);
$options['casawp_total_batches'] = $options['casawp_completed_batches'] = 0;
check(progress()['status'] === 'cleanup' && progress()['progress'] === 99, 'Empty feeds still have cleanup work.');
unset($options['casawp_current_run_id']);
$options['casawp_cleanup_done_for_run'] = 123;
check(progress()['status'] === 'completed', 'Empty-feed cleanup can finish.');
echo "Import progress checks passed.\n";
