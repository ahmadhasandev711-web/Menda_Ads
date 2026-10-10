<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Elementor widget — thin wrapper around the shared shortcode renderer.
 */
final class Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'menda-unit';
	}

	public function get_title(): string {
		return __( 'Menda Unit', 'menda-ads' );
	}

	public function get_icon(): string {
		return 'eicon-banner';
	}

	public function get_categories(): array {
		return [ 'general' ];
	}

	public function get_keywords(): array {
		return [ 'menda', 'promo', 'unit', 'sponsor' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Placement', 'menda-ads' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'placement',
			[
				'label'   => __( 'Placement', 'menda-ads' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array_merge( [ '' => __( '— Select placement —', 'menda-ads' ) ], Slots::choices() ),
				'default' => '',
			]
		);

		$this->add_control(
			'ad_id',
			[
				'label'       => __( 'Or specific unit ID', 'menda-ads' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'default'     => 0,
				'min'         => 0,
			]
		);

		$this->add_control(
			'count',
			[
				'label'   => __( 'Count', 'menda-ads' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 1,
				'min'     => 1,
				'max'     => 10,
			]
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes.
		echo Display::instance()->shortcode( [
			'placement' => $settings['placement'] ?? '',
			'id'        => $settings['ad_id'] ?? 0,
			'count'     => $settings['count'] ?? 1,
		] );
	}

	protected function content_template(): void {
		?>
		<div class="menda-unit-placeholder" style="padding:16px;border:1px dashed #c3c4c7;text-align:center;">
			<?php echo esc_html__( 'Menda unit — rendered on the front end.', 'menda-ads' ); ?>
		</div>
		<?php
	}
}
