<?php

namespace casawp;

/** Selected listing details, using the same metadata formats as source imports. */
class PropertyMeta
{
    private const KEYS = ['visualReferenceId', 'the_urls', 'extraPrice', 'integratedoffers'];
    private const LIMIT = 50;
    private static $offer_types;
    private static ?array $pending = null;

    public static function register(?callable $offer_types = null): void
    {
        self::$offer_types = $offer_types;
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('admin_action_editpost', [self::class, 'preparePostSave'], 1);
        add_action('pre_post_update', [self::class, 'preparePostSave'], 1);
        add_action('wp_after_insert_post', [self::class, 'finishPostSave'], 100);
    }

    public static function enqueue(): void
    {
        $screen = get_current_screen();
        global $post;
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'casawp_property'
            || !$post || !current_user_can('edit_post', $post->ID)) { return; }
        wp_enqueue_style('casawp-property-meta', CASASYNC_PLUGIN_URL . 'plugin-assets/global/css/property-meta.css', [],
            filemtime(CASASYNC_PLUGIN_DIR . 'plugin-assets/global/css/property-meta.css'));
        wp_enqueue_script('casawp-property-meta', CASASYNC_PLUGIN_URL . 'plugin-assets/js/property-meta.js', ['jquery', 'wp-api-fetch'],
            filemtime(CASASYNC_PLUGIN_DIR . 'plugin-assets/js/property-meta.js'), true);
        wp_localize_script('casawp-property-meta', 'casawpPropertyMeta', [
            'error' => __('Could not save additional details. Please try again.', 'casawp'),
            'unsaved' => __('You have unsaved additional details.', 'casawp'),
            'maxEntries' => self::LIMIT,
            'limitMessage' => __('Use no more than 50 entries per section.', 'casawp'),
        ]);
    }

    private static function values(int $id): array
    {
        $values = [];
        foreach (self::KEYS as $key) { $values[$key] = get_post_meta($id, $key, true); }
        return $values;
    }

    private static function revision(int $id): string
    {
        return hash('sha256', serialize(self::values($id)));
    }

    private static function offerTypes(): array
    {
        return self::$offer_types ? (self::$offer_types)() : [];
    }

    /** Flatten groups for editing, without changing the imported representation. */
    private static function links($stored): ?array
    {
        if ($stored === '' || $stored === false || $stored === []) { return []; }
        if (!is_array($stored)) { return null; }
        $rows = [];
        foreach ($stored as $type => $group) {
            if (!is_array($group)) { return null; }
            if (array_key_exists('href', $group)) {
                $group = [$group];
                $type = '';
            }
            foreach ($group as $row) {
                if (!is_array($row) || !isset($row['href']) || !is_scalar($row['href'])) { return null; }
                foreach (['label', 'title'] as $key) {
                    if (isset($row[$key]) && !is_scalar($row[$key])) { return null; }
                }
                $rows[] = ['type' => (string) $type, 'data' => $row];
            }
        }
        return count($rows) <= self::LIMIT ? $rows : null;
    }

    private static function costs($stored, bool $offers = false): ?array
    {
        if ($stored === '' || $stored === false || $stored === []) { return []; }
        if (!is_array($stored) || count($stored) > self::LIMIT) { return null; }
        foreach ($stored as $row) {
            if (!is_array($row)) { return null; }
            $fields = ['price', 'currency', 'timesegment', 'propertysegment', 'frequency'];
            if ($offers) { $fields = array_merge($fields, ['type', 'inclusive']); }
            foreach ($fields as $key) {
                if (isset($row[$key]) && !is_scalar($row[$key])) { return null; }
            }
        }
        return array_values($stored);
    }

    public static function render($post, array $numeric_keys = [], array $readable = []): void
    {
        $id = (int) $post->ID;
        echo '<div class="casawp-property-meta" data-post-id="' . $id . '" data-nonce="'
            . esc_attr(wp_create_nonce('casawp_property_meta_' . $id)) . '"><div class="casawp-meta-editors">';
        self::renderEditors($id);
        echo '</div>';
        $labels = self::labels();
        echo '<section class="casawp-meta-section"><h3>' . esc_html__('Further property details', 'casawp') . '</h3>';
        echo '<p class="description">' . esc_html__('Heating is shown for reference.', 'casawp') . '</p>';
        self::table(['heating' => $readable['heating'] ?? get_post_meta($id, 'heating', true)], $labels);
        echo '</section><details class="casawp-meta-details"><summary>' . esc_html__('Import information', 'casawp') . '</summary>';
        echo '<p class="description">' . esc_html__('These values are calculated or managed by imports. They cannot be edited here.', 'casawp') . '</p>';
        $technical = [];
        foreach (['areaForOrder', 'priceForOrder', 'content_source_lang', 'exportproperty_id', 'last_import_hash',
            'casawp_media_sync_version', 'last_processed_run', 'is_active'] as $key) {
            if (metadata_exists('post', $id, $key)) { $technical[$key] = get_post_meta($id, $key, true); }
        }
        self::table($technical, $labels, true);
        echo '</details>';
        $skip = array_merge(self::KEYS, array_keys($technical), $numeric_keys, ['heating', 'integratedoffers',
            'netPrice', 'netPrice_propertysegment', 'netPrice_timesegment', 'grossPrice', 'grossPrice_propertysegment',
            'grossPrice_timesegment', 'price_currency', 'price_propertysegment', 'start', 'casawp_id', 'referenceId', 'availability',
            'property_address_streetaddress', 'property_address_streetnumber', 'property_address_streetaddition',
            'property_address_postalcode', 'property_address_locality', 'property_address_region', 'property_address_country',
            'property_geo_latitude', 'property_geo_longitude']);
        if (function_exists('acf_add_local_field_group')) { $skip[] = 'price'; }
        $other = [];
        foreach (get_post_meta($id) as $key => $values) {
            if (in_array($key, $skip, true) || strpos($key, '_') === 0
                || strpos($key, 'seller_inquiry_person') === 0 || strpos($key, 'seller_view_person') === 0
                || strpos($key, 'seller_visit_person') === 0 || strpos($key, 'seller_org') === 0) { continue; }
            $other[$key] = count($values) === 1 ? maybe_unserialize($values[0]) : array_map('maybe_unserialize', $values);
        }
        if ($other) {
            echo '<details class="casawp-meta-details"><summary>' . esc_html__('Other stored details', 'casawp') . '</summary>';
            self::table($other, $labels, true);
            echo '</details>';
        }
        echo '</div>';
    }

    private static function renderEditors(int $id): void
    {
        $values = self::values($id);
        echo '<p class="casawp-meta-intro">' . esc_html__('Use these fields for manually managed properties. The next source import replaces these changes.', 'casawp') . '</p>';
        echo '<p class="casawp-meta-help">' . esc_html__('These details are saved when you update or publish the property.', 'casawp') . '</p>';
        wp_nonce_field('casawp_property_meta_' . $id, 'casawp_meta_nonce', false);
        echo '<input type="hidden" name="casawp_meta_revision" value="' . esc_attr(self::revision($id)) . '">';
        echo '<section class="casawp-meta-section casawp-meta-reference">';
        self::input('casawp_meta[visualReferenceId]', __('Display reference', 'casawp'), $values['visualReferenceId']);
        echo '<p class="description">' . esc_html__('Shown to visitors and included in property inquiries.', 'casawp') . '</p></section>';
        self::repeater('links', __('Links', 'casawp'), self::links($values['the_urls']), $values['the_urls'], $id);
        self::repeater('costs', __('Additional costs', 'casawp'), self::costs($values['extraPrice']), $values['extraPrice'], $id);
        self::repeater('offers', __('Additional offers', 'casawp'), self::costs($values['integratedoffers'], true), $values['integratedoffers'], $id);
        echo '<input type="hidden" name="casawp_meta[complete]" value="1">';
        echo '<p class="casawp-meta-status" role="status" aria-live="polite"></p>';
    }

    private static function repeater(string $kind, string $label, ?array $rows, $stored, int $id): void
    {
        echo '<section class="casawp-meta-section casawp-meta-repeater" data-kind="' . esc_attr($kind) . '"><h3>' . esc_html($label) . '</h3>';
        if ($rows === null) {
            echo '<p class="description">' . esc_html__('This imported format is shown read-only to preserve its data.', 'casawp') . '</p>';
            self::pretty($stored);
        } else {
            echo '<div class="casawp-meta-entries">';
            foreach ($rows as $index => $row) { self::entry($kind, (string) $index, $row, (string) $index); }
            echo '</div><template>';
            self::entry($kind, '__INDEX__', $kind === 'links' ? ['type' => 'website', 'data' => []]
                : ['currency' => get_post_meta($id, 'price_currency', true) ?: 'CHF',
                    'type' => array_key_first(self::offerTypes()) ?? '', 'inclusive' => '0'], '');
            echo '</template><button type="button" class="button casawp-meta-add" disabled data-casawp-meta-enable="1">'
                . esc_html($kind === 'links' ? __('Add link', 'casawp') : ($kind === 'offers'
                    ? __('Add additional offer', 'casawp') : __('Add additional cost', 'casawp'))) . '</button>';
        }
        echo '</section>';
    }

    private static function entry(string $kind, string $index, array $row, string $source): void
    {
        $name = 'casawp_meta[' . $kind . '][' . $index . ']';
        echo '<div class="casawp-meta-entry" data-index="' . esc_attr($index) . '"><input type="hidden" name="' . esc_attr($name . '[_row]') . '" value="' . esc_attr($source) . '">';
        echo '<div class="casawp-meta-grid' . ($kind !== 'links' ? ' casawp-meta-grid-costs' : '') . '">';
        if ($kind === 'links') {
            self::select($name . '[type]', __('Link type', 'casawp'), $row['type'], [
                'website' => __('Website', 'casawp'), 'youtube' => __('YouTube video', 'casawp'),
                'virtual-tour' => __('Virtual tour', 'casawp'), '' => __('Unspecified', 'casawp')]);
            self::input($name . '[href]', __('URL', 'casawp'), $row['data']['href'] ?? '', 'url');
            self::input($name . '[label]', __('Link label', 'casawp'), $row['data']['label'] ?? '');
            self::input($name . '[title]', __('Link title', 'casawp'), $row['data']['title'] ?? '');
        } else {
            if ($kind === 'offers') {
                self::select($name . '[type]', __('Offer type', 'casawp'), $row['type'] ?? '', self::offerTypes());
                self::select($name . '[inclusive]', __('Included in the price', 'casawp'), empty($row['inclusive']) ? '0' : '1',
                    ['0' => __('No', 'casawp'), '1' => __('Yes', 'casawp')]);
            }
            self::input($name . '[price]', $kind === 'offers' ? __('Amount (optional)', 'casawp') : __('Amount', 'casawp'), $row['price'] ?? '', 'decimal');
            self::input($name . '[currency]', __('Currency', 'casawp'), $row['currency'] ?? '');
            self::select($name . '[timesegment]', __('Billing period', 'casawp'), $row['timesegment'] ?? '', [
                '' => __('Unspecified', 'casawp'), 'infinite' => __('One-time', 'casawp'), 'h' => __('Hourly', 'casawp'),
                'd' => __('Daily', 'casawp'), 'w' => __('Weekly', 'casawp'), 'm' => __('Monthly', 'casawp'), 'y' => __('Yearly', 'casawp')]);
            self::select($name . '[propertysegment]', __('Applies to', 'casawp'), $row['propertysegment'] ?? '', [
                '' => __('Unspecified', 'casawp'), 'all' => __('Whole property', 'casawp'), 'm' => __('Per m²', 'casawp')]);
            // Frequency is source-specific; editing another detail must not reinterpret it.
            echo '<input type="hidden" name="' . esc_attr($name . '[frequency]') . '" value="' . esc_attr($row['frequency'] ?? '') . '">';
        }
        echo '</div><div class="casawp-meta-entry-actions"><button type="button" class="button casawp-meta-remove" disabled data-casawp-meta-enable="1">'
            . esc_html($kind === 'links' ? __('Remove link', 'casawp') : ($kind === 'offers'
                ? __('Remove additional offer', 'casawp') : __('Remove additional cost', 'casawp'))) . '</button></div></div>';
    }

    private static function input(string $name, string $label, $value, string $mode = 'text'): void
    {
        echo '<div class="casawp-meta-field"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label>'
            . '<input type="text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value)
            . '" inputmode="' . esc_attr($mode) . '"></div>';
    }

    private static function select(string $name, string $label, $value, array $choices): void
    {
        $value = (string) $value;
        if (!array_key_exists($value, $choices)) { $choices[$value] = $value; }
        echo '<div class="casawp-meta-field"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label><select id="'
            . esc_attr($name) . '" name="' . esc_attr($name) . '">';
        foreach ($choices as $key => $text) { echo '<option value="' . esc_attr($key) . '"' . selected($value, (string) $key, false) . '>' . esc_html($text) . '</option>'; }
        echo '</select></div>';
    }

    private static function labels(): array
    {
        return ['heating' => __('Heating', 'casawp'), 'integratedoffers' => __('Additional offers', 'casawp'),
            'areaForOrder' => __('Area used for filtering', 'casawp'), 'priceForOrder' => __('Price used for filtering', 'casawp'),
            'content_source_lang' => __('Source language', 'casawp'), 'exportproperty_id' => __('Import property ID', 'casawp'),
            'last_import_hash' => __('Import fingerprint', 'casawp'), 'casawp_media_sync_version' => __('Media synchronization version', 'casawp'),
            'last_processed_run' => __('Last import run', 'casawp'), 'is_active' => __('Processed by import', 'casawp'),
            'generation' => __('Heat source', 'casawp'), 'distribution' => __('Heat distribution', 'casawp'),
            'type' => __('Type', 'casawp'), 'price' => __('Amount', 'casawp'), 'currency' => __('Currency', 'casawp'),
            'timesegment' => __('Billing period', 'casawp'), 'propertysegment' => __('Applies to', 'casawp'),
            'frequency' => __('Frequency', 'casawp'), 'inclusive' => __('Included', 'casawp'),
            'price_range_from' => __('Price range from', 'casawp'), 'price_range_to' => __('Price range to', 'casawp'),
            'zoneTypes' => __('Zones', 'casawp'), 'keywords_string' => __('Keywords', 'casawp')];
    }

    private static function table(array $values, array $labels, bool $keys = false): void
    {
        if (!$values) { echo '<p>' . esc_html__('Not specified', 'casawp') . '</p>'; return; }
        echo '<table class="widefat striped casawp-meta-table"><tbody>';
        foreach ($values as $key => $value) {
            echo '<tr><th scope="row">' . esc_html($labels[$key] ?? $key);
            if ($keys && isset($labels[$key])) { echo '<br><code>' . esc_html($key) . '</code>'; }
            echo '</th><td>'; self::pretty($value, 0, $key); echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function pretty($value, int $depth = 0, string $field = ''): void
    {
        if ($value === '' || $value === null || $value === []) { echo esc_html__('Not specified', 'casawp'); return; }
        if (in_array($field, ['inclusive', 'is_active'], true) && in_array($value, [0, 1, '0', '1'], true)) { $value = (bool) $value; }
        if (is_bool($value)) { echo esc_html($value ? __('Yes', 'casawp') : __('No', 'casawp')); return; }
        if (is_scalar($value) && $field === 'timesegment') {
            $periods = ['infinite' => __('One-time', 'casawp'), 'h' => __('Hourly', 'casawp'), 'd' => __('Daily', 'casawp'),
                'w' => __('Weekly', 'casawp'), 'm' => __('Monthly', 'casawp'), 'y' => __('Yearly', 'casawp')];
            $value = $periods[(string) $value] ?? $value;
        }
        if (is_scalar($value) && $field === 'propertysegment') {
            $value = ['all' => __('Whole property', 'casawp'), 'm' => __('Per m²', 'casawp')][(string) $value] ?? $value;
        }
        if (!is_array($value)) { echo esc_html(is_scalar($value) ? (string) $value : __('Unavailable', 'casawp')); return; }
        if ($depth >= 6) { echo esc_html(wp_json_encode($value)); return; }
        $labels = self::labels();
        echo '<ul class="casawp-meta-value-list">';
        foreach ($value as $key => $item) {
            echo '<li>';
            if (!is_int($key)) { echo '<strong>' . esc_html($labels[$key] ?? $key) . ':</strong> '; }
            self::pretty($item, $depth + 1, (string) $key);
            echo '</li>';
        }
        echo '</ul>';
    }

    /** Validate before WordPress changes the post, and keep imports out until all fields are saved. */
    public static function preparePostSave($post_id = 0): void
    {
        if (self::$pending !== null || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || ($_POST['action'] ?? '') !== 'editpost'
            || (!isset($_POST['casawp_meta']) && !isset($_POST['casawp_meta_nonce']) && !isset($_POST['casawp_meta_revision']))
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        $id = $_POST['post_ID'] ?? '';
        if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) { return; }
        $id = (int) $id;
        if (($post_id && (int) $post_id !== $id) || get_post_type($id) !== 'casawp_property'
            || wp_is_post_revision($id) || wp_is_post_autosave($id)) { return; }
        $release = null;
        try {
            if (!current_user_can('edit_post', $id) || !isset($_POST['casawp_meta_nonce'])
                || !is_string($_POST['casawp_meta_nonce'])
                || !wp_verify_nonce(wp_unslash($_POST['casawp_meta_nonce']), 'casawp_property_meta_' . $id)) {
                throw new \RuntimeException(__('You cannot change these property details. Reload the page and try again.', 'casawp'), 403);
            }
            $release = PropertyMedia::acquireEditLock();
            wp_cache_delete($id, 'post_meta');
            $revision = $_POST['casawp_meta_revision'] ?? '';
            if (!is_string($revision) || !hash_equals(self::revision($id), $revision)) {
                throw new \RuntimeException(__('These details changed after you opened the page. Reload before saving.', 'casawp'), 409);
            }
            $input = isset($_POST['casawp_meta']) && is_array($_POST['casawp_meta']) ? wp_unslash($_POST['casawp_meta']) : [];
            if (($input['complete'] ?? '') !== '1' || !isset($input['visualReferenceId']) || !is_string($input['visualReferenceId'])) {
                throw new \RuntimeException(__('The submitted details are incomplete. Reload the page and try again.', 'casawp'));
            }
            $before = self::values($id);
            $after = ['visualReferenceId' => sanitize_text_field($input['visualReferenceId'])];
            foreach (['links' => 'the_urls', 'costs' => 'extraPrice', 'offers' => 'integratedoffers'] as $kind => $key) {
                $rows = $kind === 'links' ? self::links($before[$key]) : self::costs($before[$key], $kind === 'offers');
                $after[$key] = $rows === null ? $before[$key] : self::parseRows($kind, $input[$kind] ?? [], $rows, $before[$key]);
            }
            self::$pending = ['id' => $id, 'before' => $before, 'after' => $after, 'release' => $release, 'finished' => false];
        } catch (\Throwable $error) {
            if ($release) { $release(); }
            self::failSave($error);
        }
    }

    /** Runs after the normal post and ACF fields have been saved. */
    public static function finishPostSave(int $id): void
    {
        if (!self::$pending || self::$pending['id'] !== $id || self::$pending['finished']) { return; }
        // A save callback can update this post recursively. Wait for the outer write and its remaining callbacks.
        $writes = 0;
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (empty($frame['class']) && ($frame['function'] ?? '') === 'wp_insert_post' && ++$writes > 1) { return; }
        }
        self::$pending['finished'] = true;
        $failure = null;
        try {
            wp_cache_delete($id, 'post_meta');
            if (self::values($id) !== self::$pending['before']) {
                throw new \RuntimeException(__('These details changed after you opened the page. Reload before saving.', 'casawp'), 409);
            }
            self::writeValues($id, self::$pending['before'], self::$pending['after']);
        } catch (\Throwable $error) {
            $failure = $error;
        } finally {
            (self::$pending['release'])();
        }
        if ($failure) { self::failSave($failure); }
    }

    private static function writeValues(int $id, array $before, array $after): void
    {
        $written = [];
        $exists = [];
        foreach (self::KEYS as $key) { $exists[$key] = metadata_exists('post', $id, $key); }
        try {
            foreach ($after as $key => $value) {
                if ($value === $before[$key]) { continue; }
                $written[] = $key;
                update_post_meta($id, $key, wp_slash($value));
                if (get_post_meta($id, $key, true) !== $value) {
                    throw new \RuntimeException(__('Could not save additional details. Please try again.', 'casawp'));
                }
            }
        } catch (\Throwable $error) {
            foreach ($written as $key) {
                if ($exists[$key]) { update_post_meta($id, $key, wp_slash($before[$key])); }
                else { delete_post_meta($id, $key); }
            }
            throw $error;
        }
    }

    private static function failSave(\Throwable $error): void
    {
        $status = in_array($error->getCode(), [403, 409], true) ? $error->getCode() : 400;
        $message = $error->getMessage();
        if ($status === 409 && $message !== __('These details changed after you opened the page. Reload before saving.', 'casawp')) {
            $message = __('Wait until the import or cleanup finishes before changing these details.', 'casawp');
        }
        // The block editor submits legacy meta boxes through apiFetch and needs a WordPress error response.
        if (isset($_GET['meta-box-loader'])) {
            wp_send_json(['code' => 'casawp_property_meta_save_failed', 'message' => $message, 'data' => ['status' => $status]], $status);
        }
        wp_die(esc_html($message), esc_html__('Property details could not be saved.', 'casawp'), ['response' => $status, 'back_link' => true]);
    }

    private static function parseRows(string $kind, $input, array $original, $stored)
    {
        if (!is_array($input) || count($input) > self::LIMIT) {
            throw new \RuntimeException(__('Use no more than 50 entries per section.', 'casawp'));
        }
        $fields = $kind === 'links' ? ['type', 'href', 'label', 'title'] : ['price', 'currency', 'timesegment', 'propertysegment'];
        if ($kind === 'offers') { $fields = array_merge($fields, ['type', 'inclusive']); }
        $result = [];
        $used = [];
        $unchanged = count($input) === count($original);
        foreach (array_values($input) as $index => $input_row) {
            if (!is_array($input_row)) { throw new \RuntimeException(__('Choose valid values for every entry.', 'casawp')); }
            $source = $input_row['_row'] ?? '';
            if (!is_string($source) || ($source !== '' && (!ctype_digit($source) || $source !== (string) (int) $source
                || !isset($original[(int) $source]) || isset($used[$source])))) {
                throw new \RuntimeException(__('These details changed after you opened the page. Reload before saving.', 'casawp'), 409);
            }
            $base = $source !== '' ? $original[(int) $source] : [];
            $used[$source] = true;
            $data = $kind === 'links' ? ($base['data'] ?? []) : $base;
            $old = $kind === 'links' ? array_merge($data, ['type' => $base['type'] ?? 'website']) : $data;
            $row = [];
            $changed = $source === '';
            if ($source !== '' && (int) $source !== $index) { $unchanged = false; }
            foreach ($fields as $field) {
                if (!isset($input_row[$field]) || !is_string($input_row[$field])) {
                    throw new \RuntimeException(__('Choose valid values for every entry.', 'casawp'));
                }
                $row[$field] = $input_row[$field];
                $old_value = $field === 'inclusive' ? (empty($old[$field]) ? '0' : '1') : (string) ($old[$field] ?? '');
                if ($row[$field] !== $old_value) { $changed = true; }
            }
            if (!$changed) { $result[] = $base; continue; }
            $unchanged = false;
            if ($kind === 'links') {
                $row['href'] = trim($row['href']);
                $url = esc_url_raw($row['href'], ['http', 'https']);
                if (!$url || !preg_match('#^https?://#i', $row['href']) || !wp_parse_url($url, PHP_URL_HOST)) {
                    throw new \RuntimeException(__('Enter a complete http:// or https:// URL for each link.', 'casawp'));
                }
                $row['href'] = $url;
                if ($row['type'] !== (string) ($base['type'] ?? '')
                    && !in_array($row['type'], ['', 'website', 'youtube', 'virtual-tour'], true)) {
                    throw new \RuntimeException(__('Choose valid values for every entry.', 'casawp'));
                }
                if ($row['type'] === '' && $source === '') { $row['type'] = 'website'; }
                foreach (['label', 'title'] as $field) { $row[$field] = sanitize_text_field($row[$field]); }
                foreach (['href', 'label', 'title'] as $field) {
                    if ($source === '' || $row[$field] !== (string) ($data[$field] ?? '')) { $data[$field] = $row[$field]; }
                }
                $result[] = ['type' => $row['type'], 'data' => $data];
            } else {
                $row['price'] = str_replace(',', '.', trim($row['price']));
                if (($row['price'] !== '' || $kind !== 'offers')
                    && (!preg_match('/^\d+(?:\.\d+)?$/D', $row['price']) || !is_finite((float) $row['price']))) {
                    throw new \RuntimeException(__('Enter a nonnegative amount for each additional cost.', 'casawp'));
                }
                $row['currency'] = strtoupper(trim($row['currency']));
                if (($row['currency'] !== '' && !preg_match('/^[A-Z]{3}$/D', $row['currency']))
                    || !in_array($row['timesegment'], ['', 'infinite', 'h', 'd', 'w', 'm', 'y', (string) ($old['timesegment'] ?? '')], true)
                    || !in_array($row['propertysegment'], ['', 'all', 'm', (string) ($old['propertysegment'] ?? '')], true)) {
                    throw new \RuntimeException(__('Choose a valid currency, billing period and property unit.', 'casawp'));
                }
                if ($kind === 'offers' && ((!array_key_exists($row['type'], self::offerTypes())
                    && ($source === '' || $row['type'] !== (string) ($old['type'] ?? '')))
                    || !in_array($row['inclusive'], ['0', '1'], true))) {
                    throw new \RuntimeException(__('Choose a valid offer type and included status.', 'casawp'));
                }
                foreach ($fields as $field) {
                    $old_value = $field === 'inclusive' ? (empty($data[$field]) ? '0' : '1') : (string) ($data[$field] ?? '');
                    if ($source === '' || $row[$field] !== $old_value) { $data[$field] = $row[$field]; }
                }
                if ($source === '') { $data['frequency'] = ''; }
                $result[] = $data;
            }
        }
        if ($unchanged) { return $stored; }
        if ($kind !== 'links') { return $result; }
        $grouped = [];
        foreach ($result as $row) {
            if ($row['type'] === '') { $grouped[] = $row['data']; }
            else { $grouped[$row['type']][] = $row['data']; }
        }
        return $grouped;
    }
}
