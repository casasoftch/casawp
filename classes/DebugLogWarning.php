<?php

namespace casawp;

/** Notify the site administrator at each import startup while debug logging is active. */
class DebugLogWarning
{
    public static function check(int $run_id): void
    {
        try {
            if (apply_filters('casawp_import_log_level', 'info') !== 'debug') { return; }

            $recipient = get_option('admin_email');
            if (!is_email($recipient)) {
                self::log($run_id, 'Debug logging notification could not be sent: the WordPress administrator email is invalid.', 'preflight.debug_log.mail_failed');
                return;
            }

            /* translators: %s: website URL. */
            $subject = sprintf(__('CASAWP: Debug logging is enabled on %s', 'casawp'), home_url());
            /* translators: %s: website URL. Keep casawp_import_log_level, debug, Code Snippets and functions.php unchanged. */
            $message = sprintf(__("CASAWP debug logging is active for the import started on this website:\n%s\n\nDetailed debug logs can grow very large and consume disk space. Deactivate debug logging when it is no longer needed.\n\nRemove or deactivate the PHP snippet or filter that sets casawp_import_log_level to \"debug\" (for example, in Code Snippets, the theme's functions.php or a site plugin).\n\nThis notification is sent at the start of every import while debug logging is active. The import will continue.", 'casawp'), home_url());

            if (!wp_mail($recipient, $subject, $message)) {
                self::log($run_id, 'Debug logging notification email could not be sent; the import will continue.', 'preflight.debug_log.mail_failed');
            }
        } catch (\Throwable $error) {
            self::log($run_id, 'Debug logging notification could not complete; the import will continue.', 'preflight.debug_log.check_failed');
        }
    }

    private static function log(int $run_id, string $message, string $event): void
    {
        // Mail/filter failures must never prevent the import, even if logging fails too.
        try {
            (new ImportLogger(static function () use ($run_id) { return $run_id; }))
                ->write($message, ['event' => $event], 'warning');
        } catch (\Throwable $error) {
            error_log('CASAWP: ' . $message);
        }
    }
}
