<?php

namespace casawp;

/** Keep the private-area rule consistent across WordPress and CASAWP read routes. */
class PropertyAccess
{
    private static $authorize;
    private static $redirect;
    private static bool $registered = false;
    private static bool $sensitiveRestResponse = false;
    private const REST_QUERY = 'casawp_access_rest_query';
    private const CACHE_CONTROL = 'no-cache, must-revalidate, max-age=0, no-store, private';

    public static function register(callable $authorize, ?callable $redirect = null): void
    {
        self::$authorize = $authorize;
        self::$redirect = $redirect;
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('template_redirect', [self::class, 'guardSingular'], 0);
        add_filter('posts_where', [self::class, 'filterPostsWhere'], 100, 2);
        add_filter('rest_casawp_property_query', [self::class, 'markRestQuery'], 100, 2);
        add_filter('rest_attachment_query', [self::class, 'markRestQuery'], 100, 2);
        add_filter('rest_post_search_query', [self::class, 'markRestQuery'], 100, 2);
        add_filter('rest_comment_query', [self::class, 'markRestQuery'], 100, 2);
        add_filter('rest_request_before_callbacks', [self::class, 'guardRestRequest'], 10, 3);
        add_filter('rest_post_dispatch', [self::class, 'preventRestCaching'], 10, 3);
        add_action('pre_get_comments', [self::class, 'prepareCommentQuery'], 100);
        add_filter('comments_clauses', [self::class, 'filterCommentsClauses'], 100, 2);
        add_filter('comment_feed_where', [self::class, 'filterCommentFeedWhere'], 100, 2);
        add_filter('oembed_response_data', [self::class, 'filterOembedData'], 100, 2);
    }

    public static function isAuthorized(): bool
    {
        return self::$authorize ? (bool) call_user_func(self::$authorize) : is_user_logged_in();
    }

    public static function canRead($post): bool
    {
        if (!self::isProtected($post)) {
            return true;
        }
        if (!self::isAuthorized()) {
            return false;
        }
        self::$sensitiveRestResponse = true;
        self::preventCaching();
        return true;
    }

    public static function isProtected($post): bool
    {
        global $wpdb;
        $post = get_post($post);
        if (!$post) {
            return false;
        }
        if ($post->post_type === 'attachment') {
            $post = $post->post_parent ? get_post($post->post_parent) : null;
        }
        if (!$post || $post->post_type !== 'casawp_property') {
            return false;
        }

        // Inspect stored relationships, without applying a current WPML language filter.
        $private = $wpdb->get_var('SELECT ' . self::privatePropertyExists((string) (int) $post->ID));
        return $private === null || (bool) $private;
    }

