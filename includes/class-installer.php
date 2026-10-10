<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation, deactivation, and DB schema.
 */
final class Installer {

	public static function activate(): void {
		self::create_tables();
		self::set_defaults();
		self::grant_cap();

		if ( ! wp_next_scheduled( 'menda_ads_daily_expiry_check' ) ) {
			wp_schedule_event( time(), 'daily', 'menda_ads_daily_expiry_check' );
		}

		flush_rewrite_rules();
	}

	/**
	 * FEAT-02: Run database migration on plugin update.
	 * Compares stored DB version with current plugin version and runs activate()
	 * if they differ. dbDelta() safely adds new columns / indexes without data loss.
	 */
	public static function maybe_migrate(): void {
		$installed = get_option( MENDA_ADS_PREFIX . '_db_version', '0' );
		if ( version_compare( $installed, MENDA_ADS_VERSION, '<' ) ) {
			self::create_tables();
			update_option( MENDA_ADS_PREFIX . '_db_version', MENDA_ADS_VERSION );
			add_action( 'init', static function (): void {
				Cache::instance()->purge_all();
			}, 99 );
		}
		self::set_defaults();
		self::grant_cap();
	}

	/**
	 * Move settings-page skin creative onto a Skin-type ad (source of truth in 1.2.0).
	 */
	public static function migrate_legacy_skin(): void {
		if ( get_option( MENDA_ADS_PREFIX . '_skin_migrated_120' ) ) {
			return;
		}
		if ( ! post_type_exists( 'menda_ad' ) || wp_installing() ) {
			return;
		}
		$existing = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_menda_ad_type',
			'meta_value'     => 'skin',
		] );
		if ( ! empty( $existing ) ) {
			update_option( MENDA_ADS_PREFIX . '_skin_migrated_120', 1, false );
			return;
		}
		$img  = (int) get_option( MENDA_ADS_PREFIX . '_skin_bg_image' );
		$link = (string) get_option( MENDA_ADS_PREFIX . '_skin_link', '' );
		if ( ! $img && '' === $link ) {
			update_option( MENDA_ADS_PREFIX . '_skin_migrated_120', 1, false );
			return;
		}
		$id = wp_insert_post( [
			'post_type'   => 'menda_ad',
			'post_status' => 'publish',
			'post_title'  => __( 'Website Skin', 'menda-ads' ),
		], true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_menda_ad_type', 'skin' );
			if ( $img ) {
				update_post_meta( $id, '_menda_ad_image_id', $img );
			}
			if ( $link ) {
				update_post_meta( $id, '_menda_ad_link', esc_url_raw( $link ) );
			}
			$color = sanitize_hex_color( (string) get_option( MENDA_ADS_PREFIX . '_skin_bg_color', '' ) );
			if ( $color ) {
				update_post_meta( $id, '_menda_ad_skin_bg_color', $color );
			}
			$start = (string) get_option( MENDA_ADS_PREFIX . '_skin_start', '' );
			$end   = (string) get_option( MENDA_ADS_PREFIX . '_skin_end', '' );
			if ( $start ) {
				update_post_meta( $id, '_menda_ad_start', sanitize_text_field( $start ) );
			}
			if ( $end ) {
				update_post_meta( $id, '_menda_ad_end', sanitize_text_field( $end ) );
			}
			update_post_meta( $id, '_menda_ad_skin_fit', 'cover' );
			update_post_meta( $id, '_menda_ad_skin_frequency', 'once_per_day' );
			update_post_meta( $id, '_menda_ad_skin_mobile_mode', 'sticky' );
		}
		update_option( MENDA_ADS_PREFIX . '_skin_migrated_120', 1, false );
	}

	/**
	 * 1.2.5: turn takeovers on when a skin ad exists, and keep house skins
	 * visible on every page (once-per-day was hiding them after the homepage).
	 */
	public static function migrate_125(): void {
		if ( get_option( MENDA_ADS_PREFIX . '_migrated_125' ) ) {
			return;
		}
		if ( ! post_type_exists( 'menda_ad' ) || wp_installing() ) {
			return;
		}

		$ads = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_key'       => '_menda_ad_type',
			'meta_value'     => 'skin',
		] );

		$published = false;
		foreach ( $ads as $id ) {
			$id = (int) $id;
			$freq = (string) get_post_meta( $id, '_menda_ad_skin_frequency', true );
			if ( '' === $freq || 'once_per_day' === $freq ) {
				update_post_meta( $id, '_menda_ad_skin_frequency', 'every_page' );
			}
			$desktop = (string) get_post_meta( $id, '_menda_ad_show_desktop', true );
			$mobile  = (string) get_post_meta( $id, '_menda_ad_show_mobile', true );
			if ( '0' === $desktop && '0' === $mobile ) {
				update_post_meta( $id, '_menda_ad_show_desktop', '1' );
				update_post_meta( $id, '_menda_ad_show_mobile', '1' );
			}
			if ( 'publish' === get_post_status( $id ) ) {
				$published = true;
			}
		}

		if ( $published ) {
			update_option( MENDA_ADS_PREFIX . '_skin_enabled', 1 );
		}

		update_option( MENDA_ADS_PREFIX . '_migrated_125', 1, false );
	}

	/**
	 * 1.2.6: "All languages" was stored as "al" (substr of "all"), so ads
	 * matched no language and never rendered.
	 */
	public static function migrate_126(): void {
		if ( get_option( MENDA_ADS_PREFIX . '_migrated_126' ) ) {
			return;
		}
		if ( ! post_type_exists( 'menda_ad' ) || wp_installing() ) {
			return;
		}

		$ads = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
		] );
		foreach ( $ads as $id ) {
			$raw = strtolower( trim( (string) get_post_meta( (int) $id, '_menda_ad_languages', true ) ) );
			if ( 'al' === $raw || str_contains( $raw, 'al,' ) || str_contains( $raw, ',al' ) ) {
				update_post_meta( (int) $id, '_menda_ad_languages', 'all' );
			}
		}

		update_option( MENDA_ADS_PREFIX . '_migrated_126', 1, false );
	}

	/**
	 * 1.3.0: make published ads visible — lower overlay min viewport, turn on
	 * master switches, ease interstitial test gates.
	 */
	public static function migrate_130(): void {
		if ( get_option( MENDA_ADS_PREFIX . '_migrated_130' ) ) {
			return;
		}
		if ( ! post_type_exists( 'menda_ad' ) || wp_installing() ) {
			return;
		}

		$ads = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
		] );

		$has = [
			'skin'         => false,
			'interstitial' => false,
			'sticky_video' => false,
		];

		foreach ( $ads as $id ) {
			$id   = (int) $id;
			$type = (string) get_post_meta( $id, '_menda_ad_type', true );
			if ( isset( $has[ $type ] ) && 'publish' === get_post_status( $id ) ) {
				$has[ $type ] = true;
			}
			if ( 'skin' === $type ) {
				$layout = (string) get_post_meta( $id, '_menda_ad_skin_layout', true ) ?: 'overlay';
				$min_vp = (int) get_post_meta( $id, '_menda_ad_skin_min_vp', true );
				if ( 'overlay' === $layout && ( $min_vp < 1 || $min_vp >= 1100 ) ) {
					update_post_meta( $id, '_menda_ad_skin_min_vp', 782 );
				}
				$freq = (string) get_post_meta( $id, '_menda_ad_skin_frequency', true );
				if ( '' === $freq || 'once_per_day' === $freq ) {
					update_post_meta( $id, '_menda_ad_skin_frequency', 'every_page' );
				}
			}
		}

		if ( $has['skin'] ) {
			update_option( MENDA_ADS_PREFIX . '_skin_enabled', 1 );
		}
		if ( $has['interstitial'] ) {
			update_option( MENDA_ADS_PREFIX . '_interstitial_enabled', 1 );
		}
		if ( $has['sticky_video'] ) {
			update_option( MENDA_ADS_PREFIX . '_sticky_video_enabled', 1 );
		}

		update_option( MENDA_ADS_PREFIX . '_interstitial_min_views', 1 );
		update_option( MENDA_ADS_PREFIX . '_interstitial_skip_home', 0 );

		update_option( MENDA_ADS_PREFIX . '_migrated_130', 1, false );

		Cache::instance()->purge_all();
	}

	/**
	 * 1.3.1: overlay skins must still deliver on phones (most traffic).
	 * Empty mobile image now falls back to a side panel; "hide" becomes sticky.
	 */
	public static function migrate_131(): void {
		if ( get_option( MENDA_ADS_PREFIX . '_migrated_131' ) ) {
			return;
		}
		if ( ! post_type_exists( 'menda_ad' ) || wp_installing() ) {
			return;
		}

		$ads = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_key'       => '_menda_ad_type',
			'meta_value'     => 'skin',
		] );

		foreach ( $ads as $id ) {
			$id    = (int) $id;
			$mode  = (string) get_post_meta( $id, '_menda_ad_skin_mobile_mode', true );
			$layout = (string) get_post_meta( $id, '_menda_ad_skin_layout', true ) ?: 'overlay';
			if ( 'overlay' === $layout && ( '' === $mode || 'none' === $mode ) ) {
				update_post_meta( $id, '_menda_ad_skin_mobile_mode', 'sticky' );
			}
			if ( '' === (string) get_post_meta( $id, '_menda_ad_show_mobile', true ) ) {
				update_post_meta( $id, '_menda_ad_show_mobile', '1' );
			}
		}

		update_option( MENDA_ADS_PREFIX . '_migrated_131', 1, false );
		Cache::instance()->purge_all();
	}

	/**
	 * Persist role caps. The user_has_cap filter is what actually lets admins in
	 * even when the role row in the database is stale.
	 */
	public static function ensure_caps(): void {
		self::grant_cap();
	}

	/**
	 * Grant manage_menda_ads in-memory so CPT/REST checks never 403 an admin.
	 * Does not call has_cap() — that would recurse into this filter.
	 *
	 * @param array<string,bool> $allcaps
	 * @param string[]           $caps
	 * @param array              $args
	 * @param \WP_User           $user
	 * @return array<string,bool>
	 */
	public static function filter_user_has_cap( array $allcaps, array $caps, array $args, $user ): array {
		if ( ! in_array( MENDA_ADS_CAP, $caps, true ) ) {
			return $allcaps;
		}
		if ( self::allcaps_allow_ads( $allcaps ) ) {
			$allcaps[ MENDA_ADS_CAP ] = true;
		}
		return $allcaps;
	}

	public static function get_access_level(): string {
		$level = get_option( MENDA_ADS_PREFIX . '_access_level', 'administrators' );
		return in_array( $level, [ 'administrators', 'editors', 'authors' ], true )
			? $level
			: 'administrators';
	}

	/**
	 * @param array<string,bool> $allcaps
	 */
	private static function allcaps_allow_ads( array $allcaps ): bool {
		if ( ! empty( $allcaps['manage_options'] ) ) {
			return true;
		}
		return match ( self::get_access_level() ) {
			'editors' => ! empty( $allcaps['edit_others_posts'] ),
			'authors' => ! empty( $allcaps['publish_posts'] ),
			default   => false,
		};
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'menda_ads_daily_expiry_check' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'menda_ads_daily_expiry_check' );
		}
		flush_rewrite_rules();
	}

	/**
	 * Create custom analytics table.
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$table   = $wpdb->prefix . 'menda_ads_log';

		$sql = "CREATE TABLE {$table} (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ad_id       BIGINT UNSIGNED NOT NULL,
			event_type  VARCHAR(20)     NOT NULL COMMENT 'view | click | adblock',
			ip_hash     VARCHAR(64)     NOT NULL DEFAULT '',
			user_agent  VARCHAR(255)    NOT NULL DEFAULT '',
			referer     VARCHAR(500)    NOT NULL DEFAULT '',
			created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_ad_event   (ad_id, event_type),
			KEY idx_created    (created_at),
			KEY idx_ad_created (ad_id, created_at)
		) {$charset};";
		// Note: dbDelta() requires 'CREATE TABLE' without 'IF NOT EXISTS'.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Ensure compound index exists even on legacy tables where dbDelta might skip index creation.
		$index_exists = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_ad_created'" );
		if ( empty( $index_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_ad_created (ad_id, created_at)" );
		}

		update_option( MENDA_ADS_PREFIX . '_db_version', MENDA_ADS_VERSION );
	}

	/**
	 * Set plugin option defaults on first install.
	 */
	private static function set_defaults(): void {
		$defaults = [
			'interstitial_enabled'    => false,
			'interstitial_skip_delay' => 5,
			'interstitial_frequency'  => 'once_per_session',
			'sticky_video_enabled'    => false,
			'sticky_video_position'   => 'bottom-right',
			'skin_enabled'            => false,
			'skin_start'              => '',
			'skin_end'                => '',
			'skin_bg_color'           => '#ffffff',
			'skin_bg_image'           => '',
			'skin_link'               => '',
			'skin_content_width'      => 1200,
			'skin_wrapper_selector'   => '',
			'skin_header_selector'    => '',
			'weekly_report_enabled'   => false,
			'weekly_report_email'     => get_option( 'admin_email' ),
			'log_retention_days'      => 90,
			'access_level'            => 'administrators',
			'currency'                => I18n::default_currency_for_locale(),
			'interstitial_skip_home'  => false,
			'interstitial_min_views'  => 1,
			'insert_p2'               => true,
			'insert_p4'               => true,
			'insert_below'            => true,
			'insert_mobile_sticky'    => true,
			'insert_archive'          => false,
			'geo_unknown'             => 'hide',
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( MENDA_ADS_PREFIX . '_' . $key ) ) {
				add_option( MENDA_ADS_PREFIX . '_' . $key, $value );
			}
		}
	}

	/**
	 * Sync manage_menda_ads onto roles according to the access_level setting.
	 * Administrators always keep it. Editors/Authors only when the setting allows.
	 */
	public static function grant_cap(): void {
		$roles = wp_roles();
		if ( ! $roles ) {
			return;
		}
		$level = self::get_access_level();
		foreach ( $roles->role_objects as $role ) {
			$should = $role->has_cap( 'manage_options' )
				|| ( 'editors' === $level && $role->has_cap( 'edit_others_posts' ) )
				|| ( 'authors' === $level && $role->has_cap( 'publish_posts' ) );

			if ( $should && ! $role->has_cap( MENDA_ADS_CAP ) ) {
				$role->add_cap( MENDA_ADS_CAP );
			} elseif ( ! $should && $role->has_cap( MENDA_ADS_CAP ) ) {
				$role->remove_cap( MENDA_ADS_CAP );
			}
		}
	}
}
