<?php
namespace DataEngine\Utils;

/**
 * Logger Class.
 *
 * A simple file-based logger for debugging purposes.
 * It is controlled by the `DATA_ENGINE_DEBUG` constant. When enabled,
 * it writes logs to a dedicated file in the `wp-content/uploads` directory.
 *
 * @since 0.1.0
 */
class Logger {

    /**
     * The path to the log file.
     * @var string|null
     */
    private static ?string $log_file = null;

    /**
     * Logs a message to the debug file if debugging is enabled.
     *
     * @since 0.1.0
     * @param string $message The message to log.
     * @param string $level   The log level (e.g., INFO, DEBUG, WARNING, ERROR).
     */
    public static function log( string $message, string $level = 'INFO' ): void {
        // --- THIS IS THE KEY CHANGE ---
        // Instead of a constant, we now check a WordPress option.
        $options = get_option( \DataEngine\Core\Settings_Page::OPTION_NAME, [] );
        $debug_mode_enabled = $options['debug_mode'] ?? false;

        // The logger is completely inactive if the setting is not enabled.
        if ( ! $debug_mode_enabled ) {
            return;
        }

        if ( null === self::$log_file ) {
            self::set_log_file_path();
        }
        
        // ... (reszta metody pozostaje bez zmian) ...
        if ( ! self::$log_file ) {
            return;
        }

        $formatted_message = sprintf(
            "[%s] [%s]: %s\n",
            wp_date( 'Y-m-d H:i:s' ),
            strtoupper( $level ),
            $message
        );
        
        @file_put_contents( self::$log_file, $formatted_message, FILE_APPEND );
    }

    /**
     * Sets up the log file path and ensures the directory exists.
     *
     * @since 0.1.0
     */
    private static function set_log_file_path(): void {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/data-engine-logs';

        // Create the directory if it doesn't exist.
        if ( ! is_dir( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }

        // Check if the directory is writable.
        if ( is_dir( $log_dir ) && wp_is_writable( $log_dir ) ) {
            self::$log_file = $log_dir . '/debug.log';
            // Add an index.php to prevent directory listing.
            if ( ! file_exists( $log_dir . '/index.php' ) ) {
                file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden.' );
            }
        }
    }
}
