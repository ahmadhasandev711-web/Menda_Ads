<?php
/**
 * Plugin Name:       Menda Ads Manager
 * Plugin URI:        https://github.com/ahmadhasandev711-web/Menda_Ads
 * Description:       Complete ad management system: banners, interstitial, sticky video, website skin, sponsored articles, and analytics dashboard.
 * Version:           1.3.4
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Ahmad Hasan
 * Author URI:        https://ahmad-hasan.vercel.app/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       menda-ads
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'MENDA_ADS_VERSION',  '1.3.4' );
define( 'MENDA_ADS_FILE',     __FILE__ );
define( 'MENDA_ADS_DIR',      plugin_dir_path( __FILE__ ) );
define( 'MENDA_ADS_URL',      plugin_dir_url( __FILE__ ) );
define( 'MENDA_ADS_PREFIX',   'menda_ads' );
define( 'MENDA_ADS_CAP',      'manage_menda_ads' );

/**
 * Autoloader — PSR-4 style for Menda_Ads namespace.
 */
spl_autoload_register( function ( string $class ): void {
	$prefix = 'Menda_Ads\\';
	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}
	$relative = str_replace( [ $prefix, '\\' ], [ '', DIRECTORY_SEPARATOR ], $class );
	$file      = MENDA_ADS_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

/**
 * Bootstrap — runs after all plugins are loaded.
 */
function menda_ads_init(): void {
	// Load translations (compatible with TranslatePress, Polylang, WPML).
	add_action( 'init', function () {
		load_plugin_textdomain(
			'menda-ads',
			false,
			dirname( plugin_basename( MENDA_ADS_FILE ) ) . '/languages'
		);
	} );

	// Boot modules.
	\Menda_Ads\Post_Types::instance();
	\Menda_Ads\Analytics::instance();
	\Menda_Ads\Cache::instance();       // Must boot before ad modules.
	\Menda_Ads\Interstitial::instance();
	\Menda_Ads\Sticky_Video::instance();
	\Menda_Ads\Skin::instance();
	\Menda_Ads\Display::instance();
	\Menda_Ads\Slots::instance();
	\Menda_Ads\Insert::instance();
	\Menda_Ads\Integrations::instance();

	if ( is_admin() ) {
		\Menda_Ads\Admin::instance();
	}
}
add_action( 'plugins_loaded', 'menda_ads_init' );

/**
 * Map manage_menda_ads onto admins (and editors/authors if enabled) on every
 * capability check. This does not depend on activation or the roles table.
 */
add_filter( 'user_has_cap', [ \Menda_Ads\Installer::class, 'filter_user_has_cap' ], 1, 4 );

/**
 * FEAT-02: Run DB migration on every plugin update.
 * Must hook before menda_ads_init so tables exist when modules boot.
 */
add_action( 'plugins_loaded', function (): void {
	\Menda_Ads\Installer::maybe_migrate();
}, 5 );

add_action( 'init', static function (): void {
	\Menda_Ads\Installer::ensure_caps();
}, 1 );

add_action( 'init', static function (): void {
	\Menda_Ads\Installer::migrate_legacy_skin();
	\Menda_Ads\Installer::migrate_125();
	\Menda_Ads\Installer::migrate_126();
	\Menda_Ads\Installer::migrate_130();
	\Menda_Ads\Installer::migrate_131();
}, 30 );

add_action( 'update_option_' . MENDA_ADS_PREFIX . '_access_level', static function (): void {
	\Menda_Ads\Installer::grant_cap();
} );

/**
 * Activation hook — create DB tables & set defaults.
 */
register_activation_hook( __FILE__, function (): void {
	\Menda_Ads\Installer::activate();
} );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, function (): void {
	\Menda_Ads\Installer::deactivate();
} );
