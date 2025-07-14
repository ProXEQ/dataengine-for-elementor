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
                // Output security fields for the registered setting group.
                settings_fields( self::SETTINGS_GROUP );
                // Output the settings sections and their fields.
                do_settings_sections( 'data-engine-for-elementor' );
                // Output save settings button.
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
        // Register the main option group.
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAME,
            [ 'sanitize_callback' => [ $this, 'sanitize_options' ] ]
        );

        // Add the main settings section.
        add_settings_section(
            'data_engine_general_section',
            esc_html__( 'General Settings', 'data-engine-for-elementor' ),
            '__return_false', // No callback needed for the section description.
            'data-engine-for-elementor'
        );

        // Add the "Debug Mode" field to our section.
        add_settings_field(
            'data_engine_debug_mode',
            esc_html__( 'Debug Mode', 'data-engine-for-elementor' ),
            [ $this, 'render_debug_mode_field' ],
            'data-engine-for-elementor',
            'data_engine_general_section'
        );
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

    /**
     * Sanitizes the options before saving them to the database.
     *
     * @since 0.1.0
     * @param array $input The raw input from the settings form.
     * @return array The sanitized options.
     */
    public function sanitize_options( array $input ): array {
        $sanitized_options = [];
        // Sanitize the debug_mode checkbox. It's either true (if '1') or false.
        $sanitized_options['debug_mode'] = isset( $input['debug_mode'] ) && '1' === $input['debug_mode'];
        
        Logger::log( 'Settings saved. Debug mode is now ' . ($sanitized_options['debug_mode'] ? 'ON' : 'OFF') . '.', 'INFO' );

        return $sanitized_options;
    }
}
