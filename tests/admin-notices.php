<?php
/** Test with the real WordPress hook dispatcher, without loading a site/database.
 * php tests/admin-notices.php /path/to/wordpress
 */
$wpSource = $argv[1] ?? '';
if (!is_file($wpSource . '/wp-includes/plugin.php')) {
    fwrite(STDERR, "Usage: php tests/admin-notices.php /path/to/wordpress\n");
    exit(1);
}
$root = dirname(__DIR__);
$temporary = sys_get_temp_dir() . '/casawp-notices-' . uniqid();
mkdir($temporary . '/wordpress/wp-admin', 0700, true);
define('ABSPATH', $temporary . '/wordpress/');
define('WPINC', 'wp-includes');
define('CASASYNC_PLUGIN_DIR', $root . '/');
define('CASASYNC_PLUGIN_URL', 'https://example.test/plugins/casawp/');
require $wpSource . '/wp-includes/plugin.php';
require $root . '/classes/Admin.php';
$screen = (object) ['id' => 'toplevel_page_casawp'];
$enqueued = [];
function get_current_screen() { return $GLOBALS['screen']; }
function wp_register_style(...$args) {}
function wp_enqueue_style(...$args) {}
function wp_enqueue_script($handle, ...$args) { $GLOBALS['enqueued'][] = $handle; }
function casawp_test_own_notice() { echo '<div class="notice casawp-notice">CASAWP-TEST</div>'; }
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function outputOf($hook) { ob_start(); try { do_action($hook); } finally { $output = ob_get_clean(); } return $output; }

file_put_contents($temporary . '/wordpress/wp-admin/core-fixture.php', '<?php function casawp_test_core_notice() { echo "CORE-TEST"; }');
require $temporary . '/wordpress/wp-admin/core-fixture.php';
file_put_contents($temporary . '/plugin-fixture.php', <<<'FIXTURE'
<?php
function casawp_test_external_notice() { echo 'EXTERNAL-FUNCTION'; }
class CasawpTestExternalPlugin {
    public function admin_notice() { echo '<div class="updated" style="display:block !important">IWP-TEST</div>'; }
    public static function notice() { echo 'EXTERNAL-STATIC'; }
    public function __invoke() { echo 'EXTERNAL-INVOKABLE'; }
}
return function () { echo 'EXTERNAL-CLOSURE'; };
FIXTURE
);
$externalClosure = require $temporary . '/plugin-fixture.php';
$externalPlugin = new CasawpTestExternalPlugin();
$callbacks = ['casawp_test_external_notice', [$externalPlugin, 'admin_notice'], ['CasawpTestExternalPlugin', 'notice'], 'CasawpTestExternalPlugin::notice', $externalPlugin, $externalClosure];
$hooks = ['admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices'];

try {
    foreach ($hooks as $hook) {
        foreach (['toplevel_page_casawp', 'dashboard', 'toplevel_page_other_plugin'] as $screenId) {
            $GLOBALS['wp_filter'] = [];
            $screen = (object) ['id' => $screenId];
            $admin = new \casawp\Admin();
            add_action($hook, 'casawp_test_own_notice', 5);
            add_action($hook, 'casawp_test_core_notice', 20);
            foreach ($callbacks as $callback) { add_action($hook, $callback, 10); }
            $output = outputOf($hook);
            check(strpos($output, 'CASAWP-TEST') !== false, "$hook removed a CASAWP callback");
            check(strpos($output, 'CORE-TEST') !== false, "$hook removed a core callback");
            foreach ($callbacks as $callback) {
                check((has_action($hook, $callback) !== false) === ($screenId !== 'toplevel_page_casawp'), "$hook changed a callback on the wrong screen or failed to suppress it");
            }
            check((strpos($output, 'IWP-TEST') === false) === ($screenId === 'toplevel_page_casawp'), "$hook did not suppress the inline-important IWP banner correctly");
            $enqueued = [];
            $admin->registerAdminScriptsAndStyles();
            check(in_array('casawp-settings-notices', $enqueued, true) === ($screenId === 'toplevel_page_casawp'), 'Notice observer was enqueued on the wrong screen');
        }
        echo "$hook: external callback types blocked on CASAWP; own/core callbacks and other screens preserved.\n";
    }
    $GLOBALS['wp_filter'] = [];
    $screen = null;
    new \casawp\Admin();
    add_action('admin_notices', [$externalPlugin, 'admin_notice']);
    check(strpos(outputOf('admin_notices'), 'IWP-TEST') !== false, 'A missing screen must not suppress unrelated notices');
    echo "Inline !important banner, hook iteration, page scoping and script scoping checks passed.\n";
} finally {
    unlink($temporary . '/plugin-fixture.php');
    unlink($temporary . '/wordpress/wp-admin/core-fixture.php');
    rmdir($temporary . '/wordpress/wp-admin');
    rmdir($temporary . '/wordpress');
    rmdir($temporary);
}
