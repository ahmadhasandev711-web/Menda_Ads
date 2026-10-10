<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Display units — rendered via shortcode, widget, Gutenberg block, or Elementor.
 *
 * Usage: [menda_ad placement="homepage-top"]
 *        [menda_ad id="123"]
 */
final class Display {

	private static ?self $instance = null;
	private array $query_cache     = [];

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_shortcode( 'menda_ad', [ $this, 'shortcode' ] );
		add_action( 'widgets_init',       [ $this, 'register_widget' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function shortcode( array $atts ): string {
		$atts = shortcode_atts( [
			'id'        => 0,
			'placement' => '',
			'class'     => '',
			'count'     => 1,
		], $atts, 'menda_ad' );

		$count       = min( max( 1, (int) $atts['count'] ), 10 );
		$extra_class = sanitize_html_class( $atts['class'] );
		$id          = (int) $atts['id'];
		$placement   = sanitize_text_field( $atts['placement'] );

		if ( $count === 1 ) {
			$ad = $this->query_ad( $id, $placement );
			if ( ! $ad ) {
				return '';
			}
			return $this->render_ad( $ad, $extra_class, $placement );
		}

		$pool   = $this->query_ad_pool( $id, $placement );
		$output = '';
		$i      = 0;
		foreach ( $pool as $ad ) {
			if ( $i >= $count ) {
				break;
			}
			$output .= $this->render_ad( $ad, $extra_class, $placement );
			$i++;
		}
		return $output;
	}

	public function query_ad( int $id = 0, string $placement = '' ): ?array {
		$pool = $this->query_ad_pool( $id, $placement );
		if ( empty( $pool ) ) {
			return null;
		}
		return $pool[0];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function query_ad_pool( int $id = 0, string $placement = '' ): array {
		$cache_key = $id . '|' . $placement . '|' . ( wp_is_mobile() ? 'm' : 'd' );
		if ( isset( $this->query_cache[ $cache_key ] ) ) {
			return $this->query_cache[ $cache_key ];
		}

		$args = [
			'post_type'      => 'menda_ad',
			'post_status'    => 'publish',
			'posts_per_page' => $id ? 1 : apply_filters( 'menda_ads_max_banner_query', 50 ),
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => '_menda_ad_type', 'value' => 'banner' ],
				[
					'relation' => 'OR',
					[ 'key' => '_menda_ad_parent_id', 'value' => '0' ],
					[ 'key' => '_menda_ad_parent_id', 'compare' => 'NOT EXISTS' ],
				],
			],
		];
		if ( $id ) {
			$args['p'] = $id;
			unset( $args['meta_query'][1] );
		} elseif ( $placement ) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'menda_ad_placement',
					'field'    => 'slug',
					'terms'    => $placement,
				],
			];
		} else {
			return $this->query_cache[ $cache_key ] = [];
		}

		$ads = get_posts( $args );
		if ( empty( $ads ) ) {
			return $this->query_cache[ $cache_key ] = [];
		}

		update_postmeta_cache( wp_list_pluck( $ads, 'ID' ) );

		$now       = current_datetime();
		$timezone  = wp_timezone();
		$is_mobile = wp_is_mobile();
		$active    = [];

		foreach ( $ads as $ad ) {
			if ( ! $this->is_currently_runnable( $ad->ID, $now, $timezone, $is_mobile ) ) {
				continue;
			}
			$active[] = $ad;
		}

		if ( empty( $active ) ) {
			return $this->query_cache[ $cache_key ] = [];
		}

		$results = [];
		if ( $id ) {
			$results[] = $this->format_ad( $active[0] );
			return $this->query_cache[ $cache_key ] = $results;
		}

		$chosen_parent = $this->pick_weighted( $active );
		$chosen        = $this->pick_variant( $chosen_parent, $now, $timezone, $is_mobile );
		$results[]     = $this->format_ad( $chosen );

		foreach ( $active as $parent_ad ) {
			if ( (int) $parent_ad->ID === (int) $chosen_parent->ID ) {
				continue;
			}
			$alt = $this->pick_variant( $parent_ad, $now, $timezone, $is_mobile );
			$results[] = $this->format_ad( $alt );
		}

		return $this->query_cache[ $cache_key ] = $results;
	}

	/**
	 * @param \WP_Post[] $posts
	 */
	public function pick_weighted( array $posts ): \WP_Post {
		if ( 1 === count( $posts ) ) {
			return $posts[0];
		}
		$weights = [];
		$total   = 0;
		foreach ( $posts as $i => $post ) {
			$w = (int) get_post_meta( $post->ID, '_menda_ad_weight', true );
			$w = max( 1, $w );
			$weights[ $i ] = $w;
			$total        += $w;
		}
		$pick = random_int( 1, $total );
		$run  = 0;
		foreach ( $weights as $i => $w ) {
			$run += $w;
			if ( $pick <= $run ) {
				return $posts[ $i ];
			}
		}
		return $posts[0];
	}

	private function pick_variant( \WP_Post $parent_ad, $now, $timezone, bool $is_mobile ): \WP_Post {
		$variants = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'meta_query'     => [
				[ 'key' => '_menda_ad_parent_id', 'value' => $parent_ad->ID ],
			],
		] );

		$pool = [ $parent_ad ];
		foreach ( $variants as $var ) {
			if ( $this->is_currently_runnable( $var->ID, $now, $timezone, $is_mobile ) ) {
				$pool[] = $var;
			}
		}

		$cookie_name = 'menda_ab_' . $parent_ad->ID;
		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			$want = absint( wp_unslash( $_COOKIE[ $cookie_name ] ) );
			foreach ( $pool as $candidate ) {
				if ( (int) $candidate->ID === $want ) {
					return $candidate;
				}
			}
		}

		return $pool[ array_rand( $pool ) ];
	}

	/**
	 * Schedule + targeting. Device is optional so cached HTML can include
	 * both desktop and mobile layers of a takeover.
	 */
	public function is_deliverable( int $ad_id, bool $check_device = true ): bool {
		return $this->is_currently_runnable( $ad_id, current_datetime(), wp_timezone(), wp_is_mobile(), $check_device );
	}

	private function is_currently_runnable( int $ad_id, $now, $timezone, bool $is_mobile, bool $check_device = true ): bool {
		$start    = get_post_meta( $ad_id, '_menda_ad_start', true );
		$end      = get_post_meta( $ad_id, '_menda_ad_end', true );
		$start_dt = $start ? date_create_immutable( $start, $timezone ) : false;
		$end_dt   = $end ? date_create_immutable( $end, $timezone ) : false;
		if ( $start_dt && $start_dt > $now ) {
			return false;
		}
		if ( $end_dt && $end_dt < $now ) {
			return false;
		}

		if ( $check_device ) {
			$show_mobile  = get_post_meta( $ad_id, '_menda_ad_show_mobile', true );
			$show_desktop = get_post_meta( $ad_id, '_menda_ad_show_desktop', true );
			if ( '' === $show_mobile ) {
				$show_mobile = '1';
			}
			if ( '' === $show_desktop ) {
				$show_desktop = '1';
			}
			if ( $is_mobile && ! $show_mobile ) {
				return false;
			}
			if ( ! $is_mobile && ! $show_desktop ) {
				return false;
			}
		}
		return $this->is_ad_targeted( $ad_id );
	}

	private function format_ad( \WP_Post $ad ): array {
		return [
			'id'          => $ad->ID,
			'link'        => get_post_meta( $ad->ID, '_menda_ad_link', true ),
			'image_id'    => get_post_meta( $ad->ID, '_menda_ad_image_id', true ),
			'custom_code' => get_post_meta( $ad->ID, '_menda_ad_custom_code', true ),
		];
	}

	public function is_ad_targeted( int $ad_id, int $custom_page_id = 0 ): bool {
		if ( ! I18n::ad_matches_language( $ad_id ) ) {
			return false;
		}

		$target_countries_raw = get_post_meta( $ad_id, '_menda_ad_target_countries', true );
		if ( $target_countries_raw ) {
			$target_countries = array_map( 'strtoupper', array_map( 'trim', explode( ',', $target_countries_raw ) ) );
			$visitor_country  = $this->get_visitor_country();
			if ( ! $visitor_country ) {
				if ( 'show' !== get_option( MENDA_ADS_PREFIX . '_geo_unknown', 'hide' ) ) {
					return false;
				}
			} elseif ( ! in_array( $visitor_country, $target_countries, true ) ) {
				return false;
			}
		}

		$target_type = get_post_meta( $ad_id, '_menda_ad_target_type', true ) ?: 'all';
		if ( 'all' === $target_type ) {
			return true;
		}

		if ( 'pages' === $target_type ) {
			$target_ids_raw = get_post_meta( $ad_id, '_menda_ad_target_ids', true );
			if ( ! $target_ids_raw ) {
				return false;
			}
			$target_ids = array_map( 'intval', array_map( 'trim', explode( ',', $target_ids_raw ) ) );
			$current_id = $custom_page_id > 0 ? $custom_page_id : get_queried_object_id();

			foreach ( $target_ids as $target_id ) {
				if ( $target_id === $current_id || I18n::translate_id( $target_id ) === $current_id ) {
					return true;
				}
			}
			return false;
		}

		if ( 'categories' === $target_type ) {
			$target_cats_raw = get_post_meta( $ad_id, '_menda_ad_target_cats', true );
			if ( ! $target_cats_raw ) {
				return false;
			}
			$target_cats = array_map( 'trim', explode( ',', $target_cats_raw ) );

			if ( is_category() ) {
				$cat = get_queried_object();
				return $cat && ( in_array( $cat->slug, $target_cats, true ) || in_array( (string) $cat->term_id, $target_cats, true ) );
			}

			if ( is_single() ) {
				return has_category( $target_cats, $custom_page_id > 0 ? $custom_page_id : null );
			}
		}

		if ( 'woocommerce' === $target_type ) {
			if ( ! function_exists( 'is_shop' ) ) {
				return false;
			}
			$woo_pages = array_map( 'trim', explode( ',', get_post_meta( $ad_id, '_menda_ad_woo_pages', true ) ?: 'all' ) );
			$match_all = in_array( 'all', $woo_pages, true );
			if ( $match_all ) {
				return is_woocommerce();
			}
			$checks = [
				'shop'     => 'is_shop',
				'category' => 'is_product_category',
				'tag'      => 'is_product_tag',
				'product'  => 'is_product',
				'cart'     => 'is_cart',
				'checkout' => 'is_checkout',
				'account'  => 'is_account_page',
			];
			foreach ( $woo_pages as $page ) {
				if ( isset( $checks[ $page ] ) && function_exists( $checks[ $page ] ) && call_user_func( $checks[ $page ] ) ) {
					return true;
				}
			}
			return false;
		}

		if ( 'taxonomy' === $target_type ) {
			$tax_name      = sanitize_key( get_post_meta( $ad_id, '_menda_ad_target_taxonomy', true ) );
			$tax_terms_raw = get_post_meta( $ad_id, '_menda_ad_target_tax_terms', true );
			if ( ! $tax_name || ! $tax_terms_raw ) {
				return false;
			}
			$tax_terms = array_map( 'trim', explode( ',', $tax_terms_raw ) );
			if ( is_tax( $tax_name ) ) {
				$term = get_queried_object();
				return $term && ( in_array( $term->slug, $tax_terms, true ) || in_array( (string) $term->term_id, $tax_terms, true ) );
			}
			if ( is_singular() ) {
				$post_id = $custom_page_id > 0 ? $custom_page_id : get_the_ID();
				return has_term( $tax_terms, $tax_name, $post_id );
			}
			return false;
		}

		return false;
	}

	/**
	 * Country from trusted CDN/geo headers only. Never call a remote geo API
	 * on the HTML request, and never trust X-Forwarded-For.
	 */
	public function get_visitor_country(): string {
		$headers = [
			'HTTP_CF_IPCOUNTRY',
			'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
			'HTTP_X_APPENGINE_COUNTRY',
			'GEOIP_COUNTRY_CODE',
			'GEOIP_COUNTRY',
		];
		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			$code = strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
			if ( preg_match( '/^[A-Z]{2}$/', $code ) && 'XX' !== $code && 'T1' !== $code ) {
				return $code;
			}
		}
		return '';
	}

	public function render_ad( array $ad, string $extra_class = '', string $placement = '' ): string {
		$classes = trim( 'menda-unit ' . $extra_class );
		$cap     = (int) get_post_meta( $ad['id'], '_menda_ad_impression_cap', true );
		$parent  = (int) get_post_meta( (int) $ad['id'], '_menda_ad_parent_id', true );
		if ( $parent < 1 ) {
			$parent = (int) $ad['id'];
		}

		$slugs = wp_get_post_terms( (int) $ad['id'], 'menda_ad_placement', [ 'fields' => 'slugs' ] );
		if ( is_wp_error( $slugs ) ) {
			$slugs = [];
		}
		if ( $placement ) {
			array_unshift( $slugs, $placement );
		}
		$size = Slots::size_from_slugs( array_filter( $slugs ) );
		$slot = $size['slug'] ?? ( $placement ?: '' );
		$style = '';
		$img_atts = [
			'alt'     => esc_attr__( 'Advertisement', 'menda-ads' ),
			'loading' => 'lazy',
		];
		if ( $size ) {
			$style = sprintf( 'min-height:%dpx;max-width:%dpx', $size['height'], $size['width'] );
			$img_atts['width']  = (string) $size['width'];
			$img_atts['height'] = (string) $size['height'];
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>"
			data-menda-unit="<?php echo esc_attr( (string) (int) $ad['id'] ); ?>"
			data-impression-cap="<?php echo (int) $cap; ?>"
			data-parent="<?php echo esc_attr( (string) $parent ); ?>"
			<?php echo $slot ? 'data-menda-slot="' . esc_attr( $slot ) . '"' : ''; ?>
			<?php echo $style ? 'style="' . esc_attr( $style ) . '"' : ''; ?>>
			<span class="menda-unit__label"><?php esc_html_e( 'Ad', 'menda-ads' ); ?></span>
			<?php if ( $ad['custom_code'] ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $ad['custom_code'];
				?>
			<?php elseif ( $ad['image_id'] ) : ?>
				<a href="<?php echo esc_url( $ad['link'] ); ?>"
				   target="_blank"
				   rel="noopener nofollow sponsored"
				   class="menda-unit__link">
					<?php echo wp_get_attachment_image( (int) $ad['image_id'], 'full', false, $img_atts ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public function register_widget(): void {
		register_widget( Ads_Widget::class );
	}

	public function enqueue(): void {
		if ( is_admin() || Context::is_amp() ) {
			return;
		}
		$path = MENDA_ADS_DIR . 'assets/css/front.css';
		if ( is_readable( $path ) ) {
			$css = file_get_contents( $path );
			if ( $css ) {
				wp_register_style( 'menda-front', false, [], MENDA_ADS_VERSION );
				wp_enqueue_style( 'menda-front' );
				wp_add_inline_style( 'menda-front', $css );
			}
		}
		wp_enqueue_script(
			'menda-front',
			MENDA_ADS_URL . 'assets/js/front.js',
			[],
			MENDA_ADS_VERSION,
			true
		);
		wp_localize_script( 'menda-front', 'mendaFront', [
			'track'    => Context::track_url(),
			'nonce'    => wp_create_nonce( 'menda_ads_track' ),
			'skip'     => __( 'Skip Ad', 'menda-ads' ),
			'adblock'  => (bool) get_option( MENDA_ADS_PREFIX . '_adblock_detect', 0 ),
			'fallback' => wp_strip_all_tags( (string) get_option( MENDA_ADS_PREFIX . '_adblock_fallback', __( 'Please consider supporting us by disabling your adblocker.', 'menda-ads' ) ) ),
		] );
	}
}
