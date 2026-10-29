<?php

namespace casawp;

/** Remove detached import records without letting WordPress delete files outside the guarded sweep. */
class ImportLegacyAttachmentCleanup
{
    private string $uploads;
    private ImportLogger $logger;
    private $allowed;
    private $protected;
    private $remove;

    public function __construct(string $uploads, ImportLogger $logger, callable $allowed, callable $protected, callable $remove)
    {
        $this->uploads = rtrim($uploads, '/');
        $this->logger = $logger;
        $this->allowed = $allowed;
        $this->protected = $protected;
        $this->remove = $remove;
    }

    public function runBatch(int $after, int $limit, array $active, bool $cdn, int $seconds): array
    {
        global $wpdb;
        $prefix = 'casawp/import/attachment/';
        $upload_urls = [];
        if (defined('CASASYNC_CUR_UPLOAD_BASEURL') && preg_match('#^https?://#i', CASASYNC_CUR_UPLOAD_BASEURL)) {
            $base = preg_replace('#^https?://#i', '', rtrim(CASASYNC_CUR_UPLOAD_BASEURL, '/'));
            $upload_urls = ['https://' . $base . '/', 'http://' . $base . '/'];
        }
        $guid_patterns = [
            'https://casagateway.ch/media/%', 'http://casagateway.ch/media/%',
            'https://cdn.casasoft.com/media/%', 'http://cdn.casasoft.com/media/%',
            'https://casagateway.ch/media-thumb/%', 'http://casagateway.ch/media-thumb/%',
            'https://cdn.casasoft.com/media-thumb/%', 'http://cdn.casasoft.com/media-thumb/%',
        ];
        foreach ($upload_urls as $url) { $guid_patterns[] = $wpdb->esc_like($url . $prefix) . '%'; }
        $guid_conditions = implode(' OR ', array_fill(0, count($guid_patterns), 'p.guid LIKE %s'));
        // Include local GUIDs when historical attachment records have lost their file metadata.
        // Include metadata left behind by historical direct SQL deletion of attachment posts.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID FROM (SELECT DISTINCT pm.post_id AS ID FROM {$wpdb->postmeta} pm
             LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             LEFT JOIN {$wpdb->posts} parent ON parent.ID = p.post_parent
             WHERE pm.meta_key = '_wp_attached_file' AND pm.post_id > %d
               AND (pm.meta_value LIKE %s OR pm.meta_value LIKE %s)
               AND (p.ID IS NULL OR (p.post_type = 'attachment' AND parent.ID IS NULL))
             ORDER BY pm.post_id ASC LIMIT %d) local_candidates
             UNION
             SELECT ID FROM (SELECT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->posts} parent ON parent.ID = p.post_parent
             WHERE p.ID > %d AND p.post_type = 'attachment' AND parent.ID IS NULL
               AND ($guid_conditions)
             ORDER BY p.ID ASC LIMIT %d) guid_candidates
             ORDER BY ID ASC LIMIT %d",
            ...array_merge([
                $after, $wpdb->esc_like($prefix) . '%', $wpdb->esc_like($this->uploads . '/' . $prefix) . '%',
                $limit, $after,
            ], $guid_patterns, [$limit, $limit])
        ));
        $this->checkDatabase();
        if (!is_array($rows)) { throw new \RuntimeException('Cannot list legacy import attachments.'); }
        $this->primeCaches(array_map(static function ($row) { return (int) $row->ID; }, $rows));
        $candidates = [];
        $retained = [];
        foreach ($rows as $row) {
            $id = (int) $row->ID;
            $post = get_post($id);
            if ($post && ImportMedia::isManualAttachment($id)) {
                $retained[$id] = 'manually added media retained in the library'; continue;
            }
            $file = (string) get_post_meta($id, '_wp_attached_file', true);
            // Only fall back within this subsite's upload URL; existing metadata remains authoritative.
            if ($file === '' && $post) {
                foreach ($upload_urls as $url) {
                    if (strpos((string) $post->guid, $url . $prefix) === 0) {
                        $file = substr((string) $post->guid, strlen($url));
                        break;
                    }
                }
            }
            $remote_file = ImportMedia::usesRemote('document', $file, true);
            $relative = strpos($file, $this->uploads . '/') === 0 ? substr($file, strlen($this->uploads) + 1) : $file;
            $files = [];
            $folder = '';
            if ($relative !== '' && !$remote_file) {
                if (strpos($relative, $prefix) !== 0) { $retained[$id] = 'file is outside the import attachment folder'; continue; }
                $parts = explode('/', substr($relative, strlen($prefix)));
                $property_index = $parts[0] === 'externalsync' ? 1 : 0;
                if (count($parts) < $property_index + 2 || array_intersect($parts, ['', '.', '..']) || strpos($relative, '\\') !== false) {
                    $retained[$id] = 'unsafe or unrecognized import file path'; continue;
                }
                $property = $parts[$property_index];
                if (!$cdn && isset($active[$property])) { $retained[$id] = 'active property with CDN disabled'; continue; }
                $folder = implode('/', array_slice($parts, 0, $property_index + 1));
                $path = $this->uploads . '/' . $relative;
                $files = [$path];
                $metadata = get_post_meta($id, '_wp_attachment_metadata', true);
                foreach (is_array($metadata) ? ($metadata['sizes'] ?? []) : [] as $size) {
                    if (!empty($size['file'])) { $files[] = dirname($path) . '/' . basename($size['file']); }
                }
                if (!empty($metadata['original_image'])) { $files[] = dirname($path) . '/' . basename($metadata['original_image']); }
                $backups = get_post_meta($id, '_wp_attachment_backup_sizes', true);
                foreach (is_array($backups) ? $backups : [] as $backup) {
                    if (!empty($backup['file'])) { $files[] = dirname($path) . '/' . basename($backup['file']); }
                }
                foreach ($files as $local) {
                    $cursor = $local;
                    while ($cursor !== $this->uploads && strpos($cursor, $this->uploads . '/') === 0) {
                        if (is_link($cursor)) { $retained[$id] = 'symbolic link in the local file path'; break 2; }
                        $cursor = dirname($cursor);
                    }
                }
                if (isset($retained[$id])) { continue; }
            } elseif (!$post || (!ImportMedia::usesRemote('document', (string) $post->guid, true) && !$remote_file)) {
                $retained[$id] = 'cannot establish an import source'; continue;
            }
            if ($post && ($post->post_type !== 'attachment' || ($post->post_parent && get_post($post->post_parent)))) {
                $retained[$id] = 'attachment has an existing parent'; continue;
            }
            $candidates[$id] = ['files' => array_values(array_unique($files)), 'folder' => $folder,
                'post' => (bool) $post];
        }
        // Source ownership and the absence of a live parent define legacy records.
        // Do not infer ownership from numbers or filenames in arbitrary site data.
        $used = $this->findAttachedTranslations($candidates);
        $result = ['after' => $after, 'done' => false, 'deleted' => 0, 'metadata' => 0, 'retained' => 0, 'failures' => 0];
        // Translation checks are shared by this batch, before bounded record deletion.
        $started = microtime(true);
        foreach ($rows as $row) {
            if (!(($this->allowed)()) || ($result['after'] !== $after && microtime(true) - $started >= $seconds)) { break; }
            $id = (int) $row->ID;
            $reason = $retained[$id] ?? $used[$id] ?? '';
            if ($reason === '') { $reason = (string) apply_filters('casawp_legacy_attachment_usage', '', $id); }
            if (!(($this->allowed)())) { break; }
            if ($reason !== '') {
                $result['retained']++;
                $this->logger->write('Retained legacy attachment ' . $id . ': ' . $reason . '.', [], 'debug');
            } else {
                $candidate = $candidates[$id];
                // Recheck ownership and the parent after running extension filters.
                $post = get_post($id);
                if ($post && (ImportMedia::isManualAttachment($id) || $post->post_type !== 'attachment'
                    || ($post->post_parent && get_post($post->post_parent)))) {
                    $result['retained']++;
                } else {
                    $deleted = false;
                    $was_post = $candidate['post'];
                    if ($post) {
                        // The guarded sweep owns physical deletion and the CDN cache policy.
                        $keep_file = static function () { return ''; };
                        add_filter('wp_delete_file', $keep_file, PHP_INT_MAX);
                        try { $deleted = (bool) wp_delete_attachment($id, true); }
                        finally { remove_filter('wp_delete_file', $keep_file, PHP_INT_MAX); }
                        $deleted = $deleted && !get_post($id);
                    } elseif ($was_post) {
                        // WPML may already have removed an unused counterpart via its deletion hooks.
                        $deleted = true;
                    } else {
                        $deleted = $wpdb->query($wpdb->prepare(
                            "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                             WHERE pm.post_id = %d AND p.ID IS NULL", $id
                        )) !== false;
                        $this->checkDatabase();
                        wp_cache_delete($id, 'post_meta');
                    }
                    if ($deleted) {
                        $result[$was_post ? 'deleted' : 'metadata']++;
                        $this->logger->write('Removed detached legacy attachment ' . $id . ($was_post ? '.' : ' metadata (post no longer exists).'), [], 'debug');
                        if (array_filter($candidate['files'], 'is_file')) {
                            $protected = ($this->protected)($candidate['folder']);
                            foreach ($candidate['files'] as $file) {
                                $resolved = realpath($file);
                                if ($resolved !== false && !isset($protected[$resolved])) { ($this->remove)($file); }
                            }
                        }
                    } else {
                        $result['failures']++;
                        $this->logger->write('Could not remove legacy attachment ' . $id . '.', ['event' => 'legacy_attachments.delete_failed'], 'warning');
                    }
                }
            }
            $result['after'] = $id;
        }
        $result['done'] = count($rows) < $limit && (!$rows || $result['after'] === (int) end($rows)->ID);
        return $result;
    }

    /** Deletion hooks must not remove attached or manually preserved translations. */
    private function findAttachedTranslations(array $candidates): array
    {
        if (!$candidates || (!defined('ICL_SITEPRESS_VERSION') && !isset($GLOBALS['sitepress']))) { return []; }
        global $wpdb;
        $table = $wpdb->prefix . 'icl_translations';
        $ids = implode(',', array_map('intval', array_keys($candidates)));
        $translations = $wpdb->get_results(
            "SELECT DISTINCT source.element_id AS source_id, related.element_id AS ID, parent.ID AS parent_id
             FROM $table source INNER JOIN $table related
               ON related.trid = source.trid AND related.element_type = source.element_type
             INNER JOIN {$wpdb->posts} p ON p.ID = related.element_id
             LEFT JOIN {$wpdb->posts} parent ON parent.ID = p.post_parent
             LEFT JOIN {$wpdb->postmeta} manual ON manual.post_id = p.ID
               AND manual.meta_key = '_casawp_manual_attachment' AND manual.meta_value = '1'
             WHERE source.element_type = 'post_attachment' AND source.element_id IN ($ids)
               AND (parent.ID IS NOT NULL OR manual.meta_id IS NOT NULL)"
        );
        $this->checkDatabase();
        if (!is_array($translations)) { throw new \RuntimeException('Cannot check WPML attachment translations.'); }
        $this->primeCaches(array_map(static function ($row) { return (int) $row->ID; }, $translations));
        $used = [];
        foreach ($translations as $translation) {
            if ($translation->parent_id || ImportMedia::isManualAttachment((int) $translation->ID)) {
                $used[(int) $translation->source_id] = 'WPML attachment translation ' . $translation->ID . ' is attached or manually preserved';
            }
        }
        return $used;
    }

    private function primeCaches(array $ids): void
    {
        if ($ids && function_exists('_prime_post_caches')) {
            // Read posts and metadata together instead of loading them per attachment/sibling.
            _prime_post_caches($ids, false, true);
            $this->checkDatabase();
        }
    }

    private function checkDatabase(): void
    {
        global $wpdb;
        if ($wpdb->last_error) { throw new \RuntimeException('Cannot safely clean legacy attachments: ' . $wpdb->last_error); }
    }
}
