<?php

namespace casawp;

/** Import diagnostics in JSON, preserving the original timestamp => transcript entry. */
class ImportLogger
{
    private array $context = [];
    private string $request_id;
    private float $request_started;
    private $run_id;
    private $failure_run_id;
    private int $errors = 0;
    private array $outcomes = [];
    private int $active_operations = 0;
    private array $operation_stages = [];
    private bool $shutdown_registered = false;
    private string $memory_reserve = '';
    private static array $action_context = [];
    private static ?self $active_logger = null;
    private ?array $debug_stage = null;
    private int $stage_sequence = 0;
    private int $hook_sequence = 0;
    private array $hook_stack = [];
    private array $reported_hooks = [];
    private array $http_requests = [];
    private int $http_sequence = 0;
    private int $callback_sequence = 0;
    private array $callback_stack = [];
    private static array $wrapped_callbacks = [];
    private ?string $request_context_signature = null;

    public static function registerHooks(): void
    {
        add_action('action_scheduler_begin_execute', [self::class, 'actionStarted'], 1, 2);
        add_action('action_scheduler_after_execute', [self::class, 'actionFinished'], PHP_INT_MAX);
        add_action('action_scheduler_failed_execution', [self::class, 'actionFinished'], PHP_INT_MAX);
        add_filter('pre_http_request', [self::class, 'httpStarted'], PHP_INT_MAX, 3);
        add_action('http_api_debug', [self::class, 'httpFinished'], 10, 5);
        foreach (['save_post_casawp_property', 'save_post', 'wp_insert_post', 'wp_after_insert_post',
            'add_attachment', 'edit_attachment', 'wpml_set_element_language_details'] as $hook) {
            add_action($hook, [self::class, 'hookStarted'], PHP_INT_MIN, 1);
            add_action($hook, [self::class, 'hookFinished'], PHP_INT_MAX, 0);
        }
    }

    public static function actionStarted($action_id, $context = ''): void
    {
        self::$action_context = ['action_id' => (int) $action_id, 'runner_context' => (string) $context];
    }

    public static function actionFinished($action_id): void
    {
        if ((self::$action_context['action_id'] ?? null) === (int) $action_id) {
            self::$action_context = [];
        }
    }

    private function debugEnabled(): bool
    {
        return apply_filters('casawp_import_log_level', 'info') === 'debug';
    }

    private function queryCount(): int
    {
        global $wpdb;
        return isset($wpdb->num_queries) ? (int) $wpdb->num_queries : 0;
    }

    private function startDebugStage(): void
    {
        if (!$this->debugEnabled() || !isset($this->context['stage'])) { return; }
        $this->debug_stage = [
            'context' => $this->context,
            'sequence' => ++$this->stage_sequence,
            'started' => microtime(true),
            'queries' => $this->queryCount(),
        ];
        $this->write('Import stage started: ' . $this->context['stage'] . '.', [
            'event' => 'stage.started', 'stage_sequence' => $this->stage_sequence,
        ], 'debug');
    }

    private function finishDebugStage(bool $success = true): void
    {
        if ($this->debug_stage === null) { return; }
        $stage = $this->debug_stage;
        $this->debug_stage = null;
        $this->write('Import stage ' . ($success ? 'finished: ' : 'failed: ') . $stage['context']['stage'] . '.', array_merge($stage['context'], [
            'event' => $success ? 'stage.finished' : 'stage.failed',
            'stage_sequence' => $stage['sequence'],
            'stage_duration_seconds' => round(microtime(true) - $stage['started'], 6),
            'stage_query_count' => max(0, $this->queryCount() - $stage['queries']),
        ]), 'debug');
    }

