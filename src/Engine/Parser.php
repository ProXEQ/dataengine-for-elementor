<?php
namespace DataEngine\Engine;

use DataEngine\Utils\Logger;

/**
 * Parser Class - Final Production Version
 *
 * This version masterfully combines:
 * 1. Dot notation for data access (%acf:field.property%).
 * 2. Conditional logic ([if]/[fallback]).
 * 3. Data transformers (|filters).
 * 4. Loop context awareness for repeaters (%sub:field%).
 *
 * @since 0.1.0
 */
class Parser
{

    private Data_Provider $data_provider;

    // --- KEY CHANGE: Updated TAG_REGEX to explicitly support 'sub' source ---
    private const TAG_REGEX = '/%(sub|acf|post):([a-zA-Z0-9_.-]+)(?:\s*\|\s*([^%]+))?%/';
    private const IF_BLOCK_REGEX = '/\[if:([^\]]+)\](.*?)\[\/if\]/s';
    private const FALLBACK_BLOCK_REGEX = '/(%[^%]+%)\[fallback\](.*?)\[\/fallback\]/s';

    public function __construct(Data_Provider $data_provider)
    {
        $this->data_provider = $data_provider;
    }

    /**
     * Public method for processing content in the global page context.
     */
    public function process(string $content, ?int $context_post_id = null): string
    {
        return $this->process_content($content, $context_post_id, null);
    }

    public function process_loop_item(string $template, array $loop_item_data, ?int $context_post_id = null): string
    {
        return $this->process_content($template, $context_post_id, $loop_item_data);
    }

    /**
     * The main internal processing engine, now aware of loop context.
     * All other methods call this one to ensure consistent processing order.
     */
    private function process_content(string $content, ?int $context_post_id, ?array $loop_item_data): string
    {
        // The order is critical: process structures first, then simple tags.
        $content = $this->process_conditionals($content, $context_post_id, $loop_item_data);
        $content = $this->process_fallbacks($content, $context_post_id, $loop_item_data);
        $content = $this->process_tags($content, $context_post_id, $loop_item_data);
        return $content;
    }

    /**
     * Processes [if] blocks, now passing the loop context to sub-processes.
     */
    private function process_conditionals(string $content, ?int $context_post_id, ?array $loop_item_data): string
    {
        // Use a while loop to correctly handle nested [if] blocks from the inside out.
        while (preg_match(self::IF_BLOCK_REGEX, $content)) {
            $content = preg_replace_callback(self::IF_BLOCK_REGEX, function ($matches) use ($context_post_id, $loop_item_data) {
                $full_block_content = $matches[2];
                $parts = preg_split('/(\[else if:([^\]]+)\]|\[else\])/s', $full_block_content, -1, PREG_SPLIT_DELIM_CAPTURE);
                $conditions = [$matches[1]];
                $outputs = [array_shift($parts)];
                for ($i = 0; $i < count($parts); $i += 3) {
                    $conditions[] = $parts[$i + 1] ?? '__ELSE__';
                    $outputs[] = $parts[$i + 2] ?? '';
                }
                foreach ($conditions as $index => $condition_string) {
                    if ('__ELSE__' === $condition_string || $this->evaluate_condition($condition_string, $context_post_id, $loop_item_data)) {
                        // CRITICAL FIX: We return the raw inner content. The outer while loop
                        // will then process any nested [if] blocks within it. This prevents recursion.
                        return $outputs[$index];
                    }
                }
                return ''; // No condition met.
            }, $content, 1);
        }
        return $content;
    }

