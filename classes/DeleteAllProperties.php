<?php

namespace casawp;

/** Existing database-only deletion, with checked results and summary diagnostics. */
class DeleteAllProperties
{
    private $db;
    private ImportLogger $logger;
    private array $context;
    private array $counts = ['properties' => 0, 'attachments' => 0, 'metadata' => 0, 'relationships' => 0, 'wpml_rows' => 0, 'translation_jobs' => 0];
    private array $affected_rows = [];
    private array $completed_steps = [];
    private array $unverified_steps = [];
    private string $phase = 'initialization';
    private string $database_error = '';
    private float $started = 0;
    private bool $finished = false;
    private string $memory_reserve = '';

    public function __construct(?ImportLogger $logger = null)
    {
        global $wpdb;
        $this->db = $wpdb;
        // Deletion has its own operation identity and must never fail an import run.
        $this->logger = $logger ?? new ImportLogger(static function () { return 0; });
        $this->context = ['operation_id' => wp_generate_uuid4(), 'user_id' => get_current_user_id(),
            'site_id' => get_current_blog_id(), 'site_url' => home_url('/')];
    }

    private static function resultKey(): string
    {
        return 'casawp_delete_all_result_' . get_current_blog_id() . '_' . get_current_user_id();
    }

    public function run(): array
    {
        $this->started = microtime(true);
        delete_transient(self::resultKey());
        $this->memory_reserve = str_repeat(' ', 262144);
        register_shutdown_function([$this, 'logInterruption']);
        $this->logger->write('Property data deletion started.', $this->details('delete_all.started'));
        $previous_errors = $this->db->suppress_errors(true);

        try {
            $this->deleteRecords();
            $this->phase = 'cache_cleanup';
            clean_term_cache([], 'casawp_attachment_type');
            if (wp_cache_flush() === false) {
                throw new \RuntimeException('Object cache flush failed after property data deletion.');
            }
            $this->completed_steps[] = $this->phase;
            $this->phase = 'complete';
            $this->logger->write($this->summary('Property data deletion completed.'), $this->details('delete_all.completed'));
            $result = $this->storeResult('success');
        } catch (\Throwable $error) {
            $this->logger->exception($this->summary('Property data deletion failed; earlier changes were retained.'),
                $error, $this->details('delete_all.failed'));
            $result = $this->storeResult('failed');
        } finally {
            $this->finished = true;
            $this->memory_reserve = '';
            $this->db->suppress_errors($previous_errors);
        }
        return $result;
    }

