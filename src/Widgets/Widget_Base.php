<?php
namespace DataEngine\Widgets;

use Elementor\Widget_Base as Elementor_Widget_Base;
use DataEngine\Core\Plugin;

/**
 * Widget_Base class.
 *
 * An abstract base class for all DataEngine widgets.
 * It provides common functionality, such as access to the plugin's core components
 * and sets the custom widget category.
 *
 * @since 0.1.0
 */
abstract class Widget_Base extends Elementor_Widget_Base {

    /**
     * Get the category of the widget.
     *
     * All DataEngine widgets will be in the same custom category for easy access.
     *
     * @since 0.1.0
     * @return array
     */
    public function get_categories(): array {
        return [ 'data-engine' ];
    }

    /**
     * Get the DataEngine Parser instance.
     *
     * Provides a convenient shortcut to access the core parser from any widget.
     *
     * @since 0.1.0
     * @return \DataEngine\Engine\Parser
     */
    protected function get_parser(): \DataEngine\Engine\Parser {
        // Access the singleton instance of the plugin to get the parser.
        return Plugin::instance()->parser;
    }
}
