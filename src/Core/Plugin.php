<?php
namespace DataEngine\Core;

/**
 * Main Plugin Class.
 *
 * The main class that initiates and runs the plugin.
 *
 * @since 0.1.0
 */
final class Plugin
{

    private static ?self $_instance = null;
    public ?\DataEngine\Engine\Parser $parser = null;
    public ?\DataEngine\Engine\Data_Provider $data_provider = null;
    public ?Cache_Manager $cache_manager = null;

    public static function instance(): self
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct()
    {
        $this->cache_manager = new Cache_Manager();
        add_action('plugins_loaded', [$this, 'init']);
        add_action('de_cleanup_temp_files', [$this, 'cleanup_temp_files']);
    }

    /**
     * Initialize the plugin.
     * This method is the true entry point for our plugin's functionality.
     *
     * @since 0.1.0
     */
    public function init(): void
    {
        // First, check for compatibility. If not compatible, do nothing more.
        if (!$this->is_compatible()) {
            return;
        }

        // Now that we know the environment is correct, we can safely load our components.
        $this->data_provider = new \DataEngine\Engine\Data_Provider();
        $this->parser = new \DataEngine\Engine\Parser($this->data_provider);

        // Initialize components that add hooks.
        $this->init_components();
        add_action('save_post', [$this, 'clear_post_cache']);
    }

    /**
     * Initialize plugin components that add WordPress/Elementor hooks.
     *
     * @since 0.1.0
     */
    private function init_components(): void
    {
        if (is_admin()) {
            new \DataEngine\Core\Settings_Page();
        }

        add_action('acf/include_field_types', [$this, 'register_acf_field_types']);
        new \DataEngine\Core\Ajax_Handlers();

        add_action('wp_ajax_data_engine_get_data_dictionary', [$this, 'admin_ajax_get_data_dictionary']);

        add_action('elementor/elements/categories_registered', [$this, 'register_widget_category']);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_editor_scripts']);

        add_action('wp_loaded', [$this, 'schedule_cleanup']);
    }

    /**
     * Compatibility Checks.
     *
     * @since 0.1.0
     * @return bool
     */
    public function is_compatible(): bool
    {
        // Check if Elementor is active.
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', [$this, 'admin_notice_missing_elementor']);
            return false;
        }

        // Check if ACF is active.
        if (!class_exists('ACF')) {
            add_action('admin_notices', [$this, 'admin_notice_missing_acf']);
            return false;
        }

