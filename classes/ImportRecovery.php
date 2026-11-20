<?php

namespace casawp;

/** Site-local recovery for batch failures reported by the scheduler. */
class ImportRecovery
{
    public const HOOK = 'casawp_retry_failed_import';
    private const GROUP = 'casawp_import_recovery';

    public static function registerHooks(): void
    {
        add_action(self::HOOK, [self::class, 'run']);
    }

    public static function schedule(int $run_id): void
    {
        self::enqueue($run_id, 15 * MINUTE_IN_SECONDS, true);
    }

    private static function enqueue(int $run_id, int $delay, bool $unique): void
    {
        try {
            $scheduled_at = time() + $delay;
            $action_id = as_schedule_single_action($scheduled_at, self::HOOK,
                ['run_id' => $run_id], self::GROUP, $unique);
            self::log($run_id, $action_id ? 'recovery.scheduled' : 'recovery.schedule_failed',
                $action_id ? 'Fresh import recovery scheduled.' : 'Could not schedule import recovery.',
                ['action_id' => (int) $action_id, 'scheduled_at' => gmdate('c', $scheduled_at)],
                $action_id ? 'info' : 'warning');
        } catch (\Throwable $error) {
            // Recovery must not prevent the existing failure notification.
            self::log($run_id, 'recovery.schedule_failed', 'Could not schedule import recovery.', [], 'warning');
        }
    }

    private static function eligible(int $run_id): bool
    {
        foreach (['casawp_import_progress_run_id', 'casawp_import_failed', 'casawp_import_canceled'] as $key) {
            wp_cache_delete($key, 'options');
        }
        wp_cache_delete('notoptions', 'options');
        return $run_id > 0
            && (int) get_option('casawp_import_progress_run_id', 0) === $run_id
            && get_option('casawp_import_failed', false)
            && !get_option('casawp_import_canceled', false)
            && !casawp_current_import_run_id();
    }

    public static function run(int $run_id): void
    {
        if (!self::eligible($run_id)) { return; }
        if (!casawp_acquire_lock()) {
            // Maintenance may own the lock. Yield the worker instead of waiting
            // or canceling it. This action is running, so its successor is nonunique.
            self::enqueue($run_id, MINUTE_IN_SECONDS, false);
            return;
        }

        $handed_to_importer = false;
        try {
            // A newer import or cancellation may have won before we took the lock.
            if (!self::eligible($run_id)) { return; }
            self::log($run_id, 'recovery.started', 'Starting a fresh gateway import after a batch failure.');
            $handed_to_importer = true;
            \casawp_start_new_import('Automatic recovery after batch failure', false, true);
        } finally {
            if (!$handed_to_importer) { casawp_release_lock(); }
        }
    }

    /** New imports, explicit cancellation and deactivation supersede recovery. */
    public static function cancel(): void
    {
        try {
            as_unschedule_all_actions(self::HOOK, null, self::GROUP);
        } catch (\Throwable $error) {
            // Ownership checks also make old recovery actions harmless.
            error_log('CASAWP could not cancel pending import recovery actions.');
        }
    }

    private static function log(int $run_id, string $event, string $message, array $context = [], string $level = 'info'): void
    {
        try {
            (new ImportLogger(static function () use ($run_id) { return $run_id; }))
                ->write($message, array_merge($context, ['event' => $event]), $level);
        } catch (\Throwable $error) {
            error_log('CASAWP: ' . $message);
        }
    }
}
