# DataEngine For Elementor

[![Plugin Version](https://img.shields.io/badge/version-1.1.2-orange.svg)](https://github.com/ProXEQ/dataengine-for-elementor)
[![WordPress Tested Up To](https://img.shields.io/badge/WordPress-6.8-blue.svg)](https://wordpress.org/download/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1+-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Author](https://img.shields.io/badge/Author-PixelMobs-informational)](https://pixelmobs.com)

A developer-focused data engine to bridge Elementor with advanced custom fields. Display any data from ACF and Post fields with a simple, powerful syntax, right inside Elementor.

## What is DataEngine?

DataEngine is built for developers and power users who need a fast, efficient, and flexible way to render dynamic data in Elementor. Instead of relying on complex UI controls for every single data point, DataEngine provides a powerful "sandbox" environment where you can write clean HTML combined with a simple yet robust tagging system.

This approach gives you complete control over the markup, structure, and presentation of your data, leading to better performance and cleaner code.

## Key Features

*   **Dynamic Content Widget**: A flexible sandbox for rendering any data using a mix of HTML and dynamic tags.
*   **Dynamic Repeater Widget**: Effortlessly loop through ACF Repeater fields and render complex layouts.
*   **Simple & Powerful Syntax**: Use intuitive tags like `%acf:field_name%` and `%post:post_title%`.
*   **Dot Notation Support**: Easily access sub-properties of complex fields (e.g., `%acf:image.url%`, `%acf:user.display_name%`).
*   **Built-in Conditional Logic**: Use `[if]...[else]...[/if]` blocks to show or hide content based on data values.
*   **Data Transformers (Filters)**: Chain filters to modify your data on the fly (e.g., `%acf:text|uppercase|truncate(50)%`).
*   **Advanced "Live Editor"**: A full-featured CodeMirror 6 editor with syntax highlighting, real-time validation, and intelligent autocompletion for DataEngine tags.
*   **Performance Caching**: Built-in server-side caching (using Transients) to dramatically speed up your pages.
*   **SVG Support**: Automatically inline SVG content from Image or File fields for crisp, scalable icons and graphics.
*   **Developer Focused**: Clean, modern, and extensible codebase following best practices.

## Requirements

*   WordPress 6.0 or higher
*   PHP 8.1 or higher
*   Elementor (Free or Pro)
*   Advanced Custom Fields (ACF) (Free or Pro)

## Installation

#### From WordPress.org (Recommended)

1.  Navigate to `Plugins > Add New` in your WordPress dashboard.
2.  Search for "DataEngine For Elementor".
3.  Click "Install Now" and then "Activate".

#### From GitHub (For Developers)

1.  Download the latest release `.zip` file from the [Releases](https://github.com/ProXEQ/dataengine-for-elementor/releases) page.
2.  In your WordPress dashboard, go to `Plugins > Add New` and click "Upload Plugin".
3.  Upload the `.zip` file and activate the plugin.
4.  **Alternatively, if cloning the repository**: After cloning, you **must** run `composer install` in the plugin's root directory to generate the required autoloader. Without this step, the plugin will not work.

## How It Works

1.  Add the **Dynamic Content** or **Dynamic Repeater** widget to your Elementor layout.
2.  In the widget settings, you'll find a textarea where you can write your template.
3.  Combine standard HTML with DataEngine tags to structure your content.
4.  For the best experience, click the **"Launch Live Editor"** button. This will open a full-screen, professional code editor with syntax highlighting and autocompletion that makes writing templates a breeze.

### Syntax Reference

*   **Basic Tags**: `%source:field_name%`
    *   `%acf:my_text_field%`
    *   `%post:post_title%`
*   **Repeater Sub-Fields**: `%sub:sub_field_name%` (only inside the Dynamic Repeater widget)
*   **Field Properties**: `field_name.property`
    *   `%acf:my_image.url%`
    *   `%acf:my_image.alt%`
    *   `%acf:my_file.ID%`
    *   `%acf:taxonomy_field.name%`
*   **Filters**: `|filter_name(arg1, arg2)`
    *   `%post:post_title|uppercase%`
    *   `%acf:event_date|date_format('F j, Y')%`
*   **Conditional Logic**:
    ```
    [if:%acf:show_banner% == 'true']
      <div class="banner">...</div>
    [/if]

    [if:%acf:price% > 100]
      <span class="premium">Premium Item</span>
    [else if:%acf:price% > 50]
      <span class="standard">Standard Item</span>
    [else]
      <span class="basic">Basic Item</span>
    [/if]
    ```
*   **Fallbacks**:
    ```
    %acf:optional_image.url%[fallback]https://via.placeholder.com/150[/fallback]
    ```

## Frequently Asked Questions (FAQ)

*   **Does this work with ACF Free or Pro?**
    Yes, it works perfectly with both versions.

*   **What ACF field types are supported?**
    It supports all standard field types (Text, Textarea, Number, Email, etc.), plus advanced handling for Image, File, Taxonomy, User, Post Object, and Icon Picker (from other plugins). For complex fields, use dot notation to access specific properties (e.g., `.url`, `.title`, `.alt`).

*   **How does the performance caching work?**
    When enabled, the plugin caches the final HTML output of a widget in a WordPress transient. This means for subsequent page loads, the data processing and template parsing are completely skipped, serving the pre-generated HTML directly. The cache is automatically cleared when a post is saved.

*   **I see an error about `vendor/autoload.php` being missing.**
    This means you have installed the plugin by cloning the Git repository without running Composer. In the plugin's main directory, run the command `composer install` to generate the necessary files.

## Changelog

#### 1.1.2
*   Feature: Added `[fallback]` block for cleaner empty-value handling.
*   Feature: Added support for `Icon Picker` fields.
*   Feature: Added advanced taxonomy filters (`limit`, `separator`, `sort`, `wrap`, `exclude`).
*   Fix: Improved real-time validation (linting) in the Live Editor.
*   Fix: Enhanced context detection in the Elementor editor for better autocompletion.

#### 1.0.0
*   Initial release.