    /**
     * Evaluates a condition, now aware of loop context.
     */
    private function evaluate_condition(string $condition_string, ?int $context_post_id, ?array $loop_item_data): bool
    {
        $condition_regex = '/^\s*(%[^%]+%)\s*([=<>!]{1,2}|contains|not_contains)\s*\'?([^\']*)\'?\s*$/';
        if (!preg_match($condition_regex, $condition_string, $matches))
            return false;

        $tag = $matches[1];
        $operator = $matches[2];
        $expected_value = $matches[3];

        // CRITICAL FIX: We now use process_tags directly to resolve the value,
        // avoiding a recursive call to the full process_content method.
        $actual_value = $this->process_tags($tag, $context_post_id, $loop_item_data);

        switch ($operator) {
            case '==':
                return $actual_value == $expected_value;
            // ... (pozostałe operatory bez zmian) ...
            case '!=':
                return $actual_value != $expected_value;
            case '>':
                return (float) $actual_value > (float) $expected_value;
            case '<':
                return (float) $actual_value < (float) $expected_value;
            case '>=':
                return (float) $actual_value >= (float) $expected_value;
            case '<=':
                return (float) $actual_value <= (float) $expected_value;
            case 'contains':
                return str_contains((string) $actual_value, (string) $expected_value);
            case 'not_contains':
                return !str_contains((string) $actual_value, (string) $expected_value);
            default:
                return false;
        }
    }

    /**
     * Processes [fallback] blocks, now aware of loop context.
     */
    private function process_fallbacks(string $content, ?int $context_post_id, ?array $loop_item_data): string
    {
        return preg_replace_callback(self::FALLBACK_BLOCK_REGEX, function ($matches) use ($context_post_id, $loop_item_data) {
            $tag = $matches[1];
            $fallback_content = $matches[2];
            // --- KEY CHANGE: Call the internal process_content to resolve tags ---
            $value = $this->process_content($tag, $context_post_id, $loop_item_data);
            if (empty($value) && $value !== '0')
                return $fallback_content;
            return $value;
        }, $content);
    }

    /**
     * Processes all tags, now distinguishing between 'sub' and global sources.
     */
    private function process_tags(string $content, ?int $context_post_id, ?array $loop_item_data): string
    {
        return preg_replace_callback(self::TAG_REGEX, function ($matches) use ($context_post_id, $loop_item_data) {
            $source = $matches[1];
            $path_string = $matches[2];
            $filters_string = $matches[3] ?? '';
            $value = null;

            $path_parts = explode('.', $path_string);
            $field_name = $path_parts[0];
            $property = $path_parts[1] ?? null;


            if ($source === 'sub' && $loop_item_data !== null) {
                $raw_value = $this->resolve_path_from_array($path_string, $loop_item_data);

                // Check if this is a request for raw SVG content (no property requested)
                if (!$property && is_array($raw_value)) {
                    // Handle Icon Picker with media_library type
                    $effective_value = $raw_value;
                    if (isset($raw_value['type']) && $raw_value['type'] === 'media_library' && isset($raw_value['value'])) {
                        $effective_value = $raw_value['value'];
                    }

                    // Check if this is an SVG file and inline its content
                    if (is_array($effective_value) && isset($effective_value['mime_type']) && $effective_value['mime_type'] === 'image/svg+xml') {
                        Logger::log("Rendering SVG content for sub-field: {$field_name}", 'DEBUG');
                        $file_path = get_attached_file($effective_value['ID']);
                        if ($file_path && file_exists($file_path)) {
                            $value = file_get_contents($file_path);
                            Logger::log("SVG content loaded from: {$file_path}", 'DEBUG');
                        }
                    } else {
                        $value = $raw_value;
                    }
                } else {
                    // For property requests or non-SVG fields, use the standard logic
                    $value = $raw_value;
                }

            } else {
                // Logic for 'acf' and 'post' sources
                $raw_value = $this->data_provider->get_value($source, $field_name, $context_post_id);

                if ($property) {
                    // A specific property is requested (e.g., .url, .class, .label)
                    if ($property === 'label') {
                        $field_object = $this->data_provider->get_field_object($field_name, $context_post_id);
                        $value = $field_object['label'] ?? '';
                    } else {
                        // Let traverse_path handle finding the specific property.
                        $value = $this->traverse_path($raw_value, [$property]);
                    }
                } else {
                    // No property requested - we need to handle the raw value intelligently.

                    // First, "unwrap" the value to get the core data, which is crucial for the Icon Picker field.
                    $effective_value = $raw_value;
                    if (is_array($effective_value) && isset($effective_value['type']) && $effective_value['type'] === 'media_library' && isset($effective_value['value'])) {
                        $effective_value = $effective_value['value'];
                    }

                    // Now, based on the effective value, decide what to render.

                    // Case 1: The effective value is an SVG image array. Inline its content.
                    if (is_array($effective_value) && isset($effective_value['mime_type']) && $effective_value['mime_type'] === 'image/svg+xml') {
                        Logger::log("Rendering SVG content for field: {$field_name}", 'DEBUG');
                        $file_path = get_attached_file($effective_value['ID']);
                        if ($file_path && file_exists($file_path)) {
                            $value = file_get_contents($file_path);
                            Logger::log("SVG content loaded from: {$file_path}", 'DEBUG');
                        }
                    }
                    // Case 2: The raw value is a simple scalar type (string, number). It can be safely rendered.
                    else if (!is_array($raw_value) && !is_object($raw_value)) {
                        $value = $raw_value;
                    }
                    // Case 3: The raw value is any other complex type (Image, File, Term object, etc.)
                    // for which no specific property was requested. We return an empty string to prevent errors.
                    else {
                        $value = '';
                    }
                }
            }


            if (!empty($filters_string)) {
                if (is_array($value) || is_object($value))
                    return '';
                $filters = $this->parse_filters($filters_string);
                $value = Filters::apply($value, $filters);
            }

            if (is_array($value) || is_object($value))
                return '';
            return (string) $value;
        }, $content);
    }

