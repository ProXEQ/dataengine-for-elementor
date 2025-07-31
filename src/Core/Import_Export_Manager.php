<?php
namespace DataEngine\Core;

use DataEngine\Utils\Logger;

/**
 * Import/Export Manager Class
 * 
 * Handles importing and exporting data for ACF Repeater and Flexible Content fields
 * 
 * @since 1.2.0
 */
class Import_Export_Manager
{

    /**
     * Supported file types and their MIME types
     */
    private const SUPPORTED_TYPES = [
        'csv' => ['text/csv', 'application/csv', 'text/plain'],
        'json' => ['application/json', 'text/json'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
    ];

    /**
     * Maximum file size (5MB)
     */
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /**
     * Import CSV data to ACF Repeater field
     * 
     * @param string $file_path Path to the uploaded CSV file
     * @param string $field_key ACF field key
     * @param int $post_id Post ID to update
     * @return array Result array with success status and message
     */
    public function import_csv_to_repeater(string $file_path, string $field_key, int $post_id): array
    {
        try {
            Logger::log("Starting CSV import for field '{$field_key}' on post {$post_id}", 'INFO');

            // Validate file
            $validation_result = $this->validate_file($file_path, 'csv');
            if (!$validation_result['success']) {
                Logger::log("CSV import validation failed: " . $validation_result['message'], 'ERROR');
                return $validation_result;
            }

            // Parse CSV
            $csv_data = $this->parse_csv($file_path);
            if (empty($csv_data)) {
                Logger::log("CSV file is empty or could not be parsed", 'ERROR');
                return [
                    'success' => false,
                    'message' => 'CSV file is empty or could not be parsed'
                ];
            }

            // Get field structure
            $field_object = get_field_object($field_key, $post_id);
            if (!$field_object) {
                Logger::log("Target field not found for key: {$field_key}", 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Target field not found'
                ];
            }

            // 🔥 NEW: Enhanced field type validation
            Logger::log("Field object retrieved: " . print_r($field_object, true), 'DEBUG');

            if (!in_array($field_object['type'], ['repeater', 'flexible_content'])) {
                Logger::log("Target field is not a repeater or flexible content: " . $field_object['type'], 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Target field must be a repeater or flexible content field. Found: ' . $field_object['type']
                ];
            }

            // 🔥 NEW: Different validation for each type
            if ($field_object['type'] === 'repeater') {
                if (!isset($field_object['sub_fields']) || !is_array($field_object['sub_fields']) || empty($field_object['sub_fields'])) {
                    Logger::log("Target repeater field has no sub-fields", 'ERROR');
                    return [
                        'success' => false,
                        'message' => 'Target repeater field has no sub-fields configured'
                    ];
                }
                Logger::log("Found " . count($field_object['sub_fields']) . " sub-fields", 'DEBUG');
            } elseif ($field_object['type'] === 'flexible_content') {
                if (!isset($field_object['layouts']) || !is_array($field_object['layouts']) || empty($field_object['layouts'])) {
                    Logger::log("Target flexible content field has no layouts", 'ERROR');
                    return [
                        'success' => false,
                        'message' => 'Target flexible content field has no layouts configured'
                    ];
                }
                Logger::log("Found " . count($field_object['layouts']) . " layouts", 'DEBUG');
            }

            // 🔥 NEW: Process data based on field type
            if ($field_object['type'] === 'repeater') {
                $processed_data = $this->process_csv_data($csv_data, $field_object);
            } else { // flexible_content
                $processed_data = $this->process_flexible_csv_data($csv_data, $field_object);
            }

            if (!$processed_data['success']) {
                Logger::log("CSV data processing failed: " . $processed_data['message'], 'ERROR');
                return $processed_data;
            }

            Logger::log("Processed data for CSV import: " . print_r($processed_data, true), 'DEBUG');

            // Use enhanced save method
            $save_result = $this->save_repeater_data($field_key, $processed_data['data'], $post_id);
            return $save_result;

        } catch (\Exception $e) {
            Logger::log("CSV import error: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Import JSON data to ACF Repeater field
     * 
     * @param string $file_path Path to the uploaded JSON file
     * @param string $field_key ACF field key
     * @param int $post_id Post ID to update
     * @return array Result array with success status and message
     */
    public function import_json_to_repeater(string $file_path, string $field_key, int $post_id): array
    {
        Logger::log("Starting JSON import for field '{$field_key}' on post {$post_id}", 'INFO');
        try {
            // Validate file
            $validation_result = $this->validate_file($file_path, 'json');
            if (!$validation_result['success']) {
                Logger::log("JSON import validation failed: " . $validation_result['message'], 'ERROR');
                return $validation_result;
            }

            // Parse JSON
            $json_content = file_get_contents($file_path);
            $json_data = json_decode($json_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Logger::log("Invalid JSON format: " . json_last_error_msg(), 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Invalid JSON format: ' . json_last_error_msg()
                ];
            }

            // Get field structure
            $field_object = get_field_object($field_key, $post_id);
            if (!$field_object) {
                Logger::log("Target field not found for key: {$field_key}", 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Target field not found'
                ];
            }

            // 🔥 NEW: Enhanced field type validation
            Logger::log("Field object: " . print_r($field_object, true), 'DEBUG');

            if (!in_array($field_object['type'], ['repeater', 'flexible_content'])) {
                Logger::log("Target field is not a repeater or flexible content: " . $field_object['type'], 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Target field must be a repeater or flexible content field. Found: ' . $field_object['type']
                ];
            }

            // 🔥 NEW: Different validation for each type
            if ($field_object['type'] === 'repeater') {
                if (!isset($field_object['sub_fields']) || !is_array($field_object['sub_fields']) || empty($field_object['sub_fields'])) {
                    Logger::log("Target repeater field has no sub-fields", 'ERROR');
                    return [
                        'success' => false,
                        'message' => 'Target repeater field has no sub-fields configured'
                    ];
                }
                Logger::log("Found " . count($field_object['sub_fields']) . " sub-fields", 'DEBUG');
            } elseif ($field_object['type'] === 'flexible_content') {
                if (!isset($field_object['layouts']) || !is_array($field_object['layouts']) || empty($field_object['layouts'])) {
                    Logger::log("Target flexible content field has no layouts", 'ERROR');
                    return [
                        'success' => false,
                        'message' => 'Target flexible content field has no layouts configured'
                    ];
                }
                Logger::log("Found " . count($field_object['layouts']) . " layouts", 'DEBUG');
            }

            // 🔥 NEW: Process data based on field type
            if ($field_object['type'] === 'repeater') {
                $processed_data = $this->process_json_data($json_data, $field_object);
            } else { // flexible_content
                $processed_data = $this->process_flexible_json_data($json_data, $field_object);
            }

            Logger::log("Processed data for JSON import: " . print_r($processed_data, true), 'DEBUG');
            if (!$processed_data['success']) {
                return $processed_data;
            }

            // Use enhanced save method
            $save_result = $this->save_repeater_data($field_key, $processed_data['data'], $post_id);
            return $save_result;

        } catch (\Exception $e) {
            Logger::log("JSON import error: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    private function process_flexible_csv_data(array $csv_data, array $field_object): array
    {
        Logger::log("Processing CSV data for flexible content field: " . $field_object['key'], 'DEBUG');

        if (empty($csv_data)) {
            Logger::log("No data to process in CSV", 'ERROR');
            return [
                'success' => false,
                'message' => 'No data to process'
            ];
        }

        $headers = array_shift($csv_data);
        $layouts = $field_object['layouts'];
        $processed_data = [];

        Logger::log("Headers: " . implode(', ', $headers), 'DEBUG');
        Logger::log("Available layouts: " . implode(', ', array_keys($layouts)), 'DEBUG');

        // Check if acf_fc_layout column exists
        $layout_column_index = array_search('acf_fc_layout', $headers);
        if ($layout_column_index === false) {
            return [
                'success' => false,
                'message' => 'CSV must contain acf_fc_layout column to specify layout type'
            ];
        }

        foreach ($csv_data as $row_index => $row) {
            Logger::log("Processing row {$row_index}: " . print_r($row, true), 'DEBUG');

            if (!is_array($row)) {
                Logger::log("Row {$row_index} is not an array, skipping", 'DEBUG');
                continue;
            }

            $layout_name = $row[$layout_column_index] ?? '';
            if (empty($layout_name)) {
                Logger::log("Row {$row_index} has no layout specified, skipping", 'DEBUG');
                continue;
            }

            // Find layout
            $layout = null;
            foreach ($layouts as $l) {
                if ($l['name'] === $layout_name) {
                    $layout = $l;
                    break;
                }
            }

            if (!$layout) {
                Logger::log("Layout '{$layout_name}' not found, skipping row {$row_index}", 'DEBUG');
                continue;
            }

            $row_data = ['acf_fc_layout' => $layout_name];

            // Process each field in the row
            foreach ($headers as $column_index => $header) {
                if ($header === 'acf_fc_layout') {
                    continue; // Already processed
                }

                $value = $row[$column_index] ?? '';

                // Extract field name from header (remove layout prefix if present)
                $field_name = str_replace($layout_name . '_', '', $header);

                // Find matching sub-field in layout
                $sub_field = null;
                if (isset($layout['sub_fields'])) {
                    foreach ($layout['sub_fields'] as $sf) {
                        if ($sf['name'] === $field_name || $sf['name'] === $header) {
                            $sub_field = $sf;
                            break;
                        }
                    }
                }

                if ($sub_field) {
                    $processed_value = $this->process_field_value($value, $sub_field);
                    $row_data[$sub_field['name']] = $processed_value;
                    Logger::log("Mapped {$header} -> {$sub_field['name']} = " . print_r($processed_value, true), 'DEBUG');
                } else {
                    Logger::log("No matching sub-field found for: {$header} in layout {$layout_name}", 'DEBUG');
                }
            }

            if (count($row_data) > 1) { // More than just acf_fc_layout
                $processed_data[] = $row_data;
                Logger::log("Added row to processed data: " . print_r($row_data, true), 'DEBUG');
            } else {
                Logger::log("Row {$row_index} resulted in empty data", 'DEBUG');
            }
        }

        Logger::log("Final processed data: " . count($processed_data) . " rows", 'INFO');

        return [
            'success' => true,
            'data' => $processed_data
        ];
    }

    /**
     * Process JSON data for Flexible Content field
     */
    private function process_flexible_json_data(array $json_data, array $field_object): array
    {
        Logger::log("Processing JSON data for flexible content field: " . $field_object['key'], 'DEBUG');

        if (empty($json_data)) {
            Logger::log("No data to process in JSON", 'ERROR');
            return [
                'success' => false,
                'message' => 'No data to process'
            ];
        }

        $layouts = $field_object['layouts'];
        $processed_data = [];

        Logger::log("Processing JSON data with " . count($json_data) . " rows", 'DEBUG');
        Logger::log("Available layouts: " . implode(', ', array_keys($layouts)), 'DEBUG');

        foreach ($json_data as $row_index => $row) {
            Logger::log("Processing row {$row_index}: " . print_r($row, true), 'DEBUG');

            if (!is_array($row)) {
                Logger::log("Row {$row_index} is not an array, skipping", 'DEBUG');
                continue;
            }

            $layout_name = $row['acf_fc_layout'] ?? '';
            if (empty($layout_name)) {
                Logger::log("Row {$row_index} has no layout specified, skipping", 'DEBUG');
                continue;
            }

            // Find layout
            $layout = null;
            foreach ($layouts as $l) {
                if ($l['name'] === $layout_name) {
                    $layout = $l;
                    break;
                }
            }

            if (!$layout) {
                Logger::log("Layout '{$layout_name}' not found, skipping row {$row_index}", 'DEBUG');
                continue;
            }

            $row_data = ['acf_fc_layout' => $layout_name];

            // Process each field in the row
            foreach ($row as $field_name => $value) {
                if ($field_name === 'acf_fc_layout') {
                    continue; // Already processed
                }

                // Find matching sub-field in layout
                $sub_field = null;
                if (isset($layout['sub_fields'])) {
                    foreach ($layout['sub_fields'] as $sf) {
                        if ($sf['name'] === $field_name) {
                            $sub_field = $sf;
                            break;
                        }
                    }
                }

                if ($sub_field) {
                    $processed_value = $this->process_field_value($value, $sub_field);
                    $row_data[$sub_field['name']] = $processed_value;
                    Logger::log("Mapped {$field_name} -> {$sub_field['name']} = " . print_r($processed_value, true), 'DEBUG');
                } else {
                    Logger::log("No matching sub-field found for: {$field_name} in layout {$layout_name}", 'DEBUG');
                }
            }

            if (count($row_data) > 1) { // More than just acf_fc_layout
                $processed_data[] = $row_data;
                Logger::log("Added row to processed data: " . print_r($row_data, true), 'DEBUG');
            } else {
                Logger::log("Row {$row_index} resulted in empty data", 'DEBUG');
            }
        }

        Logger::log("Final processed data: " . count($processed_data) . " rows", 'INFO');

        return [
            'success' => true,
            'data' => $processed_data
        ];
    }

    /**
     * Export ACF Repeater data to CSV
     * 
     * @param string $field_key ACF field key
     * @param int $post_id Post ID to export from
     * @return array Result array with success status and CSV content
     */
    public function export_repeater_to_csv(string $field_key, int $post_id): array
    {
        try {
            Logger::log("Starting CSV export for field '{$field_key}' on post {$post_id}", 'INFO');

            // Get repeater/flexible data
            $field_data = get_field($field_key, $post_id);
            if (!$field_data || !is_array($field_data)) {
                return [
                    'success' => false,
                    'message' => 'No data found to export'
                ];
            }

            // Get field structure
            $field_object = get_field_object($field_key, $post_id);
            if (!$field_object) {
                Logger::log("Field object not found for key: {$field_key}", 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Field not found'
                ];
            }

            Logger::log("Field type detected: " . $field_object['type'], 'DEBUG');

            // Handle different field types
            if ($field_object['type'] === 'repeater') {
                // Standard repeater export
                if (!isset($field_object['sub_fields']) || empty($field_object['sub_fields'])) {
                    return [
                        'success' => false,
                        'message' => 'Repeater field has no sub-fields configured'
                    ];
                }

                $csv_content = $this->generate_csv_content($field_data, $field_object['sub_fields']);

            } elseif ($field_object['type'] === 'flexible_content') {
                // Flexible content export
                if (!isset($field_object['layouts']) || empty($field_object['layouts'])) {
                    return [
                        'success' => false,
                        'message' => 'Flexible Content field has no layouts configured'
                    ];
                }

                $csv_content = $this->generate_flexible_csv_content($field_data, $field_object['layouts']);

            } else {
                return [
                    'success' => false,
                    'message' => 'Field type not supported for export: ' . $field_object['type']
                ];
            }

            Logger::log("Successfully exported " . count($field_data) . " rows to CSV", 'INFO');

            return [
                'success' => true,
                'content' => $csv_content,
                'filename' => $this->generate_filename($field_key, $post_id, 'csv'),
                'rows_exported' => count($field_data)
            ];

        } catch (\Exception $e) {
            Logger::log("CSV export error: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }


    /**
     * Export ACF Repeater data to JSON
     * 
     * @param string $field_key ACF field key
     * @param int $post_id Post ID to export from
     * @return array Result array with success status and JSON content
     */
    public function export_repeater_to_json(string $field_key, int $post_id): array
    {
        try {
            Logger::log("Starting JSON export for field '{$field_key}' on post {$post_id}", 'INFO');

            // Get repeater/flexible data
            $field_data = get_field($field_key, $post_id);
            if (!$field_data || !is_array($field_data)) {
                return [
                    'success' => false,
                    'message' => 'No data found to export'
                ];
            }

            // Get field structure
            $field_object = get_field_object($field_key, $post_id);
            if (!$field_object) {
                Logger::log("Field object not found for key: {$field_key}", 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Field not found'
                ];
            }

            // Process data for JSON export (works for both repeater and flexible content)
            $processed_data = $this->process_data_for_json_export($field_data);

            // Generate JSON content
            $json_content = json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'message' => 'JSON encoding failed: ' . json_last_error_msg()
                ];
            }

            Logger::log("Successfully exported " . count($field_data) . " rows to JSON", 'INFO');

            return [
                'success' => true,
                'content' => $json_content,
                'filename' => $this->generate_filename($field_key, $post_id, 'json'),
                'rows_exported' => count($field_data)
            ];

        } catch (\Exception $e) {
            Logger::log("JSON export error: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }

    private function generate_flexible_csv_content(array $flexible_data, array $layouts): string
    {
        Logger::log("Generating CSV for flexible content with " . count($layouts) . " layouts", 'DEBUG');

        $csv_content = '';

        // Collect all possible fields from all layouts
        $all_fields = ['acf_fc_layout']; // Add layout type as first column
        $layout_fields_map = [];

        foreach ($layouts as $layout) {
            $layout_name = $layout['name'];
            $layout_fields_map[$layout_name] = [];

            if (isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                foreach ($layout['sub_fields'] as $sub_field) {
                    $field_key = $layout_name . '_' . $sub_field['name'];
                    if (!in_array($field_key, $all_fields)) {
                        $all_fields[] = $field_key;
                    }
                    $layout_fields_map[$layout_name][] = $sub_field;
                }
            }
        }

        Logger::log("All fields collected: " . implode(', ', $all_fields), 'DEBUG');

        // Generate headers
        $csv_content .= implode(',', array_map([$this, 'escape_csv_value'], $all_fields)) . "\n";

        // Generate data rows
        foreach ($flexible_data as $row) {
            $csv_row = [];
            $layout_name = $row['acf_fc_layout'] ?? '';

            foreach ($all_fields as $field_key) {
                if ($field_key === 'acf_fc_layout') {
                    $csv_row[] = $layout_name;
                } else {
                    // Extract field name from layout_fieldname format
                    $field_name = str_replace($layout_name . '_', '', $field_key);

                    if ($layout_name && strpos($field_key, $layout_name . '_') === 0) {
                        // This field belongs to current layout
                        $value = $row[$field_name] ?? '';

                        // Find the sub-field definition for proper formatting
                        $sub_field = null;
                        if (isset($layout_fields_map[$layout_name])) {
                            foreach ($layout_fields_map[$layout_name] as $sf) {
                                if ($sf['name'] === $field_name) {
                                    $sub_field = $sf;
                                    break;
                                }
                            }
                        }

                        if ($sub_field) {
                            $formatted_value = $this->format_field_for_export($value, $sub_field);
                        } else {
                            $formatted_value = (string) $value;
                        }

                        $csv_row[] = $formatted_value;
                    } else {
                        // Field doesn't belong to current layout
                        $csv_row[] = '';
                    }
                }
            }

            $csv_content .= implode(',', array_map([$this, 'escape_csv_value'], $csv_row)) . "\n";
        }

        Logger::log("Flexible content CSV generation completed", 'DEBUG');
        return $csv_content;
    }

    /**
     * Preview CSV data before import - supports both Repeater and Flexible Content
     * 
     * @param string $file_path Path to the uploaded CSV file
     * @param string $field_key ACF field key
     * @param int $preview_rows Number of rows to preview (default: 5)
     * @return array Preview data
     */
    public function preview_csv_data(string $file_path, string $field_key, int $preview_rows = 5): array
    {
        try {
            Logger::log("Starting CSV preview for field: {$field_key}", 'DEBUG');

            // Validate file
            $validation_result = $this->validate_file($file_path, 'csv');
            if (!$validation_result['success']) {
                return $validation_result;
            }

            // Parse CSV
            $csv_data = $this->parse_csv($file_path);
            if (empty($csv_data)) {
                return [
                    'success' => false,
                    'message' => 'CSV file is empty or could not be parsed'
                ];
            }

            // Get field structure
            $field_object = get_field_object($field_key);
            if (!$field_object) {
                return [
                    'success' => false,
                    'message' => 'Target field not found'
                ];
            }

            Logger::log("Field type detected for preview: " . $field_object['type'], 'DEBUG');

            // 🔥 NEW: Handle different field types
            if (!in_array($field_object['type'], ['repeater', 'flexible_content'])) {
                return [
                    'success' => false,
                    'message' => 'Preview only supports repeater and flexible content fields'
                ];
            }

            // Get headers
            $headers = array_shift($csv_data);
            Logger::log("CSV headers: " . implode(', ', $headers), 'DEBUG');

            // Get preview rows
            $preview_data = array_slice($csv_data, 0, $preview_rows);

            // 🔥 NEW: Get field mapping based on field type
            $field_mapping = [];
            if ($field_object['type'] === 'repeater') {
                if (isset($field_object['sub_fields'])) {
                    $field_mapping = $this->get_field_mapping($headers, $field_object['sub_fields']);
                }
            } elseif ($field_object['type'] === 'flexible_content') {
                if (isset($field_object['layouts'])) {
                    $field_mapping = $this->get_flexible_field_mapping($headers, $field_object['layouts']);
                }
            }

            Logger::log("CSV preview generated: " . count($preview_data) . " rows", 'DEBUG');

            return [
                'success' => true,
                'headers' => $headers,
                'preview_data' => $preview_data,
                'total_rows' => count($csv_data) + 1, // +1 for the header we shifted
                'field_mapping' => $field_mapping,
                'format' => 'csv',
                'field_type' => $field_object['type']
            ];

        } catch (\Exception $e) {
            Logger::log("CSV preview error: " . $e->getMessage(), 'ERROR');
            Logger::log("Stack trace: " . $e->getTraceAsString(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Preview failed: ' . $e->getMessage()
            ];
        }
    }


    /**
     * Preview JSON data before import - supports both Repeater and Flexible Content
     */
    public function preview_json_data(string $file_path, string $field_key, int $preview_rows = 5): array
    {
        try {
            Logger::log("Starting JSON preview for field: {$field_key}", 'DEBUG');

            // Validate file
            $validation_result = $this->validate_file($file_path, 'json');
            if (!$validation_result['success']) {
                return $validation_result;
            }

            // Parse JSON
            $json_content = file_get_contents($file_path);
            $json_data = json_decode($json_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'message' => 'Invalid JSON format: ' . json_last_error_msg()
                ];
            }

            if (!is_array($json_data)) {
                return [
                    'success' => false,
                    'message' => 'JSON data must be an array'
                ];
            }

            // Get field structure
            $field_object = get_field_object($field_key);
            if (!$field_object) {
                return [
                    'success' => false,
                    'message' => 'Target field not found'
                ];
            }

            Logger::log("Field type detected for JSON preview: " . $field_object['type'], 'DEBUG');

            // 🔥 NEW: Handle different field types
            if (!in_array($field_object['type'], ['repeater', 'flexible_content'])) {
                return [
                    'success' => false,
                    'message' => 'Preview only supports repeater and flexible content fields'
                ];
            }

            // Get preview data
            $preview_data = array_slice($json_data, 0, $preview_rows);

            // Extract headers from first row
            $headers = [];
            if (!empty($json_data)) {
                $headers = array_keys($json_data[0]);
            }

            // Convert JSON rows to array format for consistent preview display
            $formatted_preview = [];
            foreach ($preview_data as $row) {
                $formatted_row = [];
                foreach ($headers as $header) {
                    $formatted_row[] = $row[$header] ?? '';
                }
                $formatted_preview[] = $formatted_row;
            }

            // 🔥 NEW: Get field mapping based on field type
            $field_mapping = [];
            if ($field_object['type'] === 'repeater') {
                if (isset($field_object['sub_fields'])) {
                    $field_mapping = $this->get_field_mapping($headers, $field_object['sub_fields']);
                }
            } elseif ($field_object['type'] === 'flexible_content') {
                if (isset($field_object['layouts'])) {
                    $field_mapping = $this->get_flexible_field_mapping($headers, $field_object['layouts']);
                }
            }

            Logger::log("JSON preview generated: " . count($preview_data) . " rows", 'DEBUG');

            return [
                'success' => true,
                'headers' => $headers,
                'preview_data' => $formatted_preview,
                'total_rows' => count($json_data),
                'field_mapping' => $field_mapping,
                'format' => 'json',
                'field_type' => $field_object['type']
            ];

        } catch (\Exception $e) {
            Logger::log("JSON preview error: " . $e->getMessage(), 'ERROR');
            Logger::log("Stack trace: " . $e->getTraceAsString(), 'ERROR');
            return [
                'success' => false,
                'message' => 'Preview failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate uploaded file
     * 
     * @param string $file_path Path to the file
     * @param string $expected_type Expected file type
     * @return array Validation result
     */
    private function validate_file(string $file_path, string $expected_type): array
    {
        // Check if file exists
        Logger::log("Validating file: {$file_path}", 'DEBUG');
        if (!file_exists($file_path)) {
            Logger::log("File not found: {$file_path}", 'ERROR');
            return [
                'success' => false,
                'message' => 'File not found'
            ];
        }

        // Check file size
        $file_size = filesize($file_path);
        if ($file_size > self::MAX_FILE_SIZE) {
            Logger::log("File too large: {$file_size} bytes", 'ERROR');
            return [
                'success' => false,
                'message' => 'File too large. Maximum size is ' . (self::MAX_FILE_SIZE / 1024 / 1024) . 'MB'
            ];
        }

        // Check MIME type
        $mime_type = mime_content_type($file_path);
        if (!in_array($mime_type, self::SUPPORTED_TYPES[$expected_type])) {
            Logger::log("Invalid file type: {$mime_type}. Expected: " . implode(', ', self::SUPPORTED_TYPES[$expected_type]), 'ERROR');
            return [
                'success' => false,
                'message' => 'Invalid file type. Expected: ' . implode(', ', self::SUPPORTED_TYPES[$expected_type])
            ];
        }

        return ['success' => true];
    }

    /**
     * Parse CSV file
     * 
     * @param string $file_path Path to CSV file
     * @return array Parsed CSV data
     */
    private function parse_csv(string $file_path): array
    {
        $csv_data = [];
        Logger::log("Parsing CSV file: {$file_path}", 'DEBUG');
        if (($handle = fopen($file_path, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $csv_data[] = $data;
            }
            Logger::log("CSV file parsed successfully with " . count($csv_data) . " rows", 'DEBUG');
            fclose($handle);
        }
        Logger::log("CSV parsing completed", 'DEBUG');

        return $csv_data;
    }

    /**
     * Process CSV data for ACF field
     * 
     * @param array $csv_data Raw CSV data
     * @param array $field_object ACF field object
     * @return array Processed data
     */
    private function process_csv_data(array $csv_data, array $field_object): array
    {
        Logger::log("Processing CSV data for field: " . $field_object['key'], 'DEBUG');

        if (empty($csv_data)) {
            Logger::log("No data to process in CSV", 'ERROR');
            return [
                'success' => false,
                'message' => 'No data to process'
            ];
        }

        Logger::log("CSV data contains " . count($csv_data) . " rows", 'DEBUG');

        // Check if field has sub_fields (same validation as JSON)
        if (!isset($field_object['sub_fields']) || !is_array($field_object['sub_fields']) || empty($field_object['sub_fields'])) {
            Logger::log("Field has no sub_fields", 'ERROR');
            return [
                'success' => false,
                'message' => 'Target field must be a repeater with sub-fields'
            ];
        }

        $headers = array_shift($csv_data);
        $sub_fields = $field_object['sub_fields'];
        $processed_data = [];

        Logger::log("Headers: " . implode(', ', $headers), 'DEBUG');
        Logger::log("Found " . count($sub_fields) . " sub-fields", 'DEBUG');

        foreach ($csv_data as $row_index => $row) {
            Logger::log("Processing row {$row_index}: " . print_r($row, true), 'DEBUG');

            if (!is_array($row)) {
                Logger::log("Row {$row_index} is not an array, skipping", 'DEBUG');
                continue;
            }

            $row_data = [];

            foreach ($headers as $column_index => $header) {
                $value = $row[$column_index] ?? '';
                Logger::log("Processing column '{$header}' with value: " . print_r($value, true), 'DEBUG');

                // Find matching sub-field
                $sub_field = $this->find_sub_field($header, $sub_fields);
                if ($sub_field) {
                    $processed_value = $this->process_field_value($value, $sub_field);
                    $row_data[$sub_field['name']] = $processed_value;
                    Logger::log("Mapped {$header} -> {$sub_field['name']} = " . print_r($processed_value, true), 'DEBUG');
                } else {
                    Logger::log("No matching sub-field found for: {$header}", 'DEBUG');
                }
            }

            if (!empty($row_data)) {
                $processed_data[] = $row_data;
                Logger::log("Added row to processed data: " . print_r($row_data, true), 'DEBUG');
            } else {
                Logger::log("Row {$row_index} resulted in empty data", 'DEBUG');
            }
        }

        Logger::log("Final processed data: " . count($processed_data) . " rows", 'INFO');

        return [
            'success' => true,
            'data' => $processed_data
        ];
    }

    /**
     * Process JSON data for ACF field
     * 
     * @param array $json_data Raw JSON data
     * @param array $field_object ACF field object
     * @return array Processed data
     */
    private function process_json_data(array $json_data, array $field_object): array
    {
        Logger::log("Processing JSON data for field: " . $field_object['key'], 'DEBUG');
        if (empty($json_data)) {
            Logger::log("No data to process in JSON", 'ERROR');
            return [
                'success' => false,
                'message' => 'No data to process'
            ];
        }

        $sub_fields = $field_object['sub_fields'];
        $processed_data = [];
        Logger::log("Processing JSON data with " . count($json_data) . " rows", 'DEBUG');

        foreach ($json_data as $row_index => $row) {
            $row_data = [];
            Logger::log("Processing row {$row_index}", 'DEBUG');
            Logger::log("Row data: " . print_r($row, true), 'DEBUG');
            foreach ($row as $field_name => $value) {
                // Find matching sub-field
                $sub_field = $this->find_sub_field($field_name, $sub_fields);
                Logger::log("Processing field '{$field_name}' with value: " . print_r($value, true), 'DEBUG');
                if ($sub_field) {
                    $processed_value = $this->process_field_value($value, $sub_field);
                    $row_data[$sub_field['name']] = $processed_value;
                    Logger::log("Processed value for '{$sub_field['name']}': " . print_r($processed_value, true), 'DEBUG');
                }
                Logger::log("Processed field '{$field_name}' with value: " . print_r($value, true), 'DEBUG');
            }
            Logger::log("Row data after processing: " . print_r($row_data, true), 'DEBUG');

            if (!empty($row_data)) {
                $processed_data[] = $row_data;
                Logger::log("Processed row {$row_index}: " . print_r($row_data, true), 'DEBUG');
            }
            Logger::log("Processed row {$row_index} with data: " . print_r($row_data, true), 'DEBUG');
        }
        Logger::log("JSON data processing completed with " . count($processed_data) . " rows", 'DEBUG');
        return [
            'success' => true,
            'data' => $processed_data
        ];
    }

    /**
     * Find sub-field by name or label
     * 
     * @param string $identifier Field name or label
     * @param array $sub_fields Array of sub-fields
     * @return array|null Sub-field array or null if not found
     */
    private function find_sub_field(string $identifier, ?array $sub_fields): ?array
    {
        if (!is_array($sub_fields) || empty($sub_fields)) {
            Logger::log("Sub-fields array is empty or null for identifier: {$identifier}", 'DEBUG');
            return null;
        }

        Logger::log("Finding sub-field for identifier: {$identifier}", 'DEBUG');

        foreach ($sub_fields as $sub_field) {
            // Method 1: Exact match on name
            if ($sub_field['name'] === $identifier) {
                Logger::log("Found sub-field by exact name match: " . print_r($sub_field, true), 'DEBUG');
                return $sub_field;
            }

            // Method 2: Exact match on label
            if ($sub_field['label'] === $identifier) {
                Logger::log("Found sub-field by exact label match: " . print_r($sub_field, true), 'DEBUG');
                return $sub_field;
            }
        }

        // Method 3: Enhanced matching - parse CSV header format "Label (name)"
        $parsed_identifier = $identifier;
        if (preg_match('/^(.+?)\s*\(([^)]+)\)$/', $identifier, $matches)) {
            $label_part = trim($matches[1]);
            $name_part = trim($matches[2]);

            Logger::log("Parsed CSV header - Label: '{$label_part}', Name: '{$name_part}'", 'DEBUG');

            foreach ($sub_fields as $sub_field) {
                // Match by name part in parentheses
                if ($sub_field['name'] === $name_part) {
                    Logger::log("Found sub-field by name part match: " . print_r($sub_field, true), 'DEBUG');
                    return $sub_field;
                }

                // Match by label part before parentheses
                if ($sub_field['label'] === $label_part) {
                    Logger::log("Found sub-field by label part match: " . print_r($sub_field, true), 'DEBUG');
                    return $sub_field;
                }
            }

            // Use the name part as new identifier for further matching
            $parsed_identifier = $name_part;
        }

        // Method 4: Case-insensitive matching
        foreach ($sub_fields as $sub_field) {
            if (strcasecmp($sub_field['name'], $parsed_identifier) === 0) {
                Logger::log("Found sub-field by case-insensitive name match: " . print_r($sub_field, true), 'DEBUG');
                return $sub_field;
            }

            if (strcasecmp($sub_field['label'], $parsed_identifier) === 0) {
                Logger::log("Found sub-field by case-insensitive label match: " . print_r($sub_field, true), 'DEBUG');
                return $sub_field;
            }
        }

        // Method 5: Partial matching (contains)
        foreach ($sub_fields as $sub_field) {
            if (
                stripos($sub_field['name'], $parsed_identifier) !== false ||
                stripos($parsed_identifier, $sub_field['name']) !== false
            ) {
                Logger::log("Found sub-field by partial name match: " . print_r($sub_field, true), 'DEBUG');
                return $sub_field;
            }

            if (
                stripos($sub_field['label'], $parsed_identifier) !== false ||
                stripos($parsed_identifier, $sub_field['label']) !== false
            ) {
                Logger::log("Found sub-field by partial label match: " . print_r($sub_field, true), 'DEBUG');
                return $sub_field;
            }
        }

        Logger::log("Sub-field not found for identifier: {$identifier}", 'DEBUG');
        return null;
    }

    /**
     * Process field value based on field type
     * 
     * @param mixed $value Raw value
     * @param array $field Field configuration
     * @return mixed Processed value
     */
    private function process_field_value($value, array $field): mixed
    {
        if (empty($value) && $value !== '0') {
            Logger::log("Empty value for field: " . $field['name'], 'DEBUG');
            return null;
        }

        switch ($field['type']) {
            case 'text':
            case 'textarea':
            case 'email':
            case 'url':
                return sanitize_text_field($value);

            case 'number':
                return is_numeric($value) ? (float) $value : 0;

            case 'date_picker':
                return date('Y-m-d', strtotime($value));

            case 'date_time_picker':
                return date('Y-m-d H:i:s', strtotime($value));

            case 'time_picker':
                return date('H:i:s', strtotime($value));

            case 'true_false':
                return in_array(strtolower($value), ['true', '1', 'yes', 'tak', 'on']);

            case 'select':
                // Validate against choices
                if (isset($field['choices'][$value])) {
                    return $value;
                }
                // Try to find by label
                foreach ($field['choices'] as $choice_value => $choice_label) {
                    if ($choice_label === $value) {
                        return $choice_value;
                    }
                }
                return null;

            case 'checkbox':
                // Split by comma for multiple values
                $values = array_map('trim', explode(',', $value));
                $valid_values = [];

                foreach ($values as $v) {
                    if (isset($field['choices'][$v])) {
                        $valid_values[] = $v;
                    }
                }

                return $valid_values;

            case 'image':
            case 'file':
                // Handle by attachment ID or URL
                if (is_numeric($value)) {
                    return (int) $value;
                } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                    return $this->import_attachment_from_url($value);
                }
                return null;

            case 'post_object':
                // Handle by post ID or post title
                if (is_numeric($value)) {
                    return (int) $value;
                } else {
                    $post = get_page_by_title($value, OBJECT, $field['post_type']);
                    return $post ? $post->ID : null;
                }

            case 'user':
                // Handle by user ID, username, or email
                if (is_numeric($value)) {
                    return (int) $value;
                } else {
                    $user = get_user_by('login', $value) ?: get_user_by('email', $value);
                    return $user ? $user->ID : null;
                }

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Generate CSV content from repeater data
     * 
     * @param array $repeater_data Repeater data
     * @param array $sub_fields Sub-fields configuration
     * @return string CSV content
     */
    private function generate_csv_content(array $repeater_data, array $sub_fields): string
    {
        $csv_content = '';

        // Generate headers
        $headers = [];
        foreach ($sub_fields as $sub_field) {
            $headers[] = $sub_field['label'] . ' (' . $sub_field['name'] . ')';
        }
        $csv_content .= implode(',', array_map([$this, 'escape_csv_value'], $headers)) . "\n";

        // Generate data rows
        foreach ($repeater_data as $row) {
            $csv_row = [];

            foreach ($sub_fields as $sub_field) {
                $value = $row[$sub_field['name']] ?? '';
                $formatted_value = $this->format_field_for_export($value, $sub_field);
                $csv_row[] = $formatted_value;
            }

            $csv_content .= implode(',', array_map([$this, 'escape_csv_value'], $csv_row)) . "\n";
        }

        return $csv_content;
    }

    /**
     * Format field value for export
     * 
     * @param mixed $value Field value
     * @param array $field Field configuration
     * @return string Formatted value
     */
    private function format_field_for_export($value, array $field): string
    {
        if (empty($value) && $value !== '0') {
            return '';
        }

        switch ($field['type']) {
            case 'image':
            case 'file':
                if (is_array($value)) {
                    return $value['url'] ?? '';
                } elseif (is_numeric($value)) {
                    $attachment = wp_get_attachment_url($value);
                    return $attachment ?: '';
                }
                break;

            case 'checkbox':
                if (is_array($value)) {
                    return implode(', ', $value);
                }
                break;

            case 'true_false':
                return $value ? 'TRUE' : 'FALSE';

            case 'post_object':
                if (is_object($value)) {
                    return $value->post_title;
                } elseif (is_numeric($value)) {
                    $post = get_post($value);
                    return $post ? $post->post_title : '';
                }
                break;

            case 'user':
                if (is_object($value)) {
                    return $value->display_name;
                } elseif (is_numeric($value)) {
                    $user = get_user_by('id', $value);
                    return $user ? $user->display_name : '';
                }
                break;

            case 'select':
                // Return label if available
                if (isset($field['choices'][$value])) {
                    return $field['choices'][$value];
                }
                break;

            default:
                return (string) $value;
        }

        return (string) $value;
    }

    /**
     * Process data for JSON export
     * 
     * @param array $repeater_data Repeater data
     * @return array Processed data
     */
    private function process_data_for_json_export(array $repeater_data): array
    {
        $processed_data = [];

        foreach ($repeater_data as $row) {
            $processed_row = [];

            foreach ($row as $field_name => $value) {
                // Convert objects to arrays for JSON serialization
                if (is_object($value)) {
                    $processed_row[$field_name] = (array) $value;
                } else {
                    $processed_row[$field_name] = $value;
                }
            }

            $processed_data[] = $processed_row;
        }

        return $processed_data;
    }

    /**
     * Generate filename for export
     * 
     * @param string $field_key Field key
     * @param int $post_id Post ID
     * @param string $format File format
     * @return string Generated filename
     */
    private function generate_filename(string $field_key, int $post_id, string $format): string
    {
        $post_title = get_the_title($post_id);
        $post_slug = sanitize_title($post_title);
        $field_name = str_replace('field_', '', $field_key);
        $timestamp = date('Y-m-d_H-i-s');

        return sprintf('%s_%s_%s.%s', $post_slug, $field_name, $timestamp, $format);
    }

    /**
     * Escape CSV value
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    private function escape_csv_value(string $value): string
    {
        // If value contains comma, quote, or newline, wrap in quotes and escape quotes
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /**
     * Import attachment from URL
     * 
     * @param string $url Image/file URL
     * @return int|null Attachment ID or null if failed
     */
    private function import_attachment_from_url(string $url): ?int
    {
        try {
            $attachment_id = media_sideload_image($url, 0, '', 'id');

            if (is_wp_error($attachment_id)) {
                Logger::log("Failed to import attachment from URL: " . $attachment_id->get_error_message(), 'ERROR');
                return null;
            }

            return $attachment_id;

        } catch (\Exception $e) {
            Logger::log("Error importing attachment from URL: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Get field mapping suggestions
     * 
     * @param array $headers CSV headers
     * @param array $sub_fields Sub-fields configuration
     * @return array Field mapping suggestions
     */
    private function get_field_mapping(array $headers, array $sub_fields): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $suggested_field = null;

            foreach ($sub_fields as $sub_field) {
                if ($sub_field['name'] === $header || $sub_field['label'] === $header) {
                    $suggested_field = $sub_field['name'];
                    break;
                }
            }

            $mapping[$header] = $suggested_field;
        }

        return $mapping;
    }
    /**
     * Enhanced save with multiple attempts
     */
    private function save_repeater_data(string $field_key, array $data, int $post_id): array
    {
        Logger::log("Attempting to save " . count($data) . " rows to ACF repeater", 'DEBUG');
        Logger::log("Raw data structure: " . print_r($data, true), 'DEBUG');

        // Method 1: Try standard update_field
        $update_result = update_field($field_key, $data, $post_id);
        Logger::log("update_field result: " . var_export($update_result, true), 'DEBUG');

        if ($update_result === false) {
            // Method 2: Format data for ACF repeater manually
            Logger::log("Trying ACF formatted data", 'DEBUG');

            $field_object = acf_get_field($field_key);
            $field_name = $field_object['name']; // 'specyfikacja'

            // ACF repeater format requires row count
            $formatted_data = [];
            foreach ($data as $index => $row) {
                foreach ($row as $sub_field_name => $value) {
                    $formatted_data["{$field_name}_{$index}_{$sub_field_name}"] = $value;
                }
            }
            $formatted_data[$field_name] = count($data); // Row count

            Logger::log("Formatted data: " . print_r($formatted_data, true), 'DEBUG');

            // Save each meta individually
            $success = true;
            foreach ($formatted_data as $meta_key => $meta_value) {
                $result = update_post_meta($post_id, $meta_key, $meta_value);
                Logger::log("update_post_meta('{$meta_key}'): " . var_export($result, true), 'DEBUG');
                if (!$result && get_post_meta($post_id, $meta_key, true) !== $meta_value) {
                    $success = false;
                }
            }

            if (!$success) {
                // Method 3: Try with ACF prefix
                Logger::log("Trying with ACF prefix", 'DEBUG');
                foreach ($formatted_data as $meta_key => $meta_value) {
                    $prefixed_key = '_' . $meta_key; // ACF internal key
                    update_post_meta($post_id, $prefixed_key, "field_" . md5($meta_key));
                    update_post_meta($post_id, $meta_key, $meta_value);
                }
            }
        }

        // Always verify by reading the data back
        sleep(1); // Give DB time to update
        $verification_data = get_field($field_key, $post_id);
        $verification_count = is_array($verification_data) ? count($verification_data) : 0;

        Logger::log("Verification: Field contains {$verification_count} rows after save", 'INFO');
        Logger::log("Verification data: " . print_r($verification_data, true), 'DEBUG');

        if ($verification_count === count($data)) {
            return [
                'success' => true,
                'imported_rows' => $verification_count,
                'message' => sprintf('Successfully imported %d rows', $verification_count)
            ];
        } else if ($verification_count > 0) {
            return [
                'success' => true,
                'imported_rows' => $verification_count,
                'message' => sprintf('Partially imported %d of %d rows', $verification_count, count($data))
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to save data - all methods failed'
            ];
        }
    }

    private function get_flexible_field_mapping(array $headers, array $layouts): array
    {
        Logger::log("Getting flexible field mapping for " . count($headers) . " headers and " . count($layouts) . " layouts", 'DEBUG');

        $mapping = [];

        // Collect all possible sub-fields from all layouts
        $all_sub_fields = [];
        foreach ($layouts as $layout) {
            if (isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                foreach ($layout['sub_fields'] as $sub_field) {
                    $all_sub_fields[] = $sub_field;
                }
            }
        }

        Logger::log("Collected " . count($all_sub_fields) . " sub-fields from all layouts", 'DEBUG');

        foreach ($headers as $header) {
            $suggested_field = null;

            // Special handling for layout column
            if ($header === 'acf_fc_layout') {
                $suggested_field = 'acf_fc_layout';
            } else {
                // Try to match with sub-fields
                foreach ($all_sub_fields as $sub_field) {
                    // Direct match
                    if ($sub_field['name'] === $header || $sub_field['label'] === $header) {
                        $suggested_field = $sub_field['name'];
                        break;
                    }

                    // Try with layout prefix removed (e.g., "tresc_tekst" -> "tekst")
                    foreach ($layouts as $layout) {
                        $layout_prefix = $layout['name'] . '_';
                        if (strpos($header, $layout_prefix) === 0) {
                            $field_name = str_replace($layout_prefix, '', $header);
                            if ($sub_field['name'] === $field_name) {
                                $suggested_field = $sub_field['name'];
                                break 2;
                            }
                        }
                    }
                }
            }

            $mapping[$header] = $suggested_field;
            Logger::log("Mapped header '{$header}' to field: " . ($suggested_field ?: 'NO_MATCH'), 'DEBUG');
        }

        return $mapping;
    }
}
