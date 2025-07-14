<?php
namespace DataEngine\Core;

/**
 * Cache_Manager Class.
 *
 * Handles widget output caching using WordPress Transients for maximum performance.
 *
 * @since 0.1.0
 */
class Cache_Manager {

    private const CACHE_PREFIX = 'de_cache_';
    private const DEFAULT_EXPIRATION = HOUR_IN_SECONDS;
    private array $options = [];

    public function __construct() {
        $this->options = get_option( Settings_Page::OPTION_NAME, [] );
    }

    /**
     * Checks if the caching system is globally enabled in the settings.
     */
    public function is_enabled(): bool {
        return $this->options['enable_caching'] ?? false;
    }

    /**
     * Retrieves cached HTML for a given key.
     * @return string|false The cached HTML or false if not found.
     */
    public function get( string $key ) {
        return get_transient( self::CACHE_PREFIX . $key );
    }

    /**
     * Caches the generated HTML.
     */
    public function set( string $key, string $html ): void {
        $expiration = $this->options['cache_expiration'] ?? self::DEFAULT_EXPIRATION;
        set_transient( self::CACHE_PREFIX . $key, $html, absint( $expiration ) );
    }

    /**
     * Generates a unique cache key for a specific widget instance.
     * The key depends on the post ID and the widget's settings.
     */
    public function generate_key( \Elementor\Widget_Base $widget ): string {
        $post_id = get_the_ID();
        // A hash of the settings ensures that if settings change, the cache is invalidated.
        $settings_hash = md5( json_encode( $widget->get_settings_for_display() ) );
        return "{$post_id}_{$widget->get_id()}_{$settings_hash}";
    }

    /**
     * Clears all DataEngine transients from the database.
     * This is a powerful "nuke" option for debugging or major site changes.
     */
    public function clear_all(): void {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );
         $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . self::CACHE_PREFIX . '%'
            )
        );
    }
}
