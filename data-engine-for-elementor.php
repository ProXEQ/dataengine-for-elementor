<?php
/**
 * Plugin Name:       DataEngine For Elementor
 * Plugin URI:        https://pixelmobs.com/project/data-engine-for-elementor
 * Description:       A developer-focused data engine to bridge Elementor with advanced custom fields.
 * Version:           1.1.5.1
 * Author:            PixelMobs
 * Author URI:        https://pixelmobs.com
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       data-engine-for-elementor
 * Elementor tested up to: 3.29
 * Elementor Pro tested up to: 3.29
 * PHP version required: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'DATA_ENGINE_VERSION', '0.1.0' );
define( 'DATA_ENGINE_FILE', __FILE__ );
define( 'DATA_ENGINE_PATH', plugin_dir_path( DATA_ENGINE_FILE ) );

// --- CRITICAL CHANGE ---
// This is the most important part. We absolutely must load the autoloader first.
// If it doesn't exist, we stop everything and inform the admin.
if ( ! file_exists( DATA_ENGINE_PATH . 'vendor/autoload.php' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p>';
        echo esc_html__( 'DataEngine For Elementor is not working. Please run "composer install" in the plugin directory to generate the autoloader.', 'data-engine-for-elementor' );
        echo '</p></div>';
    });
    return;
}
require_once DATA_ENGINE_PATH . 'vendor/autoload.php';
// --- END CRITICAL CHANGE ---

/**
 * Main plugin class instance.
 * Provides a single, global access point to the plugin's main class.
 * This function is what triggers the autoloader for the first time for our Plugin class.
 *
 * @return \DataEngine\Core\Plugin
 */
function data_engine(): \DataEngine\Core\Plugin {
    return \DataEngine\Core\Plugin::instance();
}

// Initialize the plugin.
data_engine();
