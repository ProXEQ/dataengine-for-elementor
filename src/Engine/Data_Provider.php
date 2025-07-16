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
    public function get_value( string $source, string $field_name, ?int $post_id = null ): mixed {
        $post_id = $post_id ?? get_the_ID();
        
        $cache_key = "{$source}:{$post_id}:{$field_name}";
        
        if ( isset( $this->value_cache[ $cache_key ] ) ) {
            Logger::log( "Cache HIT for key: '{$cache_key}'", 'DEBUG' );
            return $this->value_cache[ $cache_key ];
        }
        
        Logger::log( "Cache MISS for key: '{$cache_key}'. Fetching from source.", 'DEBUG' );

        $value = null;

        switch ( $source ) {
            case 'acf':
                $value = get_field( $field_name, $post_id );
                Logger::log( "Fetching ACF field '{$field_name}' for post ID {$post_id}.", 'DEBUG' );
                Logger::log( "ACF field value: " . print_r( $value, true ), 'DEBUG' );
                break;

            case 'post':
                $value = $this->get_post_field( $field_name, $post_id );
                break;
        }

        // Store the fetched value in the cache for subsequent requests.
        $this->value_cache[ $cache_key ] = $value;
        
        return $value;
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
    public function get_field_object( string $field_name, ?int $post_id = null ): ?array {
        $post_id = $post_id ?? get_the_ID();
        $field_object = get_field_object( $field_name, $post_id );
        Logger::log( "Fetching field object for '{$field_name}' in post ID {$post_id}.", 'DEBUG' );
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
    private function get_post_field( string $field_name, ?int $post_id ): mixed {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post ) {
            return null;
        }

        // Special case for post permalink
        if ( 'permalink' === $field_name ) {
            return get_permalink( $post );
        }
        
        // Check for direct properties on the WP_Post object.
        if ( property_exists( $post, $field_name ) ) {
            return $post->{$field_name};
        }

        return null;
    }
    public function get_all_post_fields_for_editor(): array {
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
    public function get_current_context_post_id(): int {
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
