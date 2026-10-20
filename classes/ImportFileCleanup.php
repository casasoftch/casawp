<?php

namespace casawp;

/** Incremental cleanup confined to this site's CASAWP import directory. */
class ImportFileCleanup
{
    private string $uploads;
    private string $import;
    private string $attachments;
    private ImportLogger $logger;
    private array $references = [];
    private array $stats = [];
    private int $run_id = 0;
    private bool $cdn = false;
    private bool $manual;
    private string $state_key;
    private string $totals_key;

    public function __construct(string $uploads, ImportLogger $logger, bool $manual = false)
    {
        $this->uploads = rtrim($uploads, '/');
        $this->import = $this->uploads . '/casawp/import';
        $this->attachments = $this->import . '/attachment';
        $this->logger = $logger;
        $this->manual = $manual;
        $this->state_key = $manual ? 'casawp_manual_file_cleanup' : 'casawp_import_file_cleanup';
        $this->totals_key = $manual ? 'casawp_manual_file_cleanup_totals' : 'casawp_import_file_cleanup_totals';
    }

    /** Return false while another cleanup batch is needed. */
    public function runBatch(int $run_id, array $property_ids): bool
    {
        $this->stats = [];
        try {
            return $this->cleanupBatch($run_id, $property_ids);
        } finally {
            // Retain the cumulative total across requests, including partial failures.
            if ($this->stats) {
                update_option($this->totals_key, ['run_id' => $run_id, 'stats' => $this->stats], false);
            }
        }
    }

