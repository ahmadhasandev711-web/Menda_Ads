<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes plugin options and cron. Ads, sponsored posts, and analytics stay.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// ── Option keys created by the plugin ────────────────────────────────────────
$options = [
	'menda_ads_interstitial_enabled',
	'menda_ads_interstitial_skip_delay',
	'menda_ads_interstitial_frequency',
	'menda_ads_sticky_video_enabled',
	'menda_ads_sticky_video_position',
	'menda_ads_skin_enabled',
	'menda_ads_skin_start',
	'menda_ads_skin_end',
	'menda_ads_skin_bg_color',
	'menda_ads_skin_bg_image',
	'menda_ads_skin_link',
	'menda_ads_skin_content_width',
	'menda_ads_skin_wrapper_selector',
	'menda_ads_skin_header_selector',
	'menda_ads_skin_migrated_120',
	'menda_ads_weekly_report_enabled',
	'menda_ads_weekly_report_email',
	'menda_ads_adblock_detect',
	'menda_ads_adblock_fallback',
	'menda_ads_log_retention_days',
	'menda_ads_access_level',
	'menda_ads_currency',
	'menda_ads_interstitial_skip_home',
	'menda_ads_interstitial_min_views',
	'menda_ads_insert_p2',
	'menda_ads_insert_p4',
	'menda_ads_insert_below',
	'menda_ads_insert_mobile_sticky',
	'menda_ads_insert_archive',
	'menda_ads_geo_unknown',
	'menda_ads_db_version',
	'menda_ads_migrated_125',
	'menda_ads_migrated_126',
	'menda_ads_migrated_130',
	'menda_ads_migrated_131',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// ── Unschedule cron events ────────────────────────────────────────────────────
$crons = [ 'menda_ads_daily_expiry_check', 'menda_ads_weekly_report' ];
foreach ( $crons as $cron ) {
	$timestamp = wp_next_scheduled( $cron );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $cron );
	}
	wp_unschedule_hook( $cron );
}

// ── Remove transients ─────────────────────────────────────────────────────────
delete_transient( 'menda_ads_skin_selectors' );

$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_menda_%'
	    OR option_name LIKE '_transient_timeout_menda_%'"
);

// Ads, sponsored posts, and the analytics table are kept on Delete.
// Removing them made every plugin update look like data loss.

// ── Remove capability from all roles ──────────────────────────────────────────
$roles = wp_roles();
if ( $roles ) {
	foreach ( $roles->role_objects as $role ) {
		$role->remove_cap( 'manage_menda_ads' );
	}
}

// Analytics table kept so reinstalling does not wipe campaign history.
