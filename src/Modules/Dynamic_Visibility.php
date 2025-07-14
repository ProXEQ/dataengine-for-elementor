<?php
namespace DataEngine\Modules;

class Dynamic_Visibility {

    public function __construct() {
        // Add controls to all elements.
        add_action('elementor/element/common/_section_style/after_section_end', [ $this, 'register_visibility_controls' ], 10, 2);

        // The core of our server-side logic. This filter is the most performant way.
        add_filter('elementor/frontend/section/should_render', [ $this, 'should_render_element' ], 10, 2);
        add_filter('elementor/frontend/column/should_render', [ $this, 'should_render_element' ], 10, 2);
        add_filter('elementor/frontend/widget/should_render', [ $this, 'should_render_element' ], 10, 2);
    }

    public function register_visibility_controls( \Elementor\Element_Base $element, $args ): void {
        $element->start_controls_section(
            'data_engine_visibility_section',
            [
                'label' => __( '<i class="eicon-preview-medium"></i> DataEngine Visibility', 'data-engine-for-elementor' ),
                'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
            ]
        );

        $element->add_control(
            'data_engine_visibility_condition',
            [
                'label' => __( 'Display Condition', 'data-engine-for-elementor' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __( "%acf:show_section% == 'true'", 'data-engine-for-elementor' ),
                'description' => __( 'Element will be rendered only if this condition is met. Leave empty to disable.', 'data-engine-for-elementor' ),
            ]
        );

        $element->end_controls_section();
    }

    public function should_render_element( bool $should_render, \Elementor\Element_Base $element ): bool {
        $condition = $element->get_settings('data_engine_visibility_condition');

        if ( empty( $condition ) ) {
            return $should_render; // No condition, render as normal.
        }

        // Here we will eventually use our Parser to evaluate the complex condition.
        // For now, a simple placeholder logic.
        // Example: check if a field 'show_element' is true.
        $field_value = get_field('show_element', get_the_ID());
        
        // This is where the magic happens. We return false to prevent the element from rendering at all.
        // No HTML, no CSS, no JS. Pure performance.
        if ($field_value !== true) {
            return false;
        }

        return $should_render;
    }
}
