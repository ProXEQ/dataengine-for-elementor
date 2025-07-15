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
        // --- COMBINED: Data Source & Loop Template Section ---
        $this->start_controls_section('section_repeater_config', [
            'label' => __('Repeater Configuration', 'data-engine-for-elementor'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        // Data Source Controls
        $this->add_control('data_source_heading', [
            'label' => __('Data Source', 'data-engine-for-elementor'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('repeater_field_name', [
            'label' => __('Repeater Field Name', 'data-engine-for-elementor'),
            'type' => Controls_Manager::TEXT,
            'placeholder' => __('my_repeater_field', 'data-engine-for-elementor'),
            'description' => __('Enter the name (key) of the repeater field.', 'data-engine-for-elementor'),
            'classes' => 'data-engine-repeater-name-input',
        ]);

        // Template Controls
        $this->add_control('template_heading', [
            'label' => __('Templates', 'data-engine-for-elementor'),
            'type' => Controls_Manager::HEADING,
            'separator' => 'before',
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
            'placeholder' => '<li>%sub:title% - %sub:description%</li>'
        ]);

        // Live Editor Button - now in the same section
        $this->add_control(
            'launch_live_editor_repeater',
            [
                'type' => Controls_Manager::BUTTON,
                'text' => __('Launch Live Editor', 'data-engine-for-elementor'),
                'event' => 'data-engine:launch-editor',
                'separator' => 'before',
            ]
        );

        $this->add_control('footer_template', [
            'label' => __('Footer Template', 'data-engine-for-elementor'),
            'type' => Controls_Manager::TEXTAREA,
            'rows' => 4,
            'placeholder' => '</ul>',
        ]);

        $this->end_controls_section();

        // --- No Results Section (separate for better UX) ---
        $this->start_controls_section('section_no_results', [
            'label' => __('No Results', 'data-engine-for-elementor'),
            'tab' => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('no_results_template', [
            'label' => __('No Results Template', 'data-engine-for-elementor'),
            'type' => Controls_Manager::TEXTAREA,
            'rows' => 4,
            'placeholder' => __('No items found.', 'data-engine-for-elementor'),
        ]);

        $this->end_controls_section();
    }

    public function add_svg_support_for_kses($allowed_tags)
    {
        $allowed_tags['svg'] = [
            'xmlns' => true,
            'width' => true,
            'height' => true,
            'viewbox' => true,
            'class' => true,
            'fill' => true,
        ];
        $allowed_tags['path'] = [
            'd' => true,
            'fill' => true,
        ];
        $allowed_tags['g'] = [
            'fill' => true,
        ];
        return $allowed_tags;
    }

    protected function render(): void
    {
        $cache_manager = Plugin::instance()->cache_manager;

        // Caching should be disabled in the editor to see live changes.
        if ($cache_manager->is_enabled() && !\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            $cache_key = $cache_manager->generate_key($this);
            $cached_html = $cache_manager->get($cache_key);
            if (false !== $cached_html) {
                echo $cached_html;
                return;
            }
        }

        ob_start();
        $settings = $this->get_settings_for_display();
        $repeater_field_name = $settings['repeater_field_name'];

        $post_id = get_the_ID();

        if (empty($repeater_field_name)) {
            ob_end_clean();
            return;
        }

        $repeater_data = get_field($repeater_field_name, $post_id);

        // KRYTYCZNA ZMIANA: Dodaj filtr na samym początku
        add_filter('wp_kses_allowed_html', [$this, 'add_svg_support_for_kses']);
        $parser = $this->get_parser();
        try {
            if (!empty($repeater_data) && is_array($repeater_data)) {

                $html_parts = [];

                foreach ($repeater_data as $row_data) {
                    $html_parts[] = $parser->process_loop_item($settings['item_template'], $row_data, $post_id);
                }

                $header = $parser->process($settings['header_template'], $post_id);
                $footer = $parser->process($settings['footer_template'], $post_id);

                echo $header . implode('', $html_parts) . $footer;

            } else {
                echo $parser->process($settings['no_results_template'], $post_id);
            }

            $final_html = ob_get_clean();

            // Caching logic
            if ($cache_manager->is_enabled() && !\Elementor\Plugin::$instance->editor->is_edit_mode() && isset($cache_key)) {
                $cache_manager->set($cache_key, $final_html);
            }

            echo $final_html;
        } finally {
            remove_filter('wp_kses_allowed_html', [$this, 'add_svg_support_for_kses']);
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
