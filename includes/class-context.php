<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Shared front-end context checks used by overlays (skin, interstitial, sticky).
 */
final class Context {

	public static function is_amp(): bool {
		return (
			( function_exists( 'amp_is_request' ) && amp_is_request() ) ||
			( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() )
		);
	}

	/**
	 * Elementor / Bricks / Customizer / REST — overlays must not run here.
	 */
	public static function is_builder_preview(): bool {
		if ( is_customize_preview() ) {
			return true;
		}
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor-mode'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		if ( isset( $_GET['bricks'] ) || isset( $_GET['brickspreview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() ) {
			return true;
		}
		if ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
			return true;
		}
		return false;
	}

	public static function is_commerce_sensitive(): bool {
		if ( ! function_exists( 'is_checkout' ) ) {
			return false;
		}
		return is_checkout() || is_cart() || is_account_page();
	}

	public static function should_skip_overlays(): bool {
		return self::is_amp() || self::is_builder_preview() || self::is_commerce_sensitive();
	}

	/**
	 * Logged-in ads manager previewing a takeover on the live site.
	 */
	public static function takeover_preview_id(): int {
		if ( empty( $_GET['menda_takeover_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 0;
		}
		if ( ! is_user_logged_in() || ! current_user_can( MENDA_ADS_CAP ) ) {
			return 0;
		}
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'menda_takeover_preview' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 0;
		}
		return absint( wp_unslash( $_GET['menda_takeover_preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Allow only a safe CSS selector subset. Curly braces and at-rules are stripped.
	 */
	public static function sanitize_selector( $value ): string {
		$value = is_string( $value ) ? $value : '';
		$value = wp_strip_all_tags( $value );
		$value = str_replace( [ '{', '}', '@', '\\', '<', '(', ')' ], '', $value );
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^[a-zA-Z0-9\s\.\#\:\-\_\[\]\,\=\"\'\>\~\+\*]+$/', $value ) ) {
			return '';
		}
		return $value;
	}

	public static function rest_namespace(): string {
		return 'menda-am/v1';
	}

	public static function track_url(): string {
		return rest_url( self::rest_namespace() . '/track' );
	}
}
