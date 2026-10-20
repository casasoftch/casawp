<?php
/** SQLite-backed regression checks: php tests/import-legacy-attachments.php */
require_once dirname(__DIR__) . '/vendor/autoload.php';
$temporary = sys_get_temp_dir() . '/casawp-legacy-' . uniqid();
mkdir($temporary, 0700, true);
ini_set('error_log', $temporary . '/php-errors.log');
define('CASASYNC_CUR_UPLOAD_BASEDIR', $temporary);
define('CASASYNC_CUR_UPLOAD_BASEURL', 'https://site.example/uploads');
$options = $filters = $types = $taxonomies = [];
$scheduled = $hooks = [];
$can_manage = $valid_nonce = true;
class WP_Error {
    private string $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
class CleanupJsonResponse extends RuntimeException {
    public array $payload;
    public function __construct($success, $data, $code) { parent::__construct('', $code); $this->payload = ['success' => $success, 'data' => $data]; }
}
function __($text, $domain = '') { return !empty($GLOBALS['localized_ui']) ? 'DE: ' . $text : $text; }
function number_format_i18n($number, $decimals = 0) { return !empty($GLOBALS['localized_ui']) ? number_format($number, $decimals, ',', '.') : number_format($number, $decimals, '.', ''); }
function add_option($key, $value, $deprecated = '', $autoload = null) {
    if (array_key_exists($key, $GLOBALS['options'])) { return false; }
    $GLOBALS['options'][$key] = $value;
    return true;
}
function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook][] = $callback; }
function do_action(...$args) {}
function current_user_can($capability) { return $GLOBALS['can_manage']; }
function check_ajax_referer($action, $field) {
    check($action === 'casawp_manual_cleanup' && $field === 'nonce', 'Cleanup endpoints must require their own nonce');
    if (!$GLOBALS['valid_nonce']) { throw new CleanupJsonResponse(false, ['message' => 'Invalid nonce'], 403); }
}
function wp_send_json_error($data, $code = 200) { throw new CleanupJsonResponse(false, $data, $code); }
function wp_send_json_success($data) { throw new CleanupJsonResponse(true, $data, 200); }
function as_schedule_single_action($time, $hook, $args, $group) {
    if (get_option('simulate_schedule_failure')) { return 0; }
    $GLOBALS['scheduled'][] = compact('time', 'hook', 'args', 'group');
    if (isset($GLOBALS['on_schedule'])) { ($GLOBALS['on_schedule'])(); }
    return count($GLOBALS['scheduled']);
}
function as_unschedule_all_actions($hook, $args = [], $group = '') {
    $GLOBALS['scheduled'] = array_values(array_filter($GLOBALS['scheduled'], static function ($job) use ($hook, $args, $group) {
        return ($hook && $job['hook'] !== $hook) || $job['group'] !== $group || ($hook && $job['args'] !== $args);
    }));
}
function casawp_acquire_lock() { return add_option('casawp_import_lock', time()); }
function casawp_release_lock() { delete_option('casawp_import_lock'); }
function casawp_is_import_running() { return (int) get_option('casawp_import_lock', 0) > time() - 14400; }
function casawp_has_pending() { return (bool) get_option('casawp_import_pending'); }
function casawp_clear_pending() { delete_option('casawp_import_pending'); }
function casawp_gateway_poke_group() { return 'casawp_gateway_poke'; }
function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function get_date_from_gmt($date, $format) { return gmdate($format); }
function add_filter($name, $callback, $priority = 10, $args = 1) { $GLOBALS['filters'][$name][$priority][] = $callback; }
function remove_filter($name, $callback, $priority = 10) {
    foreach ($GLOBALS['filters'][$name][$priority] ?? [] as $key => $value) {
        if ($value === $callback) { unset($GLOBALS['filters'][$name][$priority][$key]); }
    }
}
function apply_filters($name, $value, ...$args) {
    $priorities = $GLOBALS['filters'][$name] ?? [];
    ksort($priorities);
    foreach ($priorities as $callbacks) { foreach ($callbacks as $callback) { $value = $callback($value, ...$args); } }
    return $value;
}
function get_post($id) { return $GLOBALS['wpdb']->get_results('SELECT * FROM wp_posts WHERE ID=' . (int) $id)[0] ?? null; }
function get_post_type($id) { return get_post($id)->post_type ?? false; }
function wp_get_post_parent_id($id) { return (int) (get_post($id)->post_parent ?? 0); }
function get_post_meta($id, $key, $single = false) {
    $value = $GLOBALS['wpdb']->get_results($GLOBALS['wpdb']->prepare('SELECT meta_value AS value FROM wp_postmeta WHERE post_id=%d AND meta_key=%s', $id, $key))[0]->value ?? '';
    $decoded = @unserialize($value, ['allowed_classes' => false]);
    return $decoded !== false ? $decoded : $value;
}
function update_post_meta($id, $key, $value) {
    delete_post_meta($id, $key);
    $query = $GLOBALS['wpdb']->db->prepare('INSERT INTO wp_postmeta(post_id,meta_key,meta_value) VALUES(?,?,?)');
    return $query->execute([$id, $key, is_array($value) ? serialize($value) : $value]);
}
function delete_post_meta($id, $key) { return $GLOBALS['wpdb']->query($GLOBALS['wpdb']->prepare('DELETE FROM wp_postmeta WHERE post_id=%d AND meta_key=%s', $id, $key)); }
function has_term($term, $taxonomy, $id) { return ($GLOBALS['types'][$id] ?? '') === $term; }
function wp_attachment_is_image($id) { return (get_post($id)->post_mime_type ?? '') === 'image/jpeg'; }
function wp_cache_delete(...$args) {}
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_taxonomies($args = [], $output = 'names') { return $GLOBALS['taxonomies']; }
function get_objects_in_term($id, $taxonomy) {
    if ($id === 1006) { return [900]; } // A relationship appeared after the candidate query.
    return $GLOBALS['wpdb']->get_col('SELECT tr.object_id FROM wp_term_relationships tr JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tt.term_id=' . (int) $id);
}
function wp_delete_term($id, $taxonomy) {
    check(!get_post(101), 'Term deletion must follow legacy attachment cleanup');
    if ($id === 1005) { return false; }
    if (get_option('term_throw_before') === $id) { throw new RuntimeException('Simulated failure before term deletion'); }
    if (get_option('term_throw_database') === $id) {
        $GLOBALS['wpdb']->last_error = 'Simulated term deletion database error';
        throw new RuntimeException('Simulated deletion exception with a database error');
    }
    if (defined('ICL_SITEPRESS_VERSION')) {
        $GLOBALS['wpdb']->query("DELETE FROM wp_icl_translations WHERE element_type='tax_" . $taxonomy . "' AND element_id=" . (int) $id);
    }
    $deleted = $GLOBALS['wpdb']->query('DELETE FROM wp_term_taxonomy WHERE term_id=' . (int) $id) > 0;
    if (get_option('term_throw_after') === $id) { throw new RuntimeException('Primary key ID missing from row or is null'); }
    return $deleted;
}
function wp_delete_attachment($id, $force) {
    if (get_option('fail_delete') === $id) { return false; }
    $post = get_post($id);
    $file = get_post_meta($id, '_wp_attached_file', true);
    if ($file) {
        $path = $file[0] === '/' ? $file : CASASYNC_CUR_UPLOAD_BASEDIR . '/' . $file;
        $delete = apply_filters('wp_delete_file', $path);
        check($delete === '', 'WordPress physical deletion must be suppressed while deleting a legacy record');
    }
    $GLOBALS['wpdb']->query('DELETE FROM wp_posts WHERE ID=' . (int) $id);
    $GLOBALS['wpdb']->query('DELETE FROM wp_postmeta WHERE post_id=' . (int) $id);
    if (defined('ICL_SITEPRESS_VERSION')) {
        $GLOBALS['wpdb']->query("DELETE FROM wp_icl_translations WHERE element_type='post_attachment' AND element_id=" . (int) $id);
        if ($id === 301) { wp_delete_attachment(302, true); } // Simulate WPML synchronized deletion.
    }
    return $post;
}
$wpdb = new class {
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $options = 'wp_options';
    public $termmeta = 'wp_termmeta';
    public $usermeta = 'wp_usermeta';
    public $commentmeta = 'wp_commentmeta';
    public $term_taxonomy = 'wp_term_taxonomy';
    public $terms = 'wp_terms';
    public $term_relationships = 'wp_term_relationships';
    public $last_error = '';
    public $fail_usage = false;
    public array $usage_queries = [];
    public array $candidate_ids = [];
    public PDO $db;
    public function __construct() {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->sqliteCreateFunction('regexp', static function ($pattern, $value) { return preg_match('~' . str_replace('~', '\\~', $pattern) . '~i', $value ?? ''); });
        $this->db->exec('CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY, post_type TEXT, post_parent INTEGER, guid TEXT, post_content TEXT DEFAULT "", post_excerpt TEXT DEFAULT "", post_mime_type TEXT DEFAULT "image/jpeg")');
        $this->db->exec('CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY AUTOINCREMENT,post_id INTEGER, meta_key TEXT, meta_value TEXT)');
        $this->db->exec('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY AUTOINCREMENT,option_name TEXT,option_value TEXT)');
        $this->db->exec('CREATE TABLE wp_term_taxonomy(term_id INTEGER,term_taxonomy_id INTEGER,taxonomy TEXT,count INTEGER)');
        $this->db->exec('CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY,slug TEXT)');
        $this->db->exec('CREATE TABLE wp_term_relationships(object_id INTEGER,term_taxonomy_id INTEGER)');
        $this->db->exec('CREATE TABLE wp_icl_translations(element_id INTEGER,element_type TEXT,trid INTEGER,language_code TEXT)');
        foreach (['term' => 'term', 'user' => 'user', 'comment' => 'comment'] as $table => $id) {
            $cursor = $table === 'user' ? 'umeta_id' : 'meta_id';
            $this->db->exec("CREATE TABLE wp_{$table}meta($cursor INTEGER PRIMARY KEY AUTOINCREMENT,{$id}_id INTEGER,meta_key TEXT,meta_value TEXT)");
        }
    }
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function prepare($query, ...$args) {
        return preg_replace_callback('/%[ds]/', function ($match) use (&$args) {
            $value = array_shift($args);
            return $match[0] === '%d' ? (string) (int) $value : $this->db->quote($value);
        }, $query);
    }
    private function sqlite($query) {
        $query = preg_replace("/(LIKE '(?:[^']|'')*')/", "$1 ESCAPE '\\'", $query);
        return preg_replace('/LEFT\(option_name, (\d+)\)/', 'substr(option_name, 1, $1)', $query);
    }
    public function get_results($query) {
        $this->last_error = '';
        if (strpos($query, 'casawp_legacy_usage') !== false) { $this->usage_queries[] = $query; }
        if ($this->fail_usage) { $this->last_error = 'Simulated usage lookup failure'; return null; }
        try {
            $rows = $this->db->query($this->sqlite($query))->fetchAll(PDO::FETCH_OBJ);
            if (strpos($query, 'ORDER BY ID ASC LIMIT') !== false) { $this->candidate_ids = array_map(static function ($row) { return (int) $row->ID; }, $rows); }
            return $rows;
        }
        catch (Throwable $e) { $this->last_error = $e->getMessage(); return null; }
    }
    public function get_col($query) { return array_map(static function ($row) { $values = (array) $row; return reset($values); }, $this->get_results($query) ?? []); }
    public function get_var($query) { return $this->get_col($query)[0] ?? null; }
    public function query($query) {
        $this->last_error = '';
        if (preg_match('/DELETE pm .* WHERE pm.post_id = (\d+) AND p.ID IS NULL/s', $query, $m)) {
            $query = "DELETE FROM wp_postmeta WHERE post_id={$m[1]} AND NOT EXISTS (SELECT 1 FROM wp_posts WHERE ID={$m[1]})";
        }
        try { return $this->db->exec($query); }
        catch (Throwable $e) { $this->last_error = $e->getMessage(); return false; }
    }
};
function put($path, $value = 'test file') {
    if (!is_dir(dirname($path))) { mkdir(dirname($path), 0700, true); }
    file_put_contents($path, $value);
    return $path;
}
function record($id, $file, $parent = 0, $exists = true) {
    global $wpdb;
    if ($exists) {
        $statement = $wpdb->db->prepare('INSERT INTO wp_posts(ID,post_type,post_parent,guid) VALUES(?,?,?,?)');
        $statement->execute([$id, 'attachment', $parent, 'https://casagateway.ch/media/provider-1/property-gone/' . basename($file)]);
    }
    if ($file) {
        update_post_meta($id, '_wp_attached_file', 'casawp/import/attachment/' . $file);
        return put(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/attachment/' . $file);
    }
    return '';
}
function sweep() {
    $logger = new \casawp\ImportLogger(static function () { return 111; });
    for ($i = 0; $i < 100; $i++) {
        if ((new \casawp\ImportFileCleanup(CASASYNC_CUR_UPLOAD_BASEDIR, $logger))->runBatch(111, ['active'])) { return $i; }
    }
    throw new RuntimeException('Cleanup failed to finish in bounded batches');
}
function removeTree($path) {
    if (is_link($path) || !is_dir($path)) { unlink($path); return; }
    foreach (scandir($path) as $file) { if ($file !== '.' && $file !== '..') { removeTree($path . '/' . $file); } }
    rmdir($path);
}
try {
    $feed = put($temporary . '/casawp/import/data.xml', '<root><properties><property id="active"/></properties></root>');
    $options = ['casawp_current_run_id' => 111, 'casawp_use_casagateway_cdn' => true,
        'casawp_import_feed_fingerprint' => ['run_id' => 111, 'sha256' => hash_file('sha256', $feed)]];
    add_filter('casawp_legacy_attachment_cleanup_batch_size', static function () { return 2; });
    $unused = record(101, 'externalsync/gone/unused.pdf');
    $shared = record(102, 'externalsync/gone/shared.jpg');
    record(103, 'externalsync/gone/shared.jpg');
    $parentless = record(104, 'externalsync/gone/missing-parent.jpg', 9999);
    $orphanmeta = record(105, 'externalsync/gone/meta-only.pdf', 0, false);
    $id_used = record(106, 'externalsync/gone/featured.jpg');
    $gallery = record(107, 'externalsync/gone/gallery.jpg');
    $acf = record(108, 'externalsync/gone/acf.jpg');
    $url_used = record(109, 'externalsync/gone/url.jpg');
    $preview = record(110, 'externalsync/gone/preview.pdf');
    $thumbnail = put(dirname($preview) . '/preview-150x150.jpg');
    update_post_meta(110, '_wp_attachment_metadata', ['sizes' => ['thumbnail' => ['file' => basename($thumbnail)]]]);
    $widget = record(111, 'externalsync/gone/widget.jpg');
    $term = record(112, 'externalsync/gone/term.jpg');
    $user = record(113, 'externalsync/gone/user.jpg');
    $comment = record(114, 'externalsync/gone/comment.jpg');
    $protected_shared = record(115, 'externalsync/gone/live-shared.jpg');
    record(116, 'externalsync/gone/live-shared.jpg', 900);
    $failed = record(117, 'externalsync/gone/failed.jpg');
    $options['fail_delete'] = 117;
    $custom = record(118, 'externalsync/gone/custom.jpg');
    add_filter('casawp_legacy_attachment_usage', static function ($reason, $id) { return $id === 118 ? 'custom integration reference' : $reason; }, 10, 2);
    $outside = put($temporary . '/outside/file.jpg');
    symlink(dirname($outside), $temporary . '/casawp/import/attachment/externalsync/linked');
    record(119, 'externalsync/linked/file.jpg');
    $outside_record = record(120, 'externalsync/gone/outside.jpg');
    update_post_meta(120, '_wp_attached_file', '../outside/file.jpg');
    record(121, ''); // A detached gateway record with no remaining local file metadata.
    record(122, '');
    update_post_meta(122, '_wp_attached_file', 'https://casagateway.ch/media/provider-1/old-remote.jpg%3Fp%3Dxl');
    record(123, '');
    update_post_meta(123, '_wp_attached_file', 'https://casagateway.ch/media/provider-1/used-remote.jpg');
    $wpdb->db->exec("UPDATE wp_posts SET guid='https://casagateway.ch/media/provider-1/used-remote.jpg' WHERE ID=123");
    $wpdb->db->exec("INSERT INTO wp_posts(ID,post_type,post_parent,guid,post_content) VALUES(900,'post',0,'','[gallery ids=\"107\"] wp-image-109 preview-150x150.jpg url.jpg used-remote.jpg')");
    update_post_meta(900, '_thumbnail_id', 106);
    update_post_meta(900, 'acf_gallery', ['108']);
    $wpdb->db->exec("INSERT INTO wp_options(option_name,option_value) VALUES('widget_media_image','{\"attachment_id\":111}')");
    $wpdb->db->exec("INSERT INTO wp_termmeta(term_id,meta_key,meta_value) VALUES(1,'image','112')");
    $wpdb->db->exec("INSERT INTO wp_usermeta(user_id,meta_key,meta_value) VALUES(1,'avatar','113')");
    $wpdb->db->exec("INSERT INTO wp_commentmeta(comment_id,meta_key,meta_value) VALUES(1,'image','114')");
    check(sweep() > 5, 'Legacy cleanup must persist its cursor across bounded requests');
    foreach (array_merge([101, 102, 103, 104, 121, 122, 123], range(106, 115)) as $id) { check(!get_post($id), "Unused legacy record $id must be deleted"); }
    check(!get_post_meta(105, '_wp_attached_file', true), 'Metadata belonging to a missing CASAWP attachment post must be deleted');
    foreach ([$unused, $shared, $parentless, $orphanmeta, $id_used, $gallery, $acf, $url_used, $preview, $thumbnail, $widget, $term, $user, $comment, $protected_shared, $failed, $custom, $outside_record] as $file) {
        check(!is_file($file), 'CDN mode must remove every regular externalsync file, including shared and referenced files');
    }
    check(is_file($outside), 'Files reached through symbolic links must survive');
    foreach ([116, 117, 118, 119, 120] as $id) { check((bool) get_post($id), 'Attached, failed, filter-retained and unsafe records must survive'); }
    check(apply_filters('wp_delete_file', 'file.jpg') === 'file.jpg', 'Temporary physical deletion filter must always be removed');
    $totals = get_option('casawp_import_file_cleanup_totals')['stats'];
    check($totals['legacy_deleted'] === 17 && $totals['legacy_metadata'] === 1 && $totals['bytes'] === 18 * strlen('test file'), 'Totals must count duplicate records separately and each physical file only once');
    check(!$wpdb->usage_queries, 'Detached import cleanup must not depend on arbitrary content, metadata or option references');

    // An unrelated media-library record and unrelated orphan metadata are outside this cleanup.
    record(124, '');
    $wpdb->db->exec("UPDATE wp_posts SET guid='https://site.example/uploads/manual.jpg' WHERE ID=124");
    update_post_meta(125, 'unrelated_setting', 'keep');
    delete_option('casawp_import_file_cleanup');
    sweep();
    check(get_post(124) && get_post_meta(125, 'unrelated_setting', true) === 'keep', 'Cleanup must remain scoped to CASAWP-owned records and metadata');

    // CDN-off cleanup must preserve legacy files for active properties.
    $options['casawp_use_casagateway_cdn'] = false;
    delete_option('casawp_import_file_cleanup');
    $local = record(201, 'externalsync/active/local.jpg');
    sweep();
    check(get_post(201) && is_file($local), 'CDN-off active property media must remain');

    // Failed database queries must leave records and files intact.
    delete_option('casawp_import_file_cleanup');
    $new = record(202, 'externalsync/gone/db-failure.jpg');
    $wpdb->fail_usage = true;
    try { sweep(); check(false, 'Database lookup failure must stop cleanup'); }
    catch (RuntimeException $e) { check(strpos($e->getMessage(), 'Simulated usage lookup failure') !== false, 'Expected safe database lookup failure'); }
    $wpdb->fail_usage = false;
    check(get_post(202) && is_file($new), 'Uncertain usage must retain both record and file');

    delete_option('casawp_import_file_cleanup');
    $taxonomies = ['casawp_category', 'casawp_feature', 'casawp_availability', 'casawp_salestype', 'category'];
    $wpdb->db->exec("INSERT INTO wp_term_taxonomy VALUES
        (1001,1001,'casawp_category',99),(1002,1002,'casawp_feature',0),
        (1003,1003,'casawp_availability',0),(1004,1004,'casawp_salestype',0),
        (1005,1005,'casawp_feature',0),(1006,1006,'casawp_feature',0),(1007,1007,'category',0)");
    $wpdb->db->exec('INSERT INTO wp_term_relationships VALUES(900,1002)');
    add_filter('casawp_file_cleanup_batch_size', static function () { return 2; });
    sweep();
    check(!$wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=1001'), 'Unused CASAWP terms must be deleted even when the stored count is wrong');
    foreach ([1002, 1003, 1004, 1005, 1006, 1007] as $id) {
        check($wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=' . $id), 'Assigned, excluded, failed and newly referenced terms must survive');
    }
    // Plugin hook exceptions must not abort the term pass, even after partial deletion.
    $wpdb->db->exec("INSERT INTO wp_term_taxonomy VALUES(1010,1010,'casawp_feature',0),(1011,1011,'casawp_feature',0),(1012,1012,'casawp_feature',0),(1013,1013,'casawp_feature',0)");
    $options['term_throw_before'] = 1010;
    $options['term_throw_after'] = 1011;
    $hook_worker = new \casawp\ImportTermCleanup(new \casawp\ImportLogger(static function () { return 111; }), static function () { return true; });
    $hook_batch = $hook_worker->runBatch(1007, 2, 5);
    check($hook_batch['after'] === 1011 && !$hook_batch['done'] && $hook_batch['deleted'] === 1 && $hook_batch['failures'] === 2,
        'Hook failures must advance the cursor, count actual deletions and retain warning counts');
    check($wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=1010') && !$wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=1011'),
        'A failed pre-delete hook must retain the term while a failed post-delete hook counts the removed term');
    $next_hook_batch = $hook_worker->runBatch($hook_batch['after'], 1, 5, $hook_batch['max_id']);
    check($next_hook_batch['deleted'] === 1 && $next_hook_batch['after'] === 1012, 'Later batches must continue past failed hooks');
    $hook_warnings = [];
    foreach (glob($temporary . '/casawp/logs/*.log') as $log_file) {
        foreach (file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $entry = json_decode($line, true);
            if (($entry['_meta']['event'] ?? '') === 'terms.delete_hook_failed') { $hook_warnings[] = $entry['_meta']; }
        }
    }
    check(count($hook_warnings) === 2 && $hook_warnings[0]['term_deleted'] === false && $hook_warnings[1]['term_deleted'] === true
        && $hook_warnings[1]['term_id'] === 1011 && $hook_warnings[1]['taxonomy'] === 'casawp_feature'
        && $hook_warnings[1]['level'] === 'warning' && isset($hook_warnings[1]['file'], $hook_warnings[1]['trace']),
        'Hook warnings must identify the exact term, taxonomy, exception location and physical deletion result');
    $options['term_throw_database'] = 1013;
    try { $hook_worker->runBatch(1012, 1, 5); check(false, 'Database errors must still stop cleanup'); }
    catch (RuntimeException $e) { check(strpos($e->getMessage(), 'Cannot safely continue CASAWP term cleanup after a database error:') === 0, 'Expected a safe database failure'); }
    foreach (['term_throw_before', 'term_throw_after', 'term_throw_database'] as $key) { delete_option($key); }
    // New terms created between requests must not cause an endless cleanup loop.
    $worker = new \casawp\ImportTermCleanup(new \casawp\ImportLogger(static function () { return 111; }), static function () { return true; });
    $wpdb->db->exec("INSERT INTO wp_term_taxonomy VALUES(1100,1100,'casawp_feature',0),(1101,1101,'casawp_feature',0)");
    $first = $worker->runBatch(0, 1, 5);
    $wpdb->db->exec("INSERT INTO wp_term_taxonomy VALUES(1200,1200,'casawp_feature',0)");
    $after = $first['after'];
    for ($i=0; $i<20; $i++) {
        $next = $worker->runBatch($after, 1, 5, $first['max_id']);
        if ($next['done']) { break; }
        $after = $next['after'];
    }
    check($next['done'] && $wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=1200'), 'The term pass must finish and retain terms created after its initial boundary');

    define('ICL_SITEPRESS_VERSION', 'test');
    delete_option('casawp_import_file_cleanup');
    $unused_de = record(301, 'externalsync/gone/unused-de.jpg');
    $unused_fr = record(302, 'externalsync/gone/unused-fr.jpg');
    update_post_meta(302, '_icl_lang_duplicate_of', 301);
    $attached_de = record(303, 'externalsync/gone/attached-de.jpg');
    $attached_fr = record(304, 'externalsync/gone/attached-fr.jpg', 900);
    $used_de = record(305, 'externalsync/gone/used-de.jpg');
    $used_fr = record(306, 'externalsync/gone/used-fr.jpg');
    update_post_meta(900, 'translated_featured_image', 306);
    $missing_de = record(307, 'externalsync/gone/missing-de.jpg', 0, false);
    $live_fr = record(308, 'externalsync/gone/live-fr.jpg', 900);
    $preview_de = record(309, 'externalsync/gone/preview-de.pdf');
    $preview_fr = record(310, 'externalsync/gone/preview-fr.pdf');
    update_post_meta(310, '_wp_attachment_metadata', ['sizes' => ['thumbnail' => ['file' => 'translated-preview.jpg']]]);
    $translated_preview = put(dirname($preview_fr) . '/translated-preview.jpg');
    $wpdb->db->exec("UPDATE wp_posts SET post_content=post_content || ' translated-preview.jpg' WHERE ID=900");
    foreach ([301 => 301, 302 => 301, 303 => 303, 304 => 303, 305 => 305, 306 => 305, 307 => 307, 308 => 307, 309 => 309, 310 => 309] as $id => $trid) {
        $wpdb->db->exec("INSERT INTO wp_icl_translations VALUES($id,'post_attachment',$trid,'test')");
    }
    $wpdb->db->exec("INSERT INTO wp_term_taxonomy VALUES(2001,2001,'casawp_category',0),(2002,2002,'casawp_category',0),(2003,2003,'casawp_category',0),(2004,2004,'casawp_category',0)");
    $wpdb->db->exec('INSERT INTO wp_term_relationships VALUES(900,2002)');
    $wpdb->db->exec("INSERT INTO wp_icl_translations VALUES(2001,'tax_casawp_category',2001,'de'),(2002,'tax_casawp_category',2001,'fr'),(2003,'tax_casawp_category',2003,'de'),(2004,'tax_casawp_category',2003,'fr')");
    sweep();
    check(!get_post(301) && !get_post(302) && !is_file($unused_de) && !is_file($unused_fr), 'Unused WPML attachment groups must be cleaned despite duplicate bookkeeping links');
    check(!$wpdb->get_var('SELECT element_id FROM wp_icl_translations WHERE element_id IN (301,302)'), 'WordPress attachment deletion APIs must let WPML hooks remove translation links');
    foreach ([$attached_de, $attached_fr, $missing_de, $live_fr] as $file) {
        check(is_file($file), 'With CDN off, WPML groups with an attached counterpart must retain local files');
    }
    foreach ([$used_de, $used_fr, $preview_de, $preview_fr, $translated_preview] as $file) {
        check(!is_file($file), 'Detached WPML groups must be cleaned without scanning arbitrary content references');
    }
    foreach ([303, 304, 308] as $id) { check(get_post($id), 'An attached WPML translation group must keep its records'); }
    foreach ([305, 306, 309, 310] as $id) { check(!get_post($id), 'Detached WPML translation records must be deleted'); }
    check($wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=2001'), 'An empty term with an assigned WPML counterpart must survive synchronized deletion');
    check(!$wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=2003') && !$wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=2004'), 'Unused WPML term groups must be removed across languages');
    check(!$wpdb->get_var('SELECT element_id FROM wp_icl_translations WHERE element_id IN (2003,2004)'), 'WordPress term deletion APIs must let WPML hooks remove translation links');

    // Standalone cleanup uses the same worker with separate state, without a feed or import lifecycle.
    \casawp\ManualCleanup::registerHooks();
    check(isset($hooks['wp_ajax_casawp_start_cleanup'], $hooks['wp_ajax_casawp_cleanup_status'], $hooks['casawp_manual_cleanup_batch']), 'Manual cleanup must register authenticated start/status endpoints and its own worker');
    $can_manage = false;
    try { \casawp\ManualCleanup::ajaxStart(); check(false, 'An unauthorized user must not start cleanup'); }
    catch (CleanupJsonResponse $response) { check($response->getCode() === 403, 'Cleanup start must require administrator privileges'); }
    $can_manage = true;
    $valid_nonce = false;
    try { \casawp\ManualCleanup::ajaxStatus(); check(false, 'Status must reject an invalid nonce'); }
    catch (CleanupJsonResponse $response) { check($response->getCode() === 403, 'Cleanup status must verify its nonce'); }
    $valid_nonce = true;
    $manual = new \casawp\ManualCleanup();
    check(is_wp_error($manual->start()), 'Standalone cleanup must reject an active import');
    delete_option('casawp_current_run_id');
    $wpdb->db->exec("INSERT INTO wp_posts(ID,post_type,post_parent,guid,post_content) VALUES(8000,'casawp_property',0,'','Original DE property'),(8001,'casawp_property',0,'','Original FR property')");
    update_post_meta(8000, 'exportproperty_id', 'active');
    update_post_meta(8001, 'exportproperty_id', 'active');
    $options['casawp_use_casagateway_cdn'] = true;
    $options['casawp_total_batches'] = 123;
    $options['casawp_completed_batches'] = 77;
    $options['casawp_import_file_cleanup'] = ['run_id' => 777, 'preserve' => true];
    $original_import_state = $options['casawp_import_file_cleanup'];
    unlink($feed);
    $current_cdn = record(401, 'externalsync/active/current-cdn.jpg', 8000);
    update_post_meta(401, '_origin', 'https://cdn.casasoft.com/media/provider-1/property-active/current-cdn.jpg');
    update_post_meta(401, '_casawp_import', 1);
    $types[401] = 'image';
    $unused_manual = record(402, 'externalsync/obsolete/manual-unused.pdf');
    $orphan_manual = record(403, 'externalsync/obsolete/manual-orphan.pdf', 0, false);
    $local_manual = record(404, 'externalsync/active/local-document.pdf', 8001);
    $historical_xml = put($temporary . '/casawp/import/data-done.xml', 'old XML');
    $old_log = put($temporary . '/casawp/logs/200001.log', str_repeat('x', 1048576));
    $wpdb->db->exec("INSERT INTO wp_term_taxonomy VALUES(2501,2501,'casawp_feature',0),(2502,2502,'casawp_feature',0),(2503,2503,'casawp_feature',0)");
    $options['term_throw_before'] = 2501;
    $options['term_throw_after'] = 2502;
    $before_files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary . '/casawp/import', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && !$file->isLink()) { $before_files[$file->getPathname()] = $file->getSize(); }
    }
    $scheduled = [];
    $options['casawp_import_cleanup_progress'] = ['run_id' => 777, 'finished_at' => time(), 'stats' => ['files' => 12], 'freed_bytes' => 123];
    $localized_ui = true;
    $started = $manual->start();
    check(!empty(get_option('casawp_import_cleanup_progress')['notice_dismissed'])
        && get_option('casawp_import_cleanup_progress')['stats']['files'] === 12, 'An accepted manual cleanup must dismiss the previous import result while preserving its counts');
    check(!is_wp_error($started) && $started['status'] === 'queued' && !get_option('casawp_current_run_id'), 'Manual cleanup must queue independently without minting an import run');
    check(is_wp_error($manual->start()), 'Double-clicking cleanup must not start a second run');
    $manual_run = get_option('casawp_manual_cleanup')['run_id'];
    for ($i=0; $i<200; $i++) {
        $state = get_option('casawp_manual_cleanup');
        if ($state['status'] !== 'queued' && $state['status'] !== 'running') { break; }
        $manual->runBatch($state['next_batch'], $manual_run);
    }
    $state = get_option('casawp_manual_cleanup');
    check($state['status'] === 'completed' && !get_option('casawp_import_lock') && !get_option('casawp_manual_cleanup_worker'), 'Manual cleanup must finish across requests and release its locks');
    check($state['stats']['terms_deleted'] >= 2 && $state['stats']['failures'] >= 2
        && $wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=2501')
        && !$wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id IN (2502,2503)'),
        'Manual cleanup must finish with warnings after hook exceptions, retain failed terms and remove later terms');
    delete_option('term_throw_before');
    delete_option('term_throw_after');
    check(!get_post(402) && !get_post_meta(403, '_wp_attached_file', true) && !is_file($unused_manual) && !is_file($orphan_manual), 'Standalone cleanup must remove unused legacy records and their files');
    check(get_post(401) && !is_file($current_cdn) && get_post_meta(401, '_is_remote', true), 'Standalone CDN cleanup must retain current attachment IDs while removing their local copies');
    check(get_post(404) && !is_file($local_manual) && !is_file($historical_xml) && !is_file($old_log) && !is_file($feed), 'Manual CDN cleanup must retain attached records, empty the externalsync cache, clear old XML/logs and never create data.xml');
    check(get_post(8000)->post_content === 'Original DE property' && get_post(8001)->post_content === 'Original FR property', 'Standalone cleanup must retain properties in every language');
    check($options['casawp_total_batches'] === 123 && $options['casawp_completed_batches'] === 77 && $options['casawp_import_file_cleanup'] === $original_import_state, 'Manual cleanup must leave import progress and cleanup state intact');
    $cleared = 0;
    foreach ($before_files as $file => $bytes) { if (!is_file($file)) { $cleared += $bytes; } }
    $status = \casawp\ManualCleanup::statusData();
    check($status['freed_bytes'] === $cleared + 1048576 && $state['log_bytes'] === 1048576, 'Manual total must match actual filesystem deletions plus old-log bytes');
    check($status['notice_seconds'] > 590 && $status['notice_seconds'] <= 600, 'A completed manual cleanup must retain its notice for ten minutes');
    \casawp\ManualCleanup::dismissCompletionNotice();
    check(\casawp\ManualCleanup::statusData()['notice_seconds'] === 0
        && get_option('casawp_manual_cleanup')['stats'] === $state['stats'], 'Starting an import must permanently dismiss the previous manual notice while retaining its results');
    update_option('casawp_manual_cleanup', $state);
    $completion_entries = [];
    $manual_events = [];
    foreach (glob($temporary . '/casawp/logs/*.log') as $log_file) {
        foreach (file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $entry = json_decode($line, true);
            if (($entry['_meta']['run_id'] ?? 0) === $manual_run) { $manual_events[] = $entry['_meta']['event'] ?? ''; }
            if (($entry['_meta']['event'] ?? '') === 'manual_cleanup.completed' && ($entry['_meta']['run_id'] ?? 0) === $manual_run) { $completion_entries[] = $entry; }
        }
    }
    check(count($completion_entries) === 1 && strpos(reset($completion_entries[0]), 'Cleanup completed with warnings. See the logs for details. Removed:') === 0
        && strpos(reset($completion_entries[0]), 'DE:') === false && strpos($status['message'], 'DE:') === 0
        && $completion_entries[0]['_meta']['freed_bytes'] === $status['freed_bytes'], 'The manual log summary must be English even when settings-page notices are translated');
    check(max(array_keys($manual_events, 'manual_cleanup_batch.finished', true)) < array_search('cleanup.total', $manual_events, true)
        && end($manual_events) === 'cleanup.separator'
        && array_search('cleanup.separator', $manual_events, true) === array_search('manual_cleanup.completed', $manual_events, true) + 1
        && count(array_keys($manual_events, 'cleanup.separator', true)) === 1, 'Manual cleanup must finish its last batch before totals and summary, then write one separator');
    check(count(array_keys($manual_events, 'cleanup.separator.start', true)) === 1
        && array_search('cleanup.separator.start', $manual_events, true) < array_search('manual_cleanup.queued', $manual_events, true)
        && !in_array('import.separator.start', $manual_events, true) && !in_array('import.separator.end', $manual_events, true),
        'Standalone cleanup must write one start separator before queuing, without import boundaries');
    $localized_ui = false;
    $summary_state = $state;
    $summary_state['stats'] = ['legacy_deleted' => 12, 'files' => 35, 'terms_deleted' => 6, 'failures' => 1, 'bytes' => 2097152];
    update_option('casawp_manual_cleanup', $summary_state);
    $summary_message = \casawp\ManualCleanup::statusData()['message'];
    check(strpos($summary_message, 'Cleanup completed with warnings. See the logs for details.') === 0
        && strpos($summary_message, 'unused attachments (12), local import files (35), empty taxonomy terms (6). 3.00 MB freed.') !== false, 'A completed manual cleanup must show deletion counts and combined MB while retaining warnings');
    $summary_state['finished_at'] = time() - 601;
    update_option('casawp_manual_cleanup', $summary_state);
    check(\casawp\ManualCleanup::statusData()['notice_seconds'] === 0, 'An expired manual summary must not reappear on reload');
    update_option('casawp_manual_cleanup', $state);
    foreach ($scheduled as $job) {
        check($job['hook'] === 'casawp_manual_cleanup_batch', 'Standalone cleanup must not queue an import unless a new import was explicitly requested during cleanup');
        check($job['time'] <= time(), 'Follow-up cleanup batches must be immediately eligible, without an artificial scheduler delay');
    }
    $job_count = count($scheduled);
    $manual->runBatch(1, $manual_run);
    check(count($scheduled) === $job_count, 'A stale completed action must not restart cleanup');

    // Missing import folders still allow database/term/log maintenance.
    rename($temporary . '/casawp/import', $temporary . '/casawp/import-kept');
    $started = $manual->start();
    check(!is_wp_error($started), 'Manual cleanup must start without an import directory or XML');
    for ($i=0; $i<200; $i++) {
        $state = get_option('casawp_manual_cleanup');
        if ($state['status'] !== 'queued' && $state['status'] !== 'running') { break; }
        $manual->runBatch($state['next_batch'], $state['run_id']);
    }
    check(get_option('casawp_manual_cleanup')['status'] === 'completed' && !is_dir($temporary . '/casawp/import'), 'Manual maintenance must not create missing import folders');
    rename($temporary . '/casawp/import-kept', $temporary . '/casawp/import');

    // A changed property set must stop the run, rather than sweep using stale assumptions.
    $manual->start();
    update_post_meta(8001, 'exportproperty_id', 'changed-after-start');
    $state = get_option('casawp_manual_cleanup');
    // A failed later batch must preserve its last known phase and partial totals.
    update_option('casawp_manual_file_cleanup', ['run_id' => $state['run_id'], 'logged_phase' => 'terms']);
    update_option('casawp_manual_file_cleanup_totals', ['run_id' => $state['run_id'], 'stats' => ['terms_deleted' => 4, 'bytes' => 123]]);
    $manual->runBatch(1, $state['run_id']);
    check(get_option('casawp_manual_cleanup')['status'] === 'failed' && !get_option('casawp_import_lock'), 'Manual cleanup must stop and release its lock if the stored property set changes');
    check(get_option('casawp_manual_cleanup')['phase'] === 'terms' && get_option('casawp_manual_cleanup')['stats']['terms_deleted'] === 4,
        'Stopped cleanup must retain the actual phase and partial totals');
    $stop_entries = [];
    foreach (glob($temporary . '/casawp/logs/*.log') as $log_file) {
        foreach (file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $entry = json_decode($line, true);
            if (($entry['_meta']['event'] ?? '') === 'manual_cleanup.failed' && ($entry['_meta']['run_id'] ?? 0) === $state['run_id']) { $stop_entries[] = $entry; }
        }
    }
    check(count($stop_entries) === 1 && $stop_entries[0]['_meta']['cleanup_phase'] === 'terms', 'The stop log must identify the current phase rather than defaulting to attachments');
    update_post_meta(8001, 'exportproperty_id', 'active');
    $manual->start();
    $options['casawp_use_casagateway_cdn'] = false;
    $state = get_option('casawp_manual_cleanup');
    $manual->runBatch(1, $state['run_id']);
    check(get_option('casawp_manual_cleanup')['status'] === 'failed', 'A CDN mode change must stop manual cleanup');
    $options['simulate_schedule_failure'] = true;
    check(is_wp_error($manual->start()) && !get_option('casawp_import_lock'), 'Scheduling failure must not leave a stuck cleanup lock');
    delete_option('simulate_schedule_failure');
    $manual->start();
    $options['casawp_import_lock']++;
    $options['casawp_current_run_id'] = 999;
    $other_lock = $options['casawp_import_lock'];
    $state = get_option('casawp_manual_cleanup');
    $manual->runBatch(1, $state['run_id']);
    check($options['casawp_import_lock'] === $other_lock && $options['casawp_current_run_id'] === 999, 'Manual failure must never release another import’s lock or clear its run ID');

    // Required media terms must survive even when unused, including translated counterparts.
    $taxonomies[] = 'casawp_attachment_type';
    foreach (\casawp\ImportMedia::TYPES as $index => $slug) {
        $id = 3000 + $index;
        $wpdb->db->exec("INSERT INTO wp_terms VALUES($id,'$slug')");
        $wpdb->db->exec("INSERT INTO wp_term_taxonomy VALUES($id,$id,'casawp_attachment_type',0)");
    }
    $wpdb->db->exec("INSERT INTO wp_terms VALUES(3300,'obsolete-type'),(3301,'offer-logo-fr')");
    $wpdb->db->exec("INSERT INTO wp_term_taxonomy VALUES(3300,3300,'casawp_attachment_type',0),(3301,3301,'casawp_attachment_type',0)");
    $wpdb->db->exec("INSERT INTO wp_icl_translations VALUES(3003,'tax_casawp_attachment_type',444,'de'),(3301,'tax_casawp_attachment_type',444,'fr')");
    $term_worker = new \casawp\ImportTermCleanup(new \casawp\ImportLogger(static function () { return 111; }), static function () { return true; });
    for ($pass = 0; $pass < 2; $pass++) {
        $after = $deleted = 0;
        $max_id = null;
        do {
            $batch = $term_worker->runBatch($after, 2, 5, $max_id);
            $after = $batch['after'];
            $max_id = $batch['max_id'];
            $deleted += $batch['deleted'];
        } while (!$batch['done']);
        foreach (range(3000, 3004) as $id) {
            check($wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=' . $id), 'Unused canonical attachment types must survive cleanup and remain available for the importer');
        }
        check($wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=3301'), 'An unused WPML counterpart of a required attachment type must survive');
        check(!$wpdb->get_var('SELECT term_id FROM wp_term_taxonomy WHERE term_id=3300'), 'Unused custom attachment types must still be cleaned');
        if ($pass === 1) { check($deleted === 0, 'Repeating cleanup must not keep deleting recreated standard terms'); }
    }

    $usage_worker = new \casawp\ImportLegacyAttachmentCleanup($temporary, new \casawp\ImportLogger(static function () { return 111; }),
        static function () { return false; }, static function () { return []; }, static function () { throw new RuntimeException('This test must not delete files'); });

    // Thousands of candidates and duplicate metadata must not expand or truncate a page.
    $wpdb->db->beginTransaction();
    $insert_post = $wpdb->db->prepare("INSERT INTO wp_posts(ID,post_type,post_parent,guid) VALUES(?,'attachment',0,?)");
    $insert_meta = $wpdb->db->prepare("INSERT INTO wp_postmeta(post_id,meta_key,meta_value) VALUES(?,'_wp_attached_file',?)");
    foreach (range(50001, 51200) as $id) {
        $insert_post->execute([$id, $id % 2 === 0 ? 'https://cdn.casasoft.com/media/provider-1/property-gone/' . $id . '.jpg' : '']);
        if ($id % 2 || $id % 3 === 0) { $insert_meta->execute([$id, 'casawp/import/attachment/externalsync/gone/' . $id . '.jpg']); }
    }
    foreach (range(1, 600) as $_) { $insert_meta->execute([50001, 'casawp/import/attachment/externalsync/gone/50001.jpg']); }
    $wpdb->db->commit();
    $usage_worker->runBatch(50000, 50, [], true, 5);
    check($wpdb->candidate_ids === range(50001, 50050), 'Bounded source queries must produce the first unique ordered page across both sources despite duplicate metadata');
    $usage_worker->runBatch(51180, 50, [], true, 5);
    check($wpdb->candidate_ids === range(51181, 51200), 'The final candidate page must preserve the cursor and include both source types');

    // Recover the production failure: a completed action whose saved next batch is still 47.
    delete_option('casawp_current_run_id');
    delete_option('casawp_import_lock');
    $scheduled = [];
    $manual->start();
    check(!\casawp\ManualCleanup::statusData()['can_resume'] && is_wp_error($manual->resume()), 'Recently started cleanup must not be resumed');
    $stalled = get_option('casawp_manual_cleanup');
    unset($stalled['updated_at']); // The deployed version did not store this field.
    $stalled['status'] = 'running';
    $stalled['next_batch'] = 47;
    $stalled['lock'] = time() - 15000;
    $stalled['stats'] = ['legacy_deleted' => 2157, 'bytes' => 123];
    update_option('casawp_import_lock', $stalled['lock']);
    update_option('casawp_manual_cleanup', $stalled);
    update_option('casawp_manual_file_cleanup_totals', ['run_id' => $stalled['run_id'], 'stats' => $stalled['stats']]);
    update_option('casawp_manual_file_cleanup', ['run_id' => $stalled['run_id'], 'legacy_after' => 50000,
        'logged_phase' => 'attachments', 'feed_hash' => $stalled['properties_hash'], 'cdn' => $stalled['cdn'],
        'stack' => [['dir' => '', 'after' => '']], 'stats' => array_merge(['files' => 0, 'directories' => 0, 'failures' => 0], $stalled['stats'])]);
    try { \casawp\ManualCleanup::ajaxStatus(); }
    catch (CleanupJsonResponse $response) { check($response->payload['data']['can_resume'], 'Status polling must preserve resumability after the four-hour lock expiry'); }
    $worker = ['run_id' => $stalled['run_id'], 'batch' => 46, 'started_at' => time()];
    update_option('casawp_manual_cleanup_worker', $worker);
    check(!\casawp\ManualCleanup::statusData()['can_resume'] && is_wp_error($manual->resume()), 'Recovery must never clear a live worker');
    $manual->runBatch(47, $stalled['run_id']);
    $retry = end($scheduled);
    check($retry['args']['batch_number'] === 47 && $retry['time'] >= time() + 29
        && get_option('casawp_manual_cleanup_worker') === $worker, 'A busy worker must reschedule the consumed batch without clearing the active worker');
    $worker['started_at'] = time() - 601;
    update_option('casawp_manual_cleanup_worker', $worker);
    update_option('casawp_import_lock', $stalled['lock'] + 1);
    check(is_wp_error($manual->resume()), 'Resume must reject another operation\'s lock even if it is old');
    update_option('casawp_import_lock', $stalled['lock']);
    $options['casawp_current_run_id'] = 999;
    check(is_wp_error($manual->resume()), 'Resume must reject an active import');
    delete_option('casawp_current_run_id');
    $checkpoint = get_option('casawp_manual_file_cleanup');
    $on_schedule = static function () { check(!get_option('casawp_manual_cleanup_worker'), 'Resume and continuations must release the worker before scheduling'); };
    $resumed = $manual->resume();
    check(!is_wp_error($resumed) && $resumed['status'] === 'queued' && !$resumed['can_resume'], 'An expired cleanup must resume and immediately disable duplicate resume requests');
    $resumed_state = get_option('casawp_manual_cleanup');
    check($resumed_state['run_id'] === $stalled['run_id'] && $resumed_state['next_batch'] === 47
        && $resumed_state['stats'] === $stalled['stats'] && get_option('casawp_manual_file_cleanup') === $checkpoint,
        'Resume must preserve the run, next batch, totals and deletion cursor');
    check(count(array_filter($scheduled, static function ($job) use ($stalled) {
        return $job['args'] === ['batch_number' => 47, 'run_id' => $stalled['run_id']];
    })) === 1, 'Resume must replace pending copies of the same batch with exactly one action');
    $manual->runBatch(47, $stalled['run_id']);
    check(get_option('casawp_manual_cleanup')['next_batch'] === 48, 'A resumed batch must continue from its saved number');
    unset($on_schedule);

    // Stopping an idle cleanup keeps partial totals and never touches unrelated jobs.
    $scheduled[] = ['time' => time(), 'hook' => 'other_plugin', 'args' => [], 'group' => 'other'];
    update_option('casawp_import_pending', 1);
    $stopped = $manual->stop();
    check($stopped['status'] === 'stopped' && !$stopped['can_resume'] && !$stopped['can_stop']
        && !get_option('casawp_import_lock') && !get_option('casawp_manual_cleanup_worker'), 'Stop must finish an idle cleanup and release its locks');
    check(get_option('casawp_manual_cleanup')['stats']['legacy_deleted'] >= 2157, 'Stop must retain the actual partial deletion totals');
    check(count(array_filter($scheduled, static function ($job) { return $job['hook'] === 'other_plugin'; })) === 1
        && count(array_filter($scheduled, static function ($job) { return $job['hook'] === 'casawp_process_gateway_poke'; })) === 1
        && !array_filter($scheduled, static function ($job) { return $job['hook'] === 'casawp_manual_cleanup_batch'; })
        && !casawp_has_pending(), 'Stop must preserve unrelated jobs and resume a deferred import');
    $job_count = count($scheduled);
    $manual->runBatch(48, $stalled['run_id']);
    check(count($scheduled) === $job_count, 'Old scheduled actions must not restart a stopped cleanup');

    // Stop arriving inside a deletion batch is observed before the next deletion.
    $scheduled = [];
    $manual->start();
    $state = get_option('casawp_manual_cleanup');
    check(!\casawp\ManualCleanup::stopRequested($state['run_id']), 'Starting a new cleanup must clear the previous stop request');
    $requested_stop = false;
    $stop_during_batch = static function ($reason) use ($manual, &$requested_stop) {
        if (!$requested_stop) {
            $requested_stop = true;
            $result = $manual->stop();
            check($result['status'] === 'stopping' && get_option('casawp_import_lock'), 'Stopping a live worker must retain the import lock until that worker exits');
        }
        return $reason;
    };
    add_filter('casawp_legacy_attachment_usage', $stop_during_batch);
    $manual->runBatch(1, $state['run_id']);
    remove_filter('casawp_legacy_attachment_usage', $stop_during_batch);
    check($requested_stop && get_option('casawp_manual_cleanup')['status'] === 'stopped'
        && !get_option('casawp_import_lock') && !get_option('casawp_manual_cleanup_worker') && !$scheduled,
        'A stop during a batch must finish without scheduling a continuation');

    $manual->start();
    $state = get_option('casawp_manual_cleanup');
    update_option('casawp_manual_cleanup_worker', ['run_id' => $state['run_id'], 'batch' => 1, 'started_at' => time()]);
    $manual->stop();
    update_option('casawp_manual_cleanup_worker', ['run_id' => $state['run_id'], 'batch' => 1, 'started_at' => time() - 601]);
    try { \casawp\ManualCleanup::ajaxStatus(); }
    catch (CleanupJsonResponse $response) { check($response->payload['data']['status'] === 'stopped', 'Polling must finish a stop request when a dead worker expires'); }

    $can_manage = false;
    foreach (['ajaxResume', 'ajaxStop'] as $endpoint) {
        try { \casawp\ManualCleanup::$endpoint(); check(false, 'Recovery controls must require administrator privileges'); }
        catch (CleanupJsonResponse $response) { check($response->getCode() === 403, 'Recovery controls must reject unauthorized users'); }
    }
    $can_manage = true;
    $valid_nonce = false;
    foreach (['ajaxResume', 'ajaxStop'] as $endpoint) {
        try { \casawp\ManualCleanup::$endpoint(); check(false, 'Recovery controls must require a valid nonce'); }
        catch (CleanupJsonResponse $response) { check($response->getCode() === 403, 'Recovery controls must verify their nonce'); }
    }
    $valid_nonce = true;
    $_POST = ['run_id' => get_option('casawp_manual_cleanup')['run_id'] - 1];
    foreach (['ajaxResume', 'ajaxStop'] as $endpoint) {
        try { \casawp\ManualCleanup::$endpoint(); check(false, 'An old settings tab must not control a different cleanup'); }
        catch (CleanupJsonResponse $response) { check($response->getCode() === 409, 'Recovery controls must reject a stale run ID'); }
    }

    $manual->start();
    $state = get_option('casawp_manual_cleanup');
    $state['updated_at'] = time() - 601;
    update_option('casawp_manual_cleanup', $state);
    delete_option('casawp_import_lock');
    check(!is_wp_error($manual->resume()) && get_option('casawp_import_lock'), 'Resume must safely reacquire a missing lock');
    $manual->stop();
    foreach (['cdn', 'properties', 'scheduler'] as $failure) {
        $manual->start();
        $state = get_option('casawp_manual_cleanup');
        $state['updated_at'] = time() - 601;
        update_option('casawp_manual_cleanup', $state);
        if ($failure === 'cdn') { $options['casawp_use_casagateway_cdn'] = !$state['cdn']; }
        if ($failure === 'properties') { update_post_meta(8001, 'exportproperty_id', 'changed-before-resume'); }
        if ($failure === 'scheduler') { $options['simulate_schedule_failure'] = true; }
        check(is_wp_error($manual->resume()) && get_option('casawp_manual_cleanup')['status'] === 'failed'
            && !get_option('casawp_import_lock') && !get_option('casawp_manual_cleanup_worker'),
            'Unsafe or failed recovery must stop cleanly: ' . $failure);
        $options['casawp_use_casagateway_cdn'] = $state['cdn'];
        update_post_meta(8001, 'exportproperty_id', 'active');
        delete_option('simulate_schedule_failure');
    }
    $manual->start();
    $options['casawp_current_run_id'] = 999;
    $options['casawp_import_lock']++;
    $other_lock = $options['casawp_import_lock'];
    $manual->stop();
    check(get_option('casawp_import_lock') === $other_lock && get_option('casawp_current_run_id') === 999,
        'Stopping an old cleanup must never release another import\'s lock');
    echo "Legacy attachment cleanup checks passed.\n";
} finally { removeTree($temporary); }
