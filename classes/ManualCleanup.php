<?php

namespace casawp;

/** Background maintenance using current WordPress properties, without starting an import. */
class ManualCleanup
{
    private const STALL_SECONDS = 600;

    public static function registerHooks(): void
    {
        add_action('wp_ajax_casawp_start_cleanup', [self::class, 'ajaxStart']);
        add_action('wp_ajax_casawp_resume_cleanup', [self::class, 'ajaxResume']);
        add_action('wp_ajax_casawp_stop_cleanup', [self::class, 'ajaxStop']);
        add_action('wp_ajax_casawp_cleanup_status', [self::class, 'ajaxStatus']);
        add_action('casawp_manual_cleanup_batch', [self::class, 'handleBatch'], 10, 2);
        foreach (['action_scheduler_failed_execution', 'action_scheduler_failed_action', 'action_scheduler_unexpected_shutdown'] as $hook) {
            add_action($hook, [self::class, 'handleFailure']);
        }
    }

    public static function isRunning(): bool
    {
        $state = get_option('casawp_manual_cleanup', []);
        return in_array($state['status'] ?? '', ['queued', 'running'], true)
            && casawp_is_import_running() && !get_option('casawp_current_run_id');
    }

    public static function dismissCompletionNotice(): void
    {
        $state = get_option('casawp_manual_cleanup', []);
        if (($state['status'] ?? '') === 'completed' && empty($state['notice_dismissed'])) {
            $state['notice_dismissed'] = true;
            update_option('casawp_manual_cleanup', $state, false);
        }
    }

