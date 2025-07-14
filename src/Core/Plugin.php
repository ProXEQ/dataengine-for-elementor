<?php
namespace DataEngine\Core;

/**
 * Main Plugin Class.
 *
 * The main class that initiates and runs the plugin.
 *
 * @since 0.1.0
 */
final class Plugin {

    private static ?self $_instance = null;
    public ?\DataEngine\Engine\Parser $parser = null;
    public ?\DataEngine\Engine\Data_Provider $data_provider = null;

    public static function instance(): self {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        // We hook into 'plugins_loaded' which is the standard, reliable way
        // to initialize a plugin. It runs after all plugins are loaded.
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }
    
    /**
     * Initialize the plugin.
     * This method is the true entry point for our plugin's functionality.
     *
     * @since 0.1.0
     */
    public function init(): void {
        // First, check for compatibility. If not compatible, do nothing more.
        if ( ! $this->is_compatible() ) {
            return;
        }
        
        // Now that we know the environment is correct, we can safely load our components.
        $this->data_provider = new \DataEngine\Engine\Data_Provider();
        $this->parser = new \DataEngine\Engine\Parser( $this->data_provider );

        // Initialize components that add hooks.
        $this->init_components();
    }
    
    /**
     * Initialize plugin components that add WordPress/Elementor hooks.
     *
     * @since 0.1.0
     */
    private function init_components(): void {
        if ( is_admin() ) {
            new \DataEngine\Core\Settings_Page();
        }
        
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_widget_category' ] );
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
    
    }

    /**
     * Compatibility Checks.
     *
     * @since 0.1.0
     * @return bool
     */
    public function is_compatible(): bool {
        // Check if Elementor is active.
        if ( ! did_action( 'elementor/loaded' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_missing_elementor' ] );
            return false;
        }
        
        // Check if ACF is active.
        if ( ! class_exists('ACF') ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_missing_acf' ] );
            return false;
        }

        return true;
    }


    public function register_widget_category( \Elementor\Elements_Manager $elements_manager ): void {
        $elements_manager->add_category( 'data-engine', [ 'title' => 'DataEngine' ] );
    }
    
    public function register_widgets( \Elementor\Widgets_Manager $widgets_manager ): void {
        $widgets_manager->register( new \DataEngine\Widgets\Dynamic_Content() );
        $widgets_manager->register( new \DataEngine\Widgets\Dynamic_Repeater() );
    }
    
    // Placeholder methods for admin notices.
    public function admin_notice_missing_elementor() {
        echo '<div class="error"><p>DataEngine: <strong>Elementor</strong> is not installed or activated. The plugin will not work.</p></div>';
    }
    public function admin_notice_missing_acf() {
        echo '<div class="error"><p>DataEngine: <strong>Advanced Custom Fields (ACF)</strong> is not installed or activated. The plugin will not work.</p></div>';
    }
}
