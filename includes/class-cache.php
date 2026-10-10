<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Cache Compatibility Layer
 *
 * All APIs verified from official documentation:
 *  - LiteSpeed: https://docs.litespeedtech.com/lscache/lscwp/api/
 *  - WP Rocket: https://docs.wp-rocket.me/article/1801-how-to-programmatically-clear-the-cache
 *  - W3TC, WP Super Cache, SG Optimizer, Breeze, Autoptimize, Cache Enabler: official hooks/functions
 *
 * Problems solved:
 *  1. REST tracking endpoint must never be cached.
 *  2. Skin/interstitial HTML is cacheable; frequency runs in the browser.
 *  3. All cache plugins get purged when an ad is saved or settings change.
 */
final class Cache {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {

		// ── Exclude REST tracking endpoint from page-level caches ─────────

		// LiteSpeed — exclude URI pattern.
		add_filter( 'litespeed_cache_exc_uri',    [ $this, 'ls_exclude_uri' ] );
		// LiteSpeed — exclude from JS optimisation so inline tracking scripts are untouched.
		add_filter( 'litespeed_optimize_js_excludes', [ $this, 'ls_exclude_js' ] );
		add_filter( 'litespeed_optm_js_defer_exc',    [ $this, 'ls_exclude_js' ] );

		// WP Rocket — reject URIs from caching.
		add_filter( 'rocket_cache_reject_uri',  [ $this, 'rocket_exclude_uri' ] );
		// WP Rocket — exclude our scripts from minification/concatenation.
		add_filter( 'rocket_exclude_js',        [ $this, 'rocket_exclude_js' ] );
		add_filter( 'rocket_exclude_defer_js',  [ $this, 'rocket_exclude_js' ] );

		// W3 Total Cache.
		add_filter( 'w3tc_pgcache_reject_uri',  [ $this, 'w3tc_exclude_uri' ] );

		// WP Super Cache.
		add_filter( 'wpsc_rejected_uris',       [ $this, 'wpsc_exclude_uri' ] );

		// SG Optimizer.
		add_filter( 'sg_optimizer_params',      [ $this, 'sg_exclude_uri' ] );

		// Breeze (Cloudways).
		add_filter( 'breeze_exclude_urls',      [ $this, 'breeze_exclude_uri' ] );

		// Overlays (skin / interstitial) are injected into cached HTML and
		// gated in the browser. Do not send no-store on the homepage.
		add_action( 'template_redirect', [ $this, 'maybe_nocache_preview' ] );

		// ── Purge on ad save ───────────────────────────────────────────────
		// save_post_* sends ( int $post_id, WP_Post $post, bool $update ).
		add_action( 'save_post_menda_ad',        [ $this, 'purge_on_save' ], 10, 2 );
		add_action( 'save_post_menda_sponsored', [ $this, 'purge_on_save' ], 10, 2 );

		// update_option_* sends ( $old_value, $new_value, $option_name ) — use 0 accepted args.
		add_action( 'updated_option', [ $this, 'purge_on_any_option' ], 10, 1 );
	}

	// =========================================================================
	// URI / JS exclusions
	// =========================================================================

	private function tracking_paths(): array {
		return [ 'menda-am/v1/track', 'menda-ads/v1/track' ];
	}

	public function ls_exclude_uri( array $excludes ): array {
		foreach ( $this->tracking_paths() as $path ) {
			$excludes[] = $path;
		}
		return $excludes;
	}

	public function ls_exclude_js( array $excludes ): array {
		$excludes[] = 'menda-ads-manager';
		return $excludes;
	}

	public function rocket_exclude_uri( array $urls ): array {
		foreach ( $this->tracking_paths() as $path ) {
			$urls[] = '(.*)' . $path . '(.*)';
		}
		return $urls;
	}

	public function rocket_exclude_js( array $excludes ): array {
		$excludes[] = 'menda-ads-manager';
		return $excludes;
	}

	public function w3tc_exclude_uri( string $regex ): string {
		foreach ( $this->tracking_paths() as $path ) {
			$regex .= '|' . preg_quote( $path, '/' );
		}
		return $regex;
	}

	public function maybe_nocache_preview(): void {
		if ( ! Context::takeover_preview_id() ) {
			return;
		}
		do_action( 'litespeed_control_set_nocache', 'menda_ads_preview' );
		if ( function_exists( 'rocket_clean_domain' ) ) {
			add_filter( 'do_rocket_generate_caching_files', '__return_false' );
		}
		add_filter( 'w3tc_process_cache', '__return_false' );
		if ( ! headers_sent() ) {
			header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Pragma: no-cache' );
		}
		nocache_headers();
	}

	public function wpsc_exclude_uri( array $uris ): array {
		return array_merge( $uris, $this->tracking_paths() );
	}

	public function sg_exclude_uri( array $params ): array {
		foreach ( $this->tracking_paths() as $path ) {
			$params['excluded_urls'][] = $path;
		}
		return $params;
	}

	public function breeze_exclude_uri( array $urls ): array {
		return array_merge( $urls, $this->tracking_paths() );
	}

	// =========================================================================
	// Cache purge on ad save / settings change
	// =========================================================================

	/**
	 * Hooked to save_post_menda_ad and save_post_menda_sponsored.
	 * WP sends ( int $post_id, WP_Post $post ).
	 */
	public function purge_on_save( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( 'revision' === $post->post_type ) return;
		$this->purge_all();
	}

	/**
	 * Hooked to update_option_* with 0 accepted args — no type confusion.
	 */
	public function purge_on_option(): void {
		$this->purge_all();
	}

	public function purge_on_any_option( $option ): void {
		if ( is_string( $option ) && str_starts_with( $option, MENDA_ADS_PREFIX . '_' ) ) {
			$this->purge_all();
		}
	}

	/**
	 * Purge every supported cache plugin.
	 */
	public function purge_all(): void {

		// ── LiteSpeed ─────────────────────────────────────────────────────
		// Official API — works on all LSCWP versions.
		do_action( 'litespeed_purge_all' );

		// ── WP Rocket ─────────────────────────────────────────────────────
		// Official function from docs.wp-rocket.me/article/1801
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// ── W3 Total Cache ─────────────────────────────────────────────────
		if ( function_exists( 'w3tc_pgcache_flush' ) ) {
			w3tc_pgcache_flush();
		}

		// ── WP Super Cache ─────────────────────────────────────────────────
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// ── SG Optimizer (SiteGround) ──────────────────────────────────────
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		// ── Breeze (Cloudways) ─────────────────────────────────────────────
		do_action( 'breeze_clear_all_cache' );

		// ── Autoptimize ────────────────────────────────────────────────────
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
		}

		// ── Cache Enabler ──────────────────────────────────────────────────
		if ( class_exists( 'Cache_Enabler' ) && method_exists( 'Cache_Enabler', 'clear_total_cache' ) ) {
			\Cache_Enabler::clear_total_cache();
		}

		// ── Cloudflare WordPress plugin ─────────────────────────────────────
		do_action( 'cloudflare_purge_everything' );

		// ── Super Page Cache for Cloudflare ────────────────────────────────
		do_action( 'swcfpc_purge_cache' );
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// Clear our own skin selector transient.
		delete_transient( MENDA_ADS_PREFIX . '_skin_selectors' );

		/**
		 * Hook for any additional cache plugins.
		 * do_action( 'menda_ads_cache_purged' );
		 */
		do_action( 'menda_ads_cache_purged' );
	}
}
