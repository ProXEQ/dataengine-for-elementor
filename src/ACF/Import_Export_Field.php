<?php
namespace DataEngine\ACF;

use DataEngine\Utils\Logger;

/**
 * ACF Import/Export Field Extension
 * 
 * Adds import/export functionality to ACF Repeater and Flexible Content fields
 * 
 * @since 1.2.0
 */
class Import_Export_Field extends \acf_field
{

    /**
     * Field type name
     */
    public $name = 'import_export';

    /**
     * Field type label
     */
    public $label = 'Import/Export Controls';

    /**
     * Field type category
     */
    public $category = 'layout';

    /**
     * Field type settings
     */
    public $defaults = [
        'target_field' => '',
        'allowed_formats' => ['csv', 'json'],
        'show_preview' => true,
        'show_export' => true,
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Enqueue assets only in admin
        add_action('acf/input/admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Render field input
     */
    public function render_field($field)
    {
        $field_key = $field['key'];
        $post_id = get_the_ID();
        $target_field = $field['target_field'] ?? '';

        // Check if target field exists and is repeater/flexible
        if (!$this->is_valid_target_field($target_field)) {
            echo '<div class="acf-notice -error">';
            echo '<p>Import/Export field requires a valid Repeater or Flexible Content target field.</p>';
            echo '</div>';
            return;
        }

        echo '<div class="acf-import-export-controls" data-field="' . esc_attr($field_key) . '" data-target="' . esc_attr($target_field) . '">';

        // Import section
        if (current_user_can('edit_posts')) {
            echo '<div class="import-section">';
            echo '<h4><i class="dashicons dashicons-upload"></i> Import Data</h4>';
            echo '<div class="import-controls">';
            echo '<input type="file" id="import-file-' . esc_attr($field_key) . '" accept=".csv,.json,.xlsx" class="import-file-input" />';
            echo '<div class="import-buttons">';
            echo '<button type="button" class="button import-preview-btn" data-field="' . esc_attr($field_key) . '">Preview</button>';
            echo '<button type="button" class="button button-primary import-btn" data-field="' . esc_attr($field_key) . '">Import</button>';
            echo '</div>';
            echo '</div>';
            echo '<div class="import-preview" id="preview-' . esc_attr($field_key) . '" style="display:none;"></div>';
            echo '</div>';
        }

        // Export section
        echo '<div class="export-section">';
        echo '<h4><i class="dashicons dashicons-download"></i> Export Data</h4>';
        echo '<div class="export-buttons">';

        if (in_array('csv', $field['allowed_formats'])) {
            echo '<button type="button" class="button export-csv-btn" data-field="' . esc_attr($field_key) . '">Export CSV</button>';
        }

        if (in_array('json', $field['allowed_formats'])) {
            echo '<button type="button" class="button export-json-btn" data-field="' . esc_attr($field_key) . '">Export JSON</button>';
        }

        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render field settings
     */
    public function render_field_settings($field)
    {
        // Target field selection
        acf_render_field_setting($field, [
            'label' => 'Target Field',
            'instructions' => 'Select the Repeater or Flexible Content field to import/export data for',
            'type' => 'select',
            'name' => 'target_field',
            'choices' => $this->get_available_target_fields(),
            'required' => true,
        ]);

        // Allowed formats
        acf_render_field_setting($field, [
            'label' => 'Allowed Formats',
            'instructions' => 'Select which file formats are allowed for import/export',
            'type' => 'checkbox',
            'name' => 'allowed_formats',
            'choices' => [
                'csv' => 'CSV (.csv)',
                'json' => 'JSON (.json)',
                'xlsx' => 'Excel (.xlsx)',
            ],
            'default_value' => ['csv', 'json'],
        ]);

        // Show preview option
        acf_render_field_setting($field, [
            'label' => 'Show Preview',
            'instructions' => 'Allow users to preview data before importing',
            'type' => 'true_false',
            'name' => 'show_preview',
            'default_value' => 1,
        ]);

        // Show export option
        acf_render_field_setting($field, [
            'label' => 'Show Export',
            'instructions' => 'Show export buttons',
            'type' => 'true_false',
            'name' => 'show_export',
            'default_value' => 1,
        ]);
    }

    /**
     * Get available target fields (repeater/flexible content)
     */
    /**
     * Get available target fields (repeater/flexible content) from current field group
     */
    private function get_available_target_fields(): array
    {
        $choices = ['' => '-- Select Target Field --'];

        // Try to get from current field group first
        $current_field_group = $this->get_current_field_group();
        
        if ($current_field_group) {
            Logger::log('Found current field group: ' . $current_field_group['title'], 'DEBUG');
            $fields = acf_get_fields($current_field_group);
            
            if ($fields) {
                Logger::log('Found ' . count($fields) . ' fields in current group', 'DEBUG');
                foreach ($fields as $field) {
                    Logger::log('Field: ' . $field['name'] . ' (type: ' . $field['type'] . ')', 'DEBUG');
                    // 🔥 ADD: Support for flexible_content
                    if (in_array($field['type'], ['repeater', 'flexible_content'])) {
                        $choices[$field['key']] = $field['label'] . ' (' . $field['name'] . ')';
                    }
                }
            }
        } else {
            Logger::log('No current field group found, trying fallback', 'DEBUG');
        }

        // Fallback: If no fields found in current group, get from all groups
        if (count($choices) === 1) {
            Logger::log('No fields found in current group, falling back to all groups', 'DEBUG');
            
            $field_groups = acf_get_field_groups();
            Logger::log('Found ' . count($field_groups) . ' field groups total', 'DEBUG');
            
            foreach ($field_groups as $group) {
                $fields = acf_get_fields($group);
                if ($fields) {
                    foreach ($fields as $field) {
                        // 🔥 ADD: Support for flexible_content
                        if (in_array($field['type'], ['repeater', 'flexible_content'])) {
                            $group_name = $group['title'] ?? 'Unknown Group';
                            $choices[$field['key']] = $group_name . ' → ' . $field['label'] . ' (' . $field['name'] . ')';
                            Logger::log('Added fallback field: ' . $field['name'] . ' from group: ' . $group_name, 'DEBUG');
                        }
                    }
                }
            }
        }

        // If still no fields found
        if (count($choices) === 1) {
            $choices[''] = '-- No Repeater/Flexible Content fields found --';
            Logger::log('No repeater/flexible content fields found in any group', 'WARNING');
        }

        Logger::log('Final choices count: ' . count($choices), 'DEBUG');
        return $choices;
    }

    /**
     * Get current field group being edited
     */
    private function get_current_field_group(): ?array
    {
        try {
            global $post;

            // Debug: Log current context
            Logger::log('Getting current field group. Context info:', 'DEBUG');
            Logger::log('- $_GET: ' . print_r($_GET, true), 'DEBUG');
            Logger::log('- $_POST: ' . print_r($_POST, true), 'DEBUG');
            Logger::log('- Global $post: ' . ($post ? $post->post_type . ' ID:' . $post->ID : 'null'), 'DEBUG');

            // Method 1: Check if we're editing a field group directly
            if (isset($_GET['post']) && is_numeric($_GET['post'])) {
                $post_id = absint($_GET['post']);
                $post_type = get_post_type($post_id);
                Logger::log('Checking URL post ID: ' . $post_id . ' (type: ' . $post_type . ')', 'DEBUG');

                if ($post_type === 'acf-field-group') {
                    $field_group = acf_get_field_group($post_id);
                    if ($field_group) {
                        Logger::log('Found field group from URL: ' . $field_group['title'], 'DEBUG');
                        return $field_group;
                    }
                }
            }

            // Method 2: Check current screen
            if (function_exists('get_current_screen')) {
                $screen = get_current_screen();
                Logger::log('Current screen: ' . ($screen ? $screen->id : 'null'), 'DEBUG');

                if ($screen && in_array($screen->id, ['acf-field-group', 'acf_page_acf-field-group'])) {
                    if (isset($_GET['post']) && is_numeric($_GET['post'])) {
                        $field_group_id = absint($_GET['post']);
                        $field_group = acf_get_field_group($field_group_id);
                        if ($field_group) {
                            Logger::log('Found field group from screen: ' . $field_group['title'], 'DEBUG');
                            return $field_group;
                        }
                    }
                }
            }

            // Method 3: Get from global $post
            if ($post && $post->post_type === 'acf-field-group') {
                $field_group = acf_get_field_group($post->ID);
                if ($field_group) {
                    Logger::log('Found field group from global post: ' . $field_group['title'], 'DEBUG');
                    return $field_group;
                }
            }

            // Method 4: AJAX context - check for field group in POST data
            if (defined('DOING_AJAX') && DOING_AJAX) {
                Logger::log('In AJAX context, checking for field group data', 'DEBUG');

                // Check various POST keys that might contain field group ID
                $possible_keys = ['field_group', 'post_id', 'post', 'acf_field_group'];

                foreach ($possible_keys as $key) {
                    if (isset($_POST[$key]) && is_numeric($_POST[$key])) {
                        $field_group_id = absint($_POST[$key]);
                        $field_group = acf_get_field_group($field_group_id);
                        if ($field_group) {
                            Logger::log('Found field group from POST[' . $key . ']: ' . $field_group['title'], 'DEBUG');
                            return $field_group;
                        }
                    }
                }
            }

            // Method 5: Try to get from ACF internal state
            if (function_exists('acf_get_form_data')) {
                $form_data = acf_get_form_data();
                if (is_array($form_data)) {
                    Logger::log('ACF form data: ' . print_r($form_data, true), 'DEBUG');

                    if (isset($form_data['field_group'])) {
                        $field_group = acf_get_field_group($form_data['field_group']);
                        if ($field_group) {
                            Logger::log('Found field group from ACF form data: ' . $field_group['title'], 'DEBUG');
                            return $field_group;
                        }
                    }
                }
            }

            // Method 6: Check HTTP referer for field group edit page
            if (isset($_SERVER['HTTP_REFERER'])) {
                $referer = $_SERVER['HTTP_REFERER'];
                Logger::log('HTTP Referer: ' . $referer, 'DEBUG');

                if (preg_match('/post=(\d+)/', $referer, $matches)) {
                    $post_id = absint($matches[1]);
                    if (get_post_type($post_id) === 'acf-field-group') {
                        $field_group = acf_get_field_group($post_id);
                        if ($field_group) {
                            Logger::log('Found field group from referer: ' . $field_group['title'], 'DEBUG');
                            return $field_group;
                        }
                    }
                }
            }

            Logger::log('No field group found in current context', 'WARNING');
            return null;

        } catch (Exception $e) {
            Logger::log('Error getting current field group: ' . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Check if target field is valid
     */
    private function is_valid_target_field(string $field_key): bool
    {
        if (empty($field_key)) {
            return false;
        }

        $field = acf_get_field($field_key);

        return $field && in_array($field['type'], ['repeater', 'flexible_content']);
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets(): void
{
    wp_enqueue_script(
        'de-import-export-field',
        plugin_dir_url(DATA_ENGINE_FILE) . 'assets/js/import-export-field.js',
        ['jquery', 'acf-input'],
        DATA_ENGINE_VERSION,
        true
    );

    wp_enqueue_style(
        'de-import-export-field',
        plugin_dir_url(DATA_ENGINE_FILE) . 'assets/css/import-export-field.css',
        ['acf-input'],
        DATA_ENGINE_VERSION
    );

    // 🔥 POPRAW: Zmień z DEImportExport na deImportExport i dodaj fallback dla post_id
    wp_localize_script('de-import-export-field', 'deImportExport', [
        'ajaxurl' => admin_url('admin-ajax.php'), // 🔥 ZMIANA: ajaxurl zamiast ajax_url
        'nonce' => wp_create_nonce('de_import_export_nonce'),
        'post_id' => get_the_ID() ?: (isset($_GET['post']) ? intval($_GET['post']) : 0), // 🔥 DODANO: fallback
        'strings' => [ // 🔥 DODANO: komunikaty dla lepszego UX
            'confirm_overwrite' => __('This will overwrite existing data. Continue?', 'dataengine'),
            'import_success' => __('Data imported successfully', 'dataengine'),
            'export_success' => __('Data exported successfully', 'dataengine'),
            'error_occurred' => __('An error occurred', 'dataengine'),
        ]
    ]);
}
}
