<?php
/** Settings save/render integration checks without WordPress or a database.
 * Run: php tests/settings-options.php
 * WordPress helpers are stubs; this exercises the real options.php template and
 * save routing, not WordPress sanitization or external services.
 */
define('ABSPATH', dirname(__DIR__) . '/');
$temporary = sys_get_temp_dir() . '/casawp-settings-' . uniqid();
mkdir($temporary, 0700);
define('CASASYNC_CUR_UPLOAD_BASEDIR', $temporary);
define('CASASYNC_CUR_UPLOAD_BASEURL', 'https://example.test/uploads');
require ABSPATH . 'classes/ImportProgress.php';
require ABSPATH . 'classes/ManualCleanup.php';
$stored_options = [];

function get_option($key, $default = false) { return $GLOBALS['stored_options'][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['stored_options'][$key] = $value; }
function __($value, $domain = '') { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_html__($value, $domain = '') { return esc_html($value); }
function esc_attr__($value, $domain = '') { return esc_attr($value); }
function esc_html_e($value, $domain = '') { echo esc_html($value); }
function esc_url($value) { return esc_attr($value); }
function esc_js($value) { return addslashes($value); }
function sanitize_text_field($value) { return (string) $value; }
function wp_kses($value, $allowed) { return $value; }
function wp_kses_post($value) { return $value; }
function wp_json_encode($value) { return json_encode($value); }
function checked($value, $expected = 1) { if ($value == $expected) { echo 'checked="checked"'; } }
function wp_create_nonce($action) { return 'settings-test-nonce'; }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function get_admin_url($id = null, $path = '') { return admin_url($path); }
function home_url($path = '', $scheme = null) { return 'https://example.test' . $path; }
function is_ssl() { return true; }
function set_url_scheme($url, $scheme) { return $url; }
function plugins_url($path, $file = '') { return 'https://example.test/plugins/casawp/' . $path; }
function add_query_arg($args, $value = null, $url = null) {
    if (!is_array($args)) { $args = [$args => $value]; } else { $url = $value; }
    return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args);
}
function wp_enqueue_script(...$args) {}
function wp_localize_script(...$args) {}
function get_categories($args) { return [(object) ['slug' => 'custom_test', 'term_id' => 12]]; }
function get_locale() { return 'en_US'; }
function icl_get_home_url() { return home_url('/'); }
function icl_get_languages($args) { return ['de' => ['translated_name' => 'Deutsch'], 'en' => ['translated_name' => 'English']]; }
function number_format_i18n($number, $decimals = 0) { return number_format($number, $decimals); }
function casawp_is_import_running() { return false; }
function casawp_has_pending() { return false; }
function get_date_from_gmt($date, $format) { return (new DateTimeImmutable('2026-10-20'))->format($format); }
function wp_dropdown_pages($args) {
    echo '<select name="' . esc_attr($args['name']) . '">';
    foreach (['0' => 'Select', '12' => 'Login', '34' => 'Properties'] as $value => $label) {
        echo '<option value="' . $value . '"' . ($value == $args['selected'] ? ' selected' : '') . '>' . $label . '</option>';
    }
    echo '</select>';
}
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function renderSettings($tab, $post = []) {
    $_GET = ['tab' => $tab];
    $_POST = $post;
    ob_start();
    try { include ABSPATH . 'options.php'; } finally { $html = ob_get_clean(); }
    // libxml's HTML4 parser treats tags inside textarea as markup. Normalize
    // RCDATA to the text/value that an HTML5 browser actually exposes.
    $textarea = <<<'REGEX'
~(<textarea\b(?:[^>"']|"[^"]*"|'[^']*')*>)(.*?)(</textarea>)~is
REGEX;
    $html = preg_replace_callback($textarea, static function ($match) {
        return $match[1] . htmlspecialchars(html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'), ENT_NOQUOTES, 'UTF-8') . $match[3];
    }, $html);
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}
function fields($document) {
    return $document->query('//input[@name] | //select[@name] | //textarea[@name]');
}
function fieldValue($field) {
    if ($field->tagName === 'textarea') { return $field->textContent; }
    if ($field->tagName === 'select') {
        $choices = $field->getElementsByTagName('option');
        foreach ($choices as $choice) { if ($choice->hasAttribute('selected')) { return $choice->getAttribute('value'); } }
        return $choices->item(0)->getAttribute('value');
    }
    return $field->getAttribute('value');
}
function fieldByName($document, $name) {
    return $document->query('//*[@name="' . $name . '"]')->item(0);
}
function submission($document, $checkboxes = true) {
    $post = [];
    foreach (fields($document) as $field) {
        $name = $field->getAttribute('name');
        $type = $field->getAttribute('type');
        if ($type === 'checkbox' && !$checkboxes) { continue; }
        if ($type === 'radio' && isset($post[$name])) { continue; }
        if ($type === 'submit') { $post[$name] = 'Save changes'; }
        elseif (in_array($type, ['checkbox', 'radio', 'hidden'], true) || $field->tagName === 'select') { $post[$name] = fieldValue($field); }
        elseif ($field->tagName === 'textarea') { $post[$name] = '<div id="settings-test">'; }
        elseif ($type === 'number') { $post[$name] = '3'; }
        else { $post[$name] = 'test-' . $name; }
    }
    return $post;
}

try {
    $tabs = ['general', 'archiveview', 'singleview', 'private', 'contactform', 'appearance', 'logs'];
    $schemas = [];
    foreach ($tabs as $tab) { $schemas[$tab] = renderSettings($tab); }
    foreach (['casawp-resume-cleanup-button', 'casawp-stop-cleanup-button'] as $id) {
        check($schemas['general']->query('//button[@id="' . $id . '" and @type="button"]')->length === 1,
            'General settings must render recovery controls without submitting the settings form');
    }
    $total = $choicesTested = 0;
    foreach ($tabs as $tab) {
        $stored_options = ['unrelated_plugin_setting' => 'untouched', 'casawp_request_per_remcat' => '1', 'casawp_request_per_mail_fallback' => '1'];
        $schema = $schemas[$tab];
        $owned = [];
        $checkboxes = [];
        foreach (fields($schema) as $field) {
            $name = $field->getAttribute('name');
            check($schema->query('ancestor::form[@id="options_form"]', $field)->length === 1, "$tab: $name is outside the settings form");
            $owned[$name] = true;
            if ($field->getAttribute('type') === 'checkbox') { $checkboxes[] = $name; }
        }
        foreach ($schemas as $otherTab => $otherSchema) {
            if ($otherTab === $tab) { continue; }
            foreach (fields($otherSchema) as $field) {
                $name = $field->getAttribute('name');
                if (!isset($owned[$name]) && strpos($name, 'casawp_') === 0) { $stored_options[$name] = 'other-tab-value'; }
            }
        }
        $before = $stored_options;
        $post = submission($schema);
        $saved = renderSettings($tab, $post);
        check($saved->query('//div[contains(concat(" ", normalize-space(@class), " "), " casawp-notice ")]')->length === 1, "$tab: the settings confirmation must remain visible as a CASAWP notice");
        foreach ($post as $name => $value) {
            if (strpos($name, 'custom_') === 0) { continue; }
            check(get_option($name) === $value, "$tab: failed to save $name");
            $field = fieldByName($saved, $name);
            if ($field->getAttribute('type') === 'radio') {
                $selected = $saved->query('//input[@name="' . $name . '" and @checked]')->item(0);
                check($selected && fieldValue($selected) === $value, "$tab: radio selection did not reload for $name");
            } else { check(fieldValue($field) === $value, "$tab: value did not reload for $name"); }
        }
        foreach ($checkboxes as $name) {
            check(fieldByName($saved, $name)->hasAttribute('checked'), "$tab: checked state did not reload for $name");
        }
        foreach ($before as $name => $value) {
            if (!isset($owned[$name])) { check(get_option($name) === $value, "$tab overwrote another tab's $name"); }
        }
        $unchecked = submission($schema, false);
        $saved = renderSettings($tab, $unchecked);
        foreach ($checkboxes as $name) {
            if (strpos($name, 'custom_') === 0) {
                check(get_option('casawp_custom_category_translations')['custom_test']['show'] === '0', 'Custom category cannot be hidden');
            } else {
                check(get_option($name) === '0', "$tab: unchecked $name was not cleared");
                check(!fieldByName($saved, $name)->hasAttribute('checked'), "$tab: unchecked state did not reload for $name");
            }
        }
        foreach (fields($schema) as $field) {
            $name = $field->getAttribute('name');
            $values = [];
            if ($field->tagName === 'select') {
                foreach ($field->getElementsByTagName('option') as $choice) { $values[] = $choice->getAttribute('value'); }
            } elseif ($field->getAttribute('type') === 'radio') { $values[] = fieldValue($field); }
            foreach ($values as $value) {
                $saved = renderSettings($tab, array_merge($unchecked, [$name => $value]));
                check(get_option($name) === $value, "$tab: failed to save $name=$value");
                if ($field->tagName === 'select') { check(fieldValue(fieldByName($saved, $name)) === $value, "$tab: dropdown did not reload $name=$value"); }
                else {
                    $selected = $saved->query('//input[@name="' . $name . '" and @checked]')->item(0);
                    check($selected && fieldValue($selected) === $value, "$tab: radio did not reload $name=$value");
                }
                $choicesTested++;
            }
        }
        if ($tab === 'general') {
            check(get_option('casawp_custom_category_translations') === ['custom_test' => ['de' => 'test-custom_test/de', 'en' => 'test-custom_test/en', 'show' => '0']], 'Custom category translations did not survive saving');
            $stored_options['casawp_enable_single_request_import'] = '1';
            $single = renderSettings('general');
            check($single->query('//*[@id="casawp-single-import-button"]')->length === 1, 'Single-request import link is missing');
            $importUrl = $single->query('//*[@id="casawp-single-import-button"]')->item(0)->getAttribute('href');
            check(strpos($importUrl, 'casawp_run_single_import=1') !== false && strpos($importUrl, '_wpnonce=') !== false, 'Single-request import link lost its action or nonce');
            check($single->query('//*[@id="casawp-import-button"]')->length === 0, 'Single-request mode also rendered the batch action');
            $stored_options['casawp_enable_single_request_import'] = '0';
            check(renderSettings('general')->query('//*[@id="casawp-import-button"]')->length === 1, 'Batch import button is missing');
        }
        $count = fields($schema)->length;
        $total += $count;
        echo "$tab: $count controls, save/reload, checkbox reset and tab isolation passed.\n";
    }
    $stored_options = [];
    $archive = renderSettings('archiveview');
    $post = submission($archive);
    $order = [];
    foreach ($archive->query('//*[@id="draggableList"]/div') as $item) { $order[] = $item->getAttribute('id'); }
    $order = array_reverse($order);
    foreach ($order as $index => $name) { $post[$name . '_order'] = (string) $index; }
    $reordered = renderSettings('archiveview', $post);
    $actual = [];
    foreach ($reordered->query('//*[@id="draggableList"]/div') as $item) { $actual[] = $item->getAttribute('id'); }
    check($actual === $order, 'Dynamic field order did not survive save/reload');
    check(renderSettings('logs')->query('//a[contains(@href, "/uploads/casawp/")]')->length === 0, 'Missing logs or XML must not have download links');
    mkdir($temporary . '/casawp/logs', 0700, true);
    mkdir($temporary . '/casawp/import', 0700);
    file_put_contents($temporary . '/casawp/logs/202610.log', 'Sample log');
    file_put_contents($temporary . '/casawp/import/data.xml', '<sample/>');
    $logs = renderSettings('logs');
    check($logs->query('//a[@href="https://example.test/uploads/casawp/logs/202610.log"]')->length === 1, 'Existing log download link is missing');
    check($logs->query('//a[@href="https://example.test/uploads/casawp/import/data.xml"]')->length === 1, 'Existing import XML download link is missing');
    echo "$total controls and $choicesTested dropdown/radio choices passed; custom categories, import modes and field ordering passed.\n";
    echo "Log and XML download availability checks passed.\n";
} finally {
    foreach (['/casawp/logs/202610.log', '/casawp/import/data.xml'] as $file) {
        if (is_file($temporary . $file)) { unlink($temporary . $file); }
    }
    foreach (['/casawp/logs', '/casawp/import', '/casawp', ''] as $directory) {
        if (is_dir($temporary . $directory)) { rmdir($temporary . $directory); }
    }
}
