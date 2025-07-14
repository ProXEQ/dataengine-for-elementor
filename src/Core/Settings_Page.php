<?php
namespace DataEngine\Core;

use DataEngine\Utils\Logger;

/**
 * Settings_Page Class.
 *
 * Handles the creation of the admin settings page for the plugin,
 * registers settings, and renders the fields.
 *
 * @since 0.1.0
 */
class Settings_Page {

    /**
     * The unique identifier for the settings group.
     * @var string
     */
    private const SETTINGS_GROUP = 'data_engine_settings';

    /**
     * The option name in the wp_options table.
     * @var string
     */
    public const OPTION_NAME = 'data_engine_options';

    /**
     * Constructor. Hooks into WordPress admin actions.
     *
     * @since 0.1.0
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Adds the settings page to the WordPress admin menu under "Settings".
     *
     * @since 0.1.0
     */
    public function add_admin_menu(): void {
        add_options_page(
            esc_html__( 'DataEngine For Elementor', 'data-engine-for-elementor' ),
            esc_html__( 'DataEngine', 'data-engine-for-elementor' ),
            'manage_options',
            'data-engine-for-elementor',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Renders the HTML for the settings page.
     *
     * @since 0.1.0
     */
    public function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( self::SETTINGS_GROUP );
                do_settings_sections( 'data-engine-for-elementor' );
                submit_button( esc_html__( 'Save Settings', 'data-engine-for-elementor' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Registers the settings, sections, and fields with the WordPress Settings API.
     *
     * @since 0.1.0
     */
    public function register_settings(): void {
        // --- NOWA LOGIKA: Obsługa przycisku do czyszczenia cache ---
        if ( isset( $_GET['de_action'] ) && $_GET['de_action'] === 'clear_cache' && check_admin_referer('de_clear_cache_nonce') ) {
            Plugin::instance()->cache_manager->clear_all();
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('DataEngine cache has been cleared.', 'data-engine-for-elementor') . '</p></div>';
            });
        }
        
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAME,
            [ 'sanitize_callback' => [ $this, 'sanitize_options' ] ]
        );
        
        // --- Sekcja General (istniejąca) ---
        add_settings_section( 'data_engine_general_section', esc_html__( 'General Settings', 'data-engine-for-elementor' ), '__return_false', 'data-engine-for-elementor' );
        add_settings_field( 'data_engine_debug_mode', esc_html__( 'Debug Mode', 'data-engine-for-elementor' ), [ $this, 'render_debug_mode_field' ], 'data-engine-for-elementor', 'data_engine_general_section' );

        // --- NOWA SEKCJA: Caching ---
        add_settings_section( 'data_engine_caching_section', esc_html__( 'Performance & Caching', 'data-engine-for-elementor' ), '__return_false', 'data-engine-for-elementor' );
        add_settings_field( 'data_engine_enable_caching', esc_html__( 'Enable Widget Cache', 'data-engine-for-elementor' ), [ $this, 'render_enable_caching_field' ], 'data-engine-for-elementor', 'data_engine_caching_section' );
        add_settings_field( 'data_engine_cache_expiration', esc_html__( 'Cache Expiration', 'data-engine-for-elementor' ), [ $this, 'render_cache_expiration_field' ], 'data-engine-for-elementor', 'data_engine_caching_section' );
        add_settings_field( 'data_engine_clear_cache', esc_html__( 'Clear Cache', 'data-engine-for-elementor' ), [ $this, 'render_clear_cache_button' ], 'data-engine-for-elementor', 'data_engine_caching_section' );
    }

    public function render_enable_caching_field(): void {
        $options = get_option( self::OPTION_NAME, [] );
        $caching_enabled = $options['enable_caching'] ?? false;
        ?>
        <label for="data_engine_enable_caching">
            <input type="checkbox" id="data_engine_enable_caching" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_caching]" value="1" <?php checked( $caching_enabled, 1 ); ?>>
            <?php esc_html_e( 'Enable server-side caching for widgets.', 'data-engine-for-elementor' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Dramatically improves performance by caching the final HTML output of widgets. Cache is automatically cleared when a post is saved.', 'data-engine-for-elementor' ); ?></p>
        <?php
    }

    public function render_cache_expiration_field(): void {
        $options = get_option( self::OPTION_NAME, [] );
        $expiration = $options['cache_expiration'] ?? HOUR_IN_SECONDS;
        ?>
        <input type="number" id="data_engine_cache_expiration" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[cache_expiration]" value="<?php echo esc_attr( $expiration ); ?>" class="small-text">
        <p class="description"><?php esc_html_e( 'Time in seconds for how long the cache should be stored. Default is 3600 (1 hour).', 'data-engine-for-elementor' ); ?></p>
        <?php
    }

    public function render_clear_cache_button(): void {
        $url = wp_nonce_url( admin_url('options-general.php?page=data-engine-for-elementor&de_action=clear_cache'), 'de_clear_cache_nonce' );
        ?>
        <a href="<?php echo esc_url($url); ?>" class="button button-secondary"><?php esc_html_e( 'Clear All DataEngine Caches', 'data-engine-for-elementor' ); ?></a>
        <p class="description"><?php esc_html_e( 'Manually clear all cached widget outputs. This is useful during development or after major changes.', 'data-engine-for-elementor' ); ?></p>
        <?php
    }
    
    /**
     * Renders the checkbox for the "Debug Mode" setting.
     *
     * @since 0.1.0
     */
    public function render_debug_mode_field(): void {
        $options = get_option( self::OPTION_NAME, [] );
        $debug_mode_enabled = $options['debug_mode'] ?? false;
        ?>
        <label for="data_engine_debug_mode">
            <input type="checkbox" id="data_engine_debug_mode" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[debug_mode]" value="1" <?php checked( $debug_mode_enabled, 1 ); ?>>
            <?php esc_html_e( 'Enable logging to the debug file.', 'data-engine-for-elementor' ); ?>
        </label>
        <p class="description">
            <?php
            echo wp_kses_post( 
                __( 'When enabled, the plugin will log its internal operations to <code>wp-content/uploads/data-engine-logs/debug.log</code>. Use this only for troubleshooting.', 'data-engine-for-elementor' ) 
            );
            ?>
        </p>
        <?php
    }

    public function sanitize_options( array $input ): array {
        $sanitized_options = [];
        $sanitized_options['debug_mode'] = isset( $input['debug_mode'] ) && '1' === $input['debug_mode'];
        
        $sanitized_options['enable_caching'] = isset( $input['enable_caching'] ) && '1' === $input['enable_caching'];
        $sanitized_options['cache_expiration'] = isset( $input['cache_expiration'] ) ? absint( $input['cache_expiration'] ) : HOUR_IN_SECONDS;
        
        return $sanitized_options;
    }
}
