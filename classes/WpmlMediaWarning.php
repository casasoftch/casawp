<?php

namespace casawp;

/** Warn once while WPML media translation remains enabled, without blocking imports. */
class WpmlMediaWarning
{
    private const SENT = 'casawp_wpml_media_warning_sent';

    public static function check(int $run_id): void
    {
        try {
            self::checkSetting($run_id);
        } catch (\Throwable $e) {
            self::log($run_id, 'WPML media preflight could not complete; the import will continue.', [
                'event' => 'preflight.wpml_media.check_failed', 'error_type' => get_class($e),
            ]);
        }
    }

    private static function checkSetting(int $run_id): void
    {
        global $sitepress;
        if (!defined('ICL_SITEPRESS_VERSION') && !is_object($sitepress)) {
            delete_option(self::SENT);
            return;
        }

        // WPML's public API covers both translatable modes and configuration overrides.
        $translated = apply_filters('wpml_is_translated_post_type', null, 'attachment');
        if ($translated === null) { return; } // WPML is not ready; do not infer a setting.
        if (!$translated) {
            delete_option(self::SENT);
            return;
        }

        self::log($run_id, 'WPML Media (attachment) is translatable. Set it to Not translatable to avoid duplicate media and additional import work.', [
            'event' => 'preflight.wpml_media.translatable',
        ]);
        if (get_option(self::SENT, false)) { return; }

        $recipient = get_option('admin_email');
        if (!is_email($recipient)) {
            self::log($run_id, 'WPML media warning could not be sent: the WordPress administrator email is invalid.', [
                'event' => 'preflight.wpml_media.mail_failed',
            ]);
            return;
        }

        /* translators: %s: website URL. */
        $subject = sprintf(__('CASAWP: WPML media translation is enabled on %s', 'casawp'), home_url());
        /* translators: %s: website URL. Keep attachment as the technical post type name. */
        $message = sprintf(__("WPML is active and Media (attachment) is configured as translatable on this website:\n%s\n\nOpen WPML > Settings > Post Types Translation and set Media (attachment) to \"Not translatable\".\n\nCASAWP already manages property media for each language. Translatable media can create duplicate attachments and increase import time.\n\nThe import will continue. This warning will not be repeated until an import detects that the setting has been corrected and it is later enabled again.", 'casawp'), home_url());

        // Import startup holds the site's import lock, so checks cannot send concurrently.
        // Mark only accepted mail; a failed send is retried at the next import start.
        if (wp_mail($recipient, $subject, $message)) {
            update_option(self::SENT, 1, false);
        } else {
            self::log($run_id, 'WPML media warning email could not be sent; it will be retried at the next import start.', [
                'event' => 'preflight.wpml_media.mail_failed',
            ]);
        }
    }

    private static function log(int $run_id, string $message, array $context): void
    {
        (new ImportLogger(static function () use ($run_id) { return $run_id; }))->write($message, $context, 'warning');
    }
}
