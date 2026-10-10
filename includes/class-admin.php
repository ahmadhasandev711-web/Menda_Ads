<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Admin dashboard: menu, settings, analytics page.
 */
final class Admin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_init',            [ $this, 'register_settings' ] );
		add_action( 'admin_init',            [ $this, 'handle_csv_export' ] );
		add_filter( 'option_page_capability_menda_ads_settings', [ $this, 'settings_capability' ] );
		add_action( 'admin_notices',         [ $this, 'display_expiry_notices' ] );
		add_action( 'admin_notices',         [ $this, 'display_skin_status_notice' ] );
		add_action( 'menda_ads_daily_expiry_check', [ $this, 'run_expiry_check' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( MENDA_ADS_FILE ), [ $this, 'plugin_action_links' ] );
		add_filter( 'parent_file', [ $this, 'menu_parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'menu_submenu_file' ], 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function register_menu(): void {
		Installer::ensure_caps();

		$cap  = MENDA_ADS_CAP;
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M3 3h14v2H3zm0 4h10v2H3zm0 4h14v2H3zm0 4h10v2H3z"/></svg>' );

		add_menu_page(
			__( 'Menda Ads', 'menda-ads' ),
			__( 'Menda Ads', 'menda-ads' ),
			$cap,
			'menda-ads',
			[ $this, 'render_dashboard' ],
			$icon,
			58
		);

		add_submenu_page( 'menda-ads', __( 'Dashboard', 'menda-ads' ),   __( 'Dashboard', 'menda-ads' ),   $cap, 'menda-ads',            [ $this, 'render_dashboard' ] );
		add_submenu_page( 'menda-ads', __( 'All Ads', 'menda-ads' ),      __( 'All Ads', 'menda-ads' ),      $cap, 'edit.php?post_type=menda_ad', null );
		add_submenu_page( 'menda-ads', __( 'Add New Ad', 'menda-ads' ),   __( 'Add New Ad', 'menda-ads' ),   $cap, 'post-new.php?post_type=menda_ad', null );
		add_submenu_page( 'menda-ads', __( 'Placements', 'menda-ads' ),   __( 'Placements', 'menda-ads' ),   $cap, 'edit-tags.php?taxonomy=menda_ad_placement&post_type=menda_ad', null );
		add_submenu_page( 'menda-ads', __( 'Sponsored Articles', 'menda-ads' ), __( 'Sponsored Articles', 'menda-ads' ), $cap, 'edit.php?post_type=menda_sponsored', null );
		add_submenu_page( 'menda-ads', __( 'Analytics', 'menda-ads' ),   __( 'Analytics', 'menda-ads' ),   $cap, 'menda-ads-analytics', [ $this, 'render_analytics' ] );
		add_submenu_page( 'menda-ads', __( 'Settings', 'menda-ads' ),    __( 'Settings', 'menda-ads' ),    $cap, 'menda-ads-settings',  [ $this, 'render_settings' ] );
	}

	public function menu_parent_file( string $parent_file ): string {
		$screen = get_current_screen();
		if ( $screen && 'menda_ad_placement' === $screen->taxonomy ) {
			return 'menda-ads';
		}
		return $parent_file;
	}

	public function menu_submenu_file( $submenu_file, $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && 'menda_ad_placement' === $screen->taxonomy ) {
			return 'edit-tags.php?taxonomy=menda_ad_placement&post_type=menda_ad';
		}
		return $submenu_file;
	}

	public function plugin_action_links( array $links ): array {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=menda-ads-settings' ) ) . '">' . esc_html__( 'Settings', 'menda-ads' ) . '</a>';
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=menda-ads-analytics' ) ) . '">' . esc_html__( 'Analytics', 'menda-ads' ) . '</a>';
		return $links;
	}

	// -------------------------------------------------------------------------
	// Enqueue
	// -------------------------------------------------------------------------

	public function enqueue( string $hook ): void {
		if ( ! str_contains( $hook, 'menda-ads' ) && ! in_array( get_current_screen()?->post_type ?? '', [ 'menda_ad', 'menda_sponsored' ], true ) ) {
			return;
		}

		wp_enqueue_style(
			'menda-ads-admin',
			MENDA_ADS_URL . 'assets/css/admin.css',
			[],
			MENDA_ADS_VERSION
		);

		wp_enqueue_script(
			'menda-ads-admin',
			MENDA_ADS_URL . 'assets/js/admin.js',
			[ 'jquery', 'wp-api-fetch', 'wp-i18n' ],
			MENDA_ADS_VERSION,
			true
		);

		wp_localize_script( 'menda-ads-admin', 'mendaAds', [
			'apiBase'  => rest_url( Context::rest_namespace() . '/' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'i18n'     => [
				'views'  => __( 'Views', 'menda-ads' ),
				'clicks' => __( 'Clicks', 'menda-ads' ),
				'ctr'    => __( 'CTR', 'menda-ads' ),
				'noData' => __( 'No data available.', 'menda-ads' ),
			],
		] );

		// Media uploader for ad edit screens and settings (skin background image).
		$screen    = get_current_screen();
		$post_type = $screen?->post_type ?? '';
		$is_settings = str_contains( $hook, 'menda-ads-settings' );

		if ( in_array( $post_type, [ 'menda_ad', 'menda_sponsored' ], true ) || $is_settings ) {
			wp_enqueue_media();
		}
	}

	// -------------------------------------------------------------------------
	// Settings Registration
	// -------------------------------------------------------------------------

	public function settings_capability(): string {
		return MENDA_ADS_CAP;
	}

	public static function sanitize_access_level( $value ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Installer::get_access_level();
		}
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, [ 'administrators', 'editors', 'authors' ], true )
			? $value
			: 'administrators';
	}

	public static function sanitize_frequency( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, [ 'once_per_session', 'once_per_day', 'every_page' ], true ) ? $value : 'once_per_session';
	}

	public static function sanitize_position( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, [ 'bottom-right', 'bottom-left', 'top-right', 'top-left' ], true ) ? $value : 'bottom-right';
	}

	public static function sanitize_skip_delay( $value ): int {
		return min( 30, max( 0, absint( $value ) ) );
	}

	public static function sanitize_content_width( $value ): int {
		return min( 1920, max( 600, absint( $value ) ) );
	}

	public static function sanitize_retention( $value ): int {
		return min( 730, max( 30, absint( $value ) ) );
	}

	public static function sanitize_min_views( $value ): int {
		return min( 20, max( 0, absint( $value ) ) );
	}

	public static function sanitize_geo_unknown( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, [ 'hide', 'show' ], true ) ? $value : 'hide';
	}

	public function register_settings(): void {
		$settings = [
			'interstitial_enabled'    => 'rest_sanitize_boolean',
			'interstitial_skip_delay' => [ self::class, 'sanitize_skip_delay' ],
			'interstitial_frequency'  => [ self::class, 'sanitize_frequency' ],
			'sticky_video_enabled'    => 'rest_sanitize_boolean',
			'sticky_video_position'   => [ self::class, 'sanitize_position' ],
			'skin_enabled'            => 'rest_sanitize_boolean',
			'skin_content_width'      => [ self::class, 'sanitize_content_width' ],
			'skin_wrapper_selector'   => [ Context::class, 'sanitize_selector' ],
			'skin_header_selector'    => [ Context::class, 'sanitize_selector' ],
			'weekly_report_enabled'   => 'rest_sanitize_boolean',
			'weekly_report_email'     => 'sanitize_email',
			'adblock_detect'          => 'rest_sanitize_boolean',
			'adblock_fallback'        => 'sanitize_textarea_field',
			'log_retention_days'      => [ self::class, 'sanitize_retention' ],
			'access_level'            => [ self::class, 'sanitize_access_level' ],
			'currency'                => [ I18n::class, 'sanitize_currency' ],
			'interstitial_skip_home'  => 'rest_sanitize_boolean',
			'interstitial_min_views'  => [ self::class, 'sanitize_min_views' ],
			'insert_p2'               => 'rest_sanitize_boolean',
			'insert_p4'               => 'rest_sanitize_boolean',
			'insert_below'            => 'rest_sanitize_boolean',
			'insert_mobile_sticky'    => 'rest_sanitize_boolean',
			'insert_archive'          => 'rest_sanitize_boolean',
			'geo_unknown'             => [ self::class, 'sanitize_geo_unknown' ],
		];

		foreach ( $settings as $key => $sanitizer ) {
			register_setting(
				'menda_ads_settings',
				MENDA_ADS_PREFIX . '_' . $key,
				[
					'sanitize_callback' => $sanitizer,
					'capability'        => 'access_level' === $key ? 'manage_options' : MENDA_ADS_CAP,
				]
			);
		}
	}

	// -------------------------------------------------------------------------
	// Pages
	// -------------------------------------------------------------------------

	public function render_dashboard(): void {
		$analytics    = Analytics::instance();
		$summary      = $analytics->get_summary( 30 );
		$total_views  = array_sum( array_column( $summary, 'views' ) );
		$total_clicks = array_sum( array_column( $summary, 'clicks' ) );
		$avg_ctr      = $total_views > 0 ? round( ( $total_clicks / $total_views ) * 100, 2 ) : 0;
		$total_ads    = wp_count_posts( 'menda_ad' )->publish ?? 0;
		$total_sponsored = wp_count_posts( 'menda_sponsored' )->publish ?? 0;

		$total_adblocks = array_sum( array_column( $summary, 'adblocks' ) );
		$avg_adblock_rate = ($total_views + $total_adblocks) > 0 ? round( ( $total_adblocks / ( $total_views + $total_adblocks ) ) * 100, 2 ) : 0;

		include MENDA_ADS_DIR . 'admin/views/dashboard.php';
	}

	public function render_analytics(): void {
		$analytics  = Analytics::instance();
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
		$days       = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		$filter_ad  = isset( $_GET['ad_id'] ) ? absint( $_GET['ad_id'] ) : 0;

		if ( $start_date && $end_date ) {
			$summary       = $analytics->get_summary( $start_date, $end_date );
			$daily_summary = $analytics->get_daily_summary( $start_date, $end_date );
		} else {
			$summary       = $analytics->get_summary( $days );
			$daily_summary = $analytics->get_daily_summary( $days );
		}

		if ( $filter_ad && ! empty( $summary ) ) {
			$summary = array_values( array_filter( $summary, static function ( $row ) use ( $filter_ad ) {
				return (int) $row['ad_id'] === $filter_ad;
			} ) );
		}
		include MENDA_ADS_DIR . 'admin/views/analytics.php';
	}

	public function render_settings(): void {
		include MENDA_ADS_DIR . 'admin/views/settings.php';
	}

	public function handle_csv_export(): void {
		if ( ! isset( $_GET['page'] ) || 'menda-ads-analytics' !== $_GET['page'] ) {
			return;
		}
		if ( ! isset( $_GET['action'] ) || 'export_csv' !== $_GET['action'] ) {
			return;
		}
		if ( ! current_user_can( MENDA_ADS_CAP ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'menda-ads' ) );
		}
		// SEC-07: Verify CSRF nonce before streaming any data.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'menda_ads_csv_export' ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'menda-ads' ) );
		}

		$analytics  = Analytics::instance();
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
		$days       = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		$filter_ad  = isset( $_GET['ad_id'] ) ? absint( $_GET['ad_id'] ) : 0;

		if ( $start_date && $end_date ) {
			$summary  = $analytics->get_summary( $start_date, $end_date );
			$filename = sprintf( 'menda-ads-report-%s-to-%s.csv', $start_date, $end_date );
		} else {
			$summary  = $analytics->get_summary( $days );
			$filename = sprintf( 'menda-ads-report-last-%d-days.csv', $days );
		}

		if ( $filter_ad && ! empty( $summary ) ) {
			$summary = array_values( array_filter( $summary, static function ( $row ) use ( $filter_ad ) {
				return (int) $row['ad_id'] === $filter_ad;
			} ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		
		// Add UTF-8 BOM for Excel compatibility
		fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

		fputcsv( $output, [
			__( 'Ad ID', 'menda-ads' ),
			__( 'Ad Title', 'menda-ads' ),
			__( 'Type', 'menda-ads' ),
			__( 'Status', 'menda-ads' ),
			__( 'Views', 'menda-ads' ),
			__( 'Clicks', 'menda-ads' ),
			__( 'Blocked', 'menda-ads' ),
			__( 'Block Rate (%)', 'menda-ads' ),
			__( 'CTR (%)', 'menda-ads' ),
		] );

		if ( ! empty( $summary ) ) {
			$ad_ids = array_map( 'intval', array_column( $summary, 'ad_id' ) );
			_prime_post_caches( $ad_ids, true, false );

			foreach ( $summary as $row ) {
				$ad_id    = (int) $row['ad_id'];
				$ad       = get_post( $ad_id );
				$title    = $ad ? get_the_title( $ad ) : '#' . $ad_id;
				$ad_type  = get_post_meta( $ad_id, '_menda_ad_type', true );
				$status   = $ad ? $ad->post_status : 'unknown';

				fputcsv( $output, [
					$ad_id,
					$title,
					ucfirst( str_replace( '_', ' ', $ad_type ) ),
					$status,
					$row['views'],
					$row['clicks'],
					$row['adblocks'] ?? 0,
					($row['adblock_rate'] ?? 0) . '%',
					$row['ctr'],
				] );
			}
		}

		fclose( $output );
		exit;
	}

	private function get_expiring_ads( int $days_threshold = 3 ): array {
		$now      = current_datetime();
		$timezone = wp_timezone();

		$ads = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'publish',
			'posts_per_page' => apply_filters( 'menda_ads_max_expiry_query', 200 ),
		] );

		if ( empty( $ads ) ) {
			return [];
		}

		// PERF-02: Prime post meta cache for all ads in a single query (avoids N+1).
		$ad_ids = wp_list_pluck( $ads, 'ID' );
		update_postmeta_cache( $ad_ids );

		$expiring = [];
		foreach ( $ads as $ad ) {
			$end = get_post_meta( $ad->ID, '_menda_ad_end', true );
			if ( ! $end ) continue;

			$end_dt = date_create_immutable( $end, $timezone );
			if ( ! $end_dt ) continue;

			$diff = $now->diff( $end_dt );
			$diff_days = $diff->days;

			// If it's already expired or expiring in <= 3 days, and not yet expired in the past
			if ( $end_dt > $now && $diff_days <= $days_threshold ) {
				$expiring[] = [
					'id'        => $ad->ID,
					'title'     => get_the_title( $ad ),
					'end_date'  => $end,
					'days_left' => $diff_days,
				];
			}
		}

		return $expiring;
	}

	public function run_expiry_check(): void {
		$expiring = $this->get_expiring_ads( 3 );
		if ( empty( $expiring ) ) return;

		$to      = get_option( 'admin_email' );
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Notice: Ads Expiring Soon', 'menda-ads' ),
			get_bloginfo( 'name' )
		);

		$body  = '<h2>' . esc_html__( 'The following ads are expiring in the next 3 days:', 'menda-ads' ) . '</h2>';
		$body .= '<ul>';
		foreach ( $expiring as $ad ) {
			$body .= sprintf(
				'<li><strong>%s</strong> (ID: %d) - %s: %s (%d %s left)</li>',
				esc_html( $ad['title'] ),
				$ad['id'],
				esc_html__( 'Expires on', 'menda-ads' ),
				esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $ad['end_date'] ) ) ),
				$ad['days_left'],
				_n( 'day', 'days', $ad['days_left'], 'menda-ads' )
			);
		}
		$body .= '</ul>';
		$body .= '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=menda_ad' ) ) . '">' . esc_html__( 'Manage Ads', 'menda-ads' ) . '</a></p>';

		wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	}

	public function display_expiry_notices(): void {
		if ( ! current_user_can( MENDA_ADS_CAP ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ( ! str_contains( $screen->id, 'menda-ads' ) && 'dashboard' !== $screen->id && 'edit-menda_ad' !== $screen->id ) ) {
			return;
		}

		$expiring = $this->get_expiring_ads( 3 );
		if ( empty( $expiring ) ) return;

		foreach ( $expiring as $ad ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong>⚠️ <?php esc_html_e( 'Ad Expiring Soon:', 'menda-ads' ); ?></strong>
					<?php
					echo wp_kses(
						sprintf(
							/* translators: 1: Ad title, 2: Days left */
							_n( 'The ad "%1$s" is expiring in %2$d day.', 'The ad "%1$s" is expiring in %2$d days.', (int) $ad['days_left'], 'menda-ads' ),
							'<strong>' . esc_html( $ad['title'] ) . '</strong>',
							(int) $ad['days_left']
						),
						[ 'strong' => [] ]
					);
					?>
					<a href="<?php echo esc_url( get_edit_post_link( $ad['id'] ) ); ?>"><?php esc_html_e( 'Edit Ad', 'menda-ads' ); ?></a>
				</p>
			</div>
			<?php
		}
	}

	public function display_skin_status_notice(): void {
		if ( ! current_user_can( MENDA_ADS_CAP ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ( 'edit-menda_ad' !== $screen->id && 'menda_ad' !== $screen->id && 'menda-ads_page_menda-ads-settings' !== $screen->id ) ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id && get_transient( 'menda_ads_notice_default_slot_' . $post_id ) ) {
			delete_transient( 'menda_ads_notice_default_slot_' . $post_id );
			?>
			<div class="notice notice-info is-dismissible">
				<p><?php esc_html_e( 'This banner had no placement, so it was assigned “in-article-300x250” (after paragraph 2 on posts). Change the Placements box if you need a different slot.', 'menda-ads' ); ?></p>
			</div>
			<?php
		}

		$gates = [
			'skin'         => [ '_skin_enabled', __( 'Website Skin is off.', 'menda-ads' ), 'admin.php?page=menda-ads-settings#skin' ],
			'interstitial' => [ '_interstitial_enabled', __( 'Interstitial ads are off.', 'menda-ads' ), 'admin.php?page=menda-ads-settings#interstitial' ],
			'sticky_video' => [ '_sticky_video_enabled', __( 'Sticky video is off.', 'menda-ads' ), 'admin.php?page=menda-ads-settings#sticky' ],
		];
		foreach ( $gates as $type => $row ) {
			if ( get_option( MENDA_ADS_PREFIX . $row[0] ) ) {
				continue;
			}
			$has = get_posts( [
				'post_type'      => 'menda_ad',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_menda_ad_type',
				'meta_value'     => $type,
			] );
			if ( empty( $has ) ) {
				continue;
			}
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php echo esc_html( $row[1] ); ?></strong>
					<?php esc_html_e( 'A unit is published but the site setting is disabled, so nothing appears on the front.', 'menda-ads' ); ?>
					<a href="<?php echo esc_url( admin_url( $row[2] ) ); ?>"><?php esc_html_e( 'Enable in Settings', 'menda-ads' ); ?></a>
				</p>
			</div>
			<?php
		}
	}
}
