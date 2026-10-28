<?php

namespace casawp;

/** Manual attachment controls for a single property's existing media records. */
class PropertyMedia
{
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('wp_ajax_casawp_property_media', [self::class, 'ajax']);
    }

    private static function types(): array
    {
        return [
            'image' => __('Image', 'casawp'),
            'document' => __('Document', 'casawp'),
            'plan' => __('Plan', 'casawp'),
            'sales-brochure' => __('Sales brochure', 'casawp'),
            'offer-logo' => __('Offer logo', 'casawp'),
        ];
    }

    public static function enqueue(): void
    {
        $screen = get_current_screen();
        global $post;
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'casawp_property'
            || !$post || !current_user_can('edit_post', $post->ID)) {
            return;
        }
        wp_enqueue_media(['post' => $post]);
        wp_enqueue_style('casawp-property-media', CASASYNC_PLUGIN_URL . 'plugin-assets/global/css/property-media.css', [],
            filemtime(CASASYNC_PLUGIN_DIR . 'plugin-assets/global/css/property-media.css'));
        wp_enqueue_script('casawp-property-media', CASASYNC_PLUGIN_URL . 'plugin-assets/js/property-media.js',
            ['jquery', 'jquery-ui-sortable', 'media-views'], filemtime(CASASYNC_PLUGIN_DIR . 'plugin-assets/js/property-media.js'), true);
        wp_localize_script('casawp-property-media', 'casawpPropertyMedia', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'addTitle' => __('Choose attachments', 'casawp'),
            'addButton' => __('Add attachments', 'casawp'),
            'saving' => __('Saving attachment changes…', 'casawp'),
            'saved' => __('Attachment changes saved.', 'casawp'),
            'error' => __('Could not save attachment changes. Please try again.', 'casawp'),
        ]);
    }

    private static function attachments(int $post_id): array
    {
        // Include unclassified records too, as in the original diagnostic overview.
        return get_posts([
            'post_type' => 'attachment', 'post_parent' => $post_id, 'post_status' => 'any',
            'posts_per_page' => -1, 'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'], 'suppress_filters' => true,
        ]);
    }

    public static function isRunning(): bool
    {
        wp_cache_delete('casawp_property_media_owner', 'options');
        wp_cache_delete('notoptions', 'options');
        $owner = get_option('casawp_property_media_owner', []);
        return is_array($owner) && !empty($owner['token']) && isset($owner['started'])
            && time() - (int) $owner['started'] <= 14400;
    }

    private static function claimOwner(): array
    {
        global $wpdb;
        $key = 'casawp_property_media_owner';
        $owner = ['token' => wp_generate_uuid4(), 'started' => time()];
        $claim = static function () use ($wpdb, $key, $owner): bool {
            // add_option can update on duplicate keys after a stale cache read; locking needs insert-only SQL.
            $result = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
                $key, maybe_serialize($owner)));
            wp_cache_delete($key, 'options');
            wp_cache_delete('notoptions', 'options');
            return $result === 1;
        };
        if ($claim()) { return $owner; }
        if (self::isRunning()) { return []; }
        $expired = get_option($key);
        // Reclaim only the exact expired value read, never a replacement owner's value.
        $wpdb->delete($wpdb->options, ['option_name' => $key, 'option_value' => maybe_serialize($expired)], ['%s', '%s']);
        wp_cache_delete($key, 'options');
        wp_cache_delete('notoptions', 'options');
        return $claim() ? $owner : [];
    }

    public static function render($post): void
    {
        $post_id = (int) $post->ID;
        echo '<div class="casawp-property-media" data-post-id="' . $post_id . '" data-nonce="'
            . esc_attr(wp_create_nonce('casawp_property_media_' . $post_id)) . '">';
        if (current_user_can('edit_post', $post_id)) {
            echo '<p class="description">' . esc_html__('Drag images to reorder them, or use the arrow buttons. Changes are saved immediately. The first image becomes the featured image.', 'casawp') . '</p>';
            echo '<p class="description">' . esc_html__('Imports take over the media list and image order again when they resume. Files added here return to the Media Library. Use these controls for properties managed manually.', 'casawp') . '</p>';
            if (current_user_can('upload_files')) {
                echo '<p class="casawp-property-media-toolbar"><label for="casawp-property-media-type">'
                    . esc_html__('Attachment type', 'casawp') . '</label> ';
                echo '<select id="casawp-property-media-type" class="casawp-property-media-type">';
                foreach (self::types() as $slug => $label) {
                    echo '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
                }
                echo '</select> <button type="button" class="button casawp-property-media-add">'
                    . esc_html__('Add attachments', 'casawp') . '</button></p>';
                echo '<p class="description">' . esc_html__('Choose new uploads, unattached files, or files already attached to this property.', 'casawp') . '</p>';
            }
        }
        echo '<p class="casawp-property-media-status" role="status" aria-live="polite"></p>';
        echo '<div class="casawp-property-media-content">';
        self::renderContent($post_id);
        echo '</div></div>';
    }

    public static function renderContent(int $post_id): void
    {
        $attachments = self::attachments($post_id);
        if (!$attachments) {
            echo '<p>' . esc_html__('No media is attached to this property.', 'casawp') . '</p>';
            return;
        }
        $rows = [];
        $images = [];
        foreach ($attachments as $attachment) {
            $terms = get_the_terms($attachment->ID, 'casawp_attachment_type');
            $types = ($terms && !is_wp_error($terms)) ? wp_list_pluck($terms, 'slug') : [];
            $is_image = in_array('image', $types, true);
            $rows[] = [$attachment, $types, $is_image];
            if ($is_image) { $images[] = $attachment; }
        }
        if ($images && current_user_can('edit_post', $post_id)) {
            echo '<ul class="casawp-property-images" aria-label="' . esc_attr__('Image order', 'casawp') . '">';
            foreach ($images as $index => $image) {
                echo '<li data-id="' . (int) $image->ID . '"><span class="casawp-image-drag dashicons dashicons-move" aria-hidden="true"></span>';
                echo wp_get_attachment_image($image->ID, [160, 110], true);
                echo '<span class="casawp-image-title">' . esc_html($image->post_title !== '' ? $image->post_title : '#' . $image->ID) . '</span>';
                echo '<span class="casawp-image-controls"><button type="button" class="button casawp-image-up" aria-label="'
                    . esc_attr__('Move image earlier', 'casawp') . '"' . disabled($index, 0, false) . '><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button> ';
                echo '<button type="button" class="button casawp-image-down" aria-label="'
                    . esc_attr__('Move image later', 'casawp') . '"' . disabled($index, count($images) - 1, false) . '><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button></span></li>';
            }
            echo '</ul>';
        }
        echo '<p><strong>' . esc_html(sprintf(
            /* translators: 1: number of attached media items, 2: number classified as images. */
            __('Attached media: %1$d. Classified as images: %2$d.', 'casawp'), count($attachments), count($images)
        )) . '</strong></p>';
        $rows = array_values(array_filter($rows, static function ($row) { return !$row[2]; }));
        echo '<h4>' . esc_html__('Other attachments', 'casawp') . '</h4>';
        if (!$rows) {
            echo '<p>' . esc_html__('No other files are attached to this property.', 'casawp') . '</p>';
            return;
        }
        echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr>';
        foreach ([__('Preview', 'casawp'), __('Attachment', 'casawp'), __('Attachment type', 'casawp'),
            __('Status', 'casawp'), __('Order', 'casawp'), __('Source', 'casawp')] as $heading) {
            echo '<th scope="col">' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as [$attachment, $types, $is_image]) {
            $id = (int) $attachment->ID;
            $url = wp_get_attachment_url($id);
            $origin = (string) get_post_meta($id, '_origin', true);
            $edit_url = get_edit_post_link($id);
            $title = $attachment->post_title !== '' ? $attachment->post_title : '#' . $id;
            echo '<tr><td style="width:120px">';
            echo wp_get_attachment_image($id, [120, 90], true, ['style' => 'max-width:120px;height:auto;']);
            echo '</td><td>' . ($edit_url ? '<a href="' . esc_url($edit_url) . '">' . esc_html($title) . '</a>' : esc_html($title));
            echo '<br><code>' . esc_html__('ID', 'casawp') . ': ' . $id . '</code><br>' . esc_html($attachment->post_mime_type);
            echo '</td><td>' . ($types ? esc_html(implode(', ', $types)) : esc_html__('Unassigned', 'casawp'));
            if (!$is_image && strpos($attachment->post_mime_type, 'image/') === 0) {
                echo '<br><strong>' . esc_html__('Not classified as an image', 'casawp') . '</strong>';
            }
            echo '</td><td>' . esc_html($attachment->post_status) . '</td><td>' . (int) $attachment->menu_order . '</td><td style="overflow-wrap:anywhere">';
            $source = $origin !== '' ? $origin : $url;
            if ($source) { echo '<a href="' . esc_url($source) . '" target="_blank" rel="noopener noreferrer">' . esc_html($source) . '</a>'; }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function ajax(): void
    {
        $post_id = self::positiveId($_POST['post_id'] ?? null);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !$post_id
            || get_post_type($post_id) !== 'casawp_property' || !current_user_can('edit_post', $post_id)
            || !isset($_POST['nonce']) || !is_string($_POST['nonce'])
            || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'casawp_property_media_' . $post_id)) {
            wp_send_json_error(['message' => __('You cannot change these property attachments. Reload the page and try again.', 'casawp')], 403);
        }
        $operation = $_POST['operation'] ?? '';
        $ids = $_POST['ids'] ?? null;
        if (!in_array($operation, ['order', 'attach'], true) || !is_array($ids) || !$ids || count($ids) > 500) {
            wp_send_json_error(['message' => __('Invalid attachment selection.', 'casawp')], 400);
        }
        $valid_ids = array_map([self::class, 'positiveId'], array_values($ids));
        if (in_array(0, $valid_ids, true) || count(array_unique($valid_ids)) !== count($valid_ids)) {
            wp_send_json_error(['message' => __('Invalid attachment selection.', 'casawp')], 400);
        }
        // Publish intent before acquiring the shared lock so forced starts cannot cancel it in between.
        $owner = self::claimOwner();
        if (!$owner) {
            wp_send_json_error(['message' => __('Wait until the import or cleanup finishes before changing attachments.', 'casawp')], 409);
        }
        $lock_time = 0;
        $released = false;
        $release = static function () use (&$lock_time, $owner, &$released): void {
            if ($released) { return; }
            $released = true;
            wp_cache_delete('casawp_import_lock', 'options');
            wp_cache_delete('casawp_property_media_owner', 'options');
            if (get_option('casawp_property_media_owner') !== $owner) { return; }
            delete_option('casawp_property_media_owner');
            if ($lock_time && !casawp_current_import_run_id() && (int) get_option('casawp_import_lock') === $lock_time) {
                casawp_release_lock();
                if (function_exists('as_schedule_single_action') && casawp_has_pending()
                    && as_schedule_single_action(time(), 'casawp_process_gateway_poke',
                        ['Deferred until manual attachment changes finished'], casawp_gateway_poke_group())) {
                    casawp_clear_pending();
                }
            }
        };
        register_shutdown_function($release);
        $error = null;
        $error_status = 400;
        try {
            if (ManualCleanup::isRunning() || casawp_current_import_run_id() || !casawp_acquire_lock()) {
                $error_status = 409;
                throw new \RuntimeException(__('Wait until the import or cleanup finishes before changing attachments.', 'casawp'));
            }
            $lock_time = (int) get_option('casawp_import_lock');
            if (get_option('casawp_property_media_owner') !== $owner) {
                throw new \RuntimeException(__('Could not save attachment changes. Please try again.', 'casawp'));
            }
            if ($operation === 'order') { self::order($post_id, $valid_ids); }
            else { self::attach($post_id, $valid_ids, $_POST['type'] ?? ''); }
        } catch (\Throwable $caught) {
            $error = $caught;
        } finally {
            $release();
        }
        ob_start();
        self::renderContent($post_id);
        $html = ob_get_clean();
        if ($error) {
            wp_send_json_error(['message' => $error->getMessage(), 'html' => $html,
                'thumbnail_id' => (int) get_post_thumbnail_id($post_id)], $error_status);
        }
        wp_send_json_success(['html' => $html, 'message' => __('Attachment changes saved.', 'casawp'),
            'thumbnail_id' => (int) get_post_thumbnail_id($post_id)]);
    }

    private static function positiveId($value): int
    {
        return (is_int($value) || is_string($value)) && ctype_digit((string) $value) && (int) $value > 0 ? (int) $value : 0;
    }

    private static function order(int $post_id, array $ids): void
    {
        $images = [];
        foreach (self::attachments($post_id) as $attachment) {
            if (has_term('image', 'casawp_attachment_type', $attachment->ID)) { $images[] = (int) $attachment->ID; }
        }
        $expected = $images;
        $received = $ids;
        sort($expected); sort($received);
        if ($received !== $expected) {
            throw new \RuntimeException(__('The attached images have changed. Reload the page before reordering them.', 'casawp'));
        }
        foreach ($ids as $id) { self::editableAttachment($id, $post_id); }
        self::markModified($post_id);
        foreach ($ids as $index => $id) {
            self::updateAttachment(['ID' => $id, 'menu_order' => $index + 1]);
        }
        self::setFeatured($post_id, $ids[0]);
    }

    private static function attach(int $post_id, array $ids, $type): void
    {
        $taxonomy = get_taxonomy('casawp_attachment_type');
        if (!current_user_can('upload_files') || !$taxonomy || !current_user_can($taxonomy->cap->assign_terms)) {
            throw new \RuntimeException(__('You cannot add attachments to this property.', 'casawp'));
        }
        if (!is_string($type) || !in_array($type, ImportMedia::TYPES, true)) {
            throw new \RuntimeException(__('Choose a valid attachment type.', 'casawp'));
        }
        $term = get_term_by('slug', $type, 'casawp_attachment_type');
        if (!$term || is_wp_error($term)) {
            throw new \RuntimeException(__('The attachment type is unavailable. Reload the page and try again.', 'casawp'));
        }
        $attachments = [];
        foreach ($ids as $id) {
            $attachment = self::editableAttachment($id, $post_id, true);
            if (!(int) $attachment->post_parent && (get_post_meta($id, '_origin', true)
                || get_post_meta($id, '_casawp_import', true) || get_post_meta($id, '_is_remote', true))) {
                throw new \RuntimeException(__('You cannot change one or more selected attachments.', 'casawp'));
            }
            if (in_array($type, ['image', 'offer-logo'], true) && !wp_attachment_is_image($id)) {
                throw new \RuntimeException(__('Select image files for this attachment type.', 'casawp'));
            }
            $attachments[] = $attachment;
        }
        $order = 0;
        foreach (self::attachments($post_id) as $attachment) { $order = max($order, (int) $attachment->menu_order); }
        self::markModified($post_id);
        foreach ($attachments as $attachment) {
            $id = (int) $attachment->ID;
            // New local additions can be detached safely when source imports resume.
            if (!get_post_meta($id, '_casawp_import', true) && !get_post_meta($id, '_origin', true)
                && !get_post_meta($id, '_is_remote', true)) {
                update_post_meta($id, '_casawp_manual_attachment', 1);
                if (!get_post_meta($id, '_casawp_manual_attachment', true)) {
                    throw new \RuntimeException(__('Could not save attachment changes. Please try again.', 'casawp'));
                }
            }
            self::updateAttachment(['ID' => $id, 'post_parent' => $post_id, 'menu_order' => ++$order]);
            $assigned = wp_set_post_terms($id, [(int) $term->term_id], 'casawp_attachment_type', false);
            if (is_wp_error($assigned) || !$assigned) {
                throw new \RuntimeException(__('Could not save attachment changes. Please try again.', 'casawp'));
            }
        }
        $featured = 0;
        foreach (self::attachments($post_id) as $attachment) {
            if (has_term('image', 'casawp_attachment_type', $attachment->ID)) {
                $featured = (int) $attachment->ID;
                break;
            }
        }
        if ($featured) { self::setFeatured($post_id, $featured); }
        else { delete_post_thumbnail($post_id); }
    }

    private static function editableAttachment(int $id, int $post_id, bool $allow_unattached = false)
    {
        $attachment = get_post($id);
        if (!$attachment || $attachment->post_type !== 'attachment' || $attachment->post_status === 'trash'
            || !current_user_can('edit_post', $id)) {
            throw new \RuntimeException(__('You cannot change one or more selected attachments.', 'casawp'));
        }
        if ((int) $attachment->post_parent !== $post_id && (!$allow_unattached || (int) $attachment->post_parent !== 0)) {
            throw new \RuntimeException(__('A selected file belongs to another post. Upload a separate copy for this property.', 'casawp'));
        }
        return $attachment;
    }

    private static function markModified(int $post_id): void
    {
        update_post_meta($post_id, '_casawp_manual_media_modified', 1);
        if (!get_post_meta($post_id, '_casawp_manual_media_modified', true)) {
            throw new \RuntimeException(__('Could not save attachment changes. Please try again.', 'casawp'));
        }
    }

    private static function updateAttachment(array $data): void
    {
        $result = wp_update_post($data, true);
        if (is_wp_error($result) || !$result) {
            throw new \RuntimeException(__('Could not save attachment changes. Please try again.', 'casawp'));
        }
    }

    private static function setFeatured(int $post_id, int $id): void
    {
        if ((int) get_post_thumbnail_id($post_id) !== $id) {
            set_post_thumbnail($post_id, $id);
        }
        if ((int) get_post_thumbnail_id($post_id) !== $id) {
            throw new \RuntimeException(__('Could not save attachment changes. Please try again.', 'casawp'));
        }
    }
}
