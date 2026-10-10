<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Full-screen overlay between page views.
 */
final class Interstitial {

	private static ?self $instance = null;
	private ?array $cached_ad      = null;
	private bool   $ad_fetched     = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'wp_footer', [ $this, 'render' ] );
	}

	public function has_active(): bool {
		if ( Context::should_skip_overlays() ) {
			return false;
		}
		if ( get_option( MENDA_ADS_PREFIX . '_interstitial_skip_home', true ) && is_front_page() ) {
			return false;
		}
		return null !== $this->get_active_ad();
	}

	private function get_active_ad(): ?array {
		if ( $this->ad_fetched ) {
			return $this->cached_ad;
		}

		if ( ! get_option( MENDA_ADS_PREFIX . '_interstitial_enabled' ) ) {
			$this->ad_fetched = true;
			$this->cached_ad  = null;
			return null;
		}

		$ads = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'publish',
			'posts_per_page' => apply_filters( 'menda_ads_max_interstitial_query', 50 ),
			'meta_query'     => [
				[ 'key' => '_menda_ad_type', 'value' => 'interstitial' ],
			],
		] );

		if ( empty( $ads ) ) {
			$this->ad_fetched = true;
			$this->cached_ad  = null;
			return null;
		}

		$active  = [];
		$display = Display::instance();
		foreach ( $ads as $ad ) {
			if ( $display->is_deliverable( $ad->ID, false ) ) {
				$active[] = $ad;
			}
		}

		if ( empty( $active ) ) {
			$this->ad_fetched = true;
			$this->cached_ad  = null;
			return null;
		}

		$ad          = $active[ array_rand( $active ) ];
		$image_id    = get_post_meta( $ad->ID, '_menda_ad_image_id', true );
		$custom_code = get_post_meta( $ad->ID, '_menda_ad_custom_code', true );
		$link        = get_post_meta( $ad->ID, '_menda_ad_link', true );
		if ( ! $image_id && '' === (string) $custom_code ) {
			$this->ad_fetched = true;
			$this->cached_ad  = null;
			return null;
		}
		$freq        = get_option( MENDA_ADS_PREFIX . '_interstitial_frequency', 'once_per_session' );
		if ( ! in_array( $freq, [ 'once_per_session', 'once_per_day', 'every_page' ], true ) ) {
			$freq = 'once_per_session';
		}
		$show_mobile  = get_post_meta( $ad->ID, '_menda_ad_show_mobile', true );
		$show_desktop = get_post_meta( $ad->ID, '_menda_ad_show_desktop', true );
		if ( '' === $show_mobile ) {
			$show_mobile = '1';
		}
		if ( '' === $show_desktop ) {
			$show_desktop = '1';
		}

		$this->ad_fetched = true;
		$this->cached_ad  = [
			'id'           => $ad->ID,
			'link'         => $link,
			'image_url'    => $image_id ? wp_get_attachment_image_url( (int) $image_id, 'large' ) : '',
			'custom_code'  => $custom_code,
			'skip_delay'   => min( 30, max( 0, (int) get_option( MENDA_ADS_PREFIX . '_interstitial_skip_delay', 5 ) ) ),
			'frequency'    => $freq,
			'show_mobile'  => '1' === (string) $show_mobile,
			'show_desktop' => '1' === (string) $show_desktop,
		];

		return $this->cached_ad;
	}

	public function render(): void {
		if ( ! $this->has_active() ) {
			return;
		}
		$ad = $this->get_active_ad();
		if ( ! $ad ) {
			return;
		}
		$min_views = min( 20, max( 0, (int) get_option( MENDA_ADS_PREFIX . '_interstitial_min_views', 1 ) ) );
		?>
		<div id="menda-gate" class="menda-gate" role="dialog" aria-modal="true"
			aria-label="<?php esc_attr_e( 'Advertisement', 'menda-ads' ); ?>"
			data-ad="<?php echo esc_attr( (string) (int) $ad['id'] ); ?>"
			data-frequency="<?php echo esc_attr( $ad['frequency'] ); ?>"
			data-skip="<?php echo esc_attr( (string) (int) $ad['skip_delay'] ); ?>"
			data-min-views="<?php echo esc_attr( (string) $min_views ); ?>"
			data-show-mobile="<?php echo ! empty( $ad['show_mobile'] ) ? '1' : '0'; ?>"
			data-show-desktop="<?php echo ! empty( $ad['show_desktop'] ) ? '1' : '0'; ?>">
			<div class="menda-gate__overlay"></div>
			<div class="menda-gate__box">
				<button class="menda-gate__skip" id="menda-gate-skip" disabled aria-label="<?php esc_attr_e( 'Skip ad', 'menda-ads' ); ?>">
					<?php esc_html_e( 'Skip in', 'menda-ads' ); ?> <span id="menda-gate-countdown"><?php echo esc_html( (string) $ad['skip_delay'] ); ?></span>s
				</button>
				<div class="menda-gate__content">
					<?php if ( $ad['custom_code'] ) : ?>
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $ad['custom_code']; ?>
					<?php elseif ( $ad['image_url'] ) : ?>
						<a href="<?php echo esc_url( $ad['link'] ); ?>" target="_blank" rel="noopener nofollow sponsored" class="menda-gate__link" data-menda-unit="<?php echo esc_attr( (string) (int) $ad['id'] ); ?>">
							<img src="<?php echo esc_url( $ad['image_url'] ); ?>" width="800" height="600" alt="<?php esc_attr_e( 'Advertisement', 'menda-ads' ); ?>" loading="eager">
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
