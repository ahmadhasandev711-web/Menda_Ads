<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap menda-admin-wrap">
	<h1><?php esc_html_e( 'Menda Ads Settings', 'menda-ads' ); ?></h1>

	<?php settings_errors( 'menda_ads_settings' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'menda_ads_settings' ); ?>

		<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<div class="menda-settings-section" id="access">
			<h2><?php esc_html_e( 'Who can manage ads', 'menda-ads' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="menda_access_level"><?php esc_html_e( 'Access level', 'menda-ads' ); ?></label></th>
					<td>
						<select id="menda_access_level" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_access_level' ); ?>">
							<option value="administrators" <?php selected( \Menda_Ads\Installer::get_access_level(), 'administrators' ); ?>>
								<?php esc_html_e( 'Administrators only', 'menda-ads' ); ?>
							</option>
							<option value="editors" <?php selected( \Menda_Ads\Installer::get_access_level(), 'editors' ); ?>>
								<?php esc_html_e( 'Administrators and Editors', 'menda-ads' ); ?>
							</option>
							<option value="authors" <?php selected( \Menda_Ads\Installer::get_access_level(), 'authors' ); ?>>
								<?php esc_html_e( 'Administrators, Editors, and Authors', 'menda-ads' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Administrators always have full access. Editors and Authors keep writing normal news posts either way. Only raise this if the ads/commercial team should use Menda Ads without a full Administrator account. Authors can paste ad HTML — keep this on Administrators unless that team is trusted.', 'menda-ads' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php endif; ?>

		<div class="menda-settings-section" id="currency">
			<h2><?php esc_html_e( 'Currency', 'menda-ads' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="menda_currency"><?php esc_html_e( 'Default currency', 'menda-ads' ); ?></label></th>
					<td>
						<select id="menda_currency" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_currency' ); ?>">
							<?php
							$current_currency = \Menda_Ads\I18n::site_currency();
							foreach ( \Menda_Ads\I18n::currencies() as $code => $meta ) :
								?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current_currency, $code ); ?>>
									<?php echo esc_html( $code . ' — ' . $meta['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Used in analytics and new ads. Each ad can still be billed in a different currency (USD, SAR, EGP, AED, …). Totals are not mixed across currencies.', 'menda-ads' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="menda-settings-section" id="inventory">
			<h2><?php esc_html_e( 'Automatic placements', 'menda-ads' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Assign banner ads to these placements. The plugin injects them on news articles without shortcodes. Gutenberg, Elementor, and widgets can still pick a placement from the list.', 'menda-ads' ); ?></p>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'In article', 'menda-ads' ); ?></th>
					<td>
						<label>
							<input type="hidden" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_insert_p2' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_insert_p2' ); ?>" value="1" <?php checked( 1, (int) get_option( MENDA_ADS_PREFIX . '_insert_p2', 1 ) ); ?>>
							<?php esc_html_e( 'After paragraph 2 (300×250)', 'menda-ads' ); ?>
						</label><br>
						<label>
							<input type="hidden" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_insert_p4' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_insert_p4' ); ?>" value="1" <?php checked( 1, (int) get_option( MENDA_ADS_PREFIX . '_insert_p4', 1 ) ); ?>>
							<?php esc_html_e( 'After paragraph 4 (300×250)', 'menda-ads' ); ?>
						</label><br>
						<label>
							<input type="hidden" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_insert_below' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_insert_below' ); ?>" value="1" <?php checked( 1, (int) get_option( MENDA_ADS_PREFIX . '_insert_below', 1 ) ); ?>>
							<?php esc_html_e( 'Below the article', 'menda-ads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Mobile sticky footer', 'menda-ads' ); ?></th>
					<td>
						<label>
							<input type="hidden" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_insert_mobile_sticky' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_insert_mobile_sticky' ); ?>" value="1" <?php checked( 1, (int) get_option( MENDA_ADS_PREFIX . '_insert_mobile_sticky', 1 ) ); ?>>
							<?php esc_html_e( '320×50 bar at the bottom on small screens (hidden on desktop via CSS, cache-safe)', 'menda-ads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Archives', 'menda-ads' ); ?></th>
					<td>
						<label>
							<input type="hidden" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_insert_archive' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_insert_archive' ); ?>" value="1" <?php checked( 1, (int) get_option( MENDA_ADS_PREFIX . '_insert_archive', 0 ) ); ?>>
							<?php esc_html_e( 'Between every 3rd post on home/category listings', 'menda-ads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Unknown visitor country', 'menda-ads' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_geo_unknown' ); ?>">
							<option value="hide" <?php selected( get_option( MENDA_ADS_PREFIX . '_geo_unknown', 'hide' ), 'hide' ); ?>><?php esc_html_e( 'Hide geo-targeted ads (recommended for billed campaigns)', 'menda-ads' ); ?></option>
							<option value="show" <?php selected( get_option( MENDA_ADS_PREFIX . '_geo_unknown', 'hide' ), 'show' ); ?>><?php esc_html_e( 'Show them anyway (no CDN country header)', 'menda-ads' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- ── INTERSTITIAL ──────────────────────────────── -->
		<div class="menda-settings-section" id="interstitial">
			<h2><?php esc_html_e( 'Interstitial Ads', 'menda-ads' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Enable', 'menda-ads' ); ?></th>
					<td>
						<label>
							<input type="hidden" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_interstitial_enabled' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_interstitial_enabled' ); ?>" value="1"
								<?php checked( 1, get_option( MENDA_ADS_PREFIX . '_interstitial_enabled' ) ); ?>>
							<?php esc_html_e( 'Show interstitial ads between pages', 'menda-ads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Skip homepage', 'menda-ads' ); ?></th>
					<td>
						<label>
							<input type="hidden" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_interstitial_skip_home' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_interstitial_skip_home' ); ?>" value="1"
								<?php checked( 1, (int) get_option( MENDA_ADS_PREFIX . '_interstitial_skip_home', 1 ) ); ?>>
							<?php esc_html_e( 'Never show the overlay on the front page', 'menda-ads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="interstitial_min_views"><?php esc_html_e( 'Minimum pageviews first', 'menda-ads' ); ?></label></th>
					<td>
						<input type="number" id="interstitial_min_views" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_interstitial_min_views' ); ?>"
							value="<?php echo esc_attr( get_option( MENDA_ADS_PREFIX . '_interstitial_min_views', 3 ) ); ?>" min="0" max="20" class="small-text">
						<p class="description"><?php esc_html_e( 'Show only after this many pages in the session. Default 3. Frequency is site-wide, not per creative.', 'menda-ads' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="interstitial_skip"><?php esc_html_e( 'Skip Delay (seconds)', 'menda-ads' ); ?></label></th>
					<td>
						<input type="number" id="interstitial_skip" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_interstitial_skip_delay' ); ?>"
							value="<?php echo esc_attr( get_option( MENDA_ADS_PREFIX . '_interstitial_skip_delay', 5 ) ); ?>" min="0" max="30" class="small-text">
					</td>
				</tr>
				<tr>
					<th><label for="interstitial_freq"><?php esc_html_e( 'Frequency', 'menda-ads' ); ?></label></th>
					<td>
						<select id="interstitial_freq" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_interstitial_frequency' ); ?>">
							<option value="once_per_session" <?php selected( get_option( MENDA_ADS_PREFIX . '_interstitial_frequency' ), 'once_per_session' ); ?>>
								<?php esc_html_e( 'Once per session', 'menda-ads' ); ?>
							</option>
							<option value="once_per_day" <?php selected( get_option( MENDA_ADS_PREFIX . '_interstitial_frequency' ), 'once_per_day' ); ?>>
								<?php esc_html_e( 'Once per day', 'menda-ads' ); ?>
							</option>
							<option value="every_page" <?php selected( get_option( MENDA_ADS_PREFIX . '_interstitial_frequency' ), 'every_page' ); ?>>
								<?php esc_html_e( 'Every page load', 'menda-ads' ); ?>
							</option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- ── STICKY VIDEO ──────────────────────────────── -->
		<div class="menda-settings-section" id="sticky-video">
			<h2><?php esc_html_e( 'Sticky Video Ad', 'menda-ads' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Enable', 'menda-ads' ); ?></th>
					<td>
						<label>
							<input type="hidden" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_sticky_video_enabled' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_sticky_video_enabled' ); ?>" value="1"
								<?php checked( 1, get_option( MENDA_ADS_PREFIX . '_sticky_video_enabled' ) ); ?>>
							<?php esc_html_e( 'Show sticky video ad on all pages', 'menda-ads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="sticky_pos"><?php esc_html_e( 'Position', 'menda-ads' ); ?></label></th>
					<td>
						<select id="sticky_pos" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_sticky_video_position' ); ?>">
							<?php foreach ( [ 'bottom-right' => __( 'Bottom Right', 'menda-ads' ), 'bottom-left' => __( 'Bottom Left', 'menda-ads' ), 'top-right' => __( 'Top Right', 'menda-ads' ), 'top-left' => __( 'Top Left', 'menda-ads' ) ] as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( get_option( MENDA_ADS_PREFIX . '_sticky_video_position', 'bottom-right' ), $val ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- ── WEBSITE SKIN ──────────────────────────────── -->
		<div class="menda-settings-section" id="skin">
			<h2><?php esc_html_e( 'Website Skin / Takeover', 'menda-ads' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Two products on the ad: Side banners (shop, full width) or Page skin (news — brand color + optional wallpaper). These options are site defaults only.', 'menda-ads' ); ?></p>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Enable', 'menda-ads' ); ?></th>
					<td>
						<label>
							<input type="hidden" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_skin_enabled' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_skin_enabled' ); ?>" value="1"
								<?php checked( 1, get_option( MENDA_ADS_PREFIX . '_skin_enabled' ) ); ?>>
							<?php esc_html_e( 'Allow takeover ads on this site', 'menda-ads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="skin_width"><?php esc_html_e( 'Default content width (px)', 'menda-ads' ); ?></label></th>
					<td><input type="number" id="skin_width" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_skin_content_width' ); ?>" value="<?php echo esc_attr( get_option( MENDA_ADS_PREFIX . '_skin_content_width', 1200 ) ); ?>" min="600" max="1920" class="small-text">
					<p class="description"><?php esc_html_e( 'Used when a takeover ad does not set its own content width. Typical news column: 1000–1200.', 'menda-ads' ); ?></p></td>
				</tr>
			<tr>
				<th><label for="skin_wrapper_selector"><?php esc_html_e( 'Content Wrapper Selector', 'menda-ads' ); ?></label></th>
				<td>
					<?php
					$manual = get_option( MENDA_ADS_PREFIX . '_skin_wrapper_selector', '' );
					?>
					<input type="text"
						id="skin_wrapper_selector"
						name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_skin_wrapper_selector' ); ?>"
						value="<?php echo esc_attr( $manual ); ?>"
						class="large-text"
						placeholder="#page">
					<p class="description">
						<?php esc_html_e( 'Leave blank to pad the whole page (recommended). Only set a selector if a specific wrapper must receive the side padding instead of the html element. Allowed characters: letters, numbers, # . - _ > ,', 'menda-ads' ); ?>
					</p>
					<?php if ( $manual ) : ?>
					<p class="description"><?php esc_html_e( 'Active selector:', 'menda-ads' ); ?>
						<code><?php echo esc_html( $manual ); ?></code>
					</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="skin_header_selector"><?php esc_html_e( 'Full-width header selector', 'menda-ads' ); ?></label></th>
				<td>
					<input type="text"
						id="skin_header_selector"
						name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_skin_header_selector' ); ?>"
						value="<?php echo esc_attr( get_option( MENDA_ADS_PREFIX . '_skin_header_selector', '' ) ); ?>"
						class="large-text"
						placeholder="#masthead">
					<p class="description"><?php esc_html_e( 'Optional. Page skin keeps header/footer full width automatically (Youm7). Set this only if your theme header is not detected.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			</table>
		</div>

		<!-- ── REPORTS ───────────────────────────────────── -->
		<div class="menda-settings-section" id="reports">
			<h2><?php esc_html_e( 'Weekly Reports', 'menda-ads' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Enable', 'menda-ads' ); ?></th>
					<td>
						<label>
							<input type="hidden" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_weekly_report_enabled' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_weekly_report_enabled' ); ?>" value="1"
								<?php checked( 1, get_option( MENDA_ADS_PREFIX . '_weekly_report_enabled' ) ); ?>>
							<?php esc_html_e( 'Send weekly performance report every Monday', 'menda-ads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="report_email"><?php esc_html_e( 'Report Email', 'menda-ads' ); ?></label></th>
					<td>
						<input type="email" id="report_email" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_weekly_report_email' ); ?>"
							value="<?php echo esc_attr( get_option( MENDA_ADS_PREFIX . '_weekly_report_email', get_option( 'admin_email' ) ) ); ?>"
							class="regular-text">
					</td>
				</tr>
			</table>
		</div>

		<!-- ── AD BLOCKER ────────────────────────────────── -->
		<div class="menda-settings-section" id="adblocker">
			<h2><?php esc_html_e( 'Ad Blocker Detection', 'menda-ads' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Enable Detection', 'menda-ads' ); ?></th>
					<td>
						<label>
							<input type="hidden" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_adblock_detect' ); ?>" value="0">
							<input type="checkbox" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_adblock_detect' ); ?>" value="1"
								<?php checked( 1, get_option( MENDA_ADS_PREFIX . '_adblock_detect', 0 ) ); ?>>
							<?php esc_html_e( 'Detect ad blockers and replace blocked ads with a custom fallback message', 'menda-ads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="adblock_fallback"><?php esc_html_e( 'Fallback Message', 'menda-ads' ); ?></label></th>
					<td>
						<textarea id="adblock_fallback" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_adblock_fallback' ); ?>" rows="4" class="large-text"><?php echo esc_textarea( get_option( MENDA_ADS_PREFIX . '_adblock_fallback', __( 'Please consider supporting us by disabling your adblocker.', 'menda-ads' ) ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'This message will appear in place of any blocked banner ad elements.', 'menda-ads' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="menda-settings-section" id="privacy">
			<h2><?php esc_html_e( 'Data retention', 'menda-ads' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="log_retention_days"><?php esc_html_e( 'Analytics log retention (days)', 'menda-ads' ); ?></label></th>
					<td>
						<input type="number" id="log_retention_days" name="<?php echo esc_attr( MENDA_ADS_PREFIX . '_log_retention_days' ); ?>"
							value="<?php echo esc_attr( get_option( MENDA_ADS_PREFIX . '_log_retention_days', 90 ) ); ?>" min="30" max="730" class="small-text">
						<p class="description"><?php esc_html_e( 'Older tracking rows are deleted daily. Minimum 30, maximum 730.', 'menda-ads' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'Save Settings', 'menda-ads' ) ); ?>
	</form>
</div>
