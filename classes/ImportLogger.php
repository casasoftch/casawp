<?php

namespace casawp;

/** Import diagnostics in JSON, preserving the original timestamp => transcript entry. */
class ImportLogger
{
    private array $context = [];
    private string $request_id;
    private $run_id;
    private $failure_run_id;
    private int $errors = 0;
    private array $outcomes = [];
    private int $active_operations = 0;
    private array $operation_stages = [];
    private bool $shutdown_registered = false;
    private string $memory_reserve = '';

    public function __construct(callable $run_id, ?callable $failure_run_id = null)
    {
        $this->run_id = $run_id;
        $this->failure_run_id = $failure_run_id;
        $this->request_id = uniqid('import_', true);
    }

    public function setStage(string $stage, array $context = []): void
    {
        $this->context = array_merge($this->context, $context, ['stage' => $stage]);
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
        $this->setStage($stage, $context);
        $started = microtime(true);
        $errors_before = $this->errors;
        $outcomes_before = $this->outcomes;
        $finished = false;
        $this->active_operations++;
        $this->operation_stages[] = $stage;
        if (!$this->shutdown_registered) {
            $this->shutdown_registered = true;
            $this->memory_reserve = str_repeat(' ', 262144);
            register_shutdown_function([$this, 'logFatalError']);
        }
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
            return $result;
        } catch (\Throwable $e) {
            $this->exception($this->stageLabel($stage) . ($finished ? ' completion failed.' : ' failed.'), $e, [
                'event' => $stage . ($finished ? '.completion_failed' : '.failed'),
                'duration_seconds' => round(microtime(true) - $started, 3),
            ]);
            throw $e;
        } finally {
            $this->active_operations--;
            array_pop($this->operation_stages);
            $this->context = $previous;
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
        // PHP can terminate a synchronous gateway request without an Action Scheduler failure event.
        if ($this->failure_run_id && array_intersect($this->operation_stages, ['gateway_import', 'single_request_import', 'properties_batch', 'final_cleanup'])
            && function_exists('casawp_fail_import_run')) {
            try {
                \casawp_fail_import_run((int) call_user_func($this->failure_run_id));
            } catch (\Throwable $failure) {
                error_log('CASAWP could not update import status after a fatal PHP error.');
            }
        }
        $entry = $this->write('Import terminated by a fatal PHP error.', [
            'event' => 'import.fatal',
            'error' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'php_error_type' => $error['type'],
        ], 'error');
        error_log('CASAWP import: ' . $entry);
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
        if (($level === 'debug' || $batch_detail) && apply_filters('casawp_import_log_level', 'info') !== 'debug') {
            return '';
        }
        // The property heading and batch summary already delimit successful offers.
        // Keep counting completions without adding another line for every translation.
        if ($level === 'debug' && $event === 'offer.finished') {
            return '';
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
        // Successful milestones carry only identity and details supplied by that event.
        // Inherited feed paths, fingerprints, stage and memory stay on warnings/errors.
        if ($level === 'info') {
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
        // Routine debug details retain the original compact timestamp => message format.
        if ($level !== 'debug') {
            $record['_meta'] = $metadata;
        }
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
                $value[$key] = preg_match('/^(?:api_?key|private_?key|hmac|authorization|password|token|secret)$/i', (string) $key)
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
