<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap menda-admin-wrap">
	<h1 class="menda-admin-title">
		<span class="menda-logo">📢</span>
		<?php esc_html_e( 'Menda Ads Manager', 'menda-ads' ); ?>
	</h1>

	<!-- KPI Cards -->
	<div class="menda-kpi-grid">
		<div class="menda-kpi-card">
			<div class="menda-kpi-icon">👁️</div>
			<div class="menda-kpi-value"><?php echo number_format_i18n( $total_views ); ?></div>
			<div class="menda-kpi-label"><?php esc_html_e( 'Total Views (30d)', 'menda-ads' ); ?></div>
		</div>
		<div class="menda-kpi-card">
			<div class="menda-kpi-icon">🖱️</div>
			<div class="menda-kpi-value"><?php echo number_format_i18n( $total_clicks ); ?></div>
			<div class="menda-kpi-label"><?php esc_html_e( 'Total Clicks (30d)', 'menda-ads' ); ?></div>
		</div>
		<div class="menda-kpi-card">
			<div class="menda-kpi-icon">📈</div>
			<div class="menda-kpi-value"><?php echo esc_html( $avg_ctr ); ?>%</div>
			<div class="menda-kpi-label"><?php esc_html_e( 'Average CTR', 'menda-ads' ); ?></div>
		</div>
		<div class="menda-kpi-card">
			<div class="menda-kpi-icon">🛡️</div>
			<div class="menda-kpi-value"><?php echo esc_html( $avg_adblock_rate ); ?>%</div>
			<div class="menda-kpi-label"><?php esc_html_e( 'Ad Block Rate', 'menda-ads' ); ?></div>
		</div>
		<div class="menda-kpi-card">
			<div class="menda-kpi-icon">📣</div>
			<div class="menda-kpi-value"><?php echo number_format_i18n( (int) $total_ads ); ?></div>
			<div class="menda-kpi-label"><?php esc_html_e( 'Active Ads', 'menda-ads' ); ?></div>
		</div>
		<div class="menda-kpi-card">
			<div class="menda-kpi-icon">✍️</div>
			<div class="menda-kpi-value"><?php echo number_format_i18n( (int) $total_sponsored ); ?></div>
			<div class="menda-kpi-label"><?php esc_html_e( 'Sponsored Articles', 'menda-ads' ); ?></div>
		</div>
	</div>

	<!-- Quick Status -->
	<div class="menda-status-grid">
		<div class="menda-status-card">
			<h3><?php esc_html_e( 'Interstitial Ads', 'menda-ads' ); ?></h3>
			<span class="menda-badge menda-badge--<?php echo get_option( MENDA_ADS_PREFIX . '_interstitial_enabled' ) ? 'active' : 'inactive'; ?>">
				<?php echo get_option( MENDA_ADS_PREFIX . '_interstitial_enabled' ) ? esc_html__( 'Active', 'menda-ads' ) : esc_html__( 'Inactive', 'menda-ads' ); ?>
			</span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=menda-ads-settings#interstitial' ) ); ?>"><?php esc_html_e( 'Configure', 'menda-ads' ); ?></a>
		</div>
		<div class="menda-status-card">
			<h3><?php esc_html_e( 'Sticky Video', 'menda-ads' ); ?></h3>
			<span class="menda-badge menda-badge--<?php echo get_option( MENDA_ADS_PREFIX . '_sticky_video_enabled' ) ? 'active' : 'inactive'; ?>">
				<?php echo get_option( MENDA_ADS_PREFIX . '_sticky_video_enabled' ) ? esc_html__( 'Active', 'menda-ads' ) : esc_html__( 'Inactive', 'menda-ads' ); ?>
			</span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=menda-ads-settings#sticky-video' ) ); ?>"><?php esc_html_e( 'Configure', 'menda-ads' ); ?></a>
		</div>
		<div class="menda-status-card">
			<h3><?php esc_html_e( 'Website Skin', 'menda-ads' ); ?></h3>
			<span class="menda-badge menda-badge--<?php echo get_option( MENDA_ADS_PREFIX . '_skin_enabled' ) ? 'active' : 'inactive'; ?>">
				<?php echo get_option( MENDA_ADS_PREFIX . '_skin_enabled' ) ? esc_html__( 'Active', 'menda-ads' ) : esc_html__( 'Inactive', 'menda-ads' ); ?>
			</span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=menda-ads-settings#skin' ) ); ?>"><?php esc_html_e( 'Configure', 'menda-ads' ); ?></a>
		</div>
	</div>

	<!-- Top Ads Table -->
	<?php if ( ! empty( $summary ) ) :
		$ad_ids = array_map( 'intval', array_slice( array_column( $summary, 'ad_id' ), 0, 10 ) );
		_prime_post_caches( $ad_ids, true, false );
	?>
	<div class="menda-card">
		<h2><?php esc_html_e( 'Top Performing Ads (Last 30 Days)', 'menda-ads' ); ?></h2>
		<table class="widefat striped menda-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ad', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Type', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Views', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Clicks', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'CTR', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'menda-ads' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_slice( $summary, 0, 10 ) as $row ) :
					$ad_title = get_the_title( (int) $row['ad_id'] ) ?: '#' . $row['ad_id'];
					$ad_type  = get_post_meta( (int) $row['ad_id'], '_menda_ad_type', true );
					$ctr_class = (float) $row['ctr'] >= 2 ? 'menda-ctr--good' : ( (float) $row['ctr'] >= 0.5 ? 'menda-ctr--avg' : 'menda-ctr--low' );
				?>
					<tr>
						<td><strong><?php echo esc_html( $ad_title ); ?></strong></td>
						<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $ad_type ) ) ); ?></td>
						<td><?php echo number_format_i18n( (int) $row['views'] ); ?></td>
						<td><?php echo number_format_i18n( (int) $row['clicks'] ); ?></td>
						<td><span class="menda-ctr <?php echo esc_attr( $ctr_class ); ?>"><?php echo esc_html( $row['ctr'] ); ?>%</span></td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( (int) $row['ad_id'] ) ); ?>"><?php esc_html_e( 'Edit', 'menda-ads' ); ?></a>
							| <a href="<?php echo esc_url( admin_url( 'admin.php?page=menda-ads-analytics&ad_id=' . $row['ad_id'] ) ); ?>"><?php esc_html_e( 'Details', 'menda-ads' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php else : ?>
	<div class="menda-card menda-empty">
		<p><?php esc_html_e( 'No ad data yet. Create your first ad to get started!', 'menda-ads' ); ?></p>
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=menda_ad' ) ); ?>" class="button button-primary">
			<?php esc_html_e( '+ Add New Ad', 'menda-ads' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<!-- Ad Scheduling Calendar -->
	<?php
	$today = current_datetime(); // C12: Uses WP timezone — correct.
	$year  = (int) $today->format('Y');
	$month = (int) $today->format('n');
	$month_days = (int) $today->format('t');
	$month_name = date_i18n( 'F Y', strtotime( $today->format('Y-m-d') ) );

	// C12: Build the first-day timestamp using WP timezone, not server timezone.
	$timezone       = wp_timezone();
	$first_day_obj  = new \DateTime( "{$year}-{$month}-01 00:00:00", $timezone );
	$start_wday     = (int) $first_day_obj->format('w'); // 0=Sun, 6=Sat — in WP timezone.

	// P2: Limit calendar query + prime meta cache to avoid N+1 per ad per day.
	$calendar_ads = get_posts( [
		'post_type'      => 'menda_ad',
		'post_status'    => 'publish',
		'posts_per_page' => apply_filters( 'menda_ads_max_calendar_query', 200 ),
	] );
	// P2: Prime meta cache for all calendar ads in a single query.
	if ( ! empty( $calendar_ads ) ) {
		update_postmeta_cache( wp_list_pluck( $calendar_ads, 'ID' ) );
	}
	// Pre-fetch meta values for all ads once (avoids O(ads) meta queries per day).
	$cal_meta = [];
	foreach ( $calendar_ads as $cad ) {
		$cal_meta[ $cad->ID ] = [
			'start' => get_post_meta( $cad->ID, '_menda_ad_start', true ),
			'end'   => get_post_meta( $cad->ID, '_menda_ad_end', true ),
			'type'  => get_post_meta( $cad->ID, '_menda_ad_type', true ) ?: 'banner',
			'title' => get_the_title( $cad ),
		];
	}
	?>
	<div class="menda-calendar-card">
		<div class="menda-calendar-header">
			<h2>📅 <?php printf( esc_html__( 'Schedule Calendar: %s', 'menda-ads' ), esc_html( $month_name ) ); ?></h2>
		</div>
		<div class="menda-cal-grid">
			<div class="menda-cal-header-day"><?php esc_html_e( 'Sun', 'menda-ads' ); ?></div>
			<div class="menda-cal-header-day"><?php esc_html_e( 'Mon', 'menda-ads' ); ?></div>
			<div class="menda-cal-header-day"><?php esc_html_e( 'Tue', 'menda-ads' ); ?></div>
			<div class="menda-cal-header-day"><?php esc_html_e( 'Wed', 'menda-ads' ); ?></div>
			<div class="menda-cal-header-day"><?php esc_html_e( 'Thu', 'menda-ads' ); ?></div>
			<div class="menda-cal-header-day"><?php esc_html_e( 'Fri', 'menda-ads' ); ?></div>
			<div class="menda-cal-header-day"><?php esc_html_e( 'Sat', 'menda-ads' ); ?></div>

			<?php for ( $i = 0; $i < $start_wday; $i++ ) : ?>
				<div class="menda-cal-day menda-cal-day--empty"></div>
			<?php endfor; ?>

			<?php
			for ( $d = 1; $d <= $month_days; $d++ ) :
				// C12: Build day boundary using WP timezone.
				$day_start = new \DateTime( sprintf( '%04d-%02d-%02d 00:00:00', $year, $month, $d ), $timezone );
				$day_end   = new \DateTime( sprintf( '%04d-%02d-%02d 23:59:59', $year, $month, $d ), $timezone );

				$running_ads = [];
				foreach ( $calendar_ads as $ad ) {
					$meta     = $cal_meta[ $ad->ID ];
					$start_dt = $meta['start'] ? new \DateTime( $meta['start'], $timezone ) : null;
					$end_dt   = $meta['end']   ? new \DateTime( $meta['end'],   $timezone ) : null;

					$is_running = true;
					if ( $start_dt && $day_end   < $start_dt ) $is_running = false;
					if ( $end_dt   && $day_start > $end_dt   ) $is_running = false;

					if ( $is_running ) $running_ads[] = $meta;
				}
				?>
				<div class="menda-cal-day">
					<span class="menda-cal-day-num"><?php echo esc_html( $d ); ?></span>
					<div class="menda-cal-day-ads">
						<?php foreach ( $running_ads as $rad_meta ) : ?>
							<div class="menda-cal-ad-pill type-<?php echo esc_attr( $rad_meta['type'] ); ?>" title="<?php echo esc_attr( $rad_meta['title'] ); ?>">
								<?php echo esc_html( wp_trim_words( $rad_meta['title'], 2, '' ) ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endfor; ?>
		</div>
	</div>

	<!-- Quick Links -->
	<div class="menda-quick-links">
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=menda_ad' ) ); ?>" class="button button-primary"><?php esc_html_e( '+ New Ad', 'menda-ads' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=menda_sponsored' ) ); ?>" class="button"><?php esc_html_e( '+ Sponsored Article', 'menda-ads' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=menda-ads-analytics' ) ); ?>" class="button"><?php esc_html_e( 'Full Analytics', 'menda-ads' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=menda-ads-settings' ) ); ?>" class="button"><?php esc_html_e( 'Settings', 'menda-ads' ); ?></a>
	</div>
</div>
