<?php

namespace casawp;

/** Read-only settings-page progress; 100% includes the final cleanup. */
class ImportProgress
{
    public static function dismissCompletionNotice(): void
    {
        $summary = get_option('casawp_import_cleanup_progress', []);
        if (!empty($summary['finished_at']) && empty($summary['notice_dismissed'])) {
            $summary['notice_dismissed'] = true;
            update_option('casawp_import_cleanup_progress', $summary, false);
        }
    }

    public static function completionNoticeSeconds(int $finished_at): int
    {
        return $finished_at > 0 ? max(0, min(600, $finished_at + 600 - time())) : 0;
    }

    public static function cleanupSummary(array $stats, int $bytes): string
    {
        return sprintf(__('Removed: unused attachments (%1$s), local import files (%2$s), empty taxonomy terms (%3$s). %4$s MB freed.', 'casawp'),
            number_format_i18n((int) ($stats['legacy_deleted'] ?? 0)),
            number_format_i18n((int) ($stats['files'] ?? 0)),
            number_format_i18n((int) ($stats['terms_deleted'] ?? 0)),
            number_format_i18n($bytes / 1048576, 2));
    }

    public static function cleanupLabel(string $phase): string
    {
        $labels = [
            'properties' => __('Outdated properties', 'casawp'),
            'attachments' => __('Database attachments', 'casawp'),
            'files' => __('Files and folders', 'casawp'),
            'terms' => __('Empty taxonomy terms', 'casawp'),
            'logs' => __('Old logs', 'casawp'),
        ];
        return $labels[$phase] ?? $labels['attachments'];
    }

    public static function data(): array
    {
        $total = max(0, (int) get_option('casawp_total_batches', 0));
        $completed = max(0, (int) get_option('casawp_completed_batches', 0));
        $run = (int) get_option('casawp_current_run_id', 0);
        $last_run = (int) get_option('casawp_import_progress_run_id', 0);
        $cleanup = get_option('casawp_import_cleanup_progress', []);
        $cleanup = is_array($cleanup) ? $cleanup : [];
        $state = get_option('casawp_import_progress_state', []);
        $phase = $run && is_array($state) && (int) ($state['run_id'] ?? 0) === $run ? ($state['phase'] ?? '') : '';
        $pending = (bool) get_option('casawp_import_pending', false);
        $cleanup_started = $run && (int) ($cleanup['run_id'] ?? 0) === $run;
        $finished = $last_run && (int) get_option('casawp_cleanup_done_for_run', 0) === $last_run;
        $failed = (bool) get_option('casawp_import_failed');
        $canceled = (bool) get_option('casawp_import_canceled');
        $status = 'idle';
        $message = '';
        $progress = 0;
        if ($failed || $canceled) {
            $status = 'stopped';
            $phase = $failed ? 'failed' : 'canceled';
            $message = __('Import stopped. See the logs for details.', 'casawp');
        } elseif ($pending && !$run) {
            // A requested import may be waiting for standalone cleanup to release its lock.
            $status = 'running';
            $phase = 'queued';
            $message = __('Import queued.', 'casawp');
        } elseif ($run) {
            $status = $phase === 'finalizing' || $cleanup_started || ($total > 0 && $completed >= $total) ? 'cleanup' : 'running';
            $progress = $total ? min(99, ($completed / $total) * 99) : 0;
            $message = __('Importing properties…', 'casawp');
            if ($status === 'cleanup') {
                $progress = 99;
                $files = get_option('casawp_import_file_cleanup', []);
                $files = is_array($files) ? $files : [];
                $phase = $phase === 'finalizing' ? 'finalizing' : ((int) ($files['run_id'] ?? 0) === $run ? ($files['logged_phase'] ?? 'attachments') : 'properties');
                if ($phase !== 'finalizing' && $cleanup_started && ($cleanup['phase'] ?? '') === 'logs') { $phase = 'logs'; }
                $totals = get_option('casawp_import_file_cleanup_totals', []);
                $bytes = is_array($totals) && (int) ($totals['run_id'] ?? 0) === $run ? (int) ($totals['stats']['bytes'] ?? 0) : 0;
                $batch = $cleanup_started ? max(0, (int) ($cleanup['batch_number'] ?? 0)) : 0;
                $message = $phase === 'finalizing' ? __('Finalizing import…', 'casawp') : ($batch
                    ? sprintf(__('Cleaning up: %1$s (batch %2$d). %3$s MB freed.', 'casawp'), self::cleanupLabel($phase), $batch, number_format_i18n($bytes / 1048576, 2))
                    : sprintf(__('Cleaning up: %1$s. %2$s MB freed.', 'casawp'), self::cleanupLabel($phase), number_format_i18n($bytes / 1048576, 2)));
            } elseif ($phase === 'fetching') {
                $message = __('Fetching data from CASAGATEWAY…', 'casawp');
            } elseif ($phase === 'queued') {
                $message = __('Import queued.', 'casawp');
            } elseif (!$total) {
                $phase = 'preparing';
                $message = __('Preparing import…', 'casawp');
            } else {
                $phase = 'properties';
            }
        } elseif ($finished || ($total > 0 && $completed >= $total)) {
            if (!$last_run || $finished) {
                $status = 'completed';
                $phase = 'completed';
                $progress = 100;
                $message = __('Import and cleanup completed.', 'casawp');
                if ($finished && (int) ($cleanup['run_id'] ?? 0) === $last_run && isset($cleanup['stats'], $cleanup['freed_bytes'])) {
                    if (!empty($cleanup['stats']['failures'])) {
                        $message = __('Import and cleanup completed with warnings. See the logs for details.', 'casawp');
                    }
                    $message .= ' ' . self::cleanupSummary($cleanup['stats'], (int) $cleanup['freed_bytes']);
                }
            } else {
                $status = 'stopped';
                $phase = 'failed';
                $message = __('Import stopped. See the logs for details.', 'casawp');
            }
        }
        $notice_seconds = $status === 'completed' && $finished && empty($cleanup['notice_dismissed']) && (int) ($cleanup['run_id'] ?? 0) === $last_run
            ? self::completionNoticeSeconds((int) ($cleanup['finished_at'] ?? 0)) : 0;
        return ['progress' => $progress, 'status' => $status, 'message' => $message, 'notice_seconds' => $notice_seconds,
            'run_id' => $run ?: $last_run, 'phase' => $phase, 'pending' => $pending,
            'can_cancel' => (bool) $run && in_array($status, ['running', 'cleanup'], true)];
    }
}
