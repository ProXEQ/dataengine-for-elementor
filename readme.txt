```txt
=== DataEngine For Elementor ===
Contributors: pixelmobs
Tags: elementor, acf, advanced custom fields, dynamic content, custom fields, data, developer tools, repeater, conditional logic
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.1.2
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A developer-focused data engine to bridge Elementor with ACF. Display any data with a simple, powerful syntax, right inside Elementor.

== Description ==

DataEngine is built for developers and power users who need a fast, efficient, and flexible way to render dynamic data in Elementor. Instead of relying on complex UI controls for every single data point, DataEngine provides a powerful "sandbox" environment where you can write clean HTML combined with a simple yet robust tagging system.

This approach gives you complete control over the markup, structure, and presentation of your data, leading to better performance and cleaner code.

**Key Features:**

*   **Dynamic Content Widget**: A flexible sandbox for rendering any data using a mix of HTML and dynamic tags.
*   **Dynamic Repeater Widget**: Effortlessly loop through ACF Repeater fields and render complex layouts.
*   **Simple & Powerful Syntax**: Use intuitive tags like `%acf:field_name%` and `%post:post_title%`.
*   **Dot Notation Support**: Easily access sub-properties of complex fields (e.g., `%acf:image.url%`).
*   **Built-in Conditional Logic**: Use `[if]...[else]...[/if]` blocks to show or hide content.
*   **Data Transformers (Filters)**: Chain filters to modify your data on the fly (e.g., `|uppercase`).
*   **Advanced "Live Editor"**: A CodeMirror 6 editor with syntax highlighting, validation, and autocompletion.
*   **Performance Caching**: Built-in server-side caching to dramatically speed up your pages.
*   **SVG Support**: Automatically inline SVG content from Image or File fields.

== Installation ==

1.  Navigate to `Plugins > Add New` in your WordPress dashboard.
2.  Search for "DataEngine For Elementor".
3.  Click "Install Now" and then "Activate".
4.  Find the "Dynamic Content" and "Dynamic Repeater" widgets in the Elementor editor under the "DataEngine" category.

For developer installation from GitHub, please refer to the `readme.md` file in the repository. You will need to run `composer install`.

== Frequently Asked Questions ==

= Does this work with ACF Free or Pro? =

Yes, it works perfectly with both versions of Advanced Custom Fields.

= What ACF field types are supported? =

It supports all standard field types, plus advanced handling for Image, File, Taxonomy, User, Post Object, and more. For complex fields, use dot notation to access properties (e.g., `%acf:my_image.url%`).

= How does the performance caching work? =

When enabled in `Settings > DataEngine`, the plugin caches the final HTML output of a widget. This means for subsequent page loads, all data processing is skipped, serving pre-generated HTML directly. The cache is automatically cleared when a post is saved.

= How do I use the conditional logic? =

Use the `[if]` block with a condition. The condition must contain a DataEngine tag, an operator, and a value.
Example: `[if:%acf:price% > 100]Content to show[/if]`
Supported operators: `==`, `!=`, `>`, `<`, `>=`, `<=`, `contains`, `not_contains`.

== Screenshots ==

1. The Dynamic Content widget in the Elementor editor, showing the template area.
2. The advanced Live Editor in action, with syntax highlighting and autocompletion suggestions.
3. The Dynamic Repeater widget's configuration panel.
4. An example of a complex layout rendered on the frontend using data from ACF.
5. The settings page, showing caching and debug mode options.

== Changelog ==

= 1.1.2 =
* Feature: Added `[fallback]` block for cleaner empty-value handling.
* Feature: Added support for `Icon Picker` fields.
* Feature: Added advanced taxonomy filters (`limit`, `separator`, `sort`, `wrap`, `exclude`).
* Fix: Improved real-time validation (linting) in the Live Editor.
* Fix: Enhanced context detection in the Elementor editor for better autocompletion.

= 1.0.0 =
* Initial release.
```