    public static function hookStarted($object_id = null): void
    {
        $logger = self::$active_logger;
        if (!$logger || !$logger->debugEnabled()) { return; }
        $hook = current_filter();
        $entry = ['hook' => $hook, 'hook_sequence' => ++$logger->hook_sequence,
            'started' => microtime(true), 'queries' => $logger->queryCount()];
        $logger->hook_stack[] = $entry;
        if (!isset($logger->reported_hooks[$hook])) {
            $logger->reported_hooks[$hook] = true;
            $logger->write('Callbacks registered for WordPress hook: ' . $hook . '.', [
                'event' => 'hook.callbacks', 'hook' => $hook, 'callbacks' => $logger->hookCallbacks($hook),
            ], 'debug');
        }
        $logger->write('WordPress hook started: ' . $hook . '.', [
            'event' => 'hook.started', 'hook' => $hook, 'hook_sequence' => $entry['hook_sequence'],
            'hook_object_id' => is_numeric($object_id) ? (int) $object_id
                : (is_array($object_id) ? (int) ($object_id['element_id'] ?? 0) : null),
        ], 'debug');
        if ($hook === 'save_post') { self::wrapSaveCallbacks(); }
        // Plugins can register at PHP_INT_MAX too. Put our end marker after them.
        remove_action($hook, [self::class, 'hookFinished'], PHP_INT_MAX);
        add_action($hook, [self::class, 'hookFinished'], PHP_INT_MAX, 0);
    }

    private static function callbackName($callback): string
    {
        if (is_array($callback)) {
            return (is_object($callback[0]) ? get_class($callback[0]) : $callback[0]) . '::' . $callback[1];
        }
        if ($callback instanceof \Closure) {
            $reflection = new \ReflectionFunction($callback);
            return 'Closure@' . $reflection->getFileName() . ':' . $reflection->getStartLine();
        }
        return is_string($callback) ? $callback : get_class($callback) . '::__invoke';
    }

    /** Keep WP_Hook's IDs, priorities and accepted_args intact for remove/has_action. */
    private static function wrapSaveCallbacks(): void
    {
        global $wp_filter;
        foreach (($wp_filter['save_post']->callbacks ?? []) as $priority => $items) {
            foreach ($items as $id => $item) {
                $callback = $item['function'];
                if (is_array($callback) && $callback[0] === self::class) { continue; }
                if ((self::$wrapped_callbacks[$priority][$id]['wrapper'] ?? null) === $callback) { continue; }
                $name = self::callbackName($callback);
                $wrapper = static function (...$args) use ($callback, $name, $priority) {
                    $logger = self::$active_logger;
                    if (!$logger || !$logger->debugEnabled()) { return call_user_func_array($callback, $args); }
                    $entry = ['callback' => $name, 'callback_priority' => (int) $priority,
                        'callback_sequence' => ++$logger->callback_sequence, 'hook' => 'save_post'];
                    $depth = count($logger->callback_stack);
                    $logger->callback_stack[] = $entry;
                    $started = microtime(true);
                    $queries = $logger->queryCount();
                    $logger->write('WordPress callback started.', array_merge($entry, ['event' => 'callback.started']), 'debug');
                    try {
                        $result = call_user_func_array($callback, $args);
                        $logger->write('WordPress callback finished.', array_merge($entry, [
                            'event' => 'callback.finished',
                            'callback_duration_seconds' => round(microtime(true) - $started, 6),
                            'callback_query_count' => max(0, $logger->queryCount() - $queries),
                        ]), 'debug');
                        return $result;
                    } catch (\Throwable $error) {
                        $logger->exception('WordPress callback failed.', $error, array_merge($entry, ['event' => 'callback.failed']));
                        throw $error;
                    } finally {
                        $logger->callback_stack = array_slice($logger->callback_stack, 0, $depth);
                        // Pick up callbacks added/replaced by the callback that just ran.
                        self::wrapSaveCallbacks();
                    }
                };
                self::$wrapped_callbacks[$priority][$id] = ['original' => $callback, 'wrapper' => $wrapper];
                $wp_filter['save_post']->callbacks[$priority][$id]['function'] = $wrapper;
            }
        }
    }

    private static function restoreSaveCallbacks(): void
    {
        global $wp_filter;
        foreach (self::$wrapped_callbacks as $priority => $items) {
            foreach ($items as $id => $entry) {
                // A callback may remove or replace itself. Preserve that change.
                if (($wp_filter['save_post']->callbacks[$priority][$id]['function'] ?? null) === $entry['wrapper']) {
                    $wp_filter['save_post']->callbacks[$priority][$id]['function'] = $entry['original'];
                }
            }
        }
        self::$wrapped_callbacks = [];
    }