        return true;
    }


    public function register_widget_category(\Elementor\Elements_Manager $elements_manager): void
    {
        $elements_manager->add_category('data-engine', ['title' => 'DataEngine']);
    }

    public function register_widgets(\Elementor\Widgets_Manager $widgets_manager): void
    {
        $widgets_manager->register(new \DataEngine\Widgets\Dynamic_Content());
        $widgets_manager->register(new \DataEngine\Widgets\Dynamic_Repeater());
    }

    // Placeholder methods for admin notices.
    public function admin_notice_missing_elementor()
    {
        echo '<div class="error"><p>DataEngine: <strong>Elementor</strong> is not installed or activated. The plugin will not work.</p></div>';
    }
    public function admin_notice_missing_acf()
    {
        echo '<div class="error"><p>DataEngine: <strong>Advanced Custom Fields (ACF)</strong> is not installed or activated. The plugin will not work.</p></div>';
    }

    public function clear_post_cache(int $post_id): void
    {
        // For now, we clear all caches. This is the safest approach.
        // A more granular approach could be developed later if needed.
        $this->cache_manager->clear_all();
    }

    // !EDYTOR

    public function enqueue_editor_scripts(): void
    {
        $script_handle = 'data-engine-editor';
        wp_enqueue_style(
            'data-engine-editor-styles',
            plugin_dir_url(DATA_ENGINE_FILE) . 'assets/css/editor.css',
            [],
            DATA_ENGINE_VERSION
        );
        wp_enqueue_style(
            'data-engine-live-editor',
            plugin_dir_url(DATA_ENGINE_FILE) . 'assets/css/live-editor.css',
            [],
            DATA_ENGINE_VERSION
        );
        wp_enqueue_script(
            $script_handle,
            plugin_dir_url(DATA_ENGINE_FILE) . 'assets/js/editor.bundle.js',
            ['jquery'],
            DATA_ENGINE_VERSION,
            true
        );

        // --- AKTUALIZACJA: Ponownie dodajemy nonce dla naszego nowego punktu AJAX ---
        wp_localize_script(
            $script_handle,
            'DataEngineEditorConfig',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('data-engine-editor-nonce'),
            ]
        );
    }


    public function admin_ajax_get_data_dictionary(): void
    {
        check_ajax_referer('data-engine-editor-nonce', 'nonce');

        \DataEngine\Utils\Logger::log('--- AJAX Request for Data Dictionary Received ---');
        \DataEngine\Utils\Logger::log('POST Data Received: ' . print_r($_POST, true));

        // --- NEW: Use the same context as %post:ID% ---
        $post_id = 0;

        // Priority 1: Use context_post_id (matches %post:ID% logic)
        if (!empty($_POST['context_post_id'])) {
            $post_id = absint($_POST['context_post_id']);
            \DataEngine\Utils\Logger::log('Using context_post_id (matches %post:ID%): ' . $post_id);
        }
        // Priority 2: Traditional fallbacks
        elseif (!empty($_POST['preview_id'])) {
            $post_id = absint($_POST['preview_id']);
            \DataEngine\Utils\Logger::log('Using preview_id fallback: ' . $post_id);
        } elseif (!empty($_POST['post_id'])) {
            $post_id = absint($_POST['post_id']);
            \DataEngine\Utils\Logger::log('Using post_id fallback: ' . $post_id);
        }

        if (!$post_id) {
            wp_send_json_error(['message' => 'Could not determine context.']);
            return;
        }

        // Log context information for debugging
        $template_id = !empty($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $is_preview = !empty($_POST['is_preview']) ? 'true' : 'false';
        \DataEngine\Utils\Logger::log("Context - Post ID: {$post_id}, Template ID: {$template_id}, Is Preview: {$is_preview}");

        $repeater_context_field = isset($_POST['repeater_context_field']) ? sanitize_text_field($_POST['repeater_context_field']) : null;

        $dictionary = [
            'post' => $this->data_provider->get_all_post_fields_for_editor(),
            'acf' => [],
            'sub' => [],
            'filters' => $this->get_available_filters()
        ];

        // Get ACF fields for the resolved post
        $fields = get_field_objects($post_id);
        \DataEngine\Utils\Logger::log('ACF fields found for post ' . $post_id . ': ' . count($fields));

        // If no fields found, try fallback methods
        if (empty($fields)) {
            \DataEngine\Utils\Logger::log('No fields found for post ID: ' . $post_id . '. Trying fallback methods...');

            // Fallback 1: Get all available field groups
            $field_groups = acf_get_field_groups();

            if ($field_groups) {
                foreach ($field_groups as $group) {
                    $group_fields = acf_get_fields($group['key']);

                    if ($group_fields) {
                        foreach ($group_fields as $field) {
                            $fields[$field['name']] = [
                                'name' => $field['name'],
                                'label' => $field['label'],
                                'type' => $field['type'],
                                'key' => $field['key']
                            ];

                            if ($field['type'] === 'repeater' && !empty($field['sub_fields'])) {
                                $fields[$field['name']]['sub_fields'] = $field['sub_fields'];
                            }
                        }
                    }
                }
                \DataEngine\Utils\Logger::log('Found ' . count($fields) . ' fields using fallback method.');
            }
        }

        // Process fields
        if ($fields) {
            foreach ($fields as $field) {
                if ($field['type'] === 'repeater')
                    continue;

                $field_data = [
                    'name' => $field['name'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'properties' => []
                ];

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
                            ['name' => 'permalink', 'label' => 'Permalink'],
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
                            ['name' => 'class', 'label' => 'Icon CSS Class'],
                            ['name' => 'url', 'label' => 'Icon URL'],
                            ['name' => 'type', 'label' => 'Icon Type'],
                        ];
                        break;
                }
                $dictionary['acf'][] = $field_data;
            }
        }

        // Handle repeater context
        if ($repeater_context_field) {
            $repeater_field_object = null;

            if (isset($fields[$repeater_context_field])) {
                $repeater_field_object = $fields[$repeater_context_field];
            } else {
                $repeater_field_object = acf_get_field($repeater_context_field);
            }

            if ($repeater_field_object && $repeater_field_object['type'] === 'repeater' && !empty($repeater_field_object['sub_fields'])) {
                foreach ($repeater_field_object['sub_fields'] as $sub_field) {
                    $sub_field_data = [
                        'name' => $sub_field['name'],
                        'label' => $sub_field['label'],
                        'type' => $sub_field['type'],
                        'properties' => []
                    ];

                    switch ($sub_field['type']) {
                        case 'taxonomy':
                            $sub_field_data['properties'] = [
                                ['name' => 'term_id', 'label' => 'Term ID'],
                                ['name' => 'name', 'label' => 'Term Name'],
                                ['name' => 'slug', 'label' => 'Term Slug'],
                                ['name' => 'taxonomy', 'label' => 'Taxonomy Name'],
                            ];
                            break;
                        case 'image':
                        case 'file':
                            $sub_field_data['properties'] = [
                                ['name' => 'url', 'label' => 'URL'],
                                ['name' => 'alt', 'label' => 'Alt Text'],
                                ['name' => 'title', 'label' => 'Title'],
                                ['name' => 'ID', 'label' => 'Attachment ID'],
                            ];
                            break;
                        case 'post_object':
                        case 'page_link':
                            $sub_field_data['properties'] = [
                                ['name' => 'ID', 'label' => 'Post ID'],
                                ['name' => 'post_title', 'label' => 'Post Title'],
                                ['name' => 'permalink', 'label' => 'Permalink'],
                            ];
                            break;
                        case 'user':
                            $sub_field_data['properties'] = [
                                ['name' => 'ID', 'label' => 'User ID'],
                                ['name' => 'display_name', 'label' => 'Display Name'],
                                ['name' => 'user_email', 'label' => 'User Email'],
                            ];
                            break;
                        case 'icon_picker':
                            $sub_field_data['properties'] = [
                                ['name' => 'class', 'label' => 'Icon CSS Class'],
                                ['name' => 'url', 'label' => 'Icon URL'],
                                ['name' => 'type', 'label' => 'Icon Type'],
                            ];
                            break;
                    }

                    $dictionary['sub'][] = $sub_field_data;
                }
            }
        }

        if (empty($dictionary['sub'])) {
            $dictionary['sub'] = [
                ['name' => 'sub_field_name', 'label' => 'Repeater Sub Field (Context Required)']
            ];
        }

        \DataEngine\Utils\Logger::log('Final dictionary counts - ACF: ' . count($dictionary['acf']) . ', SUB: ' . count($dictionary['sub']));
        \DataEngine\Utils\Logger::log('--- AJAX Request Finished ---');
        wp_send_json_success($dictionary);
    }

    private function get_available_filters(): array
    {
        return [
            [
                'name' => 'uppercase',
                'label' => 'Convert to uppercase',
                'description' => 'Converts text to uppercase letters',
                'args' => []
            ],
            [
                'name' => 'lowercase',
                'label' => 'Convert to lowercase',
                'description' => 'Converts text to lowercase letters',
                'args' => []
            ],
            [
                'name' => 'truncate',
                'label' => 'Truncate text',
                'description' => 'Limits text to specified length',
                'args' => [
                    ['name' => 'length', 'type' => 'number', 'default' => 100]
                ]
            ],
            [
                'name' => 'date_format',
                'label' => 'Format date',
                'description' => 'Formats date according to PHP date format',
                'args' => [
                    ['name' => 'format', 'type' => 'string', 'default' => 'Y-m-d']
                ]
            ],
            [
                'name' => 'strip_tags',
                'label' => 'Strip HTML tags',
                'description' => 'Removes HTML tags from text',
                'args' => []
            ],
            [
                'name' => 'limit',
                'label' => 'Limit terms',
                'description' => 'limits the number of terms in taxonomy displayed',
                'args' => [
                    ['name' => 'limit', 'type' => 'number', 'default' => 10]
                ]
            ],
            [
                'name' => 'exclude',
                'label' => 'Exclude terms',
                'description' => 'excludes specific terms by IDs',
                'args' => [
                    ['name' => 'ids', 'type' => 'string', 'default' => '']
                ]
            ],
            [
                'name' => 'separator',
                'label' => 'Change separator',
                'description' => 'changes the separator between terms',
                'args' => [
                    ['name' => 'separator', 'type' => 'string', 'default' => '" / "']
                ]
            ],
            [
                'name' => 'wrap',
                'label' => 'Wrap text',
                'description' => 'wraps text in specified HTML tags',
                'args' => [
                    ['name' => 'prefix', 'type' => 'string', 'default' => '<span>'],
                    ['name' => 'suffix', 'type' => 'string', 'default' => '</span>']
                ]
            ],
            [
                'name' => 'sort',
                'label' => 'Sort terms',
                'description' => 'sorts taxonomy terms by specified property',
                'args' => [
                    ['name' => 'sort by', 'type' => 'string', 'default' => 'name']
                ]
            ]
        ];
    }
    public function register_acf_field_types(): void
    {
        if (class_exists('acf_field')) {
            new \DataEngine\ACF\Import_Export_Field();
        }
    }

    public function schedule_cleanup(): void
    {
        if (!wp_next_scheduled('de_cleanup_temp_files')) {
            wp_schedule_event(time(), 'hourly', 'de_cleanup_temp_files');
        }
    }

    public function cleanup_temp_files(): void
    {
        $ajax_handlers = new \DataEngine\Core\Ajax_Handlers();
        $ajax_handlers->cleanup_temp_files();
    }

}
