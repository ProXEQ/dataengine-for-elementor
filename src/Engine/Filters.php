<?php
namespace DataEngine\Engine;

use DataEngine\Utils\Logger;

/**
 * Filters Class.
 *
 * Handles data transformations (filters) applied to dynamic tags.
 * Supports built-in filters and allows registration of custom filters.
 *
 * @since 0.1.0
 */
class Filters {

    /**
     * Holds registered custom filters.
     * @var array<string, callable>
     */
    private static array $custom_filters = [];

    /**
     * Applies a chain of filters to a given value.
     *
     * @param mixed $value The original value from the data source.
     * @param array $filters An array of filters to apply, each with a name and arguments.
     * @return mixed The transformed value.
     */
    public static function apply( $value, array $filters ) {
        foreach ( $filters as $filter ) {
            $filter_name = $filter['name'];
            $args = $filter['args'] ?? [];

            // Prepend the original value to the arguments list for the callback.
            array_unshift( $args, $value );

            // Check if a built-in filter method exists (e.g., self::filter_uppercase).
            $method_name = 'filter_' . $filter_name;
            if ( method_exists( self::class, $method_name ) ) {
                $value = call_user_func_array( [ self::class, $method_name ], $args );
                continue;
            }

            // Check if a custom filter has been registered by a developer.
            if ( isset( self::$custom_filters[ $filter_name ] ) ) {
                $callback = self::$custom_filters[ $filter_name ];
                $value = call_user_func_array( $callback, $args );
                continue;
            }

            Logger::log( "Unknown filter applied: '{$filter_name}'.", 'WARNING' );
        }

        return $value;
    }

    /**
     * Registers a custom filter callback.
     * Allows developers to extend DataEngine with their own transformers.
     *
     * @param string   $name     The name of the filter (e.g., 'my_custom_format').
     * @param callable $callback The function to execute for the transformation.
     */
    public static function register( string $name, callable $callback ): void {
        self::$custom_filters[ $name ] = $callback;
        Logger::log( "Custom filter '{$name}' registered.", 'INFO' );
    }

    /*
    |--------------------------------------------------------------------------
    | Built-in Filters
    |--------------------------------------------------------------------------
    */

    private static function filter_date_format( $value, string $format = 'Y-m-d H:i:s' ): string {
        if ( empty( $value ) ) return '';
        $timestamp = is_numeric($value) ? (int) $value : strtotime( (string) $value );
        return $timestamp ? wp_date( $format, $timestamp ) : '';
    }

    private static function filter_number_format( $value, int $decimals = 2, string $dec_point = '.', string $thousands_sep = ',' ): string {
        return is_numeric( $value ) ? number_format( (float) $value, $decimals, $dec_point, $thousands_sep ) : (string) $value;
    }

    private static function filter_truncate( $value, int $length = 100, string $suffix = '...' ): string {
        $string = strip_tags( (string) $value );
        if ( mb_strlen( $string ) <= $length ) return $string;
        return mb_substr( $string, 0, $length ) . $suffix;
    }

    private static function filter_uppercase( $value ): string {
        return mb_strtoupper( (string) $value );
    }

    private static function filter_lowercase( $value ): string {
        return mb_strtolower( (string) $value );
    }
}
