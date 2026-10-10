<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Website Skin / Takeover 2.0
 *
 * One Skin-type ad is the campaign: wallpaper + left/right rails + optional
 * billboard + mobile fallback. Frequency and viewport are applied in the
 * document head so cached HTML does not need Cache-Control: no-store.
 */
final class Skin {

	private static ?self $instance = null;
	private ?array $cached_skin    = null;
	private bool   $skin_fetched   = false;
	private bool   $layer_printed  = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_filter( 'language_attributes', [ $this, 'html_attributes' ] );
		add_action( 'wp_head',      [ $this, 'inject_boot' ], 0 );
		add_action( 'wp_head',      [ $this, 'inject_styles' ], 99 );
		add_action( 'wp_body_open', [ $this, 'inject_layer' ], 1 );
		add_action( 'wp_footer',    [ $this, 'inject_layer' ], 1 );
		add_action( 'switch_theme', [ $this, 'clear_selector_cache' ] );
	}

	public function html_attributes( string $output ): string {
		return $output;
	}

	public function has_active(): bool {
		return null !== $this->get_active_skin() && ! Context::should_skip_overlays();
	}

	public function has_mobile_fallback(): bool {
		$skin = $this->get_active_skin();
		if ( ! $skin || Context::should_skip_overlays() ) {
			return false;
		}
		if ( ! in_array( $skin['mobile_mode'], [ 'banner', 'sticky' ], true ) ) {
			return false;
		}
		if ( '' !== $this->mobile_creative( $skin ) ) {
			return true;
		}
		return 'boxed' === ( $skin['layout'] ?? '' ) && $this->is_brand_color( $skin );
	}

	private function is_brand_color( array $skin ): bool {
		$c = strtolower( trim( (string) ( $skin['bg_color'] ?? '' ) ) );
		return '' !== $c && ! in_array( $c, [ '#ffffff', '#fff', 'transparent' ], true );
	}

	/**
	 * Phones cannot use left/right rails. Prefer the mobile asset, else a side panel.
	 */
	private function mobile_creative( array $skin ): string {
		foreach ( [ 'mobile_image', 'left_image', 'right_image', 'bg_image' ] as $key ) {
			if ( ! empty( $skin[ $key ] ) ) {
				return (string) $skin[ $key ];
			}
		}
		return '';
	}

	public function detect_selectors(): array {
		$manual = Context::sanitize_selector( (string) get_option( MENDA_ADS_PREFIX . '_skin_wrapper_selector', '' ) );
		if ( $manual ) {
			return [ 'wrapper' => $manual, 'header' => '', 'source' => 'manual' ];
		}
		return [
			'wrapper' => '',
			'header'  => '',
			'source'  => 'rails',
		];
	}

	public function clear_selector_cache(): void {
		delete_transient( MENDA_ADS_PREFIX . '_skin_selectors' );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_active_skin(): ?array {
		if ( $this->skin_fetched ) {
			return $this->cached_skin;
		}

		$preview_id = Context::takeover_preview_id();
		if ( $preview_id ) {
			$post = get_post( $preview_id );
			if ( $post && 'menda_ad' === $post->post_type && 'skin' === get_post_meta( $post->ID, '_menda_ad_type', true ) ) {
				$skin = $this->format_skin( $post, true );
				$this->skin_fetched = true;
				$this->cached_skin  = $this->is_meaningful( $skin ) ? $skin : null;
				return $this->cached_skin;
			}
		}

		if ( Context::should_skip_overlays() ) {
			$this->skin_fetched = true;
			$this->cached_skin  = null;
			return null;
		}

		if ( ! get_option( MENDA_ADS_PREFIX . '_skin_enabled' ) ) {
			$this->skin_fetched = true;
			$this->cached_skin  = null;
			return null;
		}

		$ads = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'publish',
			'posts_per_page' => apply_filters( 'menda_ads_max_skin_query', 50 ),
			'meta_query'     => [
				[ 'key' => '_menda_ad_type', 'value' => 'skin' ],
			],
		] );

		$active = [];
		$display = Display::instance();
		foreach ( $ads as $ad ) {
			if ( $display->is_deliverable( $ad->ID, false ) ) {
				$active[] = $ad;
			}
		}

		if ( empty( $active ) ) {
			$this->skin_fetched = true;
			$this->cached_skin  = null;
			return null;
		}

		$selected = 1 === count( $active ) ? $active[0] : $display->pick_weighted( $active );
		$skin     = $this->format_skin( $selected, false );
		$this->skin_fetched = true;
		$this->cached_skin  = $this->is_meaningful( $skin ) ? $skin : null;
		return $this->cached_skin;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function format_skin( \WP_Post $ad, bool $preview ): array {
		$site_width = (int) get_option( MENDA_ADS_PREFIX . '_skin_content_width', 1200 );
		$width      = (int) get_post_meta( $ad->ID, '_menda_ad_skin_content_width', true );
		if ( $width < 600 ) {
			$width = max( 600, min( 1920, $site_width ?: 1200 ) );
		} else {
			$width = max( 600, min( 1920, $width ) );
		}

		$color = sanitize_hex_color( (string) get_post_meta( $ad->ID, '_menda_ad_skin_bg_color', true ) );
		if ( ! $color ) {
			$color = sanitize_hex_color( (string) get_option( MENDA_ADS_PREFIX . '_skin_bg_color', '#ffffff' ) ) ?: '#ffffff';
		}

		$fit = (string) get_post_meta( $ad->ID, '_menda_ad_skin_fit', true );
		if ( ! in_array( $fit, [ 'cover', 'contain', 'width', 'repeat' ], true ) ) {
			$fit = 'cover';
		}

		$freq = (string) get_post_meta( $ad->ID, '_menda_ad_skin_frequency', true );
		if ( ! in_array( $freq, [ 'once_per_session', 'once_per_day', 'every_page' ], true ) ) {
			$freq = 'every_page';
		}
		if ( $preview ) {
			$freq = 'every_page';
		}

		$z = (int) get_post_meta( $ad->ID, '_menda_ad_skin_z', true );
		if ( $z < 1 ) {
			$z = 40;
		}
		$z = min( 99999, $z );

		$layout = (string) get_post_meta( $ad->ID, '_menda_ad_skin_layout', true );
		if ( ! in_array( $layout, [ 'overlay', 'boxed' ], true ) ) {
			$layout = 'overlay';
		}
		if ( 'overlay' === $layout && $z < 1000 ) {
			$z = 10050;
		}

		$rail = (int) get_post_meta( $ad->ID, '_menda_ad_skin_rail', true );
		if ( $rail < 80 ) {
			$rail = 160;
		}
		$rail = min( 400, $rail );

		$min_vp = (int) get_post_meta( $ad->ID, '_menda_ad_skin_min_vp', true );
		if ( $min_vp < 600 ) {
			$min_vp = 'overlay' === $layout ? 782 : ( $width + 240 );
		}

		$mobile_mode = (string) get_post_meta( $ad->ID, '_menda_ad_skin_mobile_mode', true );
		if ( ! in_array( $mobile_mode, [ 'none', 'banner', 'sticky' ], true ) ) {
			$mobile_mode = 'sticky';
		}
		// Overlay shops: phones never get 160px rails, so "hide" would drop most traffic.
		if ( 'none' === $mobile_mode && 'overlay' === $layout ) {
			$mobile_mode = 'sticky';
		}

		$show_mobile  = get_post_meta( $ad->ID, '_menda_ad_show_mobile', true );
		$show_desktop = get_post_meta( $ad->ID, '_menda_ad_show_desktop', true );
		if ( '' === $show_mobile ) {
			$show_mobile = '1';
		}
		if ( '' === $show_desktop ) {
			$show_desktop = '1';
		}

		$wallpaper_id = (int) get_post_meta( $ad->ID, '_menda_ad_image_id', true );
		$left_id      = (int) get_post_meta( $ad->ID, '_menda_ad_skin_left_id', true );
		$right_id     = (int) get_post_meta( $ad->ID, '_menda_ad_skin_right_id', true );
		$top_id       = (int) get_post_meta( $ad->ID, '_menda_ad_skin_top_id', true );
		$mobile_id    = (int) get_post_meta( $ad->ID, '_menda_ad_skin_mobile_id', true );

		$link       = (string) get_post_meta( $ad->ID, '_menda_ad_link', true );
		$left_link  = (string) get_post_meta( $ad->ID, '_menda_ad_skin_left_link', true ) ?: $link;
		$right_link = (string) get_post_meta( $ad->ID, '_menda_ad_skin_right_link', true ) ?: $link;
		$top_link   = (string) get_post_meta( $ad->ID, '_menda_ad_skin_top_link', true ) ?: $link;
		$mobile_link = (string) get_post_meta( $ad->ID, '_menda_ad_skin_mobile_link', true ) ?: $link;

		$top_h = 250;
		if ( $top_id ) {
			$meta = wp_get_attachment_metadata( $top_id );
			if ( ! empty( $meta['height'] ) ) {
				$top_h = min( 400, max( 50, (int) $meta['height'] ) );
			}
		}

		return [
			'id'            => (int) $ad->ID,
			'preview'       => $preview,
			'bg_image'      => $wallpaper_id ? (string) wp_get_attachment_image_url( $wallpaper_id, 'full' ) : '',
			'bg_color'      => $color,
			'fit'           => $fit,
			'link'          => $link,
			'left_image'    => $left_id ? (string) wp_get_attachment_image_url( $left_id, 'full' ) : '',
			'right_image'   => $right_id ? (string) wp_get_attachment_image_url( $right_id, 'full' ) : '',
			'left_link'     => $left_link,
			'right_link'    => $right_link,
			'top_image'     => $top_id ? (string) wp_get_attachment_image_url( $top_id, 'full' ) : '',
			'top_link'      => $top_link,
			'top_h'         => $top_h,
			'mobile_mode'   => $mobile_mode,
			'mobile_image'  => $mobile_id ? (string) wp_get_attachment_image_url( $mobile_id, 'full' ) : '',
			'mobile_link'   => $mobile_link,
			'custom_code'   => (string) get_post_meta( $ad->ID, '_menda_ad_custom_code', true ),
			'content_width' => $width,
			'layout'        => $layout,
			'rail'          => $rail,
			'z'             => $z,
			'min_vp'        => $min_vp,
			'frequency'     => $freq,
			'show_desktop'  => '1' === (string) $show_desktop,
			'show_mobile'   => '1' === (string) $show_mobile,
			'header_full'   => 'boxed' === $layout && '0' !== (string) get_post_meta( $ad->ID, '_menda_ad_skin_header_full', true ),
			'header_sel'    => Context::sanitize_selector( (string) get_option( MENDA_ADS_PREFIX . '_skin_header_selector', '' ) ),
		];
	}

	private function is_meaningful( array $skin ): bool {
		if ( ! empty( $skin['bg_image'] ) || ! empty( $skin['left_image'] ) || ! empty( $skin['right_image'] )
			|| ! empty( $skin['top_image'] ) || ! empty( $skin['mobile_image'] ) || ! empty( $skin['custom_code'] ) ) {
			return true;
		}
		return 'boxed' === ( $skin['layout'] ?? '' ) && $this->is_brand_color( $skin );
	}

	public function inject_boot(): void {
		if ( ! $this->has_active() ) {
			return;
		}
		$skin = $this->get_active_skin();
		if ( ! $skin ) {
			return;
		}
		$payload = [
			'id'      => (int) $skin['id'],
			'freq'    => $skin['frequency'],
			'minVp'   => (int) $skin['min_vp'],
			'preview' => ! empty( $skin['preview'] ),
			'desktop' => ! empty( $skin['show_desktop'] ),
			'mobile'  => ! empty( $skin['show_mobile'] ),
			'hasTop'  => ! empty( $skin['top_image'] ) && ( $skin['layout'] ?? 'overlay' ) === 'boxed',
			'hasMob'  => $this->has_mobile_fallback() && ! empty( $skin['show_mobile'] ),
			'layout'  => $skin['layout'] ?? 'overlay',
			'hdrFull' => ! empty( $skin['header_full'] ),
		];
		?>
		<script id="menda-skin-boot">
		(function () {
			var c = <?php echo wp_json_encode( $payload ); ?>;
			var html = document.documentElement;
			var skip = false;
			try {
				if ( ! c.preview && c.freq === 'once_per_session' && sessionStorage.getItem( 'menda_skin_' + c.id ) ) {
					skip = true;
				}
				if ( ! c.preview && c.freq === 'once_per_day' ) {
					var t = localStorage.getItem( 'menda_skin_' + c.id );
					if ( t && ( Date.now() - parseInt( t, 10 ) ) < 86400000 ) {
						skip = true;
					}
				}
			} catch ( e ) {}
			if ( skip ) {
				html.setAttribute( 'data-menda-skin-skip', '1' );
				return;
			}
			var wide = window.innerWidth >= c.minVp;
			var shown = false;
			if ( wide && c.desktop ) {
				html.setAttribute( 'data-menda-skin', '1' );
				html.setAttribute( 'data-menda-skin-layout', c.layout || 'overlay' );
				if ( c.hasTop && c.layout === 'boxed' ) {
					html.setAttribute( 'data-menda-skin-top', '1' );
				}
				if ( c.hdrFull && c.layout === 'boxed' ) {
					html.setAttribute( 'data-menda-header-full', '1' );
				}
				shown = true;
			} else if ( ! wide && c.mobile && c.hasMob ) {
				html.setAttribute( 'data-menda-skin-mobile', '1' );
				shown = true;
			}
			if ( ! shown ) {
				return;
			}
			try {
				if ( ! c.preview && c.freq === 'once_per_session' ) {
					sessionStorage.setItem( 'menda_skin_' + c.id, '1' );
				}
				if ( ! c.preview && c.freq === 'once_per_day' ) {
					localStorage.setItem( 'menda_skin_' + c.id, String( Date.now() ) );
				}
			} catch ( e2 ) {}
		})();
		</script>
		<?php
	}

	public function inject_styles(): void {
		if ( ! $this->has_active() ) {
			return;
		}
		$skin     = $this->get_active_skin();
		if ( ! $skin ) {
			return;
		}
		$bg_color = sanitize_hex_color( $skin['bg_color'] ) ?: 'transparent';
		$bg_image = $skin['bg_image'] ? 'url(' . esc_url( $skin['bg_image'] ) . ')' : 'none';
		$fit      = match ( $skin['fit'] ) {
			'contain' => 'contain',
			'width'   => '100% auto',
			'repeat'  => 'auto',
			default   => 'cover',
		};
		$repeat = 'repeat' === ( $skin['fit'] ?? '' ) ? 'repeat' : 'no-repeat';
		$width    = (int) $skin['content_width'];
		$z        = (int) $skin['z'];
		$top_h    = (int) $skin['top_h'];
		$rail     = (int) ( $skin['rail'] ?? 160 );
		$layout   = ( $skin['layout'] ?? 'overlay' ) === 'boxed' ? 'boxed' : 'overlay';
		$manual   = Context::sanitize_selector( (string) get_option( MENDA_ADS_PREFIX . '_skin_wrapper_selector', '' ) );
		?>
		<style id="menda-skin-vars">
		html[data-menda-skin="1"],
		html[data-menda-skin-mobile="1"] {
			--menda-cw: <?php echo (int) $width; ?>px;
			--menda-rail: <?php echo (int) $rail; ?>px;
			--menda-skin-z: <?php echo (int) $z; ?>;
			--menda-top-h: <?php echo (int) $top_h; ?>px;
			--menda-skin-bg: <?php echo esc_html( $bg_color ); ?>;
			--menda-skin-img: <?php echo esc_html( $bg_image ); ?>;
			--menda-skin-fit: <?php echo esc_html( $fit ); ?>;
			--menda-skin-repeat: <?php echo esc_html( $repeat ); ?>;
		}
		#menda-skin {
			display: none;
			position: fixed !important;
			inset: 0 !important;
			width: 100% !important;
			height: 100% !important;
			margin: 0 !important;
			padding: 0 !important;
			border: 0 !important;
			background: transparent !important;
			pointer-events: none !important;
			z-index: <?php echo (int) $z; ?> !important;
			overflow: visible !important;
		}
		html[data-menda-skin="1"] #menda-skin,
		html[data-menda-skin-mobile="1"] #menda-skin {
			display: block !important;
		}
		<?php if ( 'boxed' === $layout ) : ?>
		<?php
		$pad_sel = $manual ? ( 'html[data-menda-skin="1"] ' . $manual ) : 'html[data-menda-skin="1"]';
		$pad_sel = Context::sanitize_selector( $pad_sel );
		if ( '' === $pad_sel ) {
			$pad_sel = 'html[data-menda-skin="1"]';
		}
		?>
		html[data-menda-skin="1"][data-menda-skin-layout="boxed"] {
			background-color: var(--menda-skin-bg);
			background-image: var(--menda-skin-img);
			background-repeat: var(--menda-skin-repeat, no-repeat);
			background-position: center top;
			background-size: var(--menda-skin-fit);
			background-attachment: fixed;
		}
		<?php if ( ! empty( $skin['header_full'] ) ) : ?>
		html[data-menda-skin="1"][data-menda-header-full="1"],
		html[data-menda-skin="1"][data-menda-header-full="1"] body {
			padding-inline: 0 !important;
		}
		html[data-menda-skin="1"][data-menda-header-full="1"] body {
			background-color: transparent !important;
			min-height: 100vh;
			width: auto;
			max-width: none;
		}
		<?php else : ?>
		html[data-menda-skin="1"][data-menda-skin-layout="boxed"] body {
			background-color: #fff;
			min-height: 100vh;
			width: auto;
			max-width: 100%;
		}
		html[data-menda-skin="1"][data-menda-skin-layout="boxed"] .site,
		html[data-menda-skin="1"][data-menda-skin-layout="boxed"] #page,
		html[data-menda-skin="1"][data-menda-skin-layout="boxed"] #wrapper {
			max-width: 100%;
		}
		<?php echo $pad_sel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized selector ?> {
			padding-inline: max(0px, calc((100vw - var(--menda-cw, 1200px)) / 2)) !important;
			box-sizing: border-box;
		}
		<?php endif; ?>
		html[data-menda-skin="1"][data-menda-skin-top="1"] {
			padding-top: var(--menda-top-h, 250px);
		}
		<?php else : ?>
		html[data-menda-skin="1"][data-menda-skin-layout="overlay"],
		html[data-menda-skin="1"][data-menda-skin-layout="overlay"] body {
			padding-inline: 0 !important;
		}
		html[data-menda-skin="1"][data-menda-skin-layout="overlay"] .menda-skin-wall,
		html[data-menda-skin="1"][data-menda-skin-layout="overlay"] .menda-skin-top {
			display: none !important;
		}
		<?php endif; ?>
		</style>
		<?php
	}

	public function inject_layer(): void {
		if ( $this->layer_printed || ! $this->has_active() ) {
			return;
		}
		$skin = $this->get_active_skin();
		if ( ! $skin ) {
			return;
		}
		$this->layer_printed = true;

		$label    = esc_attr__( 'Advertisement', 'menda-ads' );
		$is_boxed = ( $skin['layout'] ?? 'overlay' ) === 'boxed';
		$z        = (int) $skin['z'];
		$rail_w   = (int) ( $skin['rail'] ?? 160 );
		$layer_style = 'position:fixed;inset:0;width:100%;height:100%;margin:0;padding:0;border:0;background:transparent;pointer-events:none;z-index:' . $z . ';overflow:visible;';
		?>
		<div id="menda-skin" class="menda-skin menda-skin--<?php echo $is_boxed ? 'boxed' : 'overlay'; ?>" style="<?php echo esc_attr( $layer_style ); ?>" data-menda-unit="<?php echo esc_attr( (string) (int) $skin['id'] ); ?>"
			data-frequency="<?php echo esc_attr( $skin['frequency'] ); ?>"
			data-min-vp="<?php echo esc_attr( (string) (int) $skin['min_vp'] ); ?>"
			data-preview="<?php echo ! empty( $skin['preview'] ) ? '1' : '0'; ?>"
			data-show-desktop="<?php echo ! empty( $skin['show_desktop'] ) ? '1' : '0'; ?>"
			data-show-mobile="<?php echo ! empty( $skin['show_mobile'] ) ? '1' : '0'; ?>"
			data-layout="<?php echo esc_attr( $skin['layout'] ?? 'overlay' ); ?>"
			data-header-full="<?php echo ! empty( $skin['header_full'] ) ? '1' : '0'; ?>"
			data-header-sel="<?php echo esc_attr( (string) ( $skin['header_sel'] ?? '' ) ); ?>">
			<?php
			if ( $is_boxed && ( ! empty( $skin['bg_image'] ) || ( ! empty( $skin['bg_color'] ) && ! in_array( strtolower( (string) $skin['bg_color'] ), [ '#ffffff', '#fff', 'transparent', '' ], true ) ) ) ) :
				?>
				<div class="menda-skin-wall" aria-hidden="true"></div>
			<?php endif; ?>
			<?php if ( ! empty( $skin['custom_code'] ) ) : ?>
				<div class="menda-skin-html">
					<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stored with capability checks. ?>
					<?php echo $skin['custom_code']; ?>
				</div>
			<?php endif; ?>

			<?php if ( $is_boxed && ! empty( $skin['top_image'] ) ) : ?>
				<div class="menda-skin-top">
					<?php if ( $skin['top_link'] ) : ?>
						<a class="menda-skin-top__link" href="<?php echo esc_url( $skin['top_link'] ); ?>" target="_blank" rel="noopener nofollow sponsored" data-menda-click="click_top" aria-label="<?php echo $label; ?>">
							<img src="<?php echo esc_url( $skin['top_image'] ); ?>" alt="<?php echo $label; ?>" width="970" height="<?php echo (int) $skin['top_h']; ?>">
						</a>
					<?php else : ?>
						<img src="<?php echo esc_url( $skin['top_image'] ); ?>" alt="<?php echo $label; ?>" width="970" height="<?php echo (int) $skin['top_h']; ?>">
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			$is_overlay = ! $is_boxed;
			$left_src   = $skin['left_image'] ?: ( $is_overlay ? (string) $skin['bg_image'] : '' );
			$right_src  = $skin['right_image'] ?: ( $is_overlay ? (string) $skin['bg_image'] : '' );
			$boxed_click = $is_boxed && ( $skin['link'] || $this->is_brand_color( $skin ) || $left_src || $right_src );
			if ( $skin['left_link'] || $skin['right_link'] || $skin['link'] || $left_src || $right_src || $boxed_click ) :
				?>
				<div class="menda-skin-rails" aria-hidden="false">
					<?php if ( $is_overlay ) : ?>
						<button type="button" class="menda-skin-rails__close" aria-label="<?php esc_attr_e( 'Hide side ads', 'menda-ads' ); ?>">&times;</button>
					<?php endif; ?>
					<?php
					$this->rail(
						'left',
						$skin['left_link'] ?: $skin['link'],
						$left_src,
						$label,
						$is_overlay && empty( $skin['left_image'] ) && ! empty( $skin['bg_image'] ),
						$is_overlay || ( $is_boxed && ! empty( $skin['left_image'] ) ),
						$rail_w,
						$z
					);
					$this->rail(
						'right',
						$skin['right_link'] ?: $skin['link'],
						$right_src,
						$label,
						$is_overlay && empty( $skin['right_image'] ) && ! empty( $skin['bg_image'] ),
						$is_overlay || ( $is_boxed && ! empty( $skin['right_image'] ) ),
						$rail_w,
						$z
					);
					?>
				</div>
			<?php endif; ?>

			<?php
			$mobile_src  = $this->mobile_creative( $skin );
			$mobile_href = $skin['mobile_link'] ?: ( $skin['link'] ?? '' );
			if ( $this->has_mobile_fallback() && $mobile_src ) :
				$from_rail = empty( $skin['mobile_image'] );
				$mob_class = 'menda-skin-mobile menda-skin-mobile--' . $skin['mobile_mode'] . ( $from_rail ? ' menda-skin-mobile--from-rail' : '' );
				?>
				<div id="menda-skin-mobile" class="<?php echo esc_attr( $mob_class ); ?>" role="complementary" aria-label="<?php echo $label; ?>">
					<button type="button" class="menda-skin-mobile__close" aria-label="<?php esc_attr_e( 'Close', 'menda-ads' ); ?>">&times;</button>
					<?php if ( $mobile_href ) : ?>
						<a class="menda-skin-mobile__link" href="<?php echo esc_url( $mobile_href ); ?>" target="_blank" rel="noopener nofollow sponsored" data-menda-click="click">
							<img src="<?php echo esc_url( $mobile_src ); ?>" alt="<?php echo $label; ?>" width="320" height="100">
						</a>
					<?php else : ?>
						<img src="<?php echo esc_url( $mobile_src ); ?>" alt="<?php echo $label; ?>" width="320" height="100">
					<?php endif; ?>
				</div>
			<?php elseif ( $this->has_mobile_fallback() && $this->is_brand_color( $skin ) ) :
				$cta_color = $this->is_dark_hex( (string) $skin['bg_color'] ) ? '#fff' : '#111';
				?>
				<div id="menda-skin-mobile" class="menda-skin-mobile menda-skin-mobile--<?php echo esc_attr( $skin['mobile_mode'] ); ?> menda-skin-mobile--color" style="background:<?php echo esc_attr( $skin['bg_color'] ); ?>" role="complementary" aria-label="<?php echo $label; ?>">
					<button type="button" class="menda-skin-mobile__close" aria-label="<?php esc_attr_e( 'Close', 'menda-ads' ); ?>">&times;</button>
					<?php if ( $mobile_href ) : ?>
						<a class="menda-skin-mobile__cta" href="<?php echo esc_url( $mobile_href ); ?>" target="_blank" rel="noopener nofollow sponsored" data-menda-click="click" style="color:<?php echo esc_attr( $cta_color ); ?>">
							<?php esc_html_e( 'Sponsored', 'menda-ads' ); ?>
						</a>
					<?php else : ?>
						<span class="menda-skin-mobile__cta" style="color:<?php echo esc_attr( $cta_color ); ?>"><?php esc_html_e( 'Sponsored', 'menda-ads' ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function rail( string $side, string $link, string $image, string $label, bool $from_wall = false, bool $overlay = false, int $rail_w = 160, int $z = 10050 ): void {
		$click = 'left' === $side ? 'click_left' : 'click_right';
		$class = 'menda-skin-rail menda-skin-rail--' . $side;
		if ( $from_wall ) {
			$class .= ' menda-skin-rail--from-wall';
		}
		$style = '';
		if ( $overlay ) {
			$edge  = 'left' === $side ? 'left:8px;right:auto;' : 'right:8px;left:auto;';
			$top   = is_admin_bar_showing() ? 120 : 88;
			$radius = 'left' === $side ? '0 14px 14px 0' : '14px 0 0 14px';
			$style = 'position:fixed;' . $edge . 'top:' . (int) $top . 'px;width:' . (int) $rail_w . 'px;height:min(620px,calc(100vh - ' . ( (int) $top + 24 ) . 'px));margin:0;padding:0;overflow:hidden;pointer-events:auto;z-index:' . ( (int) $z + 1 ) . ';display:block;text-decoration:none;border-radius:' . $radius . ';box-shadow:0 10px 28px rgba(0,0,0,.2);';
		}
		$img = '';
		if ( $image ) {
			$img = '<img src="' . esc_url( $image ) . '" alt="" width="' . (int) $rail_w . '" height="600" style="width:100%;height:100%;object-fit:cover;display:block;border:0;">';
		}
		if ( $link ) {
			echo '<a class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '" href="' . esc_url( $link ) . '" target="_blank" rel="noopener nofollow sponsored" data-menda-click="' . esc_attr( $click ) . '" aria-label="' . esc_attr( $label ) . '">';
			echo $image ? $img : esc_html__( 'Advertisement', 'menda-ads' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above.
			echo '</a>';
			return;
		}
		if ( $image ) {
			echo '<span class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">' . $img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	private function is_dark_hex( string $hex ): bool {
		$hex = ltrim( strtolower( $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return false;
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		return ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) < 150;
	}
}