    public static function hookFinished(): void
    {
        $logger = self::$active_logger;
        if (!$logger || !$logger->hook_stack) { return; }
        $hook = current_filter();
        for ($i = count($logger->hook_stack) - 1; $i >= 0; $i--) {
            $entry = $logger->hook_stack[$i];
            if ($entry['hook'] !== $hook) { continue; }
            array_splice($logger->hook_stack, $i, 1);
            $logger->write('WordPress hook finished: ' . $hook . '.', [
                'event' => 'hook.finished', 'hook' => $hook, 'hook_sequence' => $entry['hook_sequence'],
                'hook_duration_seconds' => round(microtime(true) - $entry['started'], 6),
                'hook_query_count' => max(0, $logger->queryCount() - $entry['queries']),
            ], 'debug');
            break;
        }
    }

    private function hookCallbacks(string $hook): array
    {
        global $wp_filter;
        $callbacks = [];
        foreach (($wp_filter[$hook]->callbacks ?? []) as $priority => $registered) {
            foreach ($registered as $id => $item) {
                $callback = $item['function'];
                if ($hook === 'save_post' && (self::$wrapped_callbacks[$priority][$id]['wrapper'] ?? null) === $callback) {
                    $callback = self::$wrapped_callbacks[$priority][$id]['original'];
                }
                if (is_array($callback) && $callback[0] === self::class) { continue; }
                $callbacks[] = ['priority' => (int) $priority, 'callback' => self::callbackName($callback)];
            }
        }
        return $callbacks;
    }

    public static function httpStarted($preempt, $args, $url)
    {
        $logger = self::$active_logger;
        if ($preempt !== false || !$logger || !$logger->debugEnabled()) { return $preempt; }
        $sequence = ++$logger->http_sequence;
        $logger->http_requests[$url][] = ['sequence' => $sequence, 'started' => microtime(true)];
        $logger->write('HTTP request started.', [
            'event' => 'http.started', 'http_sequence' => $sequence, 'http_url' => $url,
            'http_method' => $args['method'] ?? 'GET', 'http_timeout' => $args['timeout'] ?? null,
        ], 'debug');
        return $preempt;
    }

