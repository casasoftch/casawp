<?php
/** Standalone regression checks: php tests/import-taxonomy.php */
define('ABSPATH', dirname(__DIR__) . '/');
define('CASASYNC_PLUGIN_DIR', ABSPATH);
require_once ABSPATH . 'classes/Plugin.php';
require_once ABSPATH . 'classes/Import.php';
require_once ABSPATH . 'classes/ImportMedia.php';

$terms = $assignments = $postmeta = $insertions = [];
$next_term_id = 1;
// Existing sites can already have completed the attachment taxonomy migration.
$options = ['casawp_attachment_type_schema_version' => 1];
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; }
function is_wp_error($value) { return false; }
function wp_insert_term($label, $taxonomy, $args) {
    check(trim($label) !== '', 'Import must never insert an empty feature term');
    $id = $GLOBALS['next_term_id']++;
    $GLOBALS['insertions'][] = [$taxonomy, $args['slug']];
    $GLOBALS['terms'][$taxonomy][$args['slug']] = (object) [
        'term_id' => $id, 'term_taxonomy_id' => $id, 'slug' => $args['slug'], 'name' => $label,
    ];
    return ['term_id' => $id];
}
function wp_update_term($id, $taxonomy, $args) {
    foreach ($GLOBALS['terms'][$taxonomy] as $term) {
        if ($term->term_id === $id) { $term->name = $args['name']; }
    }
}
function get_term_by($field, $slug, $taxonomy) { return $GLOBALS['terms'][$taxonomy][$slug] ?? false; }
function wp_get_object_terms($id, $taxonomy, $args = []) {
    $terms = $GLOBALS['assignments'][$id] ?? [];
    return ($args['fields'] ?? '') === 'slugs' ? array_column($terms, 'slug') : $terms;
}
function wp_set_object_terms($id, $ids, $taxonomy) {
    $GLOBALS['assignments'][$id] = array_values(array_filter($GLOBALS['terms'][$taxonomy] ?? [], static function ($term) use ($ids) {
        return in_array($term->term_id, $ids, true);
    }));
}
function update_post_meta($id, $key, $value) { $GLOBALS['postmeta'][$id][$key] = $value; }
$wpdb = new class {
    public $terms = 'wp_terms';
    public $term_taxonomy = 'wp_term_taxonomy';
    public function prepare($query, ...$args) { return $args; }
    public function get_row($args) { return $GLOBALS['terms'][$args[0]][$args[1]] ?? null; }
};

foreach (['image' => 'Image', 'plan' => 'Plan', 'document' => 'Document', 'sales-brochure' => 'Sales Brochure'] as $slug => $label) {
    wp_insert_term($label, 'casawp_attachment_type', ['slug' => $slug]);
}
$insertions = [];
$reflection = new ReflectionClass(\casawp\Plugin::class);
$plugin = $reflection->newInstanceWithoutConstructor();
$repair = $reflection->getMethod('maybeRepairAttachmentTypeTerms');
$repair->setAccessible(true);
$repair->invoke($plugin);
foreach (\casawp\ImportMedia::TYPES as $slug) {
    check(isset($terms['casawp_attachment_type'][$slug]), 'Repair must create every supported attachment type, including offer-logo');
}
check($insertions === [['casawp_attachment_type', 'offer-logo']], 'Repair must only insert the missing attachment type');
$repair->invoke($plugin);
check(count($insertions) === 1, 'Repeated repair must not duplicate attachment types');

$import = new \casawp\Import();
// Reproduce the empty <feature/> present in the affected site's feed.
$xml = simplexml_load_string('<features><feature/><feature>balcony</feature><feature> </feature><feature>has-evelator</feature></features>');
$features = array_map('strval', iterator_to_array($xml->feature, false));
$import->setOfferFeatures((object) ['ID' => 91], $features, 'test-de');
check(array_column($assignments[91], 'slug') === ['balcony'], 'Empty and known invalid feature values must be skipped while valid features import');
check($postmeta[91]['_hash_features'] === 'balcony', 'Feature hash must contain only imported features');
$import->setOfferFeatures((object) ['ID' => 91], ['', ' ', "\n"], 'test-de');
check($assignments[91] === [] && $postmeta[91]['_hash_features'] === '', 'An empty feature list must clear old assignments without creating blank terms');
echo "Import taxonomy checks passed.\n";
