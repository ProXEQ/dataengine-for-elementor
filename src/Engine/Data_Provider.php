<?php
namespace DataEngine\Engine;

use DataEngine\Utils\Logger;

/**
 * Data_Provider Class.
 *
 * Responsible for fetching raw data and metadata from various sources.
 *
 * @since 0.1.0
 */
class Data_Provider {

    private array $value_cache = [];

    /**
     * Retrieves a value based on a source and a field name.
     * This is the main entry point for the data provider.
     *
     * @param string $source The data source (e.g., 'acf', 'post').
     * @param string $field_name The name of the field to retrieve.
     * @param int|null $post_id The context post ID.
     * @return mixed The field value, or null if not found.
     */
    public function get_value(string $source, string $field_name, ?int $post_id = null)
    {
        $post_id = $post_id ?? get_the_ID();
        
        $cache_key = "{$source}:{$post_id}:{$field_name}";
        
        if (isset($this->value_cache[$cache_key])) {
            Logger::log("Cache HIT for key: '{$cache_key}'", 'DEBUG');
            return $this->value_cache[$cache_key];
        }
        
        Logger::log("Cache MISS for key: '{$cache_key}'. Fetching from source.", 'DEBUG');

        $value = null;

        switch ($source) {
            case 'acf':
                // Handle group field dot notation (grupa.pole)
                if (strpos($field_name, '.') !== false) {
                    $field_parts = explode('.', $field_name);
                    $group_name = array_shift($field_parts);
                    $sub_field_path = implode('.', $field_parts);
                    
                    $group_value = get_field($group_name, $post_id);
                    if (is_array($group_value)) {
                        $value = $this->traverse_group_path($group_value, $field_parts);
                    }
                    
                    Logger::log("Fetching ACF group field '{$field_name}' (group: {$group_name}, sub-path: {$sub_field_path}) for post ID {$post_id}.", 'DEBUG');
                } else {
                    $value = get_field($field_name, $post_id);
                    Logger::log("Fetching ACF field '{$field_name}' for post ID {$post_id}.", 'DEBUG');
                }
                
                Logger::log("ACF field value: " . print_r($value, true), 'DEBUG');
                break;

            case 'post':
                $value = $this->get_post_field($field_name, $post_id);
                break;
        }

        // Store the fetched value in the cache for subsequent requests.
        $this->value_cache[$cache_key] = $value;
        
        return $value;
    }

    /**
     * Traverse group field structure to get nested values
     *
     * @param array $group_data The group field data
     * @param array $path_parts The path parts to traverse
     * @return mixed The field value or null if not found
     */
    private function traverse_group_path(array $group_data, array $path_parts)
    {
        $current_value = $group_data;
        
        foreach ($path_parts as $part) {
            if (is_array($current_value) && isset($current_value[$part])) {
                $current_value = $current_value[$part];
            } elseif (is_object($current_value) && property_exists($current_value, $part)) {
                $current_value = $current_value->{$part};
            } else {
                return null;
            }
        }
        
        return $current_value;
    }

    /**
     * Retrieves the entire ACF field object for a given field name.
     * This gives access to metadata like 'label', 'type', 'instructions' etc.
     * This is the new method that was missing.
     *
     * @param string $field_name The name of the ACF field.
     * @param int|null $post_id The context post ID.
     * @return array|null The field object array or null if not found.
     */
    public function get_field_object(string $field_name, ?int $post_id = null): ?array
    {
        $post_id = $post_id ?? get_the_ID();
        
        // Handle group field dot notation
        if (strpos($field_name, '.') !== false) {
            $field_parts = explode('.', $field_name);
            $group_name = array_shift($field_parts);
            
            // Get the group field object
            $group_field_object = get_field_object($group_name, $post_id);
            
            if ($group_field_object && $group_field_object['type'] === 'group' && !empty($group_field_object['sub_fields'])) {
                // Find the specific sub-field
                $sub_field_name = $field_parts[0]; // Get first sub-field name
                
                foreach ($group_field_object['sub_fields'] as $sub_field) {
                    if ($sub_field['name'] === $sub_field_name) {
                        Logger::log("Fetching group sub-field object for '{$field_name}' in post ID {$post_id}.", 'DEBUG');
                        return $sub_field;
                    }
                }
            }
            
            return null;
        }
        
        $field_object = get_field_object($field_name, $post_id);
        Logger::log("Fetching field object for '{$field_name}' in post ID {$post_id}.", 'DEBUG');
        return $field_object ?: null;
    }

    /**
     * Retrieves a value from the WP_Post object.
     * This is one of the original methods you provided.
     *
     * @param string $field_name The property of the WP_Post object.
     * @param int|null $post_id The ID of the post.
     * @return mixed The post field value, or null if invalid.
     */
    private function get_post_field(string $field_name, ?int $post_id)
    {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return null;
        }

        // Special case for post permalink
        if ('permalink' === $field_name) {
            return get_permalink($post);
        }
        
        // Check for direct properties on the WP_Post object.
        if (property_exists($post, $field_name)) {
            return $post->{$field_name};
        }

        return null;
    }

    /**
     * Get all available post fields for editor
     *
     * @return array Array of post field definitions
     */
    public function get_all_post_fields_for_editor(): array
    {
        return [
            ['name' => 'ID', 'label' => 'Post ID'],
            ['name' => 'post_title', 'label' => 'Post Title'],
            ['name' => 'post_content', 'label' => 'Post Content'],
            ['name' => 'post_excerpt', 'label' => 'Post Excerpt'],
            ['name' => 'post_date', 'label' => 'Post Date'],
            ['name' => 'post_modified', 'label' => 'Post Modified Date'],
            ['name' => 'post_status', 'label' => 'Post Status'],
            ['name' => 'post_name', 'label' => 'Post Slug'],
            ['name' => 'post_author', 'label' => 'Post Author ID'],
            ['name' => 'permalink', 'label' => 'Permalink'],
        ];
    }

    /**
     * Get current context post ID
     *
     * @return int The current post ID
     */
    public function get_current_context_post_id(): int
    {
        $post_id = get_the_ID();
        
        // If we're in preview mode, try to get the preview post ID
        if (is_preview()) {
            $preview_id = get_query_var('preview_id');
            if ($preview_id) {
                $post_id = absint($preview_id);
            }
        }
        
        // If we're in admin/editor context, try to get from URL parameters
        if (is_admin() && isset($_GET['post'])) {
            $post_id = absint($_GET['post']);
        }
        
        return $post_id ?: 0;
    }
}