    public static function httpFinished($response, $context, $transport, $args, $url): void
    {
        $logger = self::$active_logger;
        if (!$logger || empty($logger->http_requests[$url])) { return; }
        $entry = array_pop($logger->http_requests[$url]);
        if (!$logger->http_requests[$url]) { unset($logger->http_requests[$url]); }
        $logger->write('HTTP request finished.', [
            'event' => 'http.finished', 'http_sequence' => $entry['sequence'], 'http_url' => $url,
            'http_duration_seconds' => round(microtime(true) - $entry['started'], 6),
            'http_status' => is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response),
            'http_error_code' => is_wp_error($response) ? $response->get_error_code() : null,
            'http_error' => is_wp_error($response) ? substr($response->get_error_message(), 0, 500) : null,
        ], 'debug');
    }

    public function __construct(callable $run_id, ?callable $failure_run_id = null)
    {
        $this->run_id = $run_id;
        $this->failure_run_id = $failure_run_id;
        $this->request_id = uniqid('import_', true);
        $this->request_started = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    }

    public function setStage(string $stage, array $context = []): void
    {
        $this->finishDebugStage();
        $this->context = array_merge($this->context, $context, ['stage' => $stage]);
        $this->startDebugStage();
    }

    public function separator(string $label, string $event, array $context = []): void
    {
        $this->write('-------------------- ' . $label . ' --------------------', array_merge($context, ['event' => $event]));
    }

    /** Diagnostics use English independently of the translated settings-page notices. */
    public function cleanupCompleted(array $stats, int $bytes, bool $manual = false): void
    {
        $message = $manual ? 'Cleanup completed.' : 'Import and cleanup completed.';
        if (!empty($stats['failures'])) {
            $message = $manual ? 'Cleanup completed with warnings. See the logs for details.'
                : 'Import and cleanup completed with warnings. See the logs for details.';
        }
        $counts = [
            'deleted_attachments' => (int) ($stats['legacy_deleted'] ?? 0),
            'deleted_files' => (int) ($stats['files'] ?? 0),
            'deleted_terms' => (int) ($stats['terms_deleted'] ?? 0),
            'freed_bytes' => $bytes,
        ];
        $message .= sprintf(' Removed: unused attachments (%s), local import files (%s), empty taxonomy terms (%s). %s MB freed.',
            number_format($counts['deleted_attachments'], 0, '.', ','),
            number_format($counts['deleted_files'], 0, '.', ','),
            number_format($counts['deleted_terms'], 0, '.', ','),
            number_format($bytes / 1048576, 2, '.', ','));
        $this->write($message, array_merge(['event' => $manual ? 'manual_cleanup.completed' : 'cleanup.summary'], $counts));
        $this->separator('End of cleanup', 'cleanup.separator');
        if (!$manual) { $this->separator('End of import', 'import.separator.end'); }
    }

    /** Completion work runs after the finished entry, with error/fatal capture still active. */
    public function run(string $stage, array $context, callable $callback, string $level = 'info', ?callable $afterFinished = null)
    {
        $previous = $this->context;
        $started = microtime(true);
        $errors_before = $this->errors;
        $outcomes_before = $this->outcomes;
        $finished = false;
        $succeeded = false;
        $previous_logger = self::$active_logger;
        self::$active_logger = $this->debugEnabled() ? $this : null;
        $previous_hook_depth = count($this->hook_stack);
        $previous_callback_depth = count($this->callback_stack);
        $previous_http_requests = $this->http_requests;
        $this->active_operations++;
        $this->operation_stages[] = $stage;
        if (!$this->shutdown_registered) {
            $this->shutdown_registered = true;
            $this->memory_reserve = str_repeat(' ', 262144);
            register_shutdown_function([$this, 'logFatalError']);
        }
        $this->setStage($stage, $context);
        $this->write($this->stageLabel($stage) . ' started.', ['event' => $stage . '.started'], $level);
        try {
            $result = $callback();
            $error_count = $this->errors - $errors_before;
            $counts = [];
            foreach ($this->outcomes as $event => $count) {
                $difference = $count - ($outcomes_before[$event] ?? 0);
                if ($difference) {
                    $counts[$event] = $difference;
                }
            }
            $completion = [
                'event' => $stage . '.finished',
                'duration_seconds' => round(microtime(true) - $started, 3),
                'error_count' => $error_count,
                'counts' => $counts,
            ];
            if (isset($context['cleanup_phase'])) { $completion['cleanup_phase'] = $context['cleanup_phase']; }
            $this->write($this->stageLabel($stage) . ' finished.', $completion, $error_count ? 'warning' : $level);
            $finished = true;
            if ($afterFinished) { $afterFinished($result); }
            $succeeded = true;
            return $result;
        } catch (\Throwable $e) {
            $this->exception($this->stageLabel($stage) . ($finished ? ' completion failed.' : ' failed.'), $e, [
                'event' => $stage . ($finished ? '.completion_failed' : '.failed'),
                'duration_seconds' => round(microtime(true) - $started, 3),
            ]);
            throw $e;
        } finally {
            $this->finishDebugStage($succeeded);
            $this->hook_stack = array_slice($this->hook_stack, 0, $previous_hook_depth);
            $this->callback_stack = array_slice($this->callback_stack, 0, $previous_callback_depth);
            $this->http_requests = $previous_http_requests;
            $this->active_operations--;
            array_pop($this->operation_stages);
            $this->context = $previous;
            self::$active_logger = $previous_logger;
            if ($previous_logger === null) { self::restoreSaveCallbacks(); }
            if ($this->active_operations) { $this->startDebugStage(); }
        }
    }

    public function exception(string $message, \Throwable $exception, array $context = [], string $level = 'error'): void
    {
        // Never include trace arguments: they can contain credentials or entire feeds.
        $trace = [];
        foreach (array_slice($exception->getTrace(), 0, 15) as $frame) {
            $trace[] = array_intersect_key($frame, array_flip(['file', 'line', 'class', 'type', 'function']));
        }
        $entry = $this->write($message, array_merge($context, [
            'exception' => get_class($exception),
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace,
        ]), $level);
        error_log('CASAWP import: ' . $entry);
    }

    public function logFatalError(): void
    {
        $this->memory_reserve = '';
        $error = error_get_last();
        if (!$this->active_operations || !$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            return;
        }
        // Persist the failure before status/queue updates can allocate more memory or fail.
        $entry = $this->write('Import terminated by a fatal PHP error.', [
            'event' => 'import.fatal',
            'error' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'php_error_type' => $error['type'],
        ], 'error');
        error_log('CASAWP import: ' . $entry);
        // PHP can terminate a synchronous gateway request without an Action Scheduler failure event.
        if ($this->failure_run_id && array_intersect($this->operation_stages, ['gateway_import', 'single_request_import', 'properties_batch', 'final_cleanup'])
            && function_exists('casawp_fail_import_run')) {
            try {
                \casawp_fail_import_run((int) call_user_func($this->failure_run_id));
            } catch (\Throwable $failure) {
                error_log('CASAWP could not update import status after a fatal PHP error.');
            }
        }
    }

    public function write($transcript, array $context = [], string $level = 'info'): string
    {
        $event = $context['event'] ?? '';
        if (in_array($event, ['offer.finished', 'offer.failed', 'offer.unchanged', 'offer.meta_updated', 'media.created', 'media.updated', 'media.kept', 'media.removed', 'media.create_failed', 'media.conversion_failed', 'media.download_failed'], true)) {
            $this->outcomes[$event] = ($this->outcomes[$event] ?? 0) + 1;
        }
        if ($level === 'error') {
            $this->errors++;
        }
        $batch_detail = $level === 'info' && (
            in_array($event, ['properties_batch.started', 'final_cleanup.started', 'manual_cleanup_batch.started', 'outdated_cleanup.started'], true)
            || ($event === 'scheduler.scheduled' && in_array($context['hook'] ?? '', [
                'casawp_batch_import', 'casawp_finalize_import_cleanup_batch', 'casawp_manual_cleanup_batch', 'casawp_delete_outdated_properties',
            ], true))
        );
        // Keep lifecycle details in their existing JSON format, but only in debug mode.
        // Counting, operation tracking and warning/error output remain active in normal mode.
        $debug = $this->debugEnabled();
        if (($level === 'debug' || $batch_detail) && !$debug) {
            return '';
        }
        if ($debug && $level === 'debug' && (
            in_array($event, ['offer.started', 'offer.finished', 'offer.unchanged', 'media.kept', 'batch.processed'], true)
            || ($event === 'stage.finished' && ($context['stage_duration_seconds'] ?? 0) < 0.1)
        )) { return ''; }
        // Shared runtime/feed details are emitted once, and again only if the feed
        // identity changes in this request. Errors always retain their full context.
        if ($debug && $event !== 'request.context') {
            $signature = json_encode([(int) call_user_func($this->run_id), $this->context['feed_file'] ?? null,
                $this->context['feed_sha256'] ?? null, $this->context['feed_bytes'] ?? null]);
            if ($signature !== $this->request_context_signature) {
                $this->request_context_signature = $signature;
                $this->write('Import request diagnostics.', ['event' => 'request.context'], 'debug');
            }
        }
        $metadata = array_merge([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'request_id' => $this->request_id,
            'run_id' => (int) call_user_func($this->run_id),
        ], $this->context, $context, [
            'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ]);
        if ($debug) {
            global $wpdb;
            $metadata = array_merge(self::$action_context, $metadata, [
                'pid' => function_exists('getmypid') ? getmypid() : null,
                'request_elapsed_seconds' => round(microtime(true) - $this->request_started, 6),
                'db_queries' => $this->queryCount(),
                'php_memory_limit' => ini_get('memory_limit'),
                'php_max_execution_time' => (int) ini_get('max_execution_time'),
                'active_hooks' => array_column($this->hook_stack, 'hook'),
                'active_callbacks' => $this->callback_stack,
                'active_http_urls' => array_keys($this->http_requests),
            ]);
            if ($this->debug_stage !== null) {
                $metadata['stage_sequence'] = $this->debug_stage['sequence'];
                $metadata['stage_elapsed_seconds'] = round(microtime(true) - $this->debug_stage['started'], 6);
                $metadata['stage_queries_so_far'] = max(0, $this->queryCount() - $this->debug_stage['queries']);
            }
            if (!empty($wpdb->last_error)) {
                // Keep only the error prefix: quoted SQL fragments can contain nested,
                // escaped or incomplete literals. Never include the SQL statement.
                $prefix_length = strcspn($wpdb->last_error, "'\"");
                $metadata['last_db_error'] = substr($wpdb->last_error, 0, min(1000, $prefix_length))
                    . ($prefix_length < strlen($wpdb->last_error) ? '[details redacted]' : '');
            }
        }
        $transcript = $this->redact($transcript);
        $metadata = $this->redact($metadata);
        $transcript = $this->milestoneMessage($transcript, $metadata);
        if ($level === 'debug' && $event === 'offer.started') {
            $property = (string) ($metadata['property_id'] ?? $metadata['casawp_id'] ?? '');
            $transcript = 'Property ' . $property
                . (!empty($metadata['language']) ? ' (' . $metadata['language'] . ')' : '')
                . ' | run ' . $metadata['run_id']
                . (isset($metadata['batch_number']) ? ', batch ' . $metadata['batch_number'] : '');
        }
        $record = [
            get_date_from_gmt('', 'Y-m-d H:i:s') => $transcript,
        ];
        if ($debug && !in_array($level, ['warning', 'error'], true) && $event !== 'request.context') {
            $keys = ['event', 'request_id', 'run_id', 'action_id', 'batch_number', 'stage', 'stage_sequence',
                'property_id', 'language', 'post_id', 'attachment_id', 'media_origin',
                'stage_duration_seconds', 'stage_query_count', 'hook', 'hook_sequence', 'hook_object_id',
                'hook_duration_seconds', 'hook_query_count', 'callbacks', 'callback', 'callback_priority',
                'callback_sequence', 'callback_duration_seconds', 'callback_query_count',
                'http_sequence', 'http_url', 'http_method', 'http_timeout', 'http_duration_seconds',
                'http_status', 'http_error_code', 'http_error', 'duration_seconds', 'error_count', 'counts',
                'property_offset', 'batch_end_offset', 'batch_time_budget_seconds', 'cleanup_phase'];
            if (in_array($event, ['properties_batch.started', 'properties_batch.finished'], true)) {
                $keys = array_merge($keys, ['memory_mb', 'peak_memory_mb', 'db_queries', 'request_elapsed_seconds']);
            }
            $metadata = array_filter(array_intersect_key($metadata, array_flip($keys)), static function ($value) {
                return $value !== null && $value !== [] && $value !== '';
            });
        }
        // Successful milestones carry only identity and details supplied by that event.
        // Inherited feed paths, fingerprints, stage and memory stay on warnings/errors.
        if ($level === 'info' && !$debug) {
            $details = $this->redact($context);
            if ($event === 'scheduler.scheduled') {
                $details = [];
            } elseif ($event === 'batch.processing') {
                unset($details['batch_size'], $details['total_batches'], $details['property_ids']);
            } elseif (substr($event, -9) === '.finished') {
                unset($details['duration_seconds'], $details['error_count'], $details['counts']);
            }
            $metadata = array_merge(array_intersect_key($metadata, array_flip(['run_id', 'batch_number', 'event', 'cleanup_phase'])), $details);
            if (empty($metadata['run_id'])) {
                $metadata['request_id'] = $this->request_id;
            }
        }
        $record['_meta'] = $metadata;
        $entry = json_encode($record, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $dir = CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/logs';
        if ((!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) ||
            @file_put_contents($dir . '/' . get_date_from_gmt('', 'Ym') . '.log', "\n" . $entry, FILE_APPEND | LOCK_EX) === false) {
            error_log('CASAWP import log write failed: ' . $entry);
        }
        return $entry;
    }

    private function milestoneMessage($message, array $meta)
    {
        $event = $meta['event'] ?? '';
        if ($event === 'scheduler.scheduled') {
            $labels = [
                'casawp_batch_import' => 'Import batch',
                'casawp_finalize_import_cleanup_batch' => self::cleanupPhaseLabel($meta['cleanup_phase'] ?? '') . ' batch',
                'casawp_manual_cleanup_batch' => self::cleanupPhaseLabel($meta['cleanup_phase'] ?? '') . ' batch',
                'casawp_delete_outdated_properties' => 'Outdated property cleanup batch',
            ];
            return sprintf('%s %d scheduled in %ds (action %d).',
                $labels[$meta['hook']] ?? 'Import action batch',
                $meta['scheduled_batch_number'], $meta['delay_seconds'], $meta['action_id']);
        }
        if ($event === 'properties_batch.started') {
            return sprintf('Import batch %d started.', $meta['batch_number']);
        }
        if ($event === 'final_cleanup.started' || $event === 'manual_cleanup_batch.started') {
            return sprintf('%s batch %d started.', self::cleanupPhaseLabel($meta['cleanup_phase'] ?? ''), $meta['batch_number']);
        }
        if ($event === 'final_cleanup.failed' || $event === 'manual_cleanup_batch.failed') {
            return sprintf('%s batch %d failed.', self::cleanupPhaseLabel($meta['cleanup_phase'] ?? ''), $meta['batch_number']);
        }
        if ($event === 'batch.processing') {
            $ids = $meta['property_ids'];
            $preview = implode(', ', array_slice($ids, 0, 20));
            if (count($ids) > 20) {
                $preview .= ', +' . (count($ids) - 20) . ' more';
            }
            return sprintf('Import batch %d/%d: processing %d %s (%s).',
                $meta['batch_number'], $meta['total_batches'], count($ids),
                count($ids) === 1 ? 'property' : 'properties', $preview);
        }
        if (substr($event, -9) !== '.finished' || !isset($meta['duration_seconds']) || !is_string($message)) {
            return $message;
        }

        $title = $event === 'properties_batch.finished'
            ? sprintf('Import batch %d finished', $meta['batch_number']) : rtrim($message, '.');
        if ($event === 'final_cleanup.finished' || $event === 'manual_cleanup_batch.finished') {
            $title = sprintf('%s batch %d finished', self::cleanupPhaseLabel($meta['cleanup_phase'] ?? ''), $meta['batch_number']);
        }
        $labels = [
            'offer.finished' => ['offer processed', 'offers processed'],
            'offer.failed' => ['offer failed', 'offers failed'],
            'offer.unchanged' => ['unchanged offer', 'unchanged offers'],
            'offer.meta_updated' => ['metadata update', 'metadata updates'],
            'media.created' => ['attachment created', 'attachments created'],
            'media.updated' => ['attachment updated', 'attachments updated'],
            'media.kept' => ['attachment kept', 'attachments kept'],
            'media.removed' => ['attachment removed', 'attachments removed'],
            'media.create_failed' => ['attachment creation failure', 'attachment creation failures'],
            'media.conversion_failed' => ['attachment conversion failure', 'attachment conversion failures'],
            'media.download_failed' => ['download failure', 'download failures'],
        ];
        $summary = [];
        foreach ($labels as $key => $forms) {
            $count = (int) ($meta['counts'][$key] ?? 0);
            if ($count) {
                $summary[] = $count . ' ' . $forms[$count === 1 ? 0 : 1];
            }
        }
        $errors = (int) ($meta['error_count'] ?? 0);
        $summary[] = $errors . ($errors === 1 ? ' error' : ' errors');
        return sprintf('%s in %.3fs: %s.', $title, $meta['duration_seconds'], implode(', ', $summary));
    }

    private function stageLabel(string $stage): string
    {
        $labels = [
            'gateway_import' => 'Gateway import',
            'properties_batch' => 'Import batch',
            'single_request_import' => 'Single-request import',
            'final_cleanup' => 'Import cleanup',
            'log_cleanup' => 'Old log cleanup',
            'outdated_cleanup' => 'Outdated property cleanup',
            'offer' => 'Offer',
        ];
        return $labels[$stage] ?? ucfirst(str_replace('_', ' ', $stage));
    }

    public static function cleanupPhaseLabel(string $phase): string
    {
        return [
            'properties' => 'Database property cleanup',
            'attachments' => 'Database attachment cleanup',
            'files' => 'Filesystem cleanup',
            'terms' => 'Taxonomy cleanup',
            'logs' => 'Old log cleanup',
        ][$phase] ?? 'Import cleanup';
    }

    private function redact($value, int $depth = 0)
    {
        if ($depth >= 8) {
            return '[truncated]';
        }
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = preg_match('/^(?:api_?(?:key|secret|token)|private_?key|client_?secret|hmac|authorization|password|(?:access_?|refresh_?)?token|secret|(?:set[-_])?cookie|nonce)$/i', (string) $key)
                    ? '[redacted]' : $this->redact($item, $depth + 1);
            }
            return $value;
        }
        if (!is_string($value)) {
            return $value;
        }
        // Signed media URLs and gateway URLs must not expose query values or userinfo.
        return preg_replace_callback('~https?://[^\s<>"\']+~i', static function ($match) {
            $url = preg_replace('~(https?://)[^/@\s]+@~i', '$1[redacted]@', $match[0]);
            return preg_replace('/[?#].*$/s', '?[redacted]', $url);
        }, $value);
    }
}
