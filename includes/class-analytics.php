<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Tracks ad impressions and clicks.
 * - REST API endpoint for front-end tracking (nonce-protected).
 * - Weekly email report via WP-Cron.
 * - Query helpers for the dashboard.
 */
final class Analytics {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'rest_api_init',   [ $this, 'register_rest_routes' ] );
		add_action( 'menda_ads_weekly_report', [ $this, 'send_weekly_report' ] );

		// Register the 'weekly' cron interval (WP core doesn't include it).
		add_filter( 'cron_schedules', [ $this, 'register_weekly_schedule' ] );

		// COMPAT-06: Schedule weekly report only when option changes, not every init.
		add_action( 'update_option_' . MENDA_ADS_PREFIX . '_weekly_report_enabled', [ $this, 'maybe_schedule_report' ], 10, 0 );
		// Also run once on plugin activation via plugins_loaded (first request after activation).
		add_action( 'admin_init', [ $this, 'maybe_schedule_report_once' ] );

		add_action( 'menda_ads_cache_purged', [ $this, 'flush_object_cache' ] );
		add_action( 'menda_ads_daily_expiry_check', [ $this, 'purge_old_logs' ] );
	}

	public function register_weekly_schedule( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'menda-ads' ),
			];
		}
		return $schedules;
	}

	/**
	 * COMPAT-06: Run schedule check once per admin session (not every init request).
	 * Uses a short transient so it only checks once per hour at most.
	 */
	public function maybe_schedule_report_once(): void {
		$cache_key = MENDA_ADS_PREFIX . '_schedule_checked';
		if ( get_transient( $cache_key ) ) {
			return;
		}
		set_transient( $cache_key, 1, HOUR_IN_SECONDS );
		$this->maybe_schedule_report();
	}

	/**
	 * PERF-05: Flush object cache group on cache purge.
	 */
	public function flush_object_cache(): void {
		wp_cache_delete( MENDA_ADS_PREFIX . '_summary', 'menda_ads' );
		wp_cache_delete( MENDA_ADS_PREFIX . '_daily_summary', 'menda_ads' );
	}

	public function purge_old_logs(): void {
		global $wpdb;
		$days  = max( 30, min( 730, (int) get_option( MENDA_ADS_PREFIX . '_log_retention_days', 90 ) ) );
		$table = $wpdb->prefix . 'menda_ads_log';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) )
			)
		);
	}

	// -------------------------------------------------------------------------
	// REST API
	// -------------------------------------------------------------------------

	public function register_rest_routes(): void {
		$namespaces = [ Context::rest_namespace(), 'menda-ads/v1' ];
		$track_args = [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'handle_track' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'ad_id' => [
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				],
				'event' => [
					'required'          => true,
					'type'              => 'string',
					'enum'              => [ 'view', 'click', 'click_left', 'click_right', 'click_top', 'adblock' ],
					'sanitize_callback' => 'sanitize_key',
				],
				'nonce' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		];
		foreach ( $namespaces as $ns ) {
			register_rest_route( $ns, '/track', $track_args );
		}

		register_rest_route( Context::rest_namespace(), '/stats/(?P<ad_id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_get_stats' ],
			'permission_callback' => [ $this, 'rest_permission_check' ],
			'args'                => [
				'ad_id' => [
					'required' => true,
					'type'     => 'integer',
				],
				'days' => [
					'default'           => 30,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					// S2: Cap at 2 years — prevents slow-query DoS on huge date ranges.
					'validate_callback' => fn( $v ) => $v <= 730,
				],
			],
		] );

		register_rest_route( Context::rest_namespace(), '/stats/summary', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_summary' ],
			'permission_callback' => [ $this, 'rest_permission_check' ],
		] );

		register_rest_route( Context::rest_namespace(), '/active-ads', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'handle_active_ads' ],
			'permission_callback' => [ $this, 'rest_permission_check' ],
			'args'                => [
				'device' => [
					'default'           => 'desktop',
					'type'              => 'string',
					// SEC-08: Allowlist — prevents arbitrary device strings.
					'enum'              => [ 'desktop', 'mobile', 'tablet' ],
					'sanitize_callback' => 'sanitize_key',
				],
				'page_id' => [
					'default'           => 0,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				],
			],
		] );
	}

	public function rest_permission_check(): bool {
		return current_user_can( MENDA_ADS_CAP ) || current_user_can( 'manage_options' );
	}

	/**
	 * Record a view or click event.
	 */
	public function handle_track( \WP_REST_Request $request ): \WP_REST_Response {
		$nonce_ok = (bool) wp_verify_nonce( (string) $request->get_param( 'nonce' ), 'menda_ads_track' );
		$origin   = '';
		if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
			$origin = esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$origin = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}
		$ref_host  = $origin ? (string) wp_parse_url( $origin, PHP_URL_HOST ) : '';
		$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$origin_ok = $ref_host && $home_host && strtolower( $ref_host ) === strtolower( $home_host );
		if ( ! $nonce_ok && ! $origin_ok ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => 'Invalid origin' ], 403 );
		}

		$ad_id = (int) $request->get_param( 'ad_id' );
		$event = $request->get_param( 'event' );

		if ( $ad_id < 1 || 'menda_ad' !== get_post_type( $ad_id ) || 'publish' !== get_post_status( $ad_id ) ) {
			return new \WP_REST_Response( [ 'success' => false ], 404 );
		}

		// Rate limit: 1 event per IP per ad per 60 seconds — prevents bot inflation.
		$ip_raw      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rate_key    = 'menda_rl_' . hash( 'sha256', $ip_raw . $ad_id . $event );
		if ( get_transient( $rate_key ) ) {
			return new \WP_REST_Response( [ 'success' => true ] ); // Silent — don't tell bots they're blocked.
		}
		set_transient( $rate_key, 1, 60 );

		$this->record( $ad_id, $event );

		return new \WP_REST_Response( [ 'success' => true ] );
	}

	public function handle_get_stats( \WP_REST_Request $request ): \WP_REST_Response {
		$ad_id = (int) $request->get_param( 'ad_id' );
		$days  = (int) $request->get_param( 'days' );
		return new \WP_REST_Response( $this->get_ad_stats( $ad_id, $days ) );
	}

	public function handle_summary( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( $this->get_summary() );
	}

	// -------------------------------------------------------------------------
	// Data recording
	// -------------------------------------------------------------------------

	public function record( int $ad_id, string $event ): bool {
		global $wpdb;

		// Anonymise IP — store hash only (GDPR-friendly).
		$ip_raw  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip_hash = hash( 'sha256', $ip_raw . wp_salt( 'auth' ) );
		$ua      = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( substr( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ), 0, 255 ) )
			: '';
		$ref     = isset( $_SERVER['HTTP_REFERER'] )
			? esc_url_raw( substr( wp_unslash( $_SERVER['HTTP_REFERER'] ), 0, 500 ) )
			: '';

		$result = $wpdb->insert(
			$wpdb->prefix . 'menda_ads_log',
			[
				'ad_id'      => $ad_id,
				'event_type' => $event,
				'ip_hash'    => $ip_hash,
				'user_agent' => $ua,
				'referer'    => $ref,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		return false !== $result;
	}

	private static function is_click_event( string $event ): bool {
		return in_array( $event, [ 'click', 'click_left', 'click_right', 'click_top' ], true );
	}

	private static function click_sql(): string {
		return "(event_type IN ('click','click_left','click_right','click_top'))";
	}

	// -------------------------------------------------------------------------
	// Query helpers
	// -------------------------------------------------------------------------

	/**
	 * Get views, clicks, CTR for a single ad over custom range or days.
	 */
	public function get_ad_stats( int $ad_id, $start_date = '', $end_date = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'menda_ads_log';

		if ( $start_date && $end_date ) {
			$start = gmdate( 'Y-m-d 00:00:00', strtotime( $start_date ) );
			$end   = gmdate( 'Y-m-d 23:59:59', strtotime( $end_date ) );
			$where = $wpdb->prepare( "ad_id = %d AND created_at >= %s AND created_at <= %s", $ad_id, $start, $end );
		} else {
			$days  = is_numeric( $start_date ) ? absint( $start_date ) : 30;
			$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
			$where = $wpdb->prepare( "ad_id = %d AND created_at >= %s", $ad_id, $since );
		}

		$rows = $wpdb->get_results(
			"SELECT event_type, COUNT(*) AS total
			 FROM {$table}
			 WHERE {$where}
			 GROUP BY event_type",
			ARRAY_A
		);

		$views  = 0;
		$clicks = 0;
		foreach ( $rows as $row ) {
			if ( 'view' === $row['event_type'] ) {
				$views += (int) $row['total'];
			}
			if ( self::is_click_event( (string) $row['event_type'] ) ) {
				$clicks += (int) $row['total'];
			}
		}
		$ctr = $views > 0 ? round( ( $clicks / $views ) * 100, 2 ) : 0;

		// Daily breakdown.
		$daily = $wpdb->get_results(
			"SELECT DATE(created_at) AS day, event_type, COUNT(*) AS total
			 FROM {$table}
			 WHERE {$where}
			 GROUP BY DATE(created_at), event_type
			 ORDER BY day ASC",
			ARRAY_A
		);

		return compact( 'views', 'clicks', 'ctr', 'daily' );
	}

	/**
	 * Summary of all active ads.
	 * PERF-05: Results are cached in the object cache for 2 minutes.
	 */
	public function get_summary( $start_date = '', $end_date = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'menda_ads_log';

		if ( $start_date && $end_date ) {
			$start = gmdate( 'Y-m-d 00:00:00', strtotime( $start_date ) );
			$end   = gmdate( 'Y-m-d 23:59:59', strtotime( $end_date ) );
			$where = $wpdb->prepare( "created_at >= %s AND created_at <= %s", $start, $end );
		} else {
			$days  = is_numeric( $start_date ) ? absint( $start_date ) : 30;
			$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
			$where = $wpdb->prepare( "created_at >= %s", $since );
		}

		// PERF-05: Object cache (works with Memcached/Redis; falls back to WP object cache).
		$cache_key = MENDA_ADS_PREFIX . '_summary_' . md5( $where );
		$cached    = wp_cache_get( $cache_key, 'menda_ads' );
		if ( false !== $cached ) {
			return $cached;
		}

		$click_sql = self::click_sql();
		$result    = $wpdb->get_results(
			"SELECT ad_id,
			        SUM(event_type = 'view')  AS views,
			        SUM({$click_sql}) AS clicks,
			        SUM(event_type = 'adblock') AS adblocks,
			        ROUND( SUM({$click_sql}) / NULLIF(SUM(event_type = 'view'),0) * 100, 2) AS ctr,
			        ROUND( SUM(event_type = 'adblock') / NULLIF(SUM(event_type = 'view') + SUM(event_type = 'adblock'),0) * 100, 2) AS adblock_rate
			 FROM {$table}
			 WHERE {$where}
			 GROUP BY ad_id
			 ORDER BY views DESC",
			ARRAY_A
		);

		wp_cache_set( $cache_key, $result, 'menda_ads', 2 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Get daily stats aggregated for all ads.
	 */
	public function get_daily_summary( $start_date = '', $end_date = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'menda_ads_log';

		if ( $start_date && $end_date ) {
			$start = gmdate( 'Y-m-d 00:00:00', strtotime( $start_date ) );
			$end   = gmdate( 'Y-m-d 23:59:59', strtotime( $end_date ) );
			$where = $wpdb->prepare( "created_at >= %s AND created_at <= %s", $start, $end );
		} else {
			$days  = is_numeric( $start_date ) ? absint( $start_date ) : 30;
			$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
			$where = $wpdb->prepare( "created_at >= %s", $since );
		}

		$click_sql = self::click_sql();
		return $wpdb->get_results(
			"SELECT DATE(created_at) AS day,
			        SUM(event_type = 'view')  AS views,
			        SUM({$click_sql}) AS clicks,
			        SUM(event_type = 'adblock') AS adblocks
			 FROM {$table}
			 WHERE {$where}
			 GROUP BY DATE(created_at)
			 ORDER BY day ASC",
			ARRAY_A
		);
	}

	// -------------------------------------------------------------------------
	// Weekly Report via Cron
	// -------------------------------------------------------------------------

	public function maybe_schedule_report(): void {
		$enabled = get_option( MENDA_ADS_PREFIX . '_weekly_report_enabled' );
		if ( $enabled && ! wp_next_scheduled( 'menda_ads_weekly_report' ) ) {
			// C11: Use WP timezone for scheduling — 'next monday 08:00' uses server time.
			// Convert 08:00 WP-local time to UTC timestamp for wp_schedule_event.
			$timezone       = wp_timezone();
			$next_monday    = new \DateTime( 'next monday 08:00:00', $timezone );
			$next_monday_ts = $next_monday->getTimestamp();
			wp_schedule_event( $next_monday_ts, 'weekly', 'menda_ads_weekly_report' );
		} elseif ( ! $enabled ) {
			$timestamp = wp_next_scheduled( 'menda_ads_weekly_report' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'menda_ads_weekly_report' );
			}
		}
	}

	public function send_weekly_report(): void {
		$to      = get_option( MENDA_ADS_PREFIX . '_weekly_report_email', get_option( 'admin_email' ) );
		$summary = $this->get_summary( 7 );

		if ( empty( $summary ) ) return;

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Weekly Ads Report', 'menda-ads' ),
			get_bloginfo( 'name' )
		);

		$body  = '<h2>' . esc_html__( 'Weekly Ad Performance Report', 'menda-ads' ) . '</h2>';
		$body .= '<table border="1" cellpadding="8" style="border-collapse:collapse">';
		$body .= '<tr><th>' . esc_html__( 'Ad', 'menda-ads' ) . '</th>';
		$body .= '<th>' . esc_html__( 'Views', 'menda-ads' ) . '</th>';
		$body .= '<th>' . esc_html__( 'Clicks', 'menda-ads' ) . '</th>';
		$body .= '<th>' . esc_html__( 'CTR', 'menda-ads' ) . '</th></tr>';

		foreach ( $summary as $row ) {
			$title = get_the_title( (int) $row['ad_id'] ) ?: '#' . $row['ad_id'];
			$body .= sprintf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s%%</td></tr>',
				esc_html( $title ),
				number_format_i18n( (int) $row['views'] ),
				number_format_i18n( (int) $row['clicks'] ),
				esc_html( $row['ctr'] )
			);
		}

		$body .= '</table>';

		wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	}

	public function handle_active_ads( \WP_REST_Request $request ): \WP_REST_Response {
		// SEC-08: Rate limit /active-ads — public endpoint, protect against scraping.
		$ip_raw   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rate_key = 'menda_active_ads_' . hash( 'sha256', $ip_raw );
		if ( get_transient( $rate_key ) ) {
			return new \WP_REST_Response( [], 429 );
		}
		set_transient( $rate_key, 1, 10 ); // 10s between requests per IP.

		$device  = sanitize_key( $request->get_param( 'device' ) ?: 'desktop' );
		$page_id = (int) $request->get_param( 'page_id' );

		$ads = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'publish',
			'posts_per_page' => apply_filters( 'menda_ads_max_active_query', 100 ),
			'meta_query'     => [
				'relation' => 'AND',
				[
					'relation' => 'OR',
					[ 'key' => '_menda_ad_parent_id', 'value' => '0' ],
					[ 'key' => '_menda_ad_parent_id', 'compare' => 'NOT EXISTS' ],
				]
			],
		] );

		if ( empty( $ads ) ) {
			return new \WP_REST_Response( [] );
		}

		// PERF-10: Prime meta cache for all parent ads in one query.
		update_postmeta_cache( wp_list_pluck( $ads, 'ID' ) );

		$now       = current_datetime();
		$timezone  = wp_timezone();
		$is_mobile = ( 'mobile' === $device );
		$active    = [];

		$display = Display::instance();

		foreach ( $ads as $ad ) {
			$start    = get_post_meta( $ad->ID, '_menda_ad_start', true );
			$end      = get_post_meta( $ad->ID, '_menda_ad_end', true );
			$start_dt = $start ? date_create_immutable( $start, $timezone ) : false;
			$end_dt   = $end ? date_create_immutable( $end, $timezone ) : false;

			if ( $start_dt && $start_dt > $now ) continue;
			if ( $end_dt && $end_dt < $now ) continue;

			$show_mobile  = get_post_meta( $ad->ID, '_menda_ad_show_mobile', true );
			$show_desktop = get_post_meta( $ad->ID, '_menda_ad_show_desktop', true );
			if ( '' === $show_mobile )  $show_mobile  = '1';
			if ( '' === $show_desktop ) $show_desktop = '1';

			if ( $is_mobile && ! $show_mobile ) continue;
			if ( ! $is_mobile && ! $show_desktop ) continue;

			if ( ! $display->is_ad_targeted( $ad->ID, $page_id ) ) continue;

			$active[] = $ad;
		}

		if ( empty( $active ) ) {
			return new \WP_REST_Response( [] );
		}

		// PERF-10: Fetch ALL variants in a single query instead of N+1 per parent.
		$parent_ids  = wp_list_pluck( $active, 'ID' );
		$all_variants = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'meta_query'     => [
				[ 'key' => '_menda_ad_parent_id', 'value' => $parent_ids, 'compare' => 'IN' ],
			],
		] );
		update_postmeta_cache( wp_list_pluck( $all_variants, 'ID' ) );

		// Group variants by parent_id.
		$variants_by_parent = [];
		foreach ( $all_variants as $var ) {
			$pid = (int) get_post_meta( $var->ID, '_menda_ad_parent_id', true );
			$variants_by_parent[ $pid ][] = $var;
		}

		$result = [];
		foreach ( $active as $parent_ad ) {
			$pool = [ $parent_ad ];
			foreach ( ( $variants_by_parent[ $parent_ad->ID ] ?? [] ) as $var ) {
				$var_start    = get_post_meta( $var->ID, '_menda_ad_start', true );
				$var_end      = get_post_meta( $var->ID, '_menda_ad_end', true );
				$var_start_dt = $var_start ? date_create_immutable( $var_start, $timezone ) : false;
				$var_end_dt   = $var_end ? date_create_immutable( $var_end, $timezone ) : false;

				if ( $var_start_dt && $var_start_dt > $now ) continue;
				if ( $var_end_dt && $var_end_dt < $now ) continue;

				$show_mobile  = get_post_meta( $var->ID, '_menda_ad_show_mobile', true );
				$show_desktop = get_post_meta( $var->ID, '_menda_ad_show_desktop', true );
				if ( '' === $show_mobile )  $show_mobile  = '1';
				if ( '' === $show_desktop ) $show_desktop = '1';

				if ( $is_mobile && ! $show_mobile ) continue;
				if ( ! $is_mobile && ! $show_desktop ) continue;
				if ( ! $display->is_ad_targeted( $var->ID, $page_id ) ) continue;

				$pool[] = $var;
			}

			$ad = $pool[ array_rand( $pool ) ];

			$image_url = '';
			$image_id  = get_post_meta( $ad->ID, '_menda_ad_image_id', true );
			if ( $image_id ) {
				$image_url = wp_get_attachment_url( (int) $image_id ) ?: '';
			}

			$result[] = [
				'id'        => $ad->ID,
				'title'     => get_the_title( $ad ),
				'type'      => get_post_meta( $ad->ID, '_menda_ad_type', true ) ?: 'banner',
				'link'      => get_post_meta( $ad->ID, '_menda_ad_link', true ) ?: '',
				'image_url' => $image_url,
				// SEC-01: custom_code intentionally omitted — may contain sensitive ad-network tokens.
			];
		}

		return new \WP_REST_Response( $result );
	}
}
