<?php defined( 'ABSPATH' ) || exit;

$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';
$days       = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;

// SEC-07: Add nonce to CSV export URL to prevent CSRF attacks.
$filter_ad = isset( $_GET['ad_id'] ) ? absint( $_GET['ad_id'] ) : 0;
$export_url = wp_nonce_url(
	add_query_arg( [
		'page'       => 'menda-ads-analytics',
		'action'     => 'export_csv',
		'start_date' => $start_date,
		'end_date'   => $end_date,
		'days'       => $days,
		'ad_id'      => $filter_ad,
	], admin_url( 'admin.php' ) ),
	'menda_ads_csv_export'
);
?>
<div class="wrap menda-admin-wrap">
	<h1><?php esc_html_e( 'Ad Analytics', 'menda-ads' ); ?></h1>
	<?php if ( $filter_ad ) : ?>
		<p>
			<?php echo esc_html( sprintf( /* translators: %d: ad ID */ __( 'Filtered to unit #%d', 'menda-ads' ), $filter_ad ) ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=menda-ads-analytics' ) ); ?>"><?php esc_html_e( 'Show all', 'menda-ads' ); ?></a>
		</p>
	<?php endif; ?>

	<!-- Premium Filters & CSV Export -->
	<form method="get" class="menda-filter-form">
		<input type="hidden" name="page" value="menda-ads-analytics">
		
		<div class="menda-filter-fields">
			<label>
				<?php esc_html_e( 'Presets:', 'menda-ads' ); ?>
				<select name="days" onchange="document.getElementById('start_date').value=''; document.getElementById('end_date').value=''; this.form.submit()">
					<option value="7" <?php selected( $days, 7 ); ?>><?php esc_html_e( 'Last 7 days', 'menda-ads' ); ?></option>
					<option value="14" <?php selected( $days, 14 ); ?>><?php esc_html_e( 'Last 14 days', 'menda-ads' ); ?></option>
					<option value="30" <?php selected( $days, 30 ); ?>><?php esc_html_e( 'Last 30 days', 'menda-ads' ); ?></option>
					<option value="60" <?php selected( $days, 60 ); ?>><?php esc_html_e( 'Last 60 days', 'menda-ads' ); ?></option>
					<option value="90" <?php selected( $days, 90 ); ?>><?php esc_html_e( 'Last 90 days', 'menda-ads' ); ?></option>
				</select>
			</label>

			<span style="color:#dcdcde">|</span>

			<label>
				<?php esc_html_e( 'From:', 'menda-ads' ); ?>
				<input type="date" id="start_date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>">
			</label>
			<label>
				<?php esc_html_e( 'To:', 'menda-ads' ); ?>
				<input type="date" id="end_date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>">
			</label>

			<button type="submit" class="menda-btn"><?php esc_html_e( 'Filter', 'menda-ads' ); ?></button>
			<?php if ( $start_date || $end_date ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=menda-ads-analytics' ) ); ?>" class="menda-btn menda-btn--secondary"><?php esc_html_e( 'Reset', 'menda-ads' ); ?></a>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $summary ) ) : ?>
			<a href="<?php echo esc_url( $export_url ); ?>" class="menda-btn menda-btn--secondary">
				📥 <?php esc_html_e( 'Export CSV', 'menda-ads' ); ?>
			</a>
		<?php endif; ?>
	</form>

	<?php if ( empty( $summary ) ) : ?>
		<div class="menda-card menda-empty">
			<p><?php esc_html_e( 'No analytics data for the selected period.', 'menda-ads' ); ?></p>
		</div>
	<?php else :
		$ad_ids = array_map( 'intval', array_column( $summary, 'ad_id' ) );
		_prime_post_caches( $ad_ids, true, false );

		// Render SVG Chart if daily summary exists
		if ( ! empty( $daily_summary ) ) :
			$max_val = 10;
			foreach ( $daily_summary as $r ) {
				$max_val = max( $max_val, (int) $r['views'], (int) $r['clicks'] );
			}
			$max_val = ceil( $max_val * 1.15 ); // add 15% ceiling padding

			$count = count( $daily_summary );
			$width = 1000;
			$height = 300;
			$padding = 40;

			$chart_w = $width - ($padding * 2);
			$chart_h = $height - ($padding * 2);

			$points_views  = [];
			$points_clicks = [];
			$points_circles = [];
			$x_labels = [];

			foreach ( $daily_summary as $index => $r ) {
				$x = $padding + ( $count > 1 ? ( $index / ( $count - 1 ) ) * $chart_w : 0 );
				$y_v = ($height - $padding) - ( $max_val > 0 ? ( (int) $r['views'] / $max_val ) * $chart_h : 0 );
				$y_c = ($height - $padding) - ( $max_val > 0 ? ( (int) $r['clicks'] / $max_val ) * $chart_h : 0 );

				$points_views[]  = "{$x},{$y_v}";
				$points_clicks[] = "{$x},{$y_c}";

				$date_lbl = date_i18n( 'Y-m-d', strtotime( $r['day'] ) );
				
				$points_circles[] = sprintf(
					'<circle cx="%f" cy="%f" r="5" class="menda-chart-point menda-chart-point--views" data-date="%s" data-type="views" data-val="%d" />',
					$x, $y_v, esc_attr( $date_lbl ), (int) $r['views']
				);
				$points_circles[] = sprintf(
					'<circle cx="%f" cy="%f" r="5" class="menda-chart-point menda-chart-point--clicks" data-date="%s" data-type="clicks" data-val="%d" />',
					$x, $y_c, esc_attr( $date_lbl ), (int) $r['clicks']
				);

				// Step for rendering bottom X-labels dynamically
				$step = max( 1, ceil( $count / 7 ) );
				if ( 0 === $index || $index === $count - 1 || 0 === $index % $step ) {
					$x_labels[] = [
						'x'     => $x,
						'label' => date_i18n( 'M d', strtotime( $r['day'] ) ),
					];
				}
			}

			$views_polyline  = implode( ' ', $points_views );
			$clicks_polyline = implode( ' ', $points_clicks );

			// Area path coords (closed polygon back to x axis)
			$views_area = $views_polyline ? "M {$padding}," . ($height - $padding) . " L {$views_polyline} L " . ($padding + $chart_w) . "," . ($height - $padding) . " Z" : '';
			$clicks_area = $clicks_polyline ? "M {$padding}," . ($height - $padding) . " L {$clicks_polyline} L " . ($padding + $chart_w) . "," . ($height - $padding) . " Z" : '';
		?>
		<div class="menda-card menda-chart-card">
			<div class="menda-chart-legend">
				<div class="menda-chart-legend-item">
					<div class="menda-chart-legend-color menda-chart-legend-color--views"></div>
					<?php esc_html_e( 'Views', 'menda-ads' ); ?>
				</div>
				<div class="menda-chart-legend-item">
					<div class="menda-chart-legend-color menda-chart-legend-color--clicks"></div>
					<?php esc_html_e( 'Clicks', 'menda-ads' ); ?>
				</div>
			</div>

			<svg viewBox="0 0 1000 300" class="menda-chart-svg">
				<defs>
					<linearGradient id="menda-views-gradient" x1="0" y1="0" x2="0" y2="1">
						<stop offset="0%" stop-color="#2271b1" stop-opacity="1"/>
						<stop offset="100%" stop-color="#2271b1" stop-opacity="0"/>
					</linearGradient>
					<linearGradient id="menda-clicks-gradient" x1="0" y1="0" x2="0" y2="1">
						<stop offset="0%" stop-color="#10b981" stop-opacity="1"/>
						<stop offset="100%" stop-color="#10b981" stop-opacity="0"/>
					</linearGradient>
				</defs>

				<!-- Horizontal Grid Lines -->
				<?php for ( $i = 0; $i <= 4; $i++ ) :
					$g_val = round( ($max_val / 4) * $i );
					$y_pos = ($height - $padding) - ( $max_val > 0 ? ($g_val / $max_val) * $chart_h : 0 );
				?>
					<line x1="<?php echo esc_attr( $padding ); ?>" y1="<?php echo esc_attr( $y_pos ); ?>" x2="<?php echo esc_attr( $width - $padding ); ?>" y2="<?php echo esc_attr( $y_pos ); ?>" class="menda-chart-grid-line" />
					<text x="<?php echo esc_attr( $padding - 10 ); ?>" y="<?php echo esc_attr( $y_pos + 4 ); ?>" class="menda-chart-label menda-chart-label--y"><?php echo esc_html( $g_val ); ?></text>
				<?php endfor; ?>

				<!-- Area Fills (under lines) -->
				<?php if ( $views_area ) : ?>
					<path d="<?php echo esc_attr( $views_area ); ?>" class="menda-chart-views-area" />
				<?php endif; ?>
				<?php if ( $clicks_area ) : ?>
					<path d="<?php echo esc_attr( $clicks_area ); ?>" class="menda-chart-clicks-area" />
				<?php endif; ?>

				<!-- Value Lines -->
				<?php if ( $views_polyline ) : ?>
					<polyline points="<?php echo esc_attr( $views_polyline ); ?>" class="menda-chart-views-line" />
				<?php endif; ?>
				<?php if ( $clicks_polyline ) : ?>
					<polyline points="<?php echo esc_attr( $clicks_polyline ); ?>" class="menda-chart-clicks-line" />
				<?php endif; ?>

				<!-- Data points -->
				<?php echo implode( "\n", $points_circles ); ?>

				<!-- X axis Labels -->
				<?php foreach ( $x_labels as $lbl ) : ?>
					<text x="<?php echo esc_attr( $lbl['x'] ); ?>" y="<?php echo esc_attr( $height - $padding + 20 ); ?>" class="menda-chart-label"><?php echo esc_html( $lbl['label'] ); ?></text>
					<line x1="<?php echo esc_attr( $lbl['x'] ); ?>" y1="<?php echo esc_attr( $height - $padding ); ?>" x2="<?php echo esc_attr( $lbl['x'] ); ?>" y2="<?php echo esc_attr( $height - $padding + 5 ); ?>" stroke="#8c8f94" stroke-width="1" />
				<?php endforeach; ?>
			</svg>

			<!-- Custom Floating Tooltip -->
			<div id="menda-chart-tooltip" class="menda-chart-tooltip"></div>
		</div>

		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			var tooltip = document.getElementById( 'menda-chart-tooltip' );
			var points  = document.querySelectorAll( '.menda-chart-point' );

			points.forEach( function ( p ) {
				p.addEventListener( 'mouseenter', function () {
					var date  = p.getAttribute( 'data-date' );
					var type  = p.getAttribute( 'data-type' );
					var val   = p.getAttribute( 'data-val' );
					var label = type === 'views' ? '<?php echo esc_js( __( 'Views', 'menda-ads' ) ); ?>' : '<?php echo esc_js( __( 'Clicks', 'menda-ads' ) ); ?>';
					
					tooltip.innerHTML = '<strong>' + date + '</strong><br>' + label + ': ' + val;
					tooltip.style.display = 'block';
				} );

				p.addEventListener( 'mousemove', function ( e ) {
					var container = p.closest( '.menda-chart-card' );
					var cRect     = container.getBoundingClientRect();
					
					tooltip.style.left = ( e.clientX - cRect.left + 15 ) + 'px';
					tooltip.style.top  = ( e.clientY - cRect.top - 40 ) + 'px';
				} );

				p.addEventListener( 'mouseleave', function () {
					tooltip.style.display = 'none';
				} );
			} );
		} );
		</script>
		<?php endif; ?>

	<div class="menda-card">
		<table class="widefat striped menda-table" id="menda-analytics-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ad', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Type', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Status', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Pricing Model', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Views', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Clicks', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Blocked', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Block Rate', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'CTR', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Est. Revenue', 'menda-ads' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'menda-ads' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$indexed_summary = [];
				$revenue_by_currency = [];
				foreach ( $summary as $row ) {
					$indexed_summary[ (int) $row['ad_id'] ] = $row;
				}

				$grouped_summary = [];
				$variants_temp = [];

				$ad_ids = array_keys( $indexed_summary );
				_prime_post_caches( $ad_ids, true, false );

				foreach ( $indexed_summary as $ad_id => $row ) {
					$parent_id = (int) get_post_meta( $ad_id, '_menda_ad_parent_id', true );
					if ( $parent_id > 0 ) {
						$variants_temp[ $parent_id ][] = $row;
					} else {
						$grouped_summary[ $ad_id ] = [
							'parent'   => $row,
							'variants' => [],
						];
					}
				}

				foreach ( $variants_temp as $parent_id => $vars ) {
					if ( isset( $grouped_summary[ $parent_id ] ) ) {
						$grouped_summary[ $parent_id ]['variants'] = $vars;
					} else {
						$grouped_summary[ $parent_id ] = [
							'parent' => [
								'ad_id'        => $parent_id,
								'views'        => 0,
								'clicks'       => 0,
								'adblocks'     => 0,
								'ctr'          => 0,
								'adblock_rate' => 0,
							],
							'variants' => $vars,
						];
					}
				}

				$render_row = function ( array $row, bool $is_variant ) use ( &$revenue_by_currency ) {
					$ad_id     = (int) $row['ad_id'];
					$ad        = get_post( $ad_id );
					$ad_title  = $ad ? get_the_title( $ad ) : '#' . $ad_id;
					$ad_type   = get_post_meta( $ad_id, '_menda_ad_type', true );
					$status    = $ad ? $ad->post_status : 'unknown';
					$ctr_class = (float) $row['ctr'] >= 2 ? 'menda-ctr--good' : ( (float) $row['ctr'] >= 0.5 ? 'menda-ctr--avg' : 'menda-ctr--low' );

					$pricing_model = get_post_meta( $ad_id, '_menda_ad_pricing_model', true ) ?: 'flat';
					$pricing_rate  = (float) ( get_post_meta( $ad_id, '_menda_ad_pricing_rate', true ) ?: '0' );

					$views  = (int) ( $row['views'] ?? 0 );
					$clicks = (int) ( $row['clicks'] ?? 0 );

					$revenue = 0.00;
					if ( 'flat' === $pricing_model ) {
						$revenue = $pricing_rate;
					} elseif ( 'cpc' === $pricing_model ) {
						$revenue = $clicks * $pricing_rate;
					} elseif ( 'cpm' === $pricing_model ) {
						$revenue = ( $views / 1000 ) * $pricing_rate;
					}

					$currency = \Menda_Ads\I18n::ad_currency( $ad_id );
					$revenue_by_currency[ $currency ] = ( $revenue_by_currency[ $currency ] ?? 0 ) + $revenue;
					?>
					<tr class="<?php echo $is_variant ? 'menda-variant-row' : ''; ?>" style="<?php echo $is_variant ? 'background: #f8fafc;' : ''; ?>">
						<td>
							<?php if ( $is_variant ) : ?>
								<span style="color:#94a3b8; margin-right:8px; margin-left:16px;">—</span>
								<span style="font-weight: 500; font-size: 0.9em;"><?php echo esc_html( $ad_title ); ?></span>
								<span class="menda-badge" style="background:#f1f5f9; color:#475569; font-size:10px; padding: 2px 6px;"><?php esc_html_e( 'Variant', 'menda-ads' ); ?></span>
							<?php else : ?>
								<strong><?php echo esc_html( $ad_title ); ?></strong>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $ad_type ) ) ); ?></td>
						<td>
							<span class="menda-badge menda-badge--<?php echo 'publish' === $status ? 'active' : 'inactive'; ?>">
								<?php echo 'publish' === $status ? esc_html__( 'Active', 'menda-ads' ) : esc_html( $status ); ?>
							</span>
						</td>
						<td>
							<span style="font-size: 12px; font-weight: 500;">
								<?php
								$rate_label = \Menda_Ads\I18n::format_ad_money( $pricing_rate, $ad_id );
								if ( 'flat' === $pricing_model ) {
									printf( esc_html__( 'Flat (%s)', 'menda-ads' ), esc_html( $rate_label ) );
								} elseif ( 'cpc' === $pricing_model ) {
									printf( esc_html__( 'CPC (%s)', 'menda-ads' ), esc_html( $rate_label ) );
								} elseif ( 'cpm' === $pricing_model ) {
									printf( esc_html__( 'CPM (%s)', 'menda-ads' ), esc_html( $rate_label ) );
								}
								?>
							</span>
						</td>
						<td><?php echo number_format_i18n( $views ); ?></td>
						<td><?php echo number_format_i18n( $clicks ); ?></td>
						<td><?php echo number_format_i18n( (int) ( $row['adblocks'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( $row['adblock_rate'] ?? 0 ); ?>%</td>
						<td><span class="menda-ctr <?php echo esc_attr( $ctr_class ); ?>"><?php echo esc_html( $row['ctr'] ); ?>%</span></td>
						<td><strong><?php echo esc_html( \Menda_Ads\I18n::format_ad_money( $revenue, $ad_id ) ); ?></strong></td>
						<td>
							<?php if ( $ad ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $ad_id ) ); ?>"><?php esc_html_e( 'Edit', 'menda-ads' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
					<?php
				};

				foreach ( $grouped_summary as $parent_id => $group ) {
					$render_row( $group['parent'], false );
					foreach ( $group['variants'] as $var_row ) {
						$render_row( $var_row, true );
					}
				}
				?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="4"><?php esc_html_e( 'Totals', 'menda-ads' ); ?></th>
					<th><?php echo number_format_i18n( (int) array_sum( array_column( $summary, 'views' ) ) ); ?></th>
					<th><?php echo number_format_i18n( (int) array_sum( array_column( $summary, 'clicks' ) ) ); ?></th>
					<th><?php echo number_format_i18n( (int) array_sum( array_column( $summary, 'adblocks' ) ) ); ?></th>
					<th>
						<?php
						$tv  = array_sum( array_column( $summary, 'views' ) );
						$tab = array_sum( array_column( $summary, 'adblocks' ) );
						echo esc_html( ($tv + $tab) > 0 ? round( $tab / ($tv + $tab) * 100, 2 ) . '%' : '0%' );
						?>
					</th>
					<th>
						<?php
						$tv = array_sum( array_column( $summary, 'views' ) );
						$tc = array_sum( array_column( $summary, 'clicks' ) );
						echo esc_html( $tv > 0 ? round( $tc / $tv * 100, 2 ) . '%' : '0%' );
						?>
					</th>
					<th><?php
					if ( empty( $revenue_by_currency ) ) {
						echo esc_html( \Menda_Ads\I18n::format_money( 0 ) );
					} else {
						$parts = [];
						foreach ( $revenue_by_currency as $code => $sum ) {
							$parts[] = \Menda_Ads\I18n::format_money( (float) $sum, (string) $code );
						}
						echo esc_html( implode( ' · ', $parts ) );
					}
					?></th>
					<th></th>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php endif; ?>
</div>
