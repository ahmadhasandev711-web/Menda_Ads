<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Gutenberg block + Elementor widget. Shortcode remains the shared renderer.
 */
final class Integrations {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_elementor' ] );
	}

	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'menda-unit-editor',
			MENDA_ADS_URL . 'blocks/unit/editor.js',
			[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
			MENDA_ADS_VERSION,
			true
		);

		wp_set_script_translations( 'menda-unit-editor', 'menda-ads', MENDA_ADS_DIR . 'languages' );

		$options = [
			[ 'label' => __( '— Select placement —', 'menda-ads' ), 'value' => '' ],
		];
		foreach ( Slots::choices() as $slug => $label ) {
			$options[] = [ 'label' => $label, 'value' => $slug ];
		}
		wp_localize_script( 'menda-unit-editor', 'mendaUnitBlock', [
			'placements' => $options,
		] );

		$args = [
			'title'           => __( 'Menda Unit', 'menda-ads' ),
			'description'     => __( 'Display a Menda banner in any block layout, including FSE templates.', 'menda-ads' ),
			'icon'            => 'format-image',
			'category'        => 'widgets',
			'keywords'        => [ 'menda', 'promo', 'unit', 'sponsor' ],
			'editor_script'   => 'menda-unit-editor',
			'attributes'      => [
				'placement' => [ 'type' => 'string', 'default' => '' ],
				'adId'      => [ 'type' => 'integer', 'default' => 0 ],
				'count'     => [ 'type' => 'integer', 'default' => 1 ],
			],
			'render_callback' => [ $this, 'render_block' ],
		];

		register_block_type( 'menda/unit', $args );
	}

	public function render_block( array $attributes ): string {
		return Display::instance()->shortcode( [
			'id'        => $attributes['adId'] ?? ( $attributes['ad_id'] ?? 0 ),
			'placement' => $attributes['placement'] ?? '',
			'count'     => $attributes['count'] ?? 1,
		] );
	}

	public function register_elementor( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once MENDA_ADS_DIR . 'includes/class-elementor-widget.php';
		$widgets_manager->register( new Elementor_Widget() );
	}
}
