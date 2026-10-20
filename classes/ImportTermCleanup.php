<?php

namespace casawp;

/** Delete unused CASAWP terms after attachment and property cleanup has finished. */
class ImportTermCleanup
{
    private ImportLogger $logger;
    private $allowed;

    public function __construct(ImportLogger $logger, callable $allowed)
    {
        $this->logger = $logger;
        $this->allowed = $allowed;
    }

    public function runBatch(int $after, int $limit, int $seconds, ?int $max_id = null): array
    {
        $result = ['after' => $after, 'max_id' => $max_id ?? 0, 'done' => true, 'deleted' => 0, 'retained' => 0, 'failures' => 0];
        $taxonomies = array_values(array_filter(get_taxonomies([], 'names'), static function ($taxonomy) {
            return strpos($taxonomy, 'casawp_') === 0 && !in_array($taxonomy, ['casawp_availability', 'casawp_salestype'], true);
        }));
        if (!$taxonomies) { return $result; }
        global $wpdb;
        // Limit this pass to the terms present when it started.
        if ($max_id === null) {
            $max_id = (int) $wpdb->get_var("SELECT COALESCE(MAX(term_taxonomy_id), 0) FROM {$wpdb->term_taxonomy}");
            if ($wpdb->last_error) { throw new \RuntimeException('Cannot establish the CASAWP term cleanup boundary.'); }
        }
        $result['max_id'] = $max_id;
        $placeholders = implode(',', array_fill(0, count($taxonomies), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT tt.term_taxonomy_id, tt.term_id, tt.taxonomy FROM {$wpdb->term_taxonomy} tt
             WHERE tt.taxonomy IN ($placeholders) AND tt.term_taxonomy_id > %d AND tt.term_taxonomy_id <= %d
               AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr WHERE tr.term_taxonomy_id = tt.term_taxonomy_id)
             ORDER BY tt.term_taxonomy_id ASC LIMIT %d", ...array_merge($taxonomies, [$after, $max_id, $limit])
        ));
        if ($wpdb->last_error || !is_array($rows)) { throw new \RuntimeException('Cannot safely identify empty CASAWP terms: ' . $wpdb->last_error); }
        $started = microtime(true);
        foreach ($rows as $row) {
            if (!(($this->allowed)()) || ($result['after'] !== $after && microtime(true) - $started >= $seconds)) { break; }
            $exists = $wpdb->get_var($wpdb->prepare("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", (int) $row->term_taxonomy_id));
            if ($wpdb->last_error) { throw new \RuntimeException('Cannot recheck CASAWP term existence: ' . $wpdb->last_error); }
            if (!$exists) {
                // A previous deletion can also remove unused WPML counterparts.
                $result['after'] = (int) $row->term_taxonomy_id;
                continue;
            }
            if ($row->taxonomy === 'casawp_attachment_type' && $this->isRequiredAttachmentType((int) $row->term_id, (int) $row->term_taxonomy_id)) {
                $result['retained']++;
                $result['after'] = (int) $row->term_taxonomy_id;
                $this->logger->write(sprintf('Retained required CASAWP attachment type term %d.', $row->term_id), [], 'debug');
                continue;
            }
            $objects = get_objects_in_term((int) $row->term_id, $row->taxonomy);
            if (is_wp_error($objects)) { throw new \RuntimeException('Cannot recheck CASAWP term relationships: ' . $objects->get_error_message()); }
            if ($wpdb->last_error) { throw new \RuntimeException('Cannot recheck CASAWP term relationships: ' . $wpdb->last_error); }
            if (!$objects && (defined('ICL_SITEPRESS_VERSION') || isset($GLOBALS['sitepress']))) {
                $translations = $wpdb->prefix . 'icl_translations';
                $objects = $wpdb->get_var($wpdb->prepare(
                    "SELECT tr.object_id FROM $translations source
                     INNER JOIN $translations related ON related.trid = source.trid AND related.element_type = source.element_type
                     INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = related.element_id
                     WHERE source.element_type = %s AND source.element_id = %d LIMIT 1",
                    'tax_' . $row->taxonomy, (int) $row->term_taxonomy_id
                ));
                if ($wpdb->last_error) { throw new \RuntimeException('Cannot check WPML term translation usage: ' . $wpdb->last_error); }
            }
            if ($objects) {
                $result['retained']++;
            } else {
                $deleted = wp_delete_term((int) $row->term_id, $row->taxonomy);
                if ($deleted && !is_wp_error($deleted)) {
                    $result['deleted']++;
                    $this->logger->write(sprintf('Deleted empty CASAWP term %d (%s).', $row->term_id, $row->taxonomy), [], 'debug');
                } else {
                    $result['failures']++;
                    $this->logger->write(sprintf('Could not delete empty CASAWP term %d (%s).', $row->term_id, $row->taxonomy),
                        ['event' => 'terms.delete_failed'], 'warning');
                }
            }
            $result['after'] = (int) $row->term_taxonomy_id;
        }
        $result['done'] = count($rows) < $limit && (!$rows || $result['after'] === (int) end($rows)->term_taxonomy_id);
        return $result;
    }

    private function isRequiredAttachmentType(int $term_id, int $term_taxonomy_id): bool
    {
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count(ImportMedia::TYPES), '%s'));
        $required = $wpdb->get_var($wpdb->prepare(
            "SELECT term_id FROM {$wpdb->terms} WHERE term_id = %d AND slug IN ($placeholders) LIMIT 1",
            ...array_merge([$term_id], ImportMedia::TYPES)
        ));
        if ($wpdb->last_error) { throw new \RuntimeException('Cannot check required CASAWP attachment types: ' . $wpdb->last_error); }
        if ($required) { return true; }
        if (defined('ICL_SITEPRESS_VERSION') || isset($GLOBALS['sitepress'])) {
            // Synchronized WPML deletion must not remove a required counterpart either.
            $translations = $wpdb->prefix . 'icl_translations';
            $required = $wpdb->get_var($wpdb->prepare(
                "SELECT tt.term_id FROM $translations source
                 INNER JOIN $translations related ON related.trid = source.trid AND related.element_type = source.element_type
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = related.element_id
                 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                 WHERE source.element_type = 'tax_casawp_attachment_type' AND source.element_id = %d
                   AND tt.taxonomy = 'casawp_attachment_type' AND t.slug IN ($placeholders) LIMIT 1",
                ...array_merge([$term_taxonomy_id], ImportMedia::TYPES)
            ));
            if ($wpdb->last_error) { throw new \RuntimeException('Cannot check required WPML attachment types: ' . $wpdb->last_error); }
        }
        return (bool) $required;
    }
}
