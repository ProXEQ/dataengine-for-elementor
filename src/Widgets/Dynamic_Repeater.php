<?php
namespace DataEngine\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use DataEngine\Core\Plugin;

class Dynamic_Repeater extends Widget_Base
{

    public function get_name(): string
    {
        return 'data-engine-dynamic-repeater';
    }

    public function get_title(): string
    {
        return esc_html__('Dynamic Repeater', 'data-engine-for-elementor');
    }

    public function get_icon(): string
    {
        return 'eicon-sync';
    }

    public function get_categories(): array
    {
        return ['data-engine'];
    }

    protected function register_controls(): void
    {
        // --- Data Source Section ---
        $this->start_controls_section('section_data_source', [
            'label' => __('Data Source', 'data-engine-for-elementor'),
        ]);

        $this->add_control('repeater_field_name', [
            'label' => __('Repeater Field Name', 'data-engine-for-elementor'),
            'type' => Controls_Manager::TEXT, // Zmieniono z SELECT na TEXT
            'placeholder' => __('my_repeater_field', 'data-engine-for-elementor'),
            'description' => __('Enter the name (key) of the repeater field.', 'data-engine-for-elementor'),
        ]);

        $this->end_controls_section();

        // --- Loop Template Section ---
        $this->start_controls_section('section_loop_template', [
            'label' => __('Loop Template', 'data-engine-for-elementor'),
        ]);

        $this->add_control('header_template', [
            'label' => __('Header Template', 'data-engine-for-elementor'),
            'type' => Controls_Manager::TEXTAREA,
            'rows' => 4,
            'placeholder' => '<ul>',
        ]);

        $this->add_control('item_template', [
            'label' => __('Item Template', 'data-engine-for-elementor'),
            'type' => Controls_Manager::TEXTAREA,
            'rows' => 10,
            'description' => __('Use %sub:field_name% to display sub-field values. All DataEngine features are available.', 'data-engine-for-elementor'),
            'placeholder' => '<li>%sub:title% - %sub:description%</li>',
        ]);

        $this->add_control('footer_template', [
            'label' => __('Footer Template', 'data-engine-for-elementor'),
            'type' => Controls_Manager::TEXTAREA,
            'rows' => 4,
            'placeholder' => '</ul>',
        ]);

        $this->end_controls_section();

        // --- No Results Section ---
        $this->start_controls_section('section_no_results', [
            'label' => __('No Results', 'data-engine-for-elementor'),
        ]);

        $this->add_control('no_results_template', [
            'label' => __('No Results Template', 'data-engine-for-elementor'),
            'type' => Controls_Manager::TEXTAREA,
            'rows' => 4,
            'placeholder' => __('No items found.', 'data-engine-for-elementor'),
        ]);

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $repeater_field_name = $settings['repeater_field_name'];

        if ( empty( $repeater_field_name ) ) {
            return;
        }
        
        $repeater_data = get_field( $repeater_field_name );
        $parser = $this->get_parser();

        if ( ! empty( $repeater_data ) && is_array( $repeater_data ) ) {
            
            // --- NOWA LOGIKA BUFOROWANIA ---
            $html_parts = [];
            
            // Loop through each row and add the processed HTML to an array.
            foreach ( $repeater_data as $row_data ) {
                $html_parts[] = $parser->process_loop_item( $settings['item_template'], $row_data );
            }
            
            // Process header and footer templates.
            $header = $parser->process( $settings['header_template'] );
            $footer = $parser->process( $settings['footer_template'] );
            
            // Output the final, assembled HTML in a single operation.
            echo $header . implode( '', $html_parts ) . $footer;
            // --- KONIEC NOWEJ LOGIKI BUFOROWANIA ---

        } else {
            // Handle the "no results" case.
            echo $parser->process( $settings['no_results_template'] );
        }
    }

    /**
     * Get the DataEngine Parser instance.
     * Provides a convenient shortcut to access the core parser.
     * We need to add this helper method here.
     * @return \DataEngine\Engine\Parser
     */
    protected function get_parser(): \DataEngine\Engine\Parser
    {
        return Plugin::instance()->parser;
    }
}
