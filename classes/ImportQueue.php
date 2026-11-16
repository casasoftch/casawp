<?php

namespace casawp;

/** Continue property batches promptly without combining them into longer web requests. */
class ImportQueue
{
    private static bool $limited_request = false;
    private static bool $yield_request = false;
    private static bool $checked_request = false;
    private static bool $other_action_executed = false;
    private static ?array $property_action = null;
    private const HANDOFF_STATE = 'casawp_import_async_handoff';
    private const MAX_HANDOFFS = 5;

    public static function registerHooks(): void
    {
        add_filter('action_scheduler_queue_runner_time_limit', [self::class, 'queueTimeLimit'], 20);
        add_filter('action_scheduler_timeout_period', [self::class, 'claimTimeout']);
        add_filter('action_scheduler_failure_period', [self::class, 'claimTimeout']);
        add_action('action_scheduler_begin_execute', [self::class, 'beforeAction'], 10, 2);
        add_action('action_scheduler_after_execute', [self::class, 'afterAction'], PHP_INT_MAX);
        add_action('action_scheduler_failed_execution', [self::class, 'afterAction'], PHP_INT_MAX);
        add_filter('action_scheduler_maximum_execution_time_likely_to_be_exceeded', [self::class, 'shouldYield']);
        add_filter('action_scheduler_async_request_sleep_seconds', [self::class, 'asyncRequestSleep'], 20);
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
        self::$property_action = null;
        if (self::isCli()) { return; }
        $action = \ActionScheduler::store()->fetch_action($action_id);
        if ($action->get_hook() !== 'casawp_batch_import') {
            self::$other_action_executed = true;
            return;
        }
        self::$limited_request = true;
        self::$yield_request = true;
        self::$property_action = [
            'id' => (int) $action_id, 'context' => $context, 'args' => $action->get_args(),
            'group' => $action->get_group(),
            'priority' => method_exists($action, 'get_priority') ? $action->get_priority() : 10,
        ];
        if ($context !== 'Async Request') {
            // Cron does not chain async requests itself. Run after its claim has been released;
            // priority zero also lets a successful dispatch unhook the ordinary admin dispatcher.
            add_action('shutdown', 'casawp_dispatch_import_queue_after_poke', 0);
        }
        // The native async runner dispatches its own continuation after releasing the claim.
    }

    public static function afterAction($action_id): void
    {
        if ((self::$property_action['id'] ?? null) === (int) $action_id) { self::$property_action = null; }
    }

    /** Keep WPML's frontend save handler out of cron-dispatched property batches. */
    public static function handoffPropertyBatch(): bool
    {
        global $wpml_post_translations;
        $action = self::$property_action;
        if (self::isCli() || !$action || $action['context'] !== 'WP Cron'
            || !is_a($wpml_post_translations, 'WPML_Frontend_Post_Actions')) {
            return false;
        }
        $run_id = casawp_current_import_run_id();
        $args = $action['args'];
        $owner = (int) ($args['run_id'] ?? $args[1] ?? 0);
        if (!$owner && preg_match('/^casawp_run_(\d+)$/', $action['group'], $group)) { $owner = (int) $group[1]; }
        if (!$run_id || $owner !== $run_id) { return false; }

        $batch = (int) ($args['batch_number'] ?? $args[0] ?? 0);
        $offset = $args['property_offset'] ?? $args[2] ?? null;
        $key = [$run_id, $batch, $offset === null ? null : (int) $offset];
        $state = get_option(self::HANDOFF_STATE, []);
        $attempt = is_array($state) && ($state['key'] ?? null) === $key ? (int) ($state['attempt'] ?? 0) + 1 : 1;
        $logger = new ImportLogger(static function () use ($run_id) { return $run_id; });
        $context = ['run_id' => $run_id, 'batch_number' => $batch, 'property_offset' => $offset,
            'action_id' => $action['id'], 'handoff_attempt' => $attempt];
        try {
            if ($attempt > self::MAX_HANDOFFS) {
                throw new \RuntimeException('Property batch could not reach the async runner after five cron handoffs. Check background AJAX/loopback requests.');
            }
            // Persist the attempt before scheduling. No progress/cursor is advanced here.
            $next_state = ['key' => $key, 'attempt' => $attempt];
            if (update_option(self::HANDOFF_STATE, $next_state, false) === false
                && get_option(self::HANDOFF_STATE) !== $next_state) {
                throw new \RuntimeException('Could not save the property batch async handoff attempt.');
            }
            // The original action is still running, so a uniqueness lookup would find
            // that action itself. Its claim is released before the shutdown dispatch.
            $next_id = as_schedule_single_action(time(), 'casawp_batch_import', $args, $action['group'], false, $action['priority']);
            if (!$next_id) { throw new \RuntimeException('Could not schedule the property batch async handoff.'); }
            if (method_exists('ActionScheduler', 'logger')) {
                try {
                    \ActionScheduler::logger()->log($action['id'], 'CASAWP handed this batch to async action ' . $next_id . ' before importing properties (WPML frontend handler under cron).');
                    \ActionScheduler::logger()->log($next_id, 'CASAWP continuation of cron action ' . $action['id'] . '; same batch, run and property cursor.');
                } catch (\Throwable $log_error) {
                    $logger->write('Could not add the async handoff links to Action Scheduler logs.',
                        array_merge($context, ['event' => 'scheduler.handoff_log_failed', 'next_action_id' => (int) $next_id]), 'warning');
                }
            }
            $logger->write(sprintf('Import batch %d handed from WP Cron to the async queue: action %d to %d (attempt %d/%d).',
                $batch, $action['id'], $next_id, $attempt, self::MAX_HANDOFFS), array_merge($context, [
                'event' => 'scheduler.handoff', 'next_action_id' => (int) $next_id,
            ]));
            return true;
        } catch (\Throwable $error) {
            $logger->exception('Import batch async handoff failed.', $error,
                array_merge($context, ['event' => 'scheduler.handoff_failed']));
            // Action Scheduler's native failure path marks the job failed and invokes
            // CASAWP's existing run failure, lock release, notification and status hooks.
            throw $error;
        }
    }

    public static function asyncRequestSleep($seconds)
    {
        // Preserve the native throttle when this request also processed other queue work.
        if (self::isCli() || self::$other_action_executed) {
            return $seconds;
        }
        // Property actions already yield after one batch. Sleeping after releasing their
        // claim lets another runner start and can leave every PHP worker asleep at once.
        if (self::$yield_request) { return 0; }
        if (!self::limitedRequest()) { return $seconds; }
        $state = get_option('casawp_import_progress_state', []);
        if (is_array($state) && ($state['phase'] ?? '') === 'properties'
            && (int) ($state['run_id'] ?? 0) === casawp_current_import_run_id()) {
            // A competing runner can find no work while another request owns the claim.
            // Sleeping here would occupy a PHP worker without processing a batch.
            return 0;
        }
        return $seconds;
    }

    public static function shouldYield($exceeded)
    {
        return self::$yield_request || $exceeded;
    }
}
