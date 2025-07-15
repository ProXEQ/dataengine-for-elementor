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
    public ?Cache_Manager $cache_manager = null;

    public static function instance(): self {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
         $this->cache_manager = new Cache_Manager();
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
        add_action( 'save_post', [ $this, 'clear_post_cache' ] );
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

        add_action( 'wp_ajax_data_engine_get_data_dictionary', [ $this, 'admin_ajax_get_data_dictionary' ] );
    
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_widget_category' ] );
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
        add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor_scripts' ] );
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

    public function clear_post_cache( int $post_id ): void {
        // For now, we clear all caches. This is the safest approach.
        // A more granular approach could be developed later if needed.
        $this->cache_manager->clear_all();
    }

    // !EDYTOR

    public function enqueue_editor_scripts(): void {
        $script_handle = 'data-engine-editor';
        wp_enqueue_style(
            'data-engine-editor-styles',
            plugin_dir_url( DATA_ENGINE_FILE ) . 'assets/css/editor.css',
            [],
            DATA_ENGINE_VERSION
        );
        wp_enqueue_style(
            'data-engine-live-editor',
            plugin_dir_url( DATA_ENGINE_FILE ) . 'assets/css/live-editor.css',
            [],
            DATA_ENGINE_VERSION
        );
        wp_enqueue_script(
            $script_handle,
            plugin_dir_url( DATA_ENGINE_FILE ) . 'assets/js/editor.bundle.js',
            ['jquery'],
            DATA_ENGINE_VERSION,
            true
        );

        // --- AKTUALIZACJA: Ponownie dodajemy nonce dla naszego nowego punktu AJAX ---
        wp_localize_script(
            $script_handle,
            'DataEngineEditorConfig',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'data-engine-editor-nonce' ),
            ]
        );
    }

    public function admin_ajax_get_data_dictionary(): void {
        check_ajax_referer('data-engine-editor-nonce', 'nonce');
        
        $post_id = !empty($_POST['preview_id']) ? absint($_POST['preview_id']) : (!empty($_POST['post_id']) ? absint($_POST['post_id']) : 0);
        if (!$post_id) { 
            wp_send_json_error(['message' => 'Could not determine context.']);
            return;
        }

        // Initialize the dictionary with post fields using our new method
        $dictionary = [
            'post' => $this->data_provider->get_all_post_fields_for_editor(),
            'acf'  => [], // Start with an empty ACF array
            'sub'  => [], // Prepare for sub-fields in repeaters
        ];

        // Get ACF field objects assigned to this post
        $fields = get_field_objects($post_id);

        if ($fields) {
            foreach ($fields as $field) {
                // Base structure for every field
                $field_data = [
                    'name'       => $field['name'],
                    'label'      => $field['label'],
                    'type'       => $field['type'],
                    'properties' => [] // Start with empty properties
                ];
                
                // Add properties based on field type
                switch ($field['type']) {
                    case 'taxonomy':
                        $field_data['properties'] = [
                            ['name' => 'term_id', 'label' => 'Term ID'],
                            ['name' => 'name', 'label' => 'Term Name'],
                            ['name' => 'slug', 'label' => 'Term Slug'],
                            ['name' => 'taxonomy', 'label' => 'Taxonomy Name'],
                        ];
                        break;
                    case 'image':
                    case 'file':
                        $field_data['properties'] = [
                            ['name' => 'url', 'label' => 'URL'],
                            ['name' => 'alt', 'label' => 'Alt Text'],
                            ['name' => 'title', 'label' => 'Title'],
                            ['name' => 'ID', 'label' => 'Attachment ID'],
                        ];
                        break;
                    case 'post_object':
                    case 'page_link':
                        $field_data['properties'] = [
                            ['name' => 'ID', 'label' => 'Post ID'],
                            ['name' => 'post_title', 'label' => 'Post Title'],
                            ['name' => 'permalink', 'label' => 'Permalink (using get_permalink)'],
                        ];
                        break;
                    case 'user':
                         $field_data['properties'] = [
                            ['name' => 'ID', 'label' => 'User ID'],
                            ['name' => 'display_name', 'label' => 'Display Name'],
                            ['name' => 'user_email', 'label' => 'User Email'],
                        ];
                        break;
                    case 'icon_picker': 
                        $field_data['properties'] = [
                            ['name' => 'class', 'label' => 'Icon CSS Class (e.g., \'fas fa-home\')'],
                            ['name' => 'url', 'label' => 'Icon URL (for SVG/image icons)'],
                            ['name' => 'type', 'label' => 'Icon Type (e.g., \'dashicon\', \'url\')'],
                        ];
                        break;
                }
                
                $dictionary['acf'][] = $field_data;
            }
        }
        
        // Add sub-fields for repeater context (static for now, can be dynamic later)
        $dictionary['sub'] = [
            ['name' => 'sub_field_name', 'label' => 'Repeater Sub Field']
        ];

        wp_send_json_success($dictionary);
    }
    
}
