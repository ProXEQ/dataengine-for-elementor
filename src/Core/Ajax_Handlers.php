<?php
namespace DataEngine\Core;

use DataEngine\Utils\Logger;

/**
 * AJAX Handlers Class
 * 
 * Handles AJAX requests for Import/Export functionality
 * 
 * @since 1.2.0
 */
class Ajax_Handlers
{

    private Import_Export_Manager $import_export_manager;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->import_export_manager = new Import_Export_Manager();

        // Register AJAX handlers
        add_action('wp_ajax_de_import_repeater_data', [$this, 'handle_import_repeater_data']);
        add_action('wp_ajax_de_export_repeater_data', [$this, 'handle_export_repeater_data']);
        add_action('wp_ajax_de_preview_import_data', [$this, 'handle_preview_import_data']);
        add_action('wp_ajax_de_get_field_structure', [$this, 'handle_get_field_structure']);

        // Add file upload handler
        add_action('wp_ajax_de_upload_import_file', [$this, 'handle_upload_import_file']);
    }

    /**
     * Handle import repeater data
     */
    public function handle_import_repeater_data(): void
    {
        Logger::log("=== IMPORT AJAX START ===", 'DEBUG');
        Logger::log("POST data: " . print_r($_POST, true), 'DEBUG');
        Logger::log("FILES data: " . print_r($_FILES, true), 'DEBUG');
        Logger::log("Memory usage: " . memory_get_usage(true), 'DEBUG');
        try {
            // Security check
            check_ajax_referer('de_import_export_nonce', 'nonce');
            Logger::log("Nonce verification passed", 'DEBUG');

            // Permission check
            if (!current_user_can('edit_posts')) {
                Logger::log("Permission check failed", 'ERROR');
                wp_send_json_error([
                    'message' => 'Insufficient permissions to import data'
                ]);
                return;
            }
            Logger::log("Permission check passed", 'DEBUG');

            // Get parameters
            $field_key = sanitize_text_field($_POST['field_key'] ?? '');
            $post_id = absint($_POST['post_id'] ?? 0);
            $file_format = sanitize_text_field($_POST['file_format'] ?? 'csv');
            $overwrite_existing = (bool) ($_POST['overwrite_existing'] ?? false);

            Logger::log("=== FIELD DEBUG START ===", 'DEBUG');
            Logger::log("Field key from POST: {$field_key}", 'DEBUG');

            $field_object = get_field_object($field_key, $post_id);
            if ($field_object) {
                Logger::log("Field found - Name: " . $field_object['name'], 'DEBUG');
                Logger::log("Field found - Label: " . $field_object['label'], 'DEBUG');
                Logger::log("Field found - Type: " . $field_object['type'], 'DEBUG');
                Logger::log("Field found - Has sub_fields: " . (isset($field_object['sub_fields']) ? 'YES (' . count($field_object['sub_fields']) . ')' : 'NO'), 'DEBUG');
            } else {
                Logger::log("Field NOT found for key: {$field_key}", 'ERROR');
            }
            Logger::log("=== FIELD DEBUG END ===", 'DEBUG');

            Logger::log("Import request: field_key={$field_key}, post_id={$post_id}, format={$file_format}", 'INFO');

            // Validate parameters
            if (empty($field_key) || empty($post_id)) {
                wp_send_json_error([
                    'message' => 'Missing required parameters'
                ]);
                Logger::log("Missing required parameters", 'ERROR');
                return;
            }

            // Handle file upload
            if (!isset($_FILES['import_file'])) {
                wp_send_json_error([
                    'message' => 'No file uploaded'
                ]);
                Logger::log("No file uploaded", 'ERROR');
                return;
            }

            $file = $_FILES['import_file'];
            $upload_result = $this->handle_file_upload($file, $file_format);
            Logger::log("File upload result: " . print_r($upload_result, true), 'DEBUG');

            if (!$upload_result['success']) {
                wp_send_json_error($upload_result);
                Logger::log("File upload failed: " . $upload_result['message'], 'ERROR');
                return;
            }

            // Check if field exists and has data (for overwrite warning)
            if (!$overwrite_existing) {
                $existing_data = get_field($field_key, $post_id);
                Logger::log("Existing data check: " . print_r($existing_data, true), 'DEBUG');
                if (!empty($existing_data)) {
                    wp_send_json_error([
                        'message' => 'Field already contains data. Use overwrite option to replace it.',
                        'code' => 'DATA_EXISTS',
                        'existing_rows' => count($existing_data)
                    ]);
                    Logger::log("Field already contains data", 'ERROR');
                    return;
                }
            }

            // Process import based on format
            switch ($file_format) {
                case 'csv':
                    $result = $this->import_export_manager->import_csv_to_repeater(
                        $upload_result['file_path'],
                        $field_key,
                        $post_id
                    );
                    Logger::log("CSV import result: " . print_r($result, true), 'DEBUG');
                    break;

                case 'json':
                    $result = $this->import_export_manager->import_json_to_repeater(
                        $upload_result['file_path'],
                        $field_key,
                        $post_id
                    );
                    Logger::log("JSON import result: " . print_r($result, true), 'DEBUG');
                    break;

                default:
                    wp_send_json_error([
                        'message' => 'Unsupported file format'
                    ]);
                    Logger::log("Unsupported file format: " . $file_format, 'ERROR');
                    return;
            }

            // Clean up uploaded file
            if (file_exists($upload_result['file_path'])) {
                Logger::log("Cleaning up uploaded file: " . $upload_result['file_path'], 'DEBUG');
                unlink($upload_result['file_path']);
            }

            // Return result
            if ($result['success']) {
                wp_send_json_success($result);
                Logger::log("Import successful: " . print_r($result, true), 'INFO');
            } else {
                wp_send_json_error($result);
                Logger::log("Import failed: " . $result['message'], 'ERROR');
            }

        } catch (\Exception $e) {
            Logger::log("Import AJAX error: " . $e->getMessage(), 'ERROR');
            wp_send_json_error([
                'message' => 'Import failed: ' . $e->getMessage()
            ]);

        }
    }

    /**
     * Handle export repeater data
     */
    public function handle_export_repeater_data(): void
    {
        Logger::log("=== EXPORT AJAX START ===", 'DEBUG');
        try {
            // Security check
            check_ajax_referer('de_import_export_nonce', 'nonce');

            // Permission check
            if (!current_user_can('edit_posts')) {
                wp_send_json_error([
                    'message' => 'Insufficient permissions to export data'
                ]);
                return;
            }
            Logger::log("Permission check passed", 'DEBUG');

            // Get parameters
            $field_key = sanitize_text_field($_POST['field_key'] ?? '');
            $post_id = absint($_POST['post_id'] ?? 0);
            $format = sanitize_text_field($_POST['format'] ?? 'csv');

            Logger::log("Export request: field_key={$field_key}, post_id={$post_id}, format={$format}", 'INFO');

            // Validate parameters
            if (empty($field_key) || empty($post_id)) {
                wp_send_json_error([
                    'message' => 'Missing required parameters'
                ]);
                Logger::log("Missing required parameters", 'ERROR');
                return;
            }

            // Check if field has data
            $existing_data = get_field($field_key, $post_id);
            if (empty($existing_data)) {
                wp_send_json_error([
                    'message' => 'No data found to export'
                ]);
                Logger::log("No data found to export for field: " . $field_key, 'ERROR');
                return;
            }

            // Process export based on format
            switch ($format) {
                case 'csv':
                    $result = $this->import_export_manager->export_repeater_to_csv($field_key, $post_id);
                    break;

                case 'json':
                    $result = $this->import_export_manager->export_repeater_to_json($field_key, $post_id);
                    break;

                default:
                    wp_send_json_error([
                        'message' => 'Unsupported export format'
                    ]);
                    return;
            }

            // Return result
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
                Logger::log("Export failed: " . $result['message'], 'ERROR');
            }

        } catch (\Exception $e) {
            Logger::log("Export AJAX error: " . $e->getMessage(), 'ERROR');
            wp_send_json_error([
                'message' => 'Export failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle preview import data
     */
    public function handle_preview_import_data(): void
    {
        try {
            // Security check
            check_ajax_referer('de_import_export_nonce', 'nonce');

            // Permission check
            if (!current_user_can('edit_posts')) {
                wp_send_json_error([
                    'message' => 'Insufficient permissions to preview data'
                ]);
                return;
            }
            Logger::log("Permission check passed for preview", 'DEBUG');

            // Get parameters
            $field_key = sanitize_text_field($_POST['field_key'] ?? '');
            $preview_rows = absint($_POST['preview_rows'] ?? 5);

            Logger::log("Preview request: field_key={$field_key}, preview_rows={$preview_rows}", 'INFO');

            // Validate parameters
            if (empty($field_key)) {
                wp_send_json_error([
                    'message' => 'Missing field key'
                ]);
                Logger::log("Missing field key for preview", 'ERROR');
                return;
            }

            // Handle file upload
            if (!isset($_FILES['import_file'])) {
                wp_send_json_error([
                    'message' => 'No file uploaded'
                ]);
                Logger::log("No file uploaded for preview", 'ERROR');
                return;
            }

            $file = $_FILES['import_file'];

            // 🔥 NEW: Detect file type automatically
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            Logger::log("Detected file type for preview: {$file_extension}", 'DEBUG');

            if (!in_array($file_extension, ['csv', 'json'])) {
                wp_send_json_error([
                    'message' => 'Preview only supports CSV and JSON files'
                ]);
                return;
            }

            $upload_result = $this->handle_file_upload($file, $file_extension);

            if (!$upload_result['success']) {
                wp_send_json_error($upload_result);
                Logger::log("File upload failed for preview: " . $upload_result['message'], 'ERROR');
                return;
            }

            // Generate preview based on file type
            if ($file_extension === 'csv') {
                $preview_result = $this->import_export_manager->preview_csv_data(
                    $upload_result['file_path'],
                    $field_key,
                    $preview_rows
                );
            } else { // json
                $preview_result = $this->import_export_manager->preview_json_data(
                    $upload_result['file_path'],
                    $field_key,
                    $preview_rows
                );
            }

            // Clean up uploaded file
            if (file_exists($upload_result['file_path'])) {
                unlink($upload_result['file_path']);
                Logger::log("Cleaned up uploaded file: " . $upload_result['file_path'], 'DEBUG');
            }

            // Return result
            if ($preview_result['success']) {
                wp_send_json_success($preview_result);
            } else {
                wp_send_json_error($preview_result);
                Logger::log("Preview failed: " . $preview_result['message'], 'ERROR');
            }

        } catch (\Exception $e) {
            Logger::log("Preview AJAX error: " . $e->getMessage(), 'ERROR');
            wp_send_json_error([
                'message' => 'Preview failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle get field structure
     */
    public function handle_get_field_structure(): void {
        try {
            // Security check
            check_ajax_referer('de_import_export_nonce', 'nonce');
            
            // Get parameters
            $field_key = sanitize_text_field($_POST['field_key'] ?? '');
            $post_id = absint($_POST['post_id'] ?? 0);
            
            Logger::log("Field structure request: field_key={$field_key}, post_id={$post_id}", 'INFO');
            
            // Validate parameters
            if (empty($field_key)) {
                wp_send_json_error([
                    'message' => 'Missing field key'
                ]);
                Logger::log("Missing field key", 'ERROR');
                return;
            }
            Logger::log("Field key validation passed", 'DEBUG');
            
            // Get field object
            $field_object = get_field_object($field_key, $post_id);
            
            if (!$field_object) {
                wp_send_json_error([
                    'message' => 'Field not found'
                ]);
                Logger::log("Field not found for key: " . $field_key, 'ERROR');
                return;
            }
            
            // Extract relevant field information
            $field_info = [
                'name' => $field_object['name'],
                'label' => $field_object['label'],
                'type' => $field_object['type'],
                'sub_fields' => []
            ];
            
            // 🔥 NEW: Handle different field types
            if ($field_object['type'] === 'repeater' && isset($field_object['sub_fields'])) {
                foreach ($field_object['sub_fields'] as $sub_field) {
                    $field_info['sub_fields'][] = [
                        'name' => $sub_field['name'],
                        'label' => $sub_field['label'],
                        'type' => $sub_field['type'],
                        'required' => $sub_field['required'] ?? false,
                        'choices' => $sub_field['choices'] ?? null
                    ];
                }
            } elseif ($field_object['type'] === 'flexible_content' && isset($field_object['layouts'])) {
                // For flexible content, collect all sub-fields from all layouts
                foreach ($field_object['layouts'] as $layout) {
                    if (isset($layout['sub_fields'])) {
                        foreach ($layout['sub_fields'] as $sub_field) {
                            $field_info['sub_fields'][] = [
                                'name' => $sub_field['name'],
                                'label' => $sub_field['label'],
                                'type' => $sub_field['type'],
                                'required' => $sub_field['required'] ?? false,
                                'choices' => $sub_field['choices'] ?? null,
                                'layout' => $layout['name'] // Add layout info
                            ];
                        }
                    }
                }
            }
            
            Logger::log("Field structure retrieved: " . print_r($field_info, true), 'DEBUG');
            
            wp_send_json_success($field_info);
            
        } catch (\Exception $e) {
            Logger::log("Field structure AJAX error: " . $e->getMessage(), 'ERROR');
            wp_send_json_error([
                'message' => 'Failed to get field structure: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle file upload
     * 
     * @param array $file $_FILES array entry
     * @param string $expected_format Expected file format
     * @return array Upload result
     */
    private function handle_file_upload(array $file, string $expected_format): array
    {
        try {
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return [
                    'success' => false,
                    'message' => $this->get_upload_error_message($file['error'])
                ];
            }
            Logger::log("File upload started: " . $file['name'], 'DEBUG');

            // Check file size
            $max_size = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $max_size) {
                return [
                    'success' => false,
                    'message' => 'File too large. Maximum size is 5MB'
                ];
            }
            Logger::log("File size check passed: " . $file['size'], 'DEBUG');

            // Check file type
            $allowed_types = [
                'csv' => ['text/csv', 'application/csv', 'text/plain'],
                'json' => ['application/json', 'text/json'],
                'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            ];
            Logger::log("Allowed file types: " . print_r($allowed_types, true), 'DEBUG');

            $file_type = $file['type'];
            if (!isset($allowed_types[$expected_format]) || !in_array($file_type, $allowed_types[$expected_format])) {
                // Also check by file extension as fallback
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($file_extension !== $expected_format) {
                    Logger::log("Invalid file type: {$file_type} for expected format: {$expected_format}", 'ERROR');
                    return [
                        'success' => false,
                        'message' => "Invalid file type. Expected {$expected_format} file"
                    ];
                }
            }

            // Generate unique filename
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/dataengine-temp';
            Logger::log("Temporary directory: " . $temp_dir, 'DEBUG');

            // Create temp directory if it doesn't exist
            if (!is_dir($temp_dir)) {
                wp_mkdir_p($temp_dir);
                Logger::log("Created temporary directory: " . $temp_dir, 'DEBUG');

                // Add index.php to prevent directory listing
                file_put_contents($temp_dir . '/index.php', '<?php // Silence is golden.');
            }

            $filename = 'import_' . uniqid() . '_' . time() . '.' . $expected_format;
            $file_path = $temp_dir . '/' . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                Logger::log("Failed to move uploaded file to: " . $file_path, 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Failed to process uploaded file'
                ];
            }

            Logger::log("File uploaded successfully: {$filename}", 'INFO');

            return [
                'success' => true,
                'file_path' => $file_path,
                'filename' => $filename,
                'size' => $file['size']
            ];

        } catch (\Exception $e) {
            Logger::log("File upload error: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'File upload failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get upload error message
     * 
     * @param int $error_code PHP upload error code
     * @return string Error message
     */
    private function get_upload_error_message(int $error_code): string
    {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Clean up old temporary files
     */
    public function cleanup_temp_files(): void
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/dataengine-temp';

        if (!is_dir($temp_dir)) {
            return;
        }

        $files = glob($temp_dir . '/import_*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                // Delete files older than 1 hour
                if (($now - filemtime($file)) > 3600) {
                    unlink($file);
                    Logger::log("Cleaned up old temp file: " . basename($file), 'DEBUG');
                }
            }
        }
    }
}
