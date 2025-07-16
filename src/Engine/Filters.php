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
class Filters
{

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
    public static function apply($value, array $filters)
    {
        foreach ($filters as $filter) {
            $filter_name = $filter['name'];
            $args = $filter['args'] ?? [];

            // Prepend the original value to the arguments list for the callback.
            array_unshift($args, $value);

            // Check if a built-in filter method exists (e.g., self::filter_uppercase).
            $method_name = 'filter_' . $filter_name;
            if (method_exists(self::class, $method_name)) {
                $value = call_user_func_array([self::class, $method_name], $args);
                continue;
            }

            // Check if a custom filter has been registered by a developer.
            if (isset(self::$custom_filters[$filter_name])) {
                $callback = self::$custom_filters[$filter_name];
                $value = call_user_func_array($callback, $args);
                continue;
            }

            Logger::log("Unknown filter applied: '{$filter_name}'.", 'WARNING');
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
    public static function register(string $name, callable $callback): void
    {
        self::$custom_filters[$name] = $callback;
        Logger::log("Custom filter '{$name}' registered.", 'INFO');
    }

    /*
    |--------------------------------------------------------------------------
    | Built-in Filters
    |--------------------------------------------------------------------------
    */

    private static function filter_date_format($value, string $format = 'Y-m-d H:i:s'): string
    {
        if (empty($value))
            return '';
        $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
        return $timestamp ? wp_date($format, $timestamp) : '';
    }

    private static function filter_number_format($value, int $decimals = 2, string $dec_point = '.', string $thousands_sep = ','): string
    {
        return is_numeric($value) ? number_format((float) $value, $decimals, $dec_point, $thousands_sep) : (string) $value;
    }

    private static function filter_truncate($value, int $length = 100, string $suffix = '...'): string
    {
        $string = strip_tags((string) $value);
        if (mb_strlen($string) <= $length)
            return $string;
        return mb_substr($string, 0, $length) . $suffix;
    }

    private static function filter_uppercase($value): string
    {
        return mb_strtoupper((string) $value);
    }

    private static function filter_lowercase($value): string
    {
        return mb_strtolower((string) $value);
    }

    private static function filter_limit($value, ...$args): string
    {
        if (!is_string($value) || empty($args[0])) {
            return (string) $value; // FIXED: Zawsze zwracaj string
        }

        $limit = intval($args[0]);
        $terms = explode(', ', $value);
        $limited = array_slice($terms, 0, $limit);

        return implode(', ', $limited);
    }

    /**
     * Change separator between terms
     */
    private static function filter_separator($value, ...$args): string
    {
        if (!is_string($value) || empty($args[0])) {
            return (string) $value; // FIXED: Zawsze zwracaj string
        }

        $new_separator = $args[0];
        return str_replace(', ', $new_separator, $value);
    }

    /**
     * Sort taxonomy terms by specified property
     */
    private static function filter_sort($value, ...$args): string
    {
        if (!is_string($value) || empty($args[0])) {
            return (string) $value; // FIXED: Zawsze zwracaj string
        }

        $sort_by = $args[0];
        $terms = explode(', ', $value);

        if ($sort_by === 'name') {
            sort($terms);
        } elseif ($sort_by === 'reverse') {
            rsort($terms);
        }

        return implode(', ', $terms);
    }

    /**
     * Wrap each term with custom HTML
     */
    private static function filter_wrap($value, ...$args): string
    {
        if (!is_string($value) || count($args) < 2) {
            return (string) $value; // FIXED: Zawsze zwracaj string
        }

        $before = $args[0];
        $after = $args[1];
        $terms = explode(', ', $value);

        $wrapped = array_map(function ($term) use ($before, $after) {
            return $before . trim($term) . $after;
        }, $terms);

        return implode(', ', $wrapped);
    }

    /**
     * Exclude specific terms from the list
     */
    private static function filter_exclude($value, ...$args): string
    {
        if (!is_string($value) || empty($args[0])) {
            return (string) $value; // FIXED: Zawsze zwracaj string
        }

        $exclude_terms = array_map('trim', explode(',', $args[0]));
        $terms = explode(', ', $value);

        $filtered = array_filter($terms, function ($term) use ($exclude_terms) {
            return !in_array(trim($term), $exclude_terms);
        });

        return implode(', ', $filtered);
    }
    private static function filter_join($value, string $separator = ', '): string
{
    if (is_array($value)) {
        $filtered = array_filter($value, function($item) {
            return !empty($item) && $item !== null && $item !== '';
        });
        return implode($separator, $filtered);
    }
    return (string) $value;
}

/**
 * Count number of selected values
 */
private static function filter_count($value): string
{
    if (is_array($value)) {
        $filtered = array_filter($value, function($item) {
            return !empty($item) && $item !== null && $item !== '';
        });
        return (string) count($filtered);
    }
    return is_empty($value) ? '0' : '1';
}

/**
 * Get first value from array
 */
private static function filter_first($value): string
{
    if (is_array($value)) {
        $filtered = array_filter($value, function($item) {
            return !empty($item) && $item !== null && $item !== '';
        });
        return !empty($filtered) ? (string) reset($filtered) : '';
    }
    return (string) $value;
}

/**
 * Get last value from array
 */
private static function filter_last($value): string
{
    if (is_array($value)) {
        $filtered = array_filter($value, function($item) {
            return !empty($item) && $item !== null && $item !== '';
        });
        return !empty($filtered) ? (string) end($filtered) : '';
    }
    return (string) $value;
}
}