    private function deleteRecords(): void
    {
        $p = $this->db->prefix;
        $posts = "{$p}posts";
        $meta = "{$p}postmeta";
        $terms = "{$p}terms";
        $tax = "{$p}term_taxonomy";
        $rel = "{$p}term_relationships";

        $this->phase = 'properties';
        $from = "FROM {$posts} p LEFT JOIN {$meta} pm ON pm.post_id = p.ID WHERE p.post_type = 'casawp_property'";
        $this->deletePosts('properties', $from, "DELETE p, pm
           FROM {$posts} p
           LEFT JOIN {$meta} pm ON pm.post_id = p.ID
          WHERE p.post_type = 'casawp_property'");

        $this->phase = 'attachment_types';
        // Preserve the established targets, including the legacy brochure spelling.
        $slugs = ['image', 'document', 'plan', 'offer-logo', 'sales-brochue'];
        $place = implode(',', array_fill(0, count($slugs), '%s'));
        $tt_ids = $this->db->get_col($this->db->prepare(
            "SELECT tt.term_taxonomy_id FROM {$tax} tt JOIN {$terms} t ON t.term_id = tt.term_id
              WHERE tt.taxonomy = 'casawp_attachment_type' AND t.slug IN ( {$place} )", $slugs));
        $this->checkDatabaseError();
        if (!is_array($tt_ids)) { throw new \RuntimeException('Attachment types could not be read.'); }
        $this->completed_steps[] = $this->phase;

        if ($tt_ids) {
            $tt_in = implode(',', array_map('intval', $tt_ids));
            $this->phase = 'attachments';
            $from = "FROM {$posts} p LEFT JOIN {$meta} pm ON pm.post_id = p.ID
                JOIN {$rel} tr ON tr.object_id = p.ID
                WHERE tr.term_taxonomy_id IN ( {$tt_in} ) AND p.post_type = 'attachment'";
            $this->deletePosts('attachments', $from, "DELETE p, pm
               FROM {$posts} p
               LEFT JOIN {$meta} pm ON pm.post_id = p.ID
               JOIN {$rel} tr ON tr.object_id = p.ID
              WHERE tr.term_taxonomy_id IN ( {$tt_in} )
                AND p.post_type = 'attachment'");

            $this->phase = 'attachment_relationships';
            $this->counts['relationships'] += $this->query("DELETE FROM {$rel}
              WHERE term_taxonomy_id IN ( {$tt_in} )");
            $this->completed_steps[] = $this->phase;
        }

        $this->phase = 'wpml_tables';
        $icl_trans = "{$p}icl_translations";
        $table = $this->db->get_var($this->db->prepare('SHOW TABLES LIKE %s', $this->db->esc_like($icl_trans)));
        $this->checkDatabaseError();
        $this->completed_steps[] = $this->phase;
        if ($table === $icl_trans) {
            $icl_status = "{$p}icl_translation_status";
            $icl_jobs = "{$p}icl_translate_job";
            $this->phase = 'wpml_translations';
            $this->counts['wpml_rows'] += $this->query("DELETE t , ts
               FROM {$icl_trans} AS t
               LEFT JOIN {$icl_status} AS ts ON ts.translation_id = t.translation_id
              WHERE t.element_type = 'post_casawp_property'");
            $this->completed_steps[] = $this->phase;
            $this->phase = 'wpml_jobs';
            $this->counts['translation_jobs'] += $this->query("DELETE
               FROM {$icl_jobs}
              WHERE rid NOT IN ( SELECT rid FROM {$icl_status} )");
            $this->completed_steps[] = $this->phase;
        }

        $this->phase = 'orphan_metadata';
        $this->counts['metadata'] += $this->query("DELETE pm
           FROM {$meta} pm
      LEFT JOIN {$posts} p ON p.ID = pm.post_id
          WHERE p.ID IS NULL");
        $this->completed_steps[] = $this->phase;
        $this->phase = 'term_counts';
        $this->query("UPDATE {$tax} SET count = 0 WHERE taxonomy = 'casawp_attachment_type'");
        $this->completed_steps[] = $this->phase;
    }

    private function deletePosts(string $type, string $from, string $sql): void
    {
        $before = $this->postCounts($from);
        $affected = $this->query($sql);
        // Multi-table DELETE counts posts AND metadata; never call that total a post count.
        if ($affected !== $before['posts'] + $before['metadata']) {
            $this->unverified_steps[] = $this->phase;
            throw new \RuntimeException('Deleted record counts could not be verified; data may have changed during deletion.');
        }
        $this->counts[$type] += $before['posts'];
        $this->counts['metadata'] += $before['metadata'];
        $remaining = $this->postCounts($from);
        if ($remaining['posts'] || $remaining['metadata']) {
            throw new \RuntimeException('Matching records remain after deletion.');
        }
        $this->completed_steps[] = $this->phase;
    }

    private function postCounts(string $from): array
    {
        $row = $this->db->get_row("SELECT COUNT(DISTINCT p.ID) AS posts, COUNT(DISTINCT pm.meta_id) AS metadata {$from}", ARRAY_A);
        $this->checkDatabaseError();
        if (!is_array($row) || !isset($row['posts'], $row['metadata'])
            || !is_numeric($row['posts']) || !is_numeric($row['metadata'])) {
            throw new \RuntimeException('Database record counts could not be read.');
        }
        return ['posts' => (int) $row['posts'], 'metadata' => (int) $row['metadata']];
    }

    private function query(string $sql): int
    {
        // A fatal error or an unsuccessful statement can leave this step's result unknown.
        $this->unverified_steps[] = $this->phase;
        $result = $this->db->query($sql);
        $this->checkDatabaseError();
        if ($result === false) { throw new \RuntimeException('Database deletion step failed: ' . $this->phase); }
        $this->affected_rows[$this->phase] = (int) $result;
        array_pop($this->unverified_steps);
        return (int) $result;
    }

    private function checkDatabaseError(): void
    {
        $this->database_error = (string) $this->db->last_error;
        if ($this->database_error !== '') {
            throw new \RuntimeException('Database deletion step failed: ' . $this->phase);
        }
    }

    private function details(string $event): array
    {
        return array_merge($this->context, ['event' => $event, 'phase' => $this->phase,
            'confirmed_counts' => $this->counts, 'affected_rows' => $this->affected_rows,
            'unverified_steps' => $this->unverified_steps, 'completed_steps' => $this->completed_steps,
            'duration_seconds' => round(microtime(true) - $this->started, 3), 'database_error' => $this->database_error]);
    }

    private function summary(string $message): string
    {
        return sprintf('%s Confirmed deleted: properties (%s), attachments (%s), metadata rows (%s). Duration: %.3fs.', $message,
            number_format($this->counts['properties'], 0, '.', ','), number_format($this->counts['attachments'], 0, '.', ','),
            number_format($this->counts['metadata'], 0, '.', ','), microtime(true) - $this->started);
    }

    private function storeResult(string $status): array
    {
        $result = ['status' => $status, 'operation_id' => $this->context['operation_id']];
        if (!set_transient(self::resultKey(), $result, 600)) {
            $this->logger->write('Property deletion result could not be stored for the administrator notice.',
                $this->details('delete_all.notice_failed'), 'warning');
        }
        return $result;
    }

    public function logInterruption(): void
    {
        if (!$this->started || $this->finished) { return; }
        $this->finished = true;
        $this->memory_reserve = '';
        $error = error_get_last();
        $details = $this->details('delete_all.interrupted');
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            $details['php_error'] = $error;
        }
        $this->logger->write($this->summary('Property data deletion interrupted; earlier changes may have been retained.'), $details, 'error');
        $this->storeResult('interrupted');
    }

    public static function displayNotice(): void
    {
        if (!current_user_can('manage_options')) { return; }
        if (!isset($_GET['page']) || $_GET['page'] !== 'casawp') { return; }
        $result = get_transient(self::resultKey());
        if (!is_array($result) || !isset($result['status'], $result['operation_id'])) { return; }
        if (!in_array($result['status'], ['success', 'failed', 'interrupted'], true)) { return; }
        // Consume the current administrator's result before showing it, including on refresh.
        if (!delete_transient(self::resultKey())) { return; }
        $message = $result['status'] === 'success' ? __('Property data deletion completed.', 'casawp')
            : ($result['status'] === 'interrupted'
                ? __('Property data deletion was interrupted. Some data may already have been deleted. See the logs for details.', 'casawp')
                : __('Property data deletion failed. Some data may already have been deleted. See the logs for details.', 'casawp'));
        echo '<div class="notice casawp-notice ' . ($result['status'] === 'success' ? 'notice-success' : 'notice-error')
            . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}
