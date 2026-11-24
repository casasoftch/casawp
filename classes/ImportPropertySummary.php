<?php

namespace casawp;

/** Run-scoped property outcomes, retained across background requests and retries. */
class ImportPropertySummary
{
    public static function record(int $run_id, string $property_id, string $offer_id, string $outcome): void
    {
        if (!$run_id || $run_id !== casawp_current_import_run_id()) { return; }
        // Deletion workers may run alongside import workers; keep their ledgers separate.
        $option = $outcome === 'deleted' ? 'casawp_import_deleted_properties' : 'casawp_import_property_outcomes';
        wp_cache_delete($option, 'options');
        wp_cache_delete('notoptions', 'options');
        $state = get_option($option, []);
        if (!is_array($state) || ($state['run_id'] ?? 0) !== $run_id) {
            $state = ['run_id' => $run_id, 'properties' => []];
        }
        $previous = $state['properties'][$property_id][$offer_id] ?? null;
        // Replaying an offer must not turn its original creation/change into "unchanged".
        if ($previous === $outcome || $previous === 'new' || ($previous === 'changed' && $outcome === 'unchanged')) { return; }
        $state['properties'][$property_id][$offer_id] = $outcome;
        if ($run_id === casawp_current_import_run_id()) {
            update_option($option, $state, false);
        }
    }

    public static function counts(int $run_id): array
    {
        $ledgers = [];
        foreach (['casawp_import_property_outcomes', 'casawp_import_deleted_properties'] as $option) {
            wp_cache_delete($option, 'options');
            wp_cache_delete('notoptions', 'options');
            $state = get_option($option, []);
            $ledgers[] = is_array($state) && ($state['run_id'] ?? 0) === $run_id ? $state['properties'] : [];
        }
        [$imported, $deleted] = $ledgers;
        $counts = ['new' => 0, 'changed' => 0, 'unchanged' => 0, 'deleted' => 0];
        foreach ($imported as $property_id => $offers) {
            $outcomes = array_unique(array_values($offers));
            $outcome = count($outcomes) === 1 ? $outcomes[0] : 'changed';
            // Removing a translation changes the property; it does not delete it.
            if (isset($deleted[$property_id])) { $outcome = 'changed'; }
            $counts[$outcome]++;
        }
        $counts['deleted'] = count(array_diff_key($deleted, $imported));
        return $counts;
    }
}