    private static function authorize(): void
    {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => __('Insufficient privileges.', 'casawp')], 403); exit; }
        check_ajax_referer('casawp_manual_cleanup', 'nonce');
    }

    public static function ajaxStart(): void
    {
        self::authorize();
        $result = (new self())->start();
        if (is_wp_error($result)) { wp_send_json_error(['message' => $result->get_error_message()], 409); }
        else { wp_send_json_success($result); }
    }

    public static function ajaxStatus(): void
    {
        self::authorize();
        $state = get_option('casawp_manual_cleanup', []);
        if (in_array($state['status'] ?? '', ['queued', 'running'], true) && self::stopRequested((int) $state['run_id'])) {
            wp_send_json_success((new self())->stop());
            return;
        }
        if (in_array($state['status'] ?? '', ['queued', 'running'], true) && !self::isRunning() && !self::canRecoverLock($state)) {
            (new self())->fail((int) $state['run_id'], 'Manual cleanup lost its lock or the lock expired.');
        }
        wp_send_json_success(self::statusData());
    }

    public static function ajaxResume(): void
    {
        self::authorizeRun();
        $result = (new self())->resume();
        if (is_wp_error($result)) { wp_send_json_error(['message' => $result->get_error_message()], 409); }
        else { wp_send_json_success($result); }
    }

    public static function ajaxStop(): void
    {
        self::authorizeRun();
        wp_send_json_success((new self())->stop());
    }

    private static function authorizeRun(): void
    {
        self::authorize();
        $state = get_option('casawp_manual_cleanup', []);
        if (empty($state['run_id']) || (int) ($_POST['run_id'] ?? 0) !== (int) $state['run_id']) {
            wp_send_json_error(['message' => __('Cleanup changed. Refresh its status and try again.', 'casawp')], 409);
            exit;
        }
    }

    public static function stopRequested(int $run_id): bool
    {
        return $run_id > 0 && (int) get_option('casawp_manual_cleanup_stop', 0) === $run_id;
    }

    public function stop(): array
    {
        $state = get_option('casawp_manual_cleanup', []);
        if (!in_array($state['status'] ?? '', ['queued', 'running'], true)) { return self::statusData(); }
        $run_id = (int) $state['run_id'];
        if (!self::stopRequested($run_id)) {
            update_option('casawp_manual_cleanup_stop', $run_id, false);
            $this->logger($run_id)->write('Manual cleanup stop requested.', ['event' => 'manual_cleanup.stop_requested']);
        }
        as_unschedule_all_actions('', [], 'casawp_manual_cleanup_' . $run_id);
        $worker = ['run_id' => $run_id, 'batch' => (int) $state['next_batch'], 'started_at' => time()];
        // An active worker observes the stop flag before continuing deletion or scheduling.
        if ($this->acquireWorker($worker)) {
            try { $this->finishStopped($run_id); }
            finally { $this->releaseWorker($worker); }
        }
        return self::statusData();
    }

    private function finishStopped(int $run_id): void
    {
        $state = get_option('casawp_manual_cleanup', []);
        if (($state['run_id'] ?? 0) !== $run_id || !in_array($state['status'] ?? '', ['queued', 'running'], true)) { return; }
        $files = get_option('casawp_manual_file_cleanup', []);
        $totals = get_option('casawp_manual_file_cleanup_totals', []);
        if (($files['run_id'] ?? 0) === $run_id) { $state['phase'] = $files['logged_phase'] ?? $state['phase']; }
        if (($totals['run_id'] ?? 0) === $run_id) { $state['stats'] = $totals['stats']; }
        $state['status'] = 'stopped';
        $state['finished_at'] = time();
        update_option('casawp_manual_cleanup', $state, false);
        if ($this->ownsLock($state)) { casawp_release_lock(); }
        $logger = $this->logger($run_id);
        $logger->setStage('manual_cleanup', ['cleanup_phase' => $state['phase']]);
        $logger->write('Manual cleanup stopped by an administrator. Changes already made were retained.',
            ['event' => 'manual_cleanup.stopped', 'batch_number' => $state['next_batch']]);
        $logger->separator('End of cleanup', 'cleanup.separator');
        $this->resumePendingImport();
    }

    /** Never take a lock belonging to a different import or cleanup. */
    private static function canRecoverLock(array $state): bool
    {
        if (get_option('casawp_current_run_id')) { return false; }
        $lock = (int) get_option('casawp_import_lock', 0);
        return $lock ? $lock === (int) ($state['lock'] ?? 0) : !casawp_has_pending();
    }

    private static function canResume(array $state, array $own_worker = []): bool
    {
        if (!in_array($state['status'] ?? '', ['queued', 'running'], true)
            || empty($state['run_id']) || empty($state['next_batch'])
            || self::stopRequested((int) $state['run_id'])
            || !function_exists('as_schedule_single_action') || !self::canRecoverLock($state)) { return false; }
        // Older runs have no updated_at; their lock timestamp records the last batch start.
        $updated = (int) ($state['updated_at'] ?? $state['lock'] ?? $state['started_at'] ?? 0);
        $worker = get_option('casawp_manual_cleanup_worker', []);
        return time() - $updated >= self::STALL_SECONDS
            && (!$worker || $worker === $own_worker || time() - ($worker['started_at'] ?? 0) > self::STALL_SECONDS);
    }

    private function acquireWorker(array $worker): bool
    {
        $old = get_option('casawp_manual_cleanup_worker', []);
        if ($old && time() - ($old['started_at'] ?? 0) > self::STALL_SECONDS) { delete_option('casawp_manual_cleanup_worker'); }
        return add_option('casawp_manual_cleanup_worker', $worker, '', 'no');
    }

    private function releaseWorker(array $worker): void
    {
        if (get_option('casawp_manual_cleanup_worker') === $worker) { delete_option('casawp_manual_cleanup_worker'); }
    }

    public function resume()
    {
        $state = get_option('casawp_manual_cleanup', []);
        $busy = new \WP_Error('cleanup_busy', __('Cleanup cannot be resumed while another operation is active or progress was made recently.', 'casawp'));
        if (!self::canResume($state)) { return $busy; }
        $run_id = (int) $state['run_id'];
        $worker = ['run_id' => $run_id, 'batch' => (int) $state['next_batch'], 'started_at' => time()];
        if (!$this->acquireWorker($worker)) { return $busy; }
        try {
            $state = get_option('casawp_manual_cleanup', []);
            if (($state['run_id'] ?? 0) !== $run_id || !self::canResume($state, $worker)) { return $busy; }
            if ((bool) get_option('casawp_use_casagateway_cdn', false) !== $state['cdn']
                || hash('sha256', serialize($this->propertyIds())) !== ($state['properties_hash'] ?? '')) {
                throw new \RuntimeException('Stored properties or the CDN setting changed; start a new cleanup instead.');
            }
            if (!get_option('casawp_import_lock')) {
                if (!casawp_acquire_lock()) { return $busy; }
                $state['lock'] = (int) get_option('casawp_import_lock');
            }
            if (!self::canRecoverLock($state)) { return $busy; }
            $state['lock'] = time();
            update_option('casawp_import_lock', $state['lock'], false);
            $state['status'] = 'queued';
            $state['updated_at'] = time();
            update_option('casawp_manual_cleanup', $state, false);
            as_unschedule_all_actions('casawp_manual_cleanup_batch',
                ['batch_number' => $state['next_batch'], 'run_id' => $run_id], 'casawp_manual_cleanup_' . $run_id);
        } catch (\Throwable $e) {
            $this->fail($run_id, $e->getMessage());
            return new \WP_Error('cleanup_failed', __('Cleanup could not be resumed. See the logs for details.', 'casawp'));
        } finally {
            $this->releaseWorker($worker);
        }
        try {
            $this->schedule($run_id, $state['next_batch'], $state['phase']);
            $this->logger($run_id)->write('Manual cleanup resumed from its saved batch.',
                ['event' => 'manual_cleanup.resumed', 'batch_number' => $state['next_batch'], 'cleanup_phase' => $state['phase']]);
        } catch (\Throwable $e) {
            $this->fail($run_id, $e->getMessage());
            return new \WP_Error('cleanup_failed', __('Cleanup could not be resumed. See the logs for details.', 'casawp'));
        }
        return self::statusData();
    }

    public function start()
    {
        if (get_option('casawp_current_run_id') || casawp_is_import_running() || casawp_has_pending()) {
            return new \WP_Error('cleanup_busy', __('An import or cleanup is already running or queued. Please wait until it finishes.', 'casawp'));
        }
        if (!function_exists('as_schedule_single_action')) {
            return new \WP_Error('cleanup_scheduler', __('Cleanup scheduling is unavailable.', 'casawp'));
        }
        if (!casawp_acquire_lock()) {
            return new \WP_Error('cleanup_busy', __('An import or cleanup is already running or queued. Please wait until it finishes.', 'casawp'));
        }
        ImportProgress::dismissCompletionNotice();
        $previous = get_option('casawp_manual_cleanup', []);
        $run_id = max(time(), (int) ($previous['run_id'] ?? 0) + 1, (int) get_option('casawp_cleanup_done_for_run', 0) + 1);
        $state = ['run_id' => $run_id, 'status' => 'queued', 'phase' => 'attachments', 'next_batch' => 1,
            'lock' => (int) get_option('casawp_import_lock'), 'cdn' => (bool) get_option('casawp_use_casagateway_cdn', false),
            'started_at' => time(), 'updated_at' => time(), 'stats' => [], 'log_bytes' => 0];
        update_option('casawp_manual_cleanup', $state, false);
        try {
            $ids = $this->propertyIds();
            $state['properties_hash'] = hash('sha256', serialize($ids));
            update_option('casawp_manual_cleanup', $state, false);
            delete_option('casawp_manual_file_cleanup');
            delete_option('casawp_manual_file_cleanup_totals');
            delete_option('casawp_manual_cleanup_worker');
            delete_option('casawp_manual_cleanup_stop');
            $logger = $this->logger($run_id);
            $logger->separator('Start of cleanup', 'cleanup.separator.start');
            $logger->write('Manual cleanup queued: database attachments, files, taxonomy terms, then old logs. Existing properties are retained; no feed is downloaded.', ['event' => 'manual_cleanup.queued']);
            $this->schedule($run_id, 1, 'attachments', 0);
        } catch (\Throwable $e) {
            $this->fail($run_id, $e->getMessage());
            return new \WP_Error('cleanup_failed', __('Cleanup could not be started. See the logs for details.', 'casawp'));
        }
        return self::statusData();
    }

    private function propertyIds(): array
    {
        global $wpdb;
        $ids = $wpdb->get_col("SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = 'casawp_property' AND pm.meta_key = 'exportproperty_id' AND pm.meta_value <> ''");
        if ($wpdb->last_error || !is_array($ids)) { throw new \RuntimeException('Cannot identify current properties for manual cleanup: ' . $wpdb->last_error); }
        $ids = array_values(array_unique(array_map('strval', $ids)));
        sort($ids, SORT_STRING);
        return $ids;
    }

    private function logger(int $run_id): ImportLogger
    {
        $logger = new ImportLogger(static function () use ($run_id) { return $run_id; });
        $logger->setStage('manual_cleanup', ['cleanup_phase' => 'attachments']);
        return $logger;
    }

    private function ownsLock(array $state): bool
    {
        return !get_option('casawp_current_run_id') && !empty($state['lock'])
            && (int) get_option('casawp_import_lock', 0) === $state['lock'];
    }

    private function schedule(int $run_id, int $batch, string $phase, int $delay = 0): void
    {
        $action = as_schedule_single_action(time() + $delay, 'casawp_manual_cleanup_batch',
            ['batch_number' => $batch, 'run_id' => $run_id], 'casawp_manual_cleanup_' . $run_id);
        if (!$action) { throw new \RuntimeException('Could not schedule manual cleanup batch ' . $batch . '.'); }
        $this->logger($run_id)->write('Manual cleanup scheduled.', ['event' => 'scheduler.scheduled',
            'hook' => 'casawp_manual_cleanup_batch', 'action_id' => $action, 'scheduled_batch_number' => $batch,
            'delay_seconds' => $delay, 'cleanup_phase' => $phase]);
    }

    public static function handleBatch($batch_number = 1, $run_id = 0): void
    {
        (new self())->runBatch((int) $batch_number, (int) $run_id);
    }

    public function runBatch(int $batch, int $run_id): void
    {
        $state = get_option('casawp_manual_cleanup', []);
        if (($state['run_id'] ?? 0) !== $run_id || !in_array($state['status'] ?? '', ['queued', 'running'], true)
            || ($state['next_batch'] ?? 0) !== $batch) { return; }
        $worker = ['run_id' => $run_id, 'batch' => $batch, 'started_at' => time()];
        $logger = $this->logger($run_id);
        if (!$this->acquireWorker($worker)) {
            if (self::stopRequested($run_id)) { return; }
            try {
                $this->schedule($run_id, $batch, $state['phase'], 30);
                $logger->write('Manual cleanup worker is busy; the batch will be retried in 30 seconds.',
                    ['event' => 'manual_cleanup.worker_busy', 'batch_number' => $batch], 'warning');
            } catch (\Throwable $e) {
                $logger->exception('Could not schedule a busy cleanup batch for retry.', $e);
            }
            return;
        }
        $next_batch = null;
        try {
            // State may have advanced while this request was waiting to acquire the worker.
            $state = get_option('casawp_manual_cleanup', []);
            if (($state['run_id'] ?? 0) !== $run_id || ($state['next_batch'] ?? 0) !== $batch
                || !in_array($state['status'] ?? '', ['queued', 'running'], true)) { return; }
            if (self::stopRequested($run_id)) { $this->finishStopped($run_id); return; }
            if (!$this->ownsLock($state) || (bool) get_option('casawp_use_casagateway_cdn', false) !== $state['cdn']) {
                throw new \RuntimeException('Manual cleanup stopped because its lock or CDN setting changed.');
            }
            $state['lock'] = time();
            $state['status'] = 'running';
            $state['updated_at'] = time();
            update_option('casawp_import_lock', $state['lock'], false);
            update_option('casawp_manual_cleanup', $state, false);
            $logger->run('manual_cleanup_batch', ['batch_number' => $batch, 'cleanup_phase' => $state['phase']], function () use (&$state, &$next_batch, $logger, $run_id, $batch) {
                $done = (new ImportFileCleanup(CASASYNC_CUR_UPLOAD_BASEDIR, $logger, true))->runBatch($run_id, $this->propertyIds());
                if (self::stopRequested($run_id)) { return; }
                if (!$this->ownsLock($state) || (bool) get_option('casawp_use_casagateway_cdn', false) !== $state['cdn']) {
                    throw new \RuntimeException('Manual cleanup stopped because its lock or CDN setting changed.');
                }
                $totals = get_option('casawp_manual_file_cleanup_totals', []);
                if (($totals['run_id'] ?? 0) === $run_id) { $state['stats'] = $totals['stats']; }
                if (!$done) {
                    $files = get_option('casawp_manual_file_cleanup', []);
                    $state['phase'] = empty($files['legacy_done']) ? 'attachments' : (!empty($files['stack']) ? 'files' : 'terms');
                    $state['next_batch'] = $batch + 1;
                    $state['updated_at'] = time();
                    update_option('casawp_manual_cleanup', $state, false);
                    $next_batch = $batch + 1;
                    return;
                }
                $state['phase'] = 'logs';
                update_option('casawp_manual_cleanup', $state, false);
                $import = new Import(false, false);
                $import->init_single_run($run_id);
                $logger->run('log_cleanup', ['cleanup_phase' => 'logs'], static function () use ($import) { $import->cleanup_log_files(); });
                $state['log_bytes'] = $import->getLogCleanupFreedBytes();
                update_option('casawp_manual_cleanup', $state, false);
                return true;
            }, 'info', function ($finished) use (&$state, &$next_batch, $logger, $run_id) {
                if (self::stopRequested($run_id)) { $next_batch = null; $this->finishStopped($run_id); return; }
                if (!$finished) { return; }
                $logger->setStage('manual_cleanup_finalize', ['cleanup_phase' => 'complete']);
                $bytes = (int) ($state['stats']['bytes'] ?? 0) + $state['log_bytes'];
                $logger->write(sprintf('Manual cleanup total: %.2f MB freed (attachment/XML files: %.2f MB; old logs: %.2f MB).',
                    $bytes / 1048576, ($state['stats']['bytes'] ?? 0) / 1048576, $state['log_bytes'] / 1048576),
                    ['event' => 'cleanup.total', 'freed_bytes' => $bytes]);
                $state['status'] = 'completed';
                $state['phase'] = 'complete';
                $state['finished_at'] = time();
                delete_option('casawp_manual_file_cleanup_totals');
                if ($this->ownsLock($state)) { casawp_release_lock(); }
                update_option('casawp_manual_cleanup', $state, false);
                $logger->cleanupCompleted($state['stats'], $bytes, true);
                do_action('casawp_cleanup_finished', $state);
                $this->resumePendingImport();
            });
        } catch (\Throwable $e) {
            $next_batch = null;
            if (self::stopRequested($run_id)) { $this->finishStopped($run_id); }
            else { $this->fail($run_id, $e->getMessage()); }
        } finally {
            $this->releaseWorker($worker);
        }
        // Publish the continuation only after releasing this batch's worker lock.
        if ($next_batch !== null && !self::stopRequested($run_id)) {
            try { $this->schedule($run_id, $next_batch, $state['phase']); }
            catch (\Throwable $e) { $this->fail($run_id, $e->getMessage()); }
        }
    }

    private function fail(int $run_id, string $error): void
    {
        $state = get_option('casawp_manual_cleanup', []);
        if (($state['run_id'] ?? 0) !== $run_id || !in_array($state['status'] ?? '', ['queued', 'running'], true)) { return; }
        $files = get_option('casawp_manual_file_cleanup', []);
        if (($files['run_id'] ?? 0) === $run_id) { $state['phase'] = $files['logged_phase'] ?? $state['phase']; }
        $totals = get_option('casawp_manual_file_cleanup_totals', []);
        if (($totals['run_id'] ?? 0) === $run_id) { $state['stats'] = $totals['stats']; }
        $logger = $this->logger($run_id);
        $logger->setStage('manual_cleanup', ['cleanup_phase' => $state['phase'] ?? 'attachments']);
        $logger->write('Manual cleanup stopped: ' . $error, ['event' => 'manual_cleanup.failed'], 'error');
        $state['status'] = 'failed';
        $state['finished_at'] = time();
        if ($this->ownsLock($state)) { casawp_release_lock(); }
        update_option('casawp_manual_cleanup', $state, false);
        $this->resumePendingImport();
    }

    private function resumePendingImport(): void
    {
        if (function_exists('as_schedule_single_action') && casawp_has_pending() && !casawp_is_import_running()) {
            if (as_schedule_single_action(time(), 'casawp_process_gateway_poke', ['Deferred until manual cleanup finished'], casawp_gateway_poke_group())) {
                casawp_clear_pending();
            }
        }
    }

    public static function handleFailure($action_id): void
    {
        $action = \ActionScheduler::store()->fetch_action($action_id);
        if ($action && $action->get_hook() === 'casawp_manual_cleanup_batch') {
            $args = $action->get_args();
            (new self())->fail((int) ($args['run_id'] ?? 0), 'The background cleanup action failed or terminated unexpectedly.');
        }
    }

    public static function statusData(): array
    {
        $state = get_option('casawp_manual_cleanup', []);
        $status = $state['status'] ?? 'idle';
        if (in_array($status, ['queued', 'running'], true) && self::stopRequested((int) $state['run_id'])) { $status = 'stopping'; }
        $files = get_option('casawp_manual_file_cleanup', []);
        $totals = get_option('casawp_manual_file_cleanup_totals', []);
        $stats = ($totals['run_id'] ?? 0) === ($state['run_id'] ?? -1) ? ($totals['stats'] ?? []) : ($state['stats'] ?? []);
        $bytes = (int) ($stats['bytes'] ?? 0) + (int) ($state['log_bytes'] ?? 0);
        $mb = number_format_i18n($bytes / 1048576, 2);
        $phase = ($files['run_id'] ?? 0) === ($state['run_id'] ?? -1) ? ($files['logged_phase'] ?? $state['phase']) : ($state['phase'] ?? 'attachments');
        $labels = ['attachments' => __('Database attachments', 'casawp'), 'files' => __('Files and folders', 'casawp'),
            'terms' => __('Empty taxonomy terms', 'casawp'), 'logs' => __('Old logs', 'casawp')];
        $message = '';
        $can_resume = self::canResume($state);
        if ($status === 'queued') { $message = __('Cleanup queued.', 'casawp'); }
        elseif ($status === 'running') {
            $message = sprintf(__('Cleanup running: %1$s (batch %2$d). %3$s MB freed.', 'casawp'), $labels[$phase] ?? $labels['attachments'], $state['next_batch'], $mb);
        } elseif ($status === 'completed') {
            $message = !empty($stats['failures']) ? __('Cleanup completed with warnings. See the logs for details.', 'casawp') : __('Cleanup completed.', 'casawp');
            $message .= ' ' . ImportProgress::cleanupSummary($stats, $bytes);
        } elseif ($status === 'failed') { $message = __('Cleanup stopped. See the logs for details.', 'casawp'); }
        elseif ($status === 'stopping') { $message = __('Stopping cleanup. Waiting for the active batch to exit.', 'casawp'); }
        elseif ($status === 'stopped') {
            $message = __('Cleanup stopped. Changes already made were retained.', 'casawp') . ' ' . ImportProgress::cleanupSummary($stats, $bytes);
        }
        if ($can_resume) { $message = __('Cleanup has not progressed for at least 10 minutes. Resume it to continue from the last saved batch.', 'casawp'); }
        return ['status' => $status, 'run_id' => (int) ($state['run_id'] ?? 0), 'message' => $message, 'phase' => $phase, 'freed_bytes' => $bytes,
            'can_resume' => $can_resume,
            'can_stop' => in_array($status, ['queued', 'running'], true),
            'notice_seconds' => $status === 'completed' && empty($state['notice_dismissed']) ? ImportProgress::completionNoticeSeconds((int) ($state['finished_at'] ?? 0)) : 0,
            'can_start' => !get_option('casawp_current_run_id') && !casawp_is_import_running() && !casawp_has_pending()];
    }
}
