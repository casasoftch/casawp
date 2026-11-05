<?php

namespace casawp;

/** Coalesce automatic pokes without putting manual imports behind their cooldown. */
class GatewayPokeCooldown
{
    private const HOOK = 'casawp_process_gateway_poke';
    private const ARGS = ['Poke from CasaGateway', true];
    private const PENDING = 'casawp_gateway_poke_pending';
    private const LAST_FETCH = 'casawp_gateway_last_fetch_started';

    public static function registerHooks(): void
    {
        add_action('init', [self::class, 'resume'], 20);
        // Unique actions cannot schedule their successor until their own status changes.
        add_action('action_scheduler_completed_action', [self::class, 'actionFinished']);
        add_action('action_scheduler_failed_execution', [self::class, 'actionFinished']);
        add_action('action_scheduler_failed_action', [self::class, 'actionFinished']);
        add_action('add_option_casawp_poke_cooldown', [self::class, 'settingsChanged']);
        add_action('update_option_casawp_poke_cooldown', [self::class, 'settingsChanged']);
    }

    public static function sanitizeMinutes($value): string
    {
        $value = is_string($value) || is_int($value) ? trim((string) $value) : '';
        if ($value === '' || !ctype_digit($value)) { return ''; }
        $minutes = filter_var(ltrim($value, '0'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => intdiv(PHP_INT_MAX - time(), MINUTE_IN_SECONDS)],
        ]);
        return $minutes === false ? '' : (string) $minutes;
    }

    private static function option(string $name, $default = false)
    {
        // Queue workers and frontend requests may share a persistent options cache.
        wp_cache_delete($name, 'options');
        wp_cache_delete('notoptions', 'options');
        return get_option($name, $default);
    }

    public static function hasPending(): bool
    {
        return (bool) self::option(self::PENDING, false);
    }

    private static function nextFetchAt(): int
    {
        $minutes = (int) self::sanitizeMinutes(self::option('casawp_poke_cooldown', ''));
        $last_fetch = (int) self::option(self::LAST_FETCH, 0);
        return $minutes && $last_fetch ? $last_fetch + $minutes * MINUTE_IN_SECONDS : 0;
    }

    public static function request(): void
    {
        self::option(self::PENDING);
        update_option(self::PENDING, 1, false);
        self::resume();
    }

    /** Called for every gateway fetch, including manual imports and failed attempts. */
    public static function fetchStarted(): void
    {
        self::option(self::LAST_FETCH, 0);
        update_option(self::LAST_FETCH, time(), false);
        // A fresh fetch also satisfies pokes received before a manual fetch began.
        delete_option(self::PENDING);
    }

    public static function resume(): void
    {
        if (!self::hasPending()) { return; }

        $now = time();
        $scheduled_at = max($now, self::nextFetchAt());
        if (casawp_is_import_running() && $scheduled_at <= $now) {
            // A long import/maintenance job may outlast the cooldown. Never cancel it.
            $scheduled_at = $now + MINUTE_IN_SECONDS;
        }

        $cron_at = wp_next_scheduled(self::HOOK, self::ARGS);
        if ($cron_at && $cron_at <= $scheduled_at) { return; }
        if ($cron_at) { wp_clear_scheduled_hook(self::HOOK, self::ARGS); }
        if (function_exists('as_schedule_single_action')) {
            $next = as_next_scheduled_action(self::HOOK, self::ARGS, casawp_gateway_poke_group());
            if ($next === true || (is_int($next) && $next <= $scheduled_at)) { return; }
            if ($next) { as_unschedule_all_actions(self::HOOK, self::ARGS, casawp_gateway_poke_group()); }
            $action_id = as_schedule_single_action($scheduled_at, self::HOOK, self::ARGS, casawp_gateway_poke_group(), true);
            if ($action_id) {
                (new Import(false, false))->addToLog('Gateway pokes combined into one import; next check at ' . gmdate('c', $scheduled_at) . '.');
                if ($scheduled_at <= $now) {
                    add_action('shutdown', 'casawp_dispatch_import_queue_after_poke', 0);
                }
                return;
            }
            // Another request may have won the unique-action insert.
            if (as_has_scheduled_action(self::HOOK, null, casawp_gateway_poke_group())) { return; }
        }

        // Keep the deferred request durable even if Action Scheduler is unavailable.
        wp_schedule_single_event($scheduled_at, self::HOOK, self::ARGS);
    }

    public static function run(): void
    {
        if (!self::hasPending()) { return; }
        if (!casawp_acquire_lock()) {
            self::resume();
            return;
        }

        $handed_to_importer = false;
        try {
            // Check under the same lock used by manual imports and maintenance.
            // A manual fetch may have satisfied the poke or moved its deadline.
            if (!self::hasPending() || self::nextFetchAt() > time()) { return; }
            $handed_to_importer = true;
            if (casawp_is_single_request_enabled()) {
                casawp_start_single_request_import('Poke from CasaGateway', true);
            } else {
                casawp_start_new_import('Poke from CasaGateway', false, true);
            }
        } finally {
            if (!$handed_to_importer) { casawp_release_lock(); }
            // WordPress cron removes its event before invoking us; Action Scheduler
            // retries scheduling through actionFinished after marking this action complete.
            self::resume();
        }
    }

    public static function actionFinished($action_id): void
    {
        if (!self::hasPending()) { return; }
        $action = \ActionScheduler::store()->fetch_action($action_id);
        if ($action && $action->get_hook() === self::HOOK) { self::resume(); }
    }

    public static function settingsChanged(): void
    {
        self::unschedule();
        self::resume();
    }

    private static function unschedule(): void
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::HOOK, self::ARGS, casawp_gateway_poke_group());
        }
        wp_clear_scheduled_hook(self::HOOK, self::ARGS);
    }

    public static function cancel(): void
    {
        delete_option(self::PENDING);
        self::unschedule();
    }
}
