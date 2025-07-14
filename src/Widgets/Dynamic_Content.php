<?php
namespace DataEngine\Widgets;

use Elementor\Controls_Manager;

/**
 * Dynamic Content Widget.
 *
 * The core "sandbox" widget that allows developers to write custom HTML templates
 * with dynamic tags to display data from various sources.
 *
 * @since 0.1.0
 */
class Dynamic_Content extends Widget_Base {

    /**
     * Get the widget's unique name.
     *
     * @since 0.1.0
     * @return string
     */
    public function get_name(): string {
        return 'data-engine-dynamic-content';
    }

    /**
     * Get the widget's user-facing title.
     *
     * @since 0.1.0
     * @return string
     */
    public function get_title(): string {
        return esc_html__( 'Dynamic Content', 'data-engine-for-elementor' );
    }

    /**
     * Get the widget's icon.
     *
     * @since 0.1.0
     * @return string
     */
    public function get_icon(): string {
        return 'eicon-code';
    }
    
    /**
     * Get widget keywords for the Elementor library search.
     *
     * @since 0.1.0
     * @return array
     */
    public function get_keywords(): array {
        return [ 'dataengine', 'dynamic', 'custom', 'field', 'acf', 'shortcode', 'data' ];
    }

    /**
     * Register the widget's controls in the Elementor editor.
     *
     * @since 0.1.0
     * @access protected
     */
    protected function register_controls(): void {
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__( 'Content Template', 'data-engine-for-elementor' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'template',
            [
                'label' => esc_html__( 'HTML & Data Template', 'data-engine-for-elementor' ),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 15,
                'default' => '<h3>%post:post_title%</h3>
<p>
  <strong>Custom Field Value:</strong> %acf:my_custom_field%
</p>',
                'description' => esc_html__( 'Use tags like %post:post_title% or %acf:field_name%. HTML is allowed.', 'data-engine-for-elementor' ),
                'placeholder' => '<h3>%post:post_title%</h3>',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render the widget's output on the frontend.
     *
     * This is the core rendering logic. It fetches the template, processes it
     * through our parser, and outputs the final, sanitized HTML.
     *
     * @since 0.1.0
     * @access protected
     */
    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $raw_content = $settings['template'];
        
        if ( empty( $raw_content ) ) {
            return;
        }

        // Use our central parser to process the content.
        // The get_id() method provides the current post's ID for context.
        $processed_content = $this->get_parser()->process( $raw_content, get_the_ID() );
        
        // Security is paramount. We use wp_kses_post to allow a wide range of
        // HTML tags and attributes, just like in WordPress post content,
        // while sanitizing against any potential security vulnerabilities.
        echo wp_kses_post( $processed_content );
    }
}