    private function cleanupBatch(int $run_id, array $property_ids): bool
    {
        $this->run_id = $run_id;
        $this->cdn = (bool) get_option('casawp_use_casagateway_cdn', false);
        if (!$this->allowed($run_id)) {
            $this->logger->write('Import file cleanup skipped because the run is no longer active or had errors.', ['event' => 'files.skipped'], 'warning');
            return true;
        }
        $missing_manual_directory = $this->manual && !file_exists($this->import) && $this->inside($this->uploads, $this->uploads);
        if (is_link($this->import) || (!$missing_manual_directory && !$this->inside($this->import, $this->uploads)) || (!$this->manual && is_link($this->import . '/data.xml'))) {
            $this->logger->write('Import file cleanup skipped for an unsafe import directory or linked feed.', ['event' => 'files.skipped'], 'warning');
            if ($this->manual) { throw new \RuntimeException('Manual cleanup stopped for an unsafe import directory.'); }
            return true;
        }
        $cdn = $this->cdn;
        if ($this->manual) {
            $sorted_ids = array_map('strval', $property_ids);
            sort($sorted_ids, SORT_STRING);
            $hash = hash('sha256', serialize($sorted_ids));
            $manual = get_option('casawp_manual_cleanup', []);
            if (($manual['properties_hash'] ?? '') !== $hash) {
                throw new \RuntimeException('Stored properties changed during manual cleanup; remaining files were retained.');
            }
        } else {
            $hash = hash_file('sha256', $this->import . '/data.xml');
            if ($hash === false) {
                throw new \RuntimeException('Cannot identify the current feed for import file cleanup.');
            }
            $feed = get_option('casawp_import_feed_fingerprint', []);
            if (($feed['run_id'] ?? 0) !== $run_id || ($feed['sha256'] ?? '') !== $hash) {
                $this->logger->write('Import file cleanup skipped because data.xml does not match the imported feed.', ['event' => 'files.skipped'], 'warning');
                return true;
            }
        }
        $state = get_option($this->state_key, []);
        if (!is_array($state) || ($state['run_id'] ?? 0) !== $run_id) {
            $state = [
                'run_id' => $run_id, 'feed_hash' => $hash, 'cdn' => $cdn,
                'stack' => [['dir' => '', 'after' => '']],
                'stats' => ['files' => 0, 'directories' => 0, 'bytes' => 0, 'failures' => 0],
            ];
        } elseif ($state['feed_hash'] !== $hash || $state['cdn'] !== $cdn) {
            $this->logger->write('Import file cleanup stopped because the feed or CDN setting changed.', ['event' => 'files.skipped'], 'warning');
            delete_option($this->state_key);
            return true;
        }
        $this->stats = array_merge(['legacy_deleted' => 0, 'legacy_metadata' => 0, 'legacy_retained' => 0, 'terms_deleted' => 0], $state['stats']);
        $before = $this->stats;
        $this->references = [];
        $active = array_fill_keys(array_map('strval', $property_ids), true);
        foreach ($property_ids as $id) {
            // Retain a parent folder when a legacy property ID itself contains a slash.
            $active[explode('/', (string) $id)[0]] = true;
        }
        $started = microtime(true);
        $entries = [];

        if (empty($state['legacy_done']) && !is_link($this->attachments)) {
            $this->enterPhase('attachments', $state);
            $limit = max(1, min(200, (int) apply_filters('casawp_legacy_attachment_cleanup_batch_size', 50)));
            $cleanup = new ImportLegacyAttachmentCleanup($this->uploads, $this->logger,
                function () use ($run_id) { return $this->allowed($run_id); },
                function ($folder) use ($cdn) {
                    unset($this->references[$folder]);
                    return $this->protectedFiles($folder, $cdn);
                },
                function ($file) use ($run_id) { return $this->removeFile($file, $run_id); }
            );
            $legacy = $cleanup->runBatch((int) ($state['legacy_after'] ?? 0), $limit, $active, $cdn,
                max(1, (int) apply_filters('casawp_file_cleanup_time_limit', 5)));
            $state['legacy_after'] = $legacy['after'];
            $state['legacy_done'] = $legacy['done'];
            $this->stats['legacy_deleted'] += $legacy['deleted'];
            $this->stats['legacy_metadata'] += $legacy['metadata'];
            $this->stats['legacy_retained'] += $legacy['retained'];
            $this->stats['failures'] += $legacy['failures'];
            if ($legacy['done']) {
                $this->logger->write(sprintf('Database attachment cleanup completed: %d detached attachment records and %d orphan metadata sets removed; %d database records retained.',
                    $this->stats['legacy_deleted'], $this->stats['legacy_metadata'], $this->stats['legacy_retained']), ['event' => 'legacy_attachments.completed']);
            }
            if ($legacy['deleted'] || $legacy['metadata'] || $legacy['retained'] || $legacy['failures']) {
                $this->logger->write(sprintf('Database attachment cleanup: removed %d detached attachment records and %d orphan metadata sets; retained %d database records; freed %.2f MB; %d failures.',
                    $legacy['deleted'], $legacy['metadata'], $legacy['retained'],
                    ($this->stats['bytes'] - $before['bytes']) / 1048576, $legacy['failures']),
                    ['event' => 'legacy_attachments.cleaned'], $legacy['failures'] ? 'warning' : 'info');
                $state['stats'] = $this->stats;
                update_option($this->state_key, $state, false);
                return false;
            }
            if (!$legacy['done']) {
                $state['stats'] = $this->stats;
                update_option($this->state_key, $state, false);
                return false;
            }
        }

        if (empty($state['files_done_logged'])) { $this->enterPhase('files', $state); }
        // Only these historical XML names are removed; data.xml is always retained.
        foreach (['data-done.xml', 'data-error.xml'] as $name) {
            $this->removeFile($this->import . '/' . $name, $run_id);
        }

        $root = realpath($this->attachments);
        if (!$root || is_link($this->attachments) || !$this->inside($root, $this->uploads)) {
            $state['stack'] = [];
        }
        $limit = max(1, (int) apply_filters('casawp_file_cleanup_batch_size', 200));
        $time_limit = max(1, (int) apply_filters('casawp_file_cleanup_time_limit', 5));
        for ($visited = 0; $state['stack'] && $visited < $limit && microtime(true) - $started < $time_limit; $visited++) {
            if (!$this->allowed($run_id)) {
                break;
            }
            $index = count($state['stack']) - 1;
            $frame = $state['stack'][$index];
            $directory = $this->attachments . ($frame['dir'] === '' ? '' : '/' . $frame['dir']);
            if (is_link($directory) || !is_dir($directory) || !$this->inside($directory, $this->attachments)) {
                array_pop($state['stack']);
                continue;
            }
            if (!isset($entries[$frame['dir']])) {
                $names = @scandir($directory);
                if ($names === false) {
                    throw new \RuntimeException('Cannot read import attachment directory: ' . $directory);
                }
                $entries[$frame['dir']] = array_values(array_filter($names, static function ($name) use ($frame) {
                    return $name !== '.' && $name !== '..' && strcmp($name, $frame['after']) > 0;
                }));
            }
            $name = array_shift($entries[$frame['dir']]);
            if ($name === null) {
                array_pop($state['stack']);
                // Remove empty folders after their children, including the attachment root.
                if ($this->allowed($run_id)) {
                    $remaining = @scandir($directory);
                    if ($remaining === ['.', '..']) {
                        if (@rmdir($directory)) {
                            $this->stats['directories']++;
                        } else {
                            $this->stats['failures']++;
                            $this->logger->write('Could not delete empty import folder: ' . $directory, ['event' => 'files.delete_failed'], 'warning');
                        }
                    }
                }
                continue;
            }
            $state['stack'][$index]['after'] = $name;
            $relative = ($frame['dir'] === '' ? '' : $frame['dir'] . '/') . $name;
            $path = $this->attachments . '/' . $relative;
            if (is_link($path) || !$this->inside($path, $this->attachments)) {
                continue;
            }
            if (is_dir($path)) {
                $state['stack'][] = ['dir' => $relative, 'after' => ''];
                continue;
            }
            if (!is_file($path)) {
                continue;
            }

            $parts = explode('/', $relative);
            // CDN mode owns the entire externalsync cache, regardless of extension,
            // property presence, attachment metadata or other local consumers.
            $purge_external = $cdn && $parts[0] === 'externalsync';
            $property = $parts[0] === 'externalsync' ? ($parts[1] ?? '') : $parts[0];
            $folder = count($parts) > ($parts[0] === 'externalsync' ? 2 : 1)
                ? ($parts[0] === 'externalsync' ? 'externalsync/' . $property : $property) : '';
            $protected = $this->protectedFiles($folder, $cdn);
            $is_image = in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tif', 'tiff', 'ico', 'svg'], true);
            if ($purge_external || (!isset($protected[realpath($path)]) && ($name === '.DS_Store' || ($folder !== '' && !isset($active[$property])) || ($cdn && $is_image)))) {
                $this->removeFile($path, $run_id);
            }
        }

        $state['stats'] = $this->stats;
        $deleted = $this->stats['files'] - $before['files'];
        $directories = $this->stats['directories'] - $before['directories'];
        if ($deleted || $directories || (!$state['stack'] && empty($state['files_done_logged']))) {
            $this->logger->write(sprintf('Filesystem cleanup: deleted %d files and %d empty folders; freed %.2f MB.',
                $deleted, $directories, ($this->stats['bytes'] - $before['bytes']) / 1048576), ['event' => 'files.cleaned']);
        }
        if (!$state['stack']) {
            if (empty($state['files_done_logged'])) {
                $this->logger->write(sprintf('Filesystem cleanup completed: %d files, %d empty folders, %.2f MB freed, %d failures.',
                    $this->stats['files'], $this->stats['directories'], $this->stats['bytes'] / 1048576, $this->stats['failures']),
                    ['event' => 'files.completed'], $this->stats['failures'] ? 'warning' : 'info');
                $state['files_done_logged'] = true;
            }
            $this->enterPhase('terms', $state);
            $terms = (new ImportTermCleanup($this->logger, function () use ($run_id) { return $this->allowed($run_id); }))
                ->runBatch((int) ($state['terms_after'] ?? 0), $limit, $time_limit, $state['terms_max_id'] ?? null);
            $state['terms_after'] = $terms['after'];
            $state['terms_max_id'] = $terms['max_id'];
            $this->stats['terms_deleted'] += $terms['deleted'];
            $this->stats['failures'] += $terms['failures'];
            if ($terms['deleted'] || $terms['failures'] || $terms['done']) {
                $this->logger->write(sprintf('Taxonomy cleanup: deleted %d empty CASAWP terms; %d failures%s.',
                    $terms['deleted'], $terms['failures'], $terms['done'] ? sprintf('; completed with %d terms removed in total', $this->stats['terms_deleted']) : ''),
                    ['event' => $terms['done'] ? 'terms.completed' : 'terms.cleaned'], $terms['failures'] ? 'warning' : 'info');
            }
            if (!$terms['done']) {
                $state['stats'] = $this->stats;
                update_option($this->state_key, $state, false);
                return false;
            }
            delete_option($this->state_key);
            return true;
        }
        update_option($this->state_key, $state, false);
        return false;
    }

