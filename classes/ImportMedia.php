<?php

namespace casawp;

/** Shared attachment ownership and remote-source rules. */
class ImportMedia
{
    public const TYPES = ['image', 'document', 'plan', 'offer-logo', 'sales-brochure'];
    private const IMAGE_TEXT_MARKERS = [
        'post_title' => '_casawp_imported_image_title',
        'post_excerpt' => '_casawp_imported_image_caption',
    ];

    public static function imageText(array $media): array
    {
        $title = !empty($media['title']) ? (string) $media['title']
            : wp_basename((string) parse_url(self::mediaSource($media), PHP_URL_PATH));
        return ['post_title' => sanitize_text_field($title), 'post_excerpt' => (string) ($media['caption'] ?? '')];
    }

    /** Preserve nonempty manual overrides, including existing unmarked values. */
    public static function imageTextForImport(int $id, string $field, string $current, string $incoming): string
    {
        $previous = (string) get_post_meta($id, self::IMAGE_TEXT_MARKERS[$field], true);
        return $current !== '' && $current !== $incoming && $current !== $previous ? $current : $incoming;
    }

    /** Adopt only fields that actually match the imported text after a successful save. */
    public static function recordImportedImageText(int $id, array $media): void
    {
        if (($media['type'] ?? 'image') !== 'image') { return; }
        $post = get_post($id);
        if (!$post) { return; }
        foreach (self::imageText($media) as $field => $incoming) {
            $key = self::IMAGE_TEXT_MARKERS[$field];
            if ((string) $post->$field === $incoming && (string) get_post_meta($id, $key, true) !== $incoming
                && update_post_meta($id, $key, wp_slash($incoming)) === false) {
                throw new \RuntimeException('Could not record imported image text for attachment ' . $id . '.');
            }
        }
    }

    /** Explicit feed alt wins; image captions provide the fallback. */
    public static function alternativeText(array $media): string
    {
        $alt = isset($media['alt']) ? (string) $media['alt'] : '';
        if (trim($alt) !== '') { return $alt; }
        if (($media['type'] ?? 'image') !== 'image') { return ''; }

        return trim(wp_strip_all_tags(html_entity_decode((string) ($media['caption'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true));
    }

    /** Separate from property hashes: image text changes need no property save. */
    public static function alternativeTextHash(array $medias): ?string
    {
        $values = [];
        foreach ($medias as $media) {
            if (($media['type'] ?? 'image') !== 'image') { continue; }
            $source = self::mediaSource($media);
            if ($source === '') { continue; }
            $values[] = [$source, self::imageText($media), trim((string) ($media['alt'] ?? '')) !== '', self::alternativeText($media)];
        }
        return $values ? hash('sha256', serialize(['image-text-v2', $values])) : null;
    }

    private static function mediaSource(array $media): string
    {
        foreach ([$media['media']['original_file'] ?? '', $media['url'] ?? '', $media['file'] ?? ''] as $source) {
            if ($source !== '') { return (string) $source; }
        }
        return '';
    }

    public static function syncAlternativeText(int $id, array $media): bool
    {
        $current = (string) get_post_meta($id, '_wp_attachment_image_alt', true);
        $previous = (string) get_post_meta($id, '_casawp_imported_image_alt', true);
        $explicit = trim((string) ($media['alt'] ?? '')) !== '';
        $image = ($media['type'] ?? 'image') === 'image';
        // Without an explicit feed value, preserve nonempty text we did not set,
        // including manual edits made after a previous automatic import.
        if ($image && !$explicit && $current !== '' && ($previous === '' || $current !== $previous)) {
            return false;
        }
        $new = self::alternativeText($media);
        $changed = $current !== $new;
        if ($changed && update_post_meta($id, '_wp_attachment_image_alt', wp_slash($new)) === false) {
            throw new \RuntimeException('Could not update imported image alternative text for attachment ' . $id . '.');
        }
        if ($image && $previous !== $new
            && update_post_meta($id, '_casawp_imported_image_alt', wp_slash($new)) === false) {
            throw new \RuntimeException('Could not record imported image alternative text for attachment ' . $id . '.');
        }
        return $changed;
    }

    /** Manual additions remain library-owned after an import detaches them. */
    public static function isManualAttachment(int $id): bool
    {
        return (bool) get_post_meta($id, '_casawp_manual_attachment', true)
            && get_post_meta($id, '_origin', true) === ''
            && !get_post_meta($id, '_casawp_import', true)
            && !get_post_meta($id, '_is_remote', true);
    }

    public static function usesRemote(string $type, string $source, bool $cdn): bool
    {
        if (!$cdn || !in_array($type, self::TYPES, true) || !preg_match('#^https?://#i', $source)) {
            return false;
        }
        // Preserve existing remote-image support for third-party image sources.
        if ($type === 'image') {
            return true;
        }
        $host = strtolower((string) parse_url($source, PHP_URL_HOST));
        $path = (string) parse_url($source, PHP_URL_PATH);
        return in_array($host, ['casagateway.ch', 'cdn.casasoft.com'], true)
            && (strpos($path, '/media/') === 0 || strpos($path, '/media-thumb/') === 0);
    }
}
