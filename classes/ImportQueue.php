<?php

namespace casawp;

/** Continue property batches promptly without combining them into longer web requests. */
class ImportQueue
{
    private static bool $limited_request = false;
    private static bool $yield_request = false;
    private static bool $checked_request = false;

    public static function registerHooks(): void
    {
        add_filter('action_scheduler_queue_runner_time_limit', [self::class, 'queueTimeLimit'], 20);
        add_filter('action_scheduler_timeout_period', [self::class, 'claimTimeout']);
        add_filter('action_scheduler_failure_period', [self::class, 'claimTimeout']);
        add_action('action_scheduler_begin_execute', [self::class, 'beforeAction'], 10, 2);
        add_filter('action_scheduler_maximum_execution_time_likely_to_be_exceeded', [self::class, 'shouldYield']);
    }

    private static function isCli(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }

    private static function limitedRequest(): bool
    {
        if (self::isCli()) { return false; }
        // Keep this limit for the rest of the request, even when its last action completes the import.
        if (!self::$limited_request && !self::$checked_request) {
            self::$checked_request = true;
            self::$limited_request = casawp_current_import_run_id() > 0;
        }
        return self::$limited_request;
    }

    public static function queueTimeLimit($seconds)
    {
        return self::limitedRequest() ? min(20, (int) $seconds) : $seconds;
    }

    public static function claimTimeout($seconds)
    {
        // The queue cleaner derives claim expiry from its time budget. Shorter requests must not
        // prematurely expire another plugin's work, or enable an explicitly disabled expiry.
        return (int) $seconds >= 0 && self::limitedRequest() ? max(600, (int) $seconds) : $seconds;
    }

    public static function beforeAction($action_id, $context = ''): void
    {
        if (self::isCli()) { return; }
        $action = \ActionScheduler::store()->fetch_action($action_id);
        if ($action->get_hook() !== 'casawp_batch_import') { return; }
        self::$limited_request = true;
        self::$yield_request = true;
        if ($context !== 'Async Request') {
            // Cron does not chain async requests itself. Run after its claim has been released;
            // priority zero also lets a successful dispatch unhook the ordinary admin dispatcher.
            add_action('shutdown', 'casawp_dispatch_import_queue_after_poke', 0);
        }
        // The native async runner retains its five-second pause and dispatches its own continuation.
    }

    public static function shouldYield($exceeded)
    {
        return self::$yield_request || $exceeded;
    }
}