    private static function privatePropertyExists(string $post_id): string
    {
        global $wpdb;
        return "EXISTS (
            SELECT 1 FROM {$wpdb->posts} AS casawp_private_property
            INNER JOIN {$wpdb->term_relationships} AS casawp_private_relationship
                ON casawp_private_relationship.object_id = casawp_private_property.ID
            INNER JOIN {$wpdb->term_taxonomy} AS casawp_private_taxonomy
                ON casawp_private_taxonomy.term_taxonomy_id = casawp_private_relationship.term_taxonomy_id
            INNER JOIN {$wpdb->terms} AS casawp_private_term
                ON casawp_private_term.term_id = casawp_private_taxonomy.term_id
            WHERE casawp_private_property.ID = {$post_id}
                AND casawp_private_property.post_type = 'casawp_property'
                AND casawp_private_taxonomy.taxonomy = 'casawp_availability'
                AND (casawp_private_term.slug = 'private' OR casawp_private_term.name = 'private')
        )";
    }

    private static function protectedPostExists(string $post_id): string
    {
        global $wpdb;
        $parent = "CASE WHEN casawp_access_post.post_type = 'attachment' THEN casawp_access_post.post_parent ELSE casawp_access_post.ID END";
        return "EXISTS (SELECT 1 FROM {$wpdb->posts} AS casawp_access_post
            WHERE casawp_access_post.ID = {$post_id}
                AND casawp_access_post.post_type IN ('casawp_property', 'attachment')
                AND " . self::privatePropertyExists($parent) . ')';
    }

    private static function isPublicRead(bool $rest_query = false): bool
    {
        if ($rest_query) {
            return true;
        }
        return !is_admin()
            && !(function_exists('wp_doing_cron') && wp_doing_cron())
            && !(defined('WP_CLI') && WP_CLI);
    }

    public static function markRestQuery(array $args, $request): array
    {
        $args[self::REST_QUERY] = true;
        if (self::isAuthorized()) {
            self::$sensitiveRestResponse = true;
        }
        return $args;
    }

    public static function filterPostsWhere(string $where, $query): string
    {
        global $wpdb;
        if (!self::isPublicRead((bool) $query->get(self::REST_QUERY))) {
            return $where;
        }
        if (self::isAuthorized()) {
            $post_types = (array) $query->get('post_type');
            if (array_intersect(['casawp_property', 'attachment', 'any'], $post_types)
                || (!$query->get('post_type') && $query->is_main_query())) {
                // Archive templates may send their headers before rendering each offer.
                self::preventCaching();
            }
            return $where;
        }

        // Keep the main singular post available for the early login/JSON guard.
        if ($query->is_main_query() && $query->is_singular() && !$query->is_feed()) {
            return $where;
        }

        // A term change must invalidate queries even when they have no tax_query.
        $query->set('casawp_access_terms', wp_cache_get_last_changed('terms'));
        $parent = "CASE WHEN {$wpdb->posts}.post_type = 'attachment' THEN {$wpdb->posts}.post_parent ELSE {$wpdb->posts}.ID END";
        // Keep this mandatory condition outside any existing OR expression.
        return ' AND (1=1 ' . $where . ") AND NOT ({$wpdb->posts}.post_type IN ('casawp_property', 'attachment') AND "
            . self::privatePropertyExists($parent) . ')';
    }

    public static function guardSingular(): void
    {
        if (is_singular(['casawp_property', 'attachment'])) {
            self::guardOutput(get_queried_object());
        }
    }

    public static function guardOutput($post): void
    {
        if (!self::isProtected($post)) {
            return;
        }
        self::preventCaching();
        if (self::isAuthorized()) {
            return;
        }
        if (isset($_GET['json']) || isset($_GET['ajax']) || isset($_GET['casawp_map'])) {
            wp_send_json_error(['message' => __('Please log in to view this property.', 'casawp')], 401);
        }
        if (self::$redirect) {
            call_user_func(self::$redirect);
        }
        wp_die(esc_html__('Please log in to view this property.', 'casawp'), '', ['response' => 401]);
    }

    public static function guardRestRequest($response, array $handler, $request)
    {
        if (!in_array($request->get_method(), ['GET', 'HEAD'], true) || is_wp_error($response)) {
            return $response;
        }
        $callback = $handler['callback'] ?? null;
        $controller = is_array($callback) ? ($callback[0] ?? null) : null;
        $post = null;
        if ($controller instanceof \WP_REST_Posts_Controller && !empty($request['id'])) {
            $post = get_post((int) $request['id']);
        } elseif ($controller instanceof \WP_REST_Comments_Controller && !empty($request['id'])) {
            $comment = get_comment((int) $request['id']);
            $post = $comment ? get_post($comment->comment_post_ID) : null;
        }
        if (!$post || !self::isProtected($post)) {
            return $response;
        }
        self::$sensitiveRestResponse = true;
        if (self::isAuthorized()) {
            return $response;
        }
        return new \WP_Error('casawp_private_property', __('Please log in to view this property.', 'casawp'), ['status' => 401]);
    }

    public static function prepareCommentQuery($query): void
    {
        if (!self::isAuthorized() && self::isPublicRead(!empty($query->query_vars[self::REST_QUERY]))) {
            // Comment cache keys omit SQL filters and custom query variables.
            $query->query_vars['cache_domain'] = ($query->query_vars['cache_domain'] ?? 'core')
                . ':casawp-public:' . wp_cache_get_last_changed('terms') . ':' . wp_cache_get_last_changed('posts');
        }
    }

    public static function filterCommentsClauses(array $clauses, $query): array
    {
        global $wpdb;
        if (!self::isAuthorized() && self::isPublicRead(!empty($query->query_vars[self::REST_QUERY]))) {
            $clauses['where'] = '(' . ($clauses['where'] ?: '1=1') . ') AND NOT '
                . self::protectedPostExists("{$wpdb->comments}.comment_post_ID");
        }
        return $clauses;
    }

    public static function filterCommentFeedWhere(string $where, $query): string
    {
        global $wpdb;
        if (!self::isAuthorized() && self::isPublicRead((bool) $query->get(self::REST_QUERY))) {
            $where = preg_replace('/^\s*WHERE\s+/i', '', $where);
            $where = 'WHERE (' . ($where ?: '1=1') . ') AND NOT '
                . self::protectedPostExists("{$wpdb->comments}.comment_post_ID")
                // WordPress keys its comment-feed cache by SQL, without a terms version.
                . ' /* casawp-access:' . md5(wp_cache_get_last_changed('terms')) . ' */';
        }
        return $where;
    }

    public static function filterOembedData($data, $post)
    {
        if (!self::canRead($post)) {
            return false;
        }
        if (self::isProtected($post)) {
            self::$sensitiveRestResponse = true;
            self::preventCaching();
        }
        return $data;
    }

    public static function preventRestCaching($response, $server, $request)
    {
        if (self::$sensitiveRestResponse && $response instanceof \WP_HTTP_Response) {
            $response->header('Cache-Control', self::CACHE_CONTROL);
        }
        return $response;
    }

    public static function preventCaching(): void
    {
        if (!headers_sent()) {
            nocache_headers();
            header('Cache-Control: ' . self::CACHE_CONTROL);
        }
    }
}