    /**
     * Helper to resolve dot notation paths from the main Data_Provider.
     */
    private function resolve_path_from_provider(string $source, string $path_string, ?int $context_post_id): mixed
    {
        $path_parts = explode('.', $path_string);
        $field_name = array_shift($path_parts);
        if (!empty($path_parts) && end($path_parts) === 'label') {
            $field_object = $this->data_provider->get_field_object($field_name, $context_post_id);
            return $field_object['label'] ?? '';
        }
        $value = $this->data_provider->get_value($source, $field_name, $context_post_id);
        return $this->traverse_path($value, $path_parts);
    }

    /**
     * Helper to resolve dot notation paths from a simple array (the repeater row data).
     */
    private function resolve_path_from_array(string $path_string, array $data): mixed
    {
        $path_parts = explode('.', $path_string);
        $field_name = array_shift($path_parts);
        $value = $data[$field_name] ?? null;
        return $this->traverse_path($value, $path_parts);
    }

    /**
     * A generic helper to traverse a path on a given value (array or object).
     */
    private function traverse_path(mixed $value, array $path_parts): mixed
    {
        if (is_array($value) && isset($value['type']) && $value['type'] === 'media_library' && isset($value['value'])) {
            $value = $value['value'];
        }

        foreach ($path_parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } elseif (is_object($value) && property_exists($value, $part)) {
                $value = $value->{$part};
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Parses the filter string into a structured array.
     */
    private function parse_filters(string $filters_string): array
    {
        $parsed_filters = [];
        $filter_parts = preg_split('/\|(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', $filters_string);
        foreach ($filter_parts as $part) {
            $part = trim($part);
            if (preg_match('/^([a-zA-Z0-9_]+)(?:\((.*)\))?$/', $part, $matches)) {
                $name = $matches[1];
                $args_string = $matches[2] ?? '';
                $args = [];
                if (!empty($args_string)) {
                    $arg_parts = preg_split('/,(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', $args_string);
                    foreach ($arg_parts as $arg) {
                        $args[] = trim(trim($arg), "'\"");
                    }
                }
                $parsed_filters[] = ['name' => $name, 'args' => $args];
            }
        }
        return $parsed_filters;
    }

    public function evaluate(string $condition_string, ?int $context_post_id = null): bool
    {
        // We reuse the private evaluate_condition method, passing null for the loop context.
        return $this->evaluate_condition($condition_string, $context_post_id, null);
    }
}
