<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Classic widget for placing a banner in any widget area.
 */
class Ads_Widget extends \WP_Widget {

	public function __construct() {
		parent::__construct(
			'menda_ads_banner',
			__( 'Menda Ad Banner', 'menda-ads' ),
			[ 'description' => __( 'Display a Menda Ads banner in any widget area.', 'menda-ads' ) ]
		);
	}

	public function widget( $args, $instance ): void {
		$placement = ! empty( $instance['placement'] ) ? sanitize_text_field( $instance['placement'] ) : '';
		$ad_id     = ! empty( $instance['ad_id'] ) ? absint( $instance['ad_id'] ) : 0;

		$html = Display::instance()->shortcode( [
			'placement' => $placement,
			'id'        => $ad_id,
		] );
		if ( '' === $html ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['before_widget'];
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['after_widget'];
	}

	public function form( $instance ): void {
		$placement = ! empty( $instance['placement'] ) ? $instance['placement'] : '';
		$ad_id     = ! empty( $instance['ad_id'] ) ? (int) $instance['ad_id'] : 0;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'placement' ) ); ?>">
				<?php esc_html_e( 'Placement:', 'menda-ads' ); ?>
			</label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'placement' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'placement' ) ); ?>">
				<option value=""><?php esc_html_e( '— Select placement —', 'menda-ads' ); ?></option>
				<?php foreach ( Slots::choices() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $placement, $slug ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>">
				<?php esc_html_e( 'Or specific Ad ID:', 'menda-ads' ); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'ad_id' ) ); ?>"
				   name="<?php echo esc_attr( $this->get_field_name( 'ad_id' ) ); ?>"
				   type="number" value="<?php echo esc_attr( (string) $ad_id ); ?>">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ): array {
		return [
			'placement' => sanitize_text_field( $new_instance['placement'] ?? '' ),
			'ad_id'     => absint( $new_instance['ad_id'] ?? 0 ),
		];
	}
}
