<?php

namespace casawp;

/** Shared rules for remote-only imports and removal of obsolete local copies. */
class ImportMedia
{
    public const TYPES = ['image', 'document', 'plan', 'offer-logo', 'sales-brochure'];

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