    private function enterPhase(string $phase, array &$state): void
    {
        $this->logger->setStage('cleanup_' . $phase, ['cleanup_phase' => $phase, 'post_id' => null, 'attachment_id' => null]);
        if (($state['logged_phase'] ?? '') !== $phase) {
            $descriptions = [
                'attachments' => 'removing detached import attachment records and orphaned metadata; preserving records with existing parents and their WPML groups',
                'files' => $this->cdn ? 'clearing all regular files in externalsync (CDN enabled), obsolete local import files and empty folders'
                    : 'removing obsolete local import files and empty folders; preserving active properties and local consumers',
                'terms' => 'removing unused CASAWP taxonomy terms',
            ];
            $this->logger->write(ImportLogger::cleanupPhaseLabel($phase) . ' phase started: ' . $descriptions[$phase] . '.',
                ['event' => 'cleanup.phase_started', 'cleanup_phase' => $phase]);
            $state['logged_phase'] = $phase;
        }
    }

    private function allowed(int $run_id): bool
    {
        if ($this->manual) {
            $state = get_option('casawp_manual_cleanup', []);
            return $run_id > 0 && ($state['run_id'] ?? 0) === $run_id && ($state['status'] ?? '') === 'running'
                && !ManualCleanup::stopRequested($run_id)
                && !empty($state['lock'])
                && !get_option('casawp_current_run_id')
                && (int) get_option('casawp_import_lock', 0) === ($state['lock'] ?? -1)
                && (bool) get_option('casawp_use_casagateway_cdn', false) === $this->cdn;
        }
        return $run_id > 0 && (int) get_option('casawp_current_run_id', 0) === $run_id
            && (bool) get_option('casawp_use_casagateway_cdn', false) === $this->cdn
            && !get_option('casawp_import_canceled') && !get_option('casawp_import_failed')
            && (int) get_option('casawp_file_cleanup_failed_run', 0) !== $run_id;
    }

