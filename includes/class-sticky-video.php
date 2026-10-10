<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Corner floating video unit. Supports MP4, YouTube, and Vimeo.
 */
final class Sticky_Video {

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
		$ad = $this->get_active_ad();
		return $ad && ! empty( $ad['video_url'] ) && ! Context::should_skip_overlays();
	}

	private function get_active_ad(): ?array {
		if ( $this->ad_fetched ) {
			return $this->cached_ad;
		}

		if ( ! get_option( MENDA_ADS_PREFIX . '_sticky_video_enabled' ) ) {
			$this->ad_fetched = true;
			$this->cached_ad  = null;
			return null;
		}

		$ads = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'publish',
			'posts_per_page' => apply_filters( 'menda_ads_max_sticky_query', 50 ),
			'meta_query'     => [
				[ 'key' => '_menda_ad_type', 'value' => 'sticky_video' ],
			],
		] );

		if ( empty( $ads ) ) {
			$this->ad_fetched = true;
			$this->cached_ad  = null;
			return null;
		}

		$now       = current_datetime();
		$timezone  = wp_timezone();
		$is_mobile = wp_is_mobile();
		$active    = [];

		foreach ( $ads as $ad ) {
			$start    = get_post_meta( $ad->ID, '_menda_ad_start', true );
			$end      = get_post_meta( $ad->ID, '_menda_ad_end', true );
			$start_dt = $start ? date_create_immutable( $start, $timezone ) : false;
			$end_dt   = $end ? date_create_immutable( $end, $timezone ) : false;
			if ( $start_dt && $start_dt > $now ) {
				continue;
			}
			if ( $end_dt && $end_dt < $now ) {
				continue;
			}
			$show_mobile  = get_post_meta( $ad->ID, '_menda_ad_show_mobile', true );
			$show_desktop = get_post_meta( $ad->ID, '_menda_ad_show_desktop', true );
			if ( '' === $show_mobile ) {
				$show_mobile = '1';
			}
			if ( '' === $show_desktop ) {
				$show_desktop = '1';
			}
			if ( $is_mobile && ! $show_mobile ) {
				continue;
			}
			if ( ! $is_mobile && ! $show_desktop ) {
				continue;
			}
			if ( ! Display::instance()->is_ad_targeted( $ad->ID ) ) {
				continue;
			}
			$active[] = $ad;
		}

		if ( empty( $active ) ) {
			$this->ad_fetched = true;
			$this->cached_ad  = null;
			return null;
		}

		$ad       = $active[ array_rand( $active ) ];
		$position = get_option( MENDA_ADS_PREFIX . '_sticky_video_position', 'bottom-right' );
		if ( ! in_array( $position, [ 'bottom-right', 'bottom-left', 'top-right', 'top-left' ], true ) ) {
			$position = 'bottom-right';
		}

		$this->ad_fetched = true;
		$this->cached_ad  = [
			'id'        => $ad->ID,
			'link'      => get_post_meta( $ad->ID, '_menda_ad_link', true ),
			'video_url' => get_post_meta( $ad->ID, '_menda_ad_video_url', true ),
			'position'  => $position,
		];

		return $this->cached_ad;
	}

	public function render(): void {
		if ( ! $this->has_active() ) {
			return;
		}
		$ad = $this->get_active_ad();
		if ( ! $ad || empty( $ad['video_url'] ) ) {
			return;
		}

		$video_url  = $ad['video_url'];
		$is_youtube = str_contains( $video_url, 'youtube.com' ) || str_contains( $video_url, 'youtu.be' );
		$is_vimeo   = str_contains( $video_url, 'vimeo.com' );
		$video_embed = null;

		if ( $is_youtube ) {
			preg_match( '/(?:v=|youtu\.be\/|embed\/|shorts\/)([a-zA-Z0-9_-]{11})/', $video_url, $m );
			$video_embed = isset( $m[1] ) ? 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=1&mute=1' : '';
		} elseif ( $is_vimeo ) {
			preg_match( '/vimeo\.com(?:\/video|\/channels\/[^\/]+|\/[^\/]+)?\/([0-9]+)/', $video_url, $m );
			$video_embed = isset( $m[1] ) ? 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&muted=1&badge=0' : '';
		}

		$position_class = 'menda-float--' . sanitize_html_class( $ad['position'] );
		$nonce          = wp_create_nonce( 'menda_ads_track' );
		?>
		<div id="menda-float" class="menda-float <?php echo esc_attr( $position_class ); ?>" role="complementary" aria-label="<?php esc_attr_e( 'Video Advertisement', 'menda-ads' ); ?>">
			<div class="menda-float__header">
				<span class="menda-float__label"><?php esc_html_e( 'Ad', 'menda-ads' ); ?></span>
				<button class="menda-float__close" type="button" aria-label="<?php esc_attr_e( 'Close video ad', 'menda-ads' ); ?>">×</button>
			</div>
			<div class="menda-float__player">
				<?php if ( $video_embed ) : ?>
					<iframe
						src="<?php echo esc_url( $video_embed ); ?>"
						allow="autoplay; encrypted-media"
						allowfullscreen
						title="<?php esc_attr_e( 'Video Advertisement', 'menda-ads' ); ?>"
						loading="lazy"
						referrerpolicy="strict-origin-when-cross-origin"
						sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"
					></iframe>
				<?php else : ?>
					<video autoplay muted playsinline loop>
						<source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4">
					</video>
				<?php endif; ?>
			</div>
			<?php if ( $ad['link'] ) : ?>
				<a href="<?php echo esc_url( $ad['link'] ); ?>" target="_blank" rel="noopener nofollow sponsored" class="menda-float__cta" data-menda-unit="<?php echo esc_attr( (string) (int) $ad['id'] ); ?>">
					<?php esc_html_e( 'Learn More', 'menda-ads' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<script>
		( function () {
			var adId = <?php echo (int) $ad['id']; ?>;
			var restUrl = <?php echo wp_json_encode( Context::track_url() ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var closeKey = 'menda_float_closed_' + adId;
			var wrapper = document.getElementById( 'menda-float' );

			if ( sessionStorage.getItem( closeKey ) ) {
				if ( wrapper ) wrapper.style.display = 'none';
				return;
			}

			function track( event ) {
				if ( ! navigator.sendBeacon ) return;
				var fd = new FormData();
				fd.append( 'ad_id', adId );
				fd.append( 'event', event );
				fd.append( 'nonce', nonce );
				navigator.sendBeacon( restUrl, fd );
			}

			document.addEventListener( 'DOMContentLoaded', function () {
				track( 'view' );
				var closeBtn = document.querySelector( '.menda-float__close' );
				if ( closeBtn ) {
					closeBtn.addEventListener( 'click', function () {
						if ( wrapper ) wrapper.style.display = 'none';
						sessionStorage.setItem( closeKey, '1' );
					} );
				}
				var cta = document.querySelector( '.menda-float__cta' );
				if ( cta ) {
					cta.addEventListener( 'click', function () { track( 'click' ); } );
				}
			} );
		} )();
		</script>
		<?php
	}
}
