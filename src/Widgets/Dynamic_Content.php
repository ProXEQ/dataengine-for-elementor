<?php
namespace DataEngine\Widgets;

use Elementor\Controls_Manager;
use DataEngine\Core\Plugin;

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
                'default' => '<h3>%post:post_title%</h3><p><strong>Custom Field Value:</strong> %acf:my_custom_field%</p>',
                'description' => esc_html__( 'Use tags like %post:post_title% or %acf:field_name%. HTML is allowed.', 'data-engine-for-elementor' ),
                'placeholder' => '<h3>%post:post_title%</h3>'
            ]
        );
        
        // --- OTO BRAKUJĄCY PRZYCISK ---
        $this->add_control(
            'launch_live_editor_content',
            [
                'type' => Controls_Manager::BUTTON,
                'text' => __( 'Launch Live Editor', 'data-engine-for-elementor' ),
                'event' => 'data-engine:launch-editor', // Niestandardowe zdarzenie, na które nasłuchuje nasz JS
                'separator' => 'before',
            ]
        );

        $this->end_controls_section();
    }

    public function add_svg_support_for_kses( $allowed_tags ) {
        $allowed_tags['svg'] = [
            'xmlns'   => true,
            'width'   => true,
            'height'  => true,
            'viewbox' => true, // Note: 'viewBox' is case-sensitive in SVGs
            'class'   => true,
            'fill'    => true,
        ];
        $allowed_tags['path'] = [
            'd'    => true,
            'fill' => true,
        ];
        $allowed_tags['g'] = [
            'fill' => true,
        ];
        // You can add more SVG elements like 'rect', 'circle' if needed
        return $allowed_tags;
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
        $cache_manager = Plugin::instance()->cache_manager;

        // Try to fetch from cache first
        if ( $cache_manager->is_enabled() ) {
            $cache_key = $cache_manager->generate_key( $this );
            $cached_html = $cache_manager->get( $cache_key );
            if ( false !== $cached_html ) {
                echo $cached_html;
                return; // Cache HIT, we're done.
            }
        }
        
        // --- Cache MISS, generate the content ---
        ob_start(); // Start output buffering
        
        $settings = $this->get_settings_for_display();
        $raw_content = $settings['template'];
        add_filter('wp_kses_allowed_html', [ $this, 'add_svg_support_for_kses' ]);
        
        if ( ! empty( $raw_content ) ) {
            $processed_content = $this->get_parser()->process( $raw_content, get_the_ID() );
            echo wp_kses_post( $processed_content );
        }

        $final_html = ob_get_clean(); // Get the generated HTML
        
        // Store the generated HTML in cache if enabled
        if ( $cache_manager->is_enabled() && isset($cache_key) ) {
            $cache_manager->set( $cache_key, $final_html );
        }

        echo $final_html; // Output the final HTML
        remove_filter('wp_kses_allowed_html', [ $this, 'add_svg_support_for_kses' ] );
    }
}