    private function inside(string $path, string $base): bool
    {
        $path = realpath($path);
        $base = realpath($base);
        return $path !== false && $base !== false && ($path === $base || strpos($path, $base . '/') === 0);
    }

    private function removeFile(string $path, int $run_id): bool
    {
        if (!$this->allowed($run_id) || is_link($path)) {
            return false;
        }
        if (!file_exists($path)) {
            return true;
        }
        if (!is_file($path) || !$this->inside($path, $this->import)) {
            return false;
        }
        $bytes = filesize($path);
        if (@unlink($path)) {
            $this->stats['files']++;
            $this->stats['bytes'] += $bytes === false ? 0 : $bytes;
            $this->logger->write('Deleted unused import file: ' . $path, [], 'debug');
            return true;
        } else {
            $this->stats['failures']++;
            $this->logger->write('Could not delete unused import file: ' . $path, ['event' => 'files.delete_failed'], 'warning');
            return false;
        }
    }

    /** Migrate remote attachments and protect local consumers outside the CDN externalsync cache. */
    private function protectedFiles(string $folder, bool $cdn): array
    {
        if (isset($this->references[$folder])) {
            return $this->references[$folder];
        }
        global $wpdb;
        $relative = 'casawp/import/attachment/' . ($folder === '' ? '' : $folder . '/');
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND (meta_value LIKE %s OR meta_value LIKE %s)",
            $wpdb->esc_like($relative) . '%', $wpdb->esc_like($this->uploads . '/' . $relative) . '%'
        ));
        if ($wpdb->last_error) {
            throw new \RuntimeException('Cannot check attachment file references: ' . $wpdb->last_error);
        }
        $protected = [];
        $remote = [];
        foreach ($ids as $id) {
            $file = (string) get_post_meta($id, '_wp_attached_file', true);
            $path = substr($file, 0, 1) === '/' ? $file : $this->uploads . '/' . $file;
            $metadata = get_post_meta($id, '_wp_attachment_metadata', true);
            $backups = get_post_meta($id, '_wp_attachment_backup_sizes', true);
            $files = [$path];
            if (is_array($metadata)) {
                foreach ($metadata['sizes'] ?? [] as $size) {
                    if (!empty($size['file'])) { $files[] = dirname($path) . '/' . basename($size['file']); }
                }
                if (!empty($metadata['original_image'])) { $files[] = dirname($path) . '/' . basename($metadata['original_image']); }
            }
            foreach (is_array($backups) ? $backups : [] as $backup) {
                if (!empty($backup['file'])) { $files[] = dirname($path) . '/' . basename($backup['file']); }
            }
            $origin = (string) get_post_meta($id, '_origin', true);
            $origin_host = strtolower((string) parse_url($origin, PHP_URL_HOST));
            $local_host = defined('CASASYNC_CUR_UPLOAD_BASEURL') ? strtolower((string) parse_url(CASASYNC_CUR_UPLOAD_BASEURL, PHP_URL_HOST)) : '';
            $owned = get_post_meta($id, '_casawp_import', true) || get_post_type(wp_get_post_parent_id($id)) === 'casawp_property';
            $cdn_attachment = false;
            $purge_external = $cdn && strpos($path, $this->attachments . '/externalsync/') === 0
                && $this->inside(dirname($path), $this->attachments . '/externalsync');
            if ($purge_external && $origin_host !== '' && $origin_host !== $local_host) {
                // Historical records often lack the import marker or media taxonomy.
                $cdn_attachment = ImportMedia::usesRemote(wp_attachment_is_image($id) ? 'image' : 'document', $origin, true);
            }
            if (!$purge_external && $owned && $origin_host !== '' && $origin_host !== $local_host && $this->inside(dirname($path), $this->attachments)) {
                foreach (ImportMedia::TYPES as $type) {
                    if (has_term($type, 'casawp_attachment_type', $id) && ImportMedia::usesRemote($type, $origin, $cdn)
                        && ($type !== 'image' || wp_attachment_is_image($id))) {
                        $cdn_attachment = true;
                        break;
                    }
                }
            }
            if ($cdn_attachment) {
                $remote[] = ['id' => $id, 'metadata' => $metadata, 'files' => $files];
            } elseif (!$purge_external) {
                foreach ($files as $local) {
                    $resolved = realpath($local);
                    if ($resolved !== false) { $protected[$resolved] = true; }
                }
            }
        }
        // Resolve all local consumers before removing a file shared by several attachments.
        foreach ($remote as $attachment) {
            if (!$this->allowed($this->run_id)) {
                throw new \RuntimeException('Import run changed during CDN attachment cleanup.');
            }
            $id = $attachment['id'];
            $metadata = $attachment['metadata'];
            foreach (['width', 'height'] as $dimension) {
                if (!empty($metadata[$dimension]) && !get_post_meta($id, '_remote_' . $dimension, true)) {
                    update_post_meta($id, '_remote_' . $dimension, (int) $metadata[$dimension]);
                    if ((int) get_post_meta($id, '_remote_' . $dimension, true) !== (int) $metadata[$dimension]) {
                        throw new \RuntimeException('Cannot preserve CDN image dimensions for attachment: ' . $id);
                    }
                }
            }
            update_post_meta($id, '_is_remote', 1);
            if (!get_post_meta($id, '_is_remote', true)) {
                throw new \RuntimeException('Cannot mark the CDN attachment as remote: ' . $id);
            }
            $removed = true;
            foreach ($attachment['files'] as $file) {
                $resolved = realpath($file);
                if ($resolved !== false && isset($protected[$resolved])) {
                    continue;
                }
                if (!$this->removeFile($file, $this->run_id)) {
                    $removed = false;
                }
            }
            if (!$removed) {
                // Retain file metadata so a future import can retry failed deletions.
                foreach ($attachment['files'] as $file) {
                    $resolved = realpath($file);
                    if ($resolved !== false) { $protected[$resolved] = true; }
                }
                continue;
            }
            delete_post_meta($id, '_wp_attached_file');
            delete_post_meta($id, '_wp_attachment_metadata');
            delete_post_meta($id, '_wp_attachment_backup_sizes');
            if (get_post_meta($id, '_wp_attached_file', true) || get_post_meta($id, '_wp_attachment_metadata', true)
                || get_post_meta($id, '_wp_attachment_backup_sizes', true) || $wpdb->last_error) {
                throw new \RuntimeException('Cannot detach the obsolete local file metadata for CDN attachment: ' . $id);
            }
        }
        return $this->references[$folder] = $protected;
    }
}
