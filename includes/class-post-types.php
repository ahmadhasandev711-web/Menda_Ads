<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Registers custom post types:
 *  - menda_ad            : All ad units (banner, interstitial, sticky-video, skin).
 *  - menda_sponsored     : Sponsored articles shown as regular posts with a badge.
 */
final class Post_Types {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'init', [ $this, 'register_ad_cpt' ] );
		add_action( 'init', [ $this, 'register_sponsored_cpt' ] );
		add_action( 'init', [ $this, 'register_taxonomies' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'add_meta_boxes_menda_ad', [ $this, 'remove_unused_boxes' ] );
		add_action( 'save_post_menda_ad',        [ $this, 'save_ad_meta' ], 10, 2 );
		add_action( 'save_post_menda_sponsored',  [ $this, 'save_sponsored_meta' ], 10, 2 );
		add_filter( 'manage_menda_ad_posts_columns', [ $this, 'ad_columns' ] );
		add_action( 'manage_menda_ad_posts_custom_column', [ $this, 'ad_column_content' ], 10, 2 );
		add_filter( 'the_content', [ $this, 'prepend_sponsored_badge' ] );
	}

	// -------------------------------------------------------------------------
	// CPT Registration
	// -------------------------------------------------------------------------

	public function register_ad_cpt(): void {
		register_post_type( 'menda_ad', [
			'labels'              => [
				'name'               => __( 'Ads', 'menda-ads' ),
				'singular_name'      => __( 'Ad', 'menda-ads' ),
				'add_new_item'       => __( 'Add New Ad', 'menda-ads' ),
				'edit_item'          => __( 'Edit Ad', 'menda-ads' ),
				'search_items'       => __( 'Search Ads', 'menda-ads' ),
				'not_found'          => __( 'No ads found.', 'menda-ads' ),
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => true,
			'capability_type'     => 'menda_ad',
			'map_meta_cap'        => false,
			'capabilities'        => [
				'edit_post'              => MENDA_ADS_CAP,
				'read_post'              => MENDA_ADS_CAP,
				'delete_post'            => MENDA_ADS_CAP,
				'edit_posts'             => MENDA_ADS_CAP,
				'edit_others_posts'      => MENDA_ADS_CAP,
				'delete_posts'           => MENDA_ADS_CAP,
				'publish_posts'          => MENDA_ADS_CAP,
				'read_private_posts'     => MENDA_ADS_CAP,
				'create_posts'           => MENDA_ADS_CAP,
				'delete_private_posts'   => MENDA_ADS_CAP,
				'delete_published_posts' => MENDA_ADS_CAP,
				'delete_others_posts'    => MENDA_ADS_CAP,
				'edit_private_posts'     => MENDA_ADS_CAP,
				'edit_published_posts'   => MENDA_ADS_CAP,
			],
			'supports'            => [ 'title' ],
			'rewrite'             => false,
		] );
	}

	public function register_sponsored_cpt(): void {
		register_post_type( 'menda_sponsored', [
			'labels'              => [
				'name'               => __( 'Sponsored Articles', 'menda-ads' ),
				'singular_name'      => __( 'Sponsored Article', 'menda-ads' ),
				'add_new_item'       => __( 'Add Sponsored Article', 'menda-ads' ),
				'edit_item'          => __( 'Edit Sponsored Article', 'menda-ads' ),
			],
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => true,
			'has_archive'         => false,
			'capability_type'     => 'menda_sponsored',
			'map_meta_cap'        => false,
			'capabilities'        => [
				'edit_post'              => MENDA_ADS_CAP,
				'read_post'              => 'read',
				'delete_post'            => MENDA_ADS_CAP,
				'edit_posts'             => MENDA_ADS_CAP,
				'edit_others_posts'      => MENDA_ADS_CAP,
				'delete_posts'           => MENDA_ADS_CAP,
				'publish_posts'          => MENDA_ADS_CAP,
				'read_private_posts'     => MENDA_ADS_CAP,
				'create_posts'           => MENDA_ADS_CAP,
				'delete_private_posts'   => MENDA_ADS_CAP,
				'delete_published_posts' => MENDA_ADS_CAP,
				'delete_others_posts'    => MENDA_ADS_CAP,
				'edit_private_posts'     => MENDA_ADS_CAP,
				'edit_published_posts'   => MENDA_ADS_CAP,
			],
			'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'custom-fields' ],
			'rewrite'             => [ 'slug' => 'sponsored' ],
			'taxonomies'          => [ 'category', 'post_tag' ],
		] );
	}

	public function register_taxonomies(): void {
		// Ad placement taxonomy (e.g. "homepage-top", "article-mid").
		register_taxonomy( 'menda_ad_placement', 'menda_ad', [
			'labels'            => [
				'name'              => __( 'Placements', 'menda-ads' ),
				'singular_name'     => __( 'Placement', 'menda-ads' ),
				'add_new_item'      => __( 'Add Placement', 'menda-ads' ),
				'not_found'         => __( 'No placements. Add one like in-article-300x250 — banners need a slot or they never render.', 'menda-ads' ),
			],
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => false,
			'show_in_rest'      => true,
			'hierarchical'      => false,
			'rewrite'           => false,
			'capabilities'      => [
				'manage_terms' => MENDA_ADS_CAP,
				'edit_terms'   => MENDA_ADS_CAP,
				'delete_terms' => MENDA_ADS_CAP,
				'assign_terms' => MENDA_ADS_CAP,
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Meta Boxes
	// -------------------------------------------------------------------------

	public function remove_unused_boxes(): void {
		remove_meta_box( 'postimagediv', 'menda_ad', 'side' );
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id ) {
			$type = (string) get_post_meta( $post_id, '_menda_ad_type', true ) ?: 'banner';
			if ( 'banner' !== $type ) {
				remove_meta_box( 'tagsdiv-menda_ad_placement', 'menda_ad', 'side' );
			}
		}
	}

	public function add_meta_boxes(): void {
		add_meta_box(
			'menda_ad_settings',
			__( 'Ad Settings', 'menda-ads' ),
			[ $this, 'render_ad_meta_box' ],
			'menda_ad',
			'normal',
			'high'
		);

		add_meta_box(
			'menda_sponsored_settings',
			__( 'Sponsor Info', 'menda-ads' ),
			[ $this, 'render_sponsored_meta_box' ],
			'menda_sponsored',
			'side',
			'high'
		);
	}

	public function render_ad_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'menda_ad_meta_nonce', 'menda_ad_meta_nonce' );
		$type       = get_post_meta( $post->ID, '_menda_ad_type', true ) ?: 'banner';
		$link       = get_post_meta( $post->ID, '_menda_ad_link', true );
		$start      = get_post_meta( $post->ID, '_menda_ad_start', true );
		$end        = get_post_meta( $post->ID, '_menda_ad_end', true );
		$video_url  = get_post_meta( $post->ID, '_menda_ad_video_url', true );
		$image_id   = get_post_meta( $post->ID, '_menda_ad_image_id', true );
		$custom_code= get_post_meta( $post->ID, '_menda_ad_custom_code', true );
		$mobile     = get_post_meta( $post->ID, '_menda_ad_show_mobile', true );
		$desktop    = get_post_meta( $post->ID, '_menda_ad_show_desktop', true );
		if ( '' === $mobile ) {
			$mobile = '1';
		}
		if ( '' === $desktop ) {
			$desktop = '1';
		}

		$target_type    = get_post_meta( $post->ID, '_menda_ad_target_type', true ) ?: 'all';
		$target_ids     = get_post_meta( $post->ID, '_menda_ad_target_ids', true );
		$target_cats    = get_post_meta( $post->ID, '_menda_ad_target_cats', true );
		$impression_cap = get_post_meta( $post->ID, '_menda_ad_impression_cap', true ) ?: '0';

		$target_countries = get_post_meta( $post->ID, '_menda_ad_target_countries', true );
		$parent_id        = (int) get_post_meta( $post->ID, '_menda_ad_parent_id', true );
		$pricing_model    = get_post_meta( $post->ID, '_menda_ad_pricing_model', true ) ?: 'flat';
		// COMPAT-B08 + FEAT-A04: New targeting fields.
		$woo_pages        = get_post_meta( $post->ID, '_menda_ad_woo_pages', true ) ?: 'all';
		$target_taxonomy  = get_post_meta( $post->ID, '_menda_ad_target_taxonomy', true );
		$target_tax_terms = get_post_meta( $post->ID, '_menda_ad_target_tax_terms', true );
		$pricing_rate     = get_post_meta( $post->ID, '_menda_ad_pricing_rate', true ) ?: '0.00';
		$ad_currency      = (string) get_post_meta( $post->ID, '_menda_ad_currency', true );
		$ad_languages     = I18n::ad_languages( $post->ID );
		$ad_weight        = (int) get_post_meta( $post->ID, '_menda_ad_weight', true ) ?: 1;
		$selected_cats    = array_filter( array_map( 'trim', explode( ',', (string) $target_cats ) ) );
		$skin_left_id     = (int) get_post_meta( $post->ID, '_menda_ad_skin_left_id', true );
		$skin_right_id    = (int) get_post_meta( $post->ID, '_menda_ad_skin_right_id', true );
		$skin_top_id      = (int) get_post_meta( $post->ID, '_menda_ad_skin_top_id', true );
		$skin_mobile_id   = (int) get_post_meta( $post->ID, '_menda_ad_skin_mobile_id', true );
		$skin_left_link   = (string) get_post_meta( $post->ID, '_menda_ad_skin_left_link', true );
		$skin_right_link  = (string) get_post_meta( $post->ID, '_menda_ad_skin_right_link', true );
		$skin_top_link    = (string) get_post_meta( $post->ID, '_menda_ad_skin_top_link', true );
		$skin_mobile_link = (string) get_post_meta( $post->ID, '_menda_ad_skin_mobile_link', true );
		$skin_color       = (string) get_post_meta( $post->ID, '_menda_ad_skin_bg_color', true );
		$skin_width       = (int) get_post_meta( $post->ID, '_menda_ad_skin_content_width', true );
		$skin_z           = (int) get_post_meta( $post->ID, '_menda_ad_skin_z', true );
		$skin_min_vp      = (int) get_post_meta( $post->ID, '_menda_ad_skin_min_vp', true );
		$skin_fit         = (string) get_post_meta( $post->ID, '_menda_ad_skin_fit', true ) ?: 'cover';
		$skin_layout      = (string) get_post_meta( $post->ID, '_menda_ad_skin_layout', true ) ?: 'overlay';
		$skin_rail        = (int) get_post_meta( $post->ID, '_menda_ad_skin_rail', true );
		$skin_freq        = (string) get_post_meta( $post->ID, '_menda_ad_skin_frequency', true ) ?: 'every_page';
		$skin_mobile_mode = (string) get_post_meta( $post->ID, '_menda_ad_skin_mobile_mode', true ) ?: 'sticky';
		$skin_header_full = get_post_meta( $post->ID, '_menda_ad_skin_header_full', true );
		if ( '' === $skin_header_full ) {
			$skin_header_full = '1';
		}
		$preview_url      = '';
		if ( $post->ID && 'auto-draft' !== $post->post_status ) {
			$preview_url = wp_nonce_url(
				add_query_arg( 'menda_takeover_preview', $post->ID, home_url( '/' ) ),
				'menda_takeover_preview'
			);
		}

		$sibling_ads = get_posts( [
			'post_type'      => 'menda_ad',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'exclude'        => [ $post->ID ],
		] );

		// Default both to checked on new ads (no meta saved yet = empty string).
		if ( '' === $mobile )  $mobile  = '1';
		if ( '' === $desktop ) $desktop = '1';
		?>
		<nav class="menda-tabs" role="tablist">
			<button type="button" class="menda-tab is-active" data-tab="creative"><?php esc_html_e( 'Creative', 'menda-ads' ); ?></button>
			<button type="button" class="menda-tab" data-tab="delivery"><?php esc_html_e( 'Delivery', 'menda-ads' ); ?></button>
			<button type="button" class="menda-tab" data-tab="targeting"><?php esc_html_e( 'Targeting', 'menda-ads' ); ?></button>
			<button type="button" class="menda-tab" data-tab="billing"><?php esc_html_e( 'Billing', 'menda-ads' ); ?></button>
		</nav>
		<table class="form-table menda-meta-table" role="presentation">
			<tr class="menda-tab-row" data-tab="creative">
				<th><label for="menda_ad_type"><?php esc_html_e( 'Ad Type', 'menda-ads' ); ?></label></th>
				<td>
					<select id="menda_ad_type" name="menda_ad_type" class="regular-text">
						<option value="banner"       <?php selected( $type, 'banner' ); ?>><?php esc_html_e( 'Banner', 'menda-ads' ); ?></option>
						<option value="interstitial" <?php selected( $type, 'interstitial' ); ?>><?php esc_html_e( 'Interstitial (Between Pages)', 'menda-ads' ); ?></option>
						<option value="sticky_video" <?php selected( $type, 'sticky_video' ); ?>><?php esc_html_e( 'Sticky Video', 'menda-ads' ); ?></option>
						<option value="skin"         <?php selected( $type, 'skin' ); ?>><?php esc_html_e( 'Website Skin / Takeover', 'menda-ads' ); ?></option>
					</select>
					<p class="description menda-label-banner"><?php esc_html_e( 'A banner only appears inside a Placement (box on the right). Empty placement = nowhere. Skin/Interstitial/Sticky do not use that box.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-tab-row" data-tab="creative">
				<th><label for="menda_ad_link"><?php esc_html_e( 'Click destination URL', 'menda-ads' ); ?></label></th>
				<td>
					<input type="url" id="menda_ad_link" name="menda_ad_link" value="<?php echo esc_url( $link ); ?>" class="regular-text" placeholder="https://">
					<p class="description"><?php esc_html_e( 'Where the visitor goes after clicking the ad — not which pages show it. Site-wide display is the Targeting tab (Show Everywhere).', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-tab-row" data-tab="creative">
				<th><label for="menda_ad_image_id"><span class="menda-label-banner"><?php esc_html_e( 'Ad Image', 'menda-ads' ); ?></span><span class="menda-label-skin"><?php esc_html_e( 'Wallpaper (1920px+)', 'menda-ads' ); ?></span></label></th>
				<td>
					<?php $this->render_image_control( 'menda_ad_image_id', (int) $image_id ); ?>
					<p class="description menda-row-skin menda-skin-boxed-only"><?php esc_html_e( 'Optional. Brand color can be the whole campaign. Wallpaper sits in the gutters behind the news column.', 'menda-ads' ); ?></p>
					<p class="description menda-row-skin menda-skin-overlay-only"><?php esc_html_e( 'Ignored when Left/Right panels are set; otherwise cropped into the edge strips only.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-row-skin menda-tab-row" data-tab="creative" style="<?php echo 'skin' !== $type ? 'display:none' : ''; ?>">
				<th><?php esc_html_e( 'Left panel', 'menda-ads' ); ?> <span class="description menda-skin-boxed-only"><?php esc_html_e( '(optional on page skin)', 'menda-ads' ); ?></span></th>
				<td>
					<?php $this->render_image_control( 'menda_ad_skin_left_id', $skin_left_id ); ?>
					<p>
						<label for="menda_ad_skin_left_link"><?php esc_html_e( 'Left click URL', 'menda-ads' ); ?></label><br>
						<input type="url" id="menda_ad_skin_left_link" name="menda_ad_skin_left_link" value="<?php echo esc_url( $skin_left_link ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Same as destination URL', 'menda-ads' ); ?>">
					</p>
				</td>
			</tr>
			<tr class="menda-row-skin menda-tab-row" data-tab="creative" style="<?php echo 'skin' !== $type ? 'display:none' : ''; ?>">
				<th><?php esc_html_e( 'Right panel', 'menda-ads' ); ?> <span class="description menda-skin-boxed-only"><?php esc_html_e( '(optional on page skin)', 'menda-ads' ); ?></span></th>
				<td>
					<?php $this->render_image_control( 'menda_ad_skin_right_id', $skin_right_id ); ?>
					<p>
						<label for="menda_ad_skin_right_link"><?php esc_html_e( 'Right click URL', 'menda-ads' ); ?></label><br>
						<input type="url" id="menda_ad_skin_right_link" name="menda_ad_skin_right_link" value="<?php echo esc_url( $skin_right_link ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Same as destination URL', 'menda-ads' ); ?>">
					</p>
				</td>
			</tr>
			<tr class="menda-row-skin menda-tab-row" data-tab="creative" style="<?php echo 'skin' !== $type ? 'display:none' : ''; ?>">
				<th><?php esc_html_e( 'Top billboard (970×250)', 'menda-ads' ); ?></th>
				<td>
					<?php $this->render_image_control( 'menda_ad_skin_top_id', $skin_top_id ); ?>
					<p>
						<label for="menda_ad_skin_top_link"><?php esc_html_e( 'Billboard click URL', 'menda-ads' ); ?></label><br>
						<input type="url" id="menda_ad_skin_top_link" name="menda_ad_skin_top_link" value="<?php echo esc_url( $skin_top_link ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Same as destination URL', 'menda-ads' ); ?>">
					</p>
					<p class="description"><?php esc_html_e( 'News (Boxed) only. Overlay shops ignore this so the header is not covered.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-row-skin menda-tab-row" data-tab="creative" style="<?php echo 'skin' !== $type ? 'display:none' : ''; ?>">
				<th><?php esc_html_e( 'Mobile fallback', 'menda-ads' ); ?></th>
				<td>
					<select id="menda_ad_skin_mobile_mode" name="menda_ad_skin_mobile_mode">
						<option value="sticky" <?php selected( $skin_mobile_mode, 'sticky' ); ?>><?php esc_html_e( 'Sticky footer (recommended — most traffic is phones)', 'menda-ads' ); ?></option>
						<option value="banner" <?php selected( $skin_mobile_mode, 'banner' ); ?>><?php esc_html_e( 'Top banner', 'menda-ads' ); ?></option>
						<option value="none" <?php selected( $skin_mobile_mode, 'none' ); ?>><?php esc_html_e( 'Hide on phones (not recommended)', 'menda-ads' ); ?></option>
					</select>
					<div style="margin-top:8px;">
						<?php $this->render_image_control( 'menda_ad_skin_mobile_id', $skin_mobile_id ); ?>
					</div>
					<p>
						<label for="menda_ad_skin_mobile_link"><?php esc_html_e( 'Mobile click URL', 'menda-ads' ); ?></label><br>
						<input type="url" id="menda_ad_skin_mobile_link" name="menda_ad_skin_mobile_link" value="<?php echo esc_url( $skin_mobile_link ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Same as destination URL', 'menda-ads' ); ?>">
					</p>
					<p class="description"><?php esc_html_e( 'Optional image. If empty: side banners reuse the left/right panel; page skin uses a brand-color bar so phones are not left blank.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-row-skin menda-tab-row" data-tab="creative" style="<?php echo 'skin' !== $type ? 'display:none' : ''; ?>">
				<th><?php esc_html_e( 'Takeover look', 'menda-ads' ); ?></th>
				<td>
					<p>
						<label><?php esc_html_e( 'Layout', 'menda-ads' ); ?>
							<select name="menda_ad_skin_layout">
								<option value="overlay" <?php selected( $skin_layout, 'overlay' ); ?>><?php esc_html_e( 'Side banners — site full width (shops)', 'menda-ads' ); ?></option>
								<option value="boxed" <?php selected( $skin_layout, 'boxed' ); ?>><?php esc_html_e( 'Page skin — brand color + wallpaper (news sites)', 'menda-ads' ); ?></option>
							</select>
						</label>
					</p>
					<label><span class="menda-skin-boxed-only"><?php esc_html_e( 'Brand / page color', 'menda-ads' ); ?></span><span class="menda-skin-overlay-only"><?php esc_html_e( 'Rail color', 'menda-ads' ); ?></span>
						<input type="color" name="menda_ad_skin_bg_color" value="<?php echo esc_attr( $skin_color ?: '#ffffff' ); ?>">
					</label>
					&nbsp;
					<label><?php esc_html_e( 'Wallpaper fit', 'menda-ads' ); ?>
						<select name="menda_ad_skin_fit">
							<option value="cover" <?php selected( $skin_fit, 'cover' ); ?>><?php esc_html_e( 'Cover (recommended)', 'menda-ads' ); ?></option>
							<option value="contain" <?php selected( $skin_fit, 'contain' ); ?>><?php esc_html_e( 'Contain', 'menda-ads' ); ?></option>
							<option value="width" <?php selected( $skin_fit, 'width' ); ?>><?php esc_html_e( '100% width', 'menda-ads' ); ?></option>
							<option value="repeat" <?php selected( $skin_fit, 'repeat' ); ?>><?php esc_html_e( 'Tile / pattern', 'menda-ads' ); ?></option>
						</select>
					</label>
					<p class="menda-skin-boxed-only">
						<input type="hidden" name="menda_ad_skin_header_full" value="0">
						<label>
							<input type="checkbox" name="menda_ad_skin_header_full" value="1" <?php checked( $skin_header_full, '1' ); ?>>
							<?php esc_html_e( 'Keep header & footer full width (Youm7 style)', 'menda-ads' ); ?>
						</label>
					</p>
					<p>
						<label class="menda-skin-overlay-only"><?php esc_html_e( 'Edge width (px)', 'menda-ads' ); ?>
							<input type="number" name="menda_ad_skin_rail" value="<?php echo esc_attr( $skin_rail ? (string) $skin_rail : '160' ); ?>" min="80" max="400" class="small-text">
						</label>
						<label class="menda-skin-boxed-only"><?php esc_html_e( 'News column width (px)', 'menda-ads' ); ?>
							<input type="number" name="menda_ad_skin_content_width" value="<?php echo esc_attr( $skin_width ? (string) $skin_width : '' ); ?>" min="0" max="1920" class="small-text" placeholder="1200">
						</label>
						&nbsp;
						<label><?php esc_html_e( 'Min viewport (px)', 'menda-ads' ); ?>
							<input type="number" name="menda_ad_skin_min_vp" value="<?php echo esc_attr( $skin_min_vp ? (string) $skin_min_vp : '' ); ?>" min="0" max="2560" class="small-text" placeholder="782">
						</label>
						&nbsp;
						<label><?php esc_html_e( 'Z-index', 'menda-ads' ); ?>
							<input type="number" name="menda_ad_skin_z" value="<?php echo esc_attr( $skin_z ? (string) $skin_z : '' ); ?>" min="1" max="99999" class="small-text" placeholder="10050">
						</label>
					</p>
					<p class="description menda-skin-boxed-only"><?php esc_html_e( 'News takeover like Youm7: header stays full width, brand color fills the gutters beside the article column, optional wallpaper, clickable sides. Color alone is a valid campaign.', 'menda-ads' ); ?></p>
					<p class="description menda-skin-overlay-only"><?php esc_html_e( 'Shop side banners float on the edges. The site stays full width. Color only tints behind the panel images.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-row-video menda-tab-row" data-tab="creative" style="<?php echo $type !== 'sticky_video' ? 'display:none' : ''; ?>">
				<th><label for="menda_ad_video_url"><?php esc_html_e( 'Video URL (MP4, YouTube, or Vimeo)', 'menda-ads' ); ?></label></th>
				<td><input type="url" id="menda_ad_video_url" name="menda_ad_video_url" value="<?php echo esc_url( $video_url ); ?>" class="regular-text"></td>
			</tr>
			<tr class="menda-tab-row" data-tab="creative">
				<th><label for="menda_ad_custom_code"><?php esc_html_e( 'Custom HTML / Embed Code', 'menda-ads' ); ?></label></th>
				<td><textarea id="menda_ad_custom_code" name="menda_ad_custom_code" rows="4" class="large-text code"><?php echo esc_textarea( $custom_code ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Optional. Overrides the image if set.', 'menda-ads' ); ?></p></td>
			</tr>
			<tr class="menda-tab-row" data-tab="delivery">
				<th><?php esc_html_e( 'Schedule', 'menda-ads' ); ?></th>
				<td>
					<label><?php esc_html_e( 'Start:', 'menda-ads' ); ?>
						<input type="datetime-local" name="menda_ad_start" value="<?php echo esc_attr( $start ); ?>">
					</label>
					&nbsp;&nbsp;
					<label><?php esc_html_e( 'End:', 'menda-ads' ); ?>
						<input type="datetime-local" name="menda_ad_end" value="<?php echo esc_attr( $end ); ?>">
					</label>
					<p class="description"><?php esc_html_e( 'Leave blank to run indefinitely.', 'menda-ads' ); ?></p>
					<?php /* UX-A04: Show active WP timezone so admins enter times correctly. */ ?>
					<p class="description"><?php printf( /* translators: %s: timezone string */ esc_html__( 'Timezone: %s', 'menda-ads' ), '<strong>' . esc_html( wp_timezone_string() ) . '</strong>' ); ?></p>
				</td>
			</tr>
			<tr class="menda-tab-row" data-tab="targeting">
				<th><label for="menda_ad_target_type"><?php esc_html_e( 'Targeting', 'menda-ads' ); ?></label></th>
				<td>
				<select id="menda_ad_target_type" name="menda_ad_target_type" class="regular-text">
					<option value="all"         <?php selected( $target_type, 'all' ); ?>><?php esc_html_e( 'Show Everywhere', 'menda-ads' ); ?></option>
					<option value="pages"       <?php selected( $target_type, 'pages' ); ?>><?php esc_html_e( 'Specific Pages/Posts', 'menda-ads' ); ?></option>
					<option value="categories"  <?php selected( $target_type, 'categories' ); ?>><?php esc_html_e( 'Specific Categories', 'menda-ads' ); ?></option>
					<option value="taxonomy"    <?php selected( $target_type, 'taxonomy' ); ?>><?php esc_html_e( 'Custom Taxonomy', 'menda-ads' ); ?></option>
					<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<option value="woocommerce" <?php selected( $target_type, 'woocommerce' ); ?>><?php esc_html_e( 'WooCommerce Pages', 'menda-ads' ); ?></option>
					<?php endif; ?>
				</select>
					<p class="description"><?php esc_html_e( 'This is where the ad appears. Show Everywhere = every page on the site. Click destination URL does not limit this. Leave the Placements box empty for a takeover — that box is for banners.', 'menda-ads' ); ?></p>
			</td>
		</tr>
			<tr class="menda-tab-row" data-tab="targeting">
				<th><?php esc_html_e( 'Languages', 'menda-ads' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="menda_ad_languages[]" value="all" <?php checked( in_array( 'all', $ad_languages, true ) ); ?>>
						<?php esc_html_e( 'All languages', 'menda-ads' ); ?>
					</label>
					<?php foreach ( I18n::active_languages() as $slug => $label ) : ?>
						&nbsp;&nbsp;
						<label>
							<input type="checkbox" name="menda_ad_languages[]" value="<?php echo esc_attr( $slug ); ?>"
								<?php checked( ! in_array( 'all', $ad_languages, true ) && in_array( $slug, $ad_languages, true ) ); ?>>
							<?php echo esc_html( $label ); ?>
							<code><?php echo esc_html( $slug ); ?></code>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'On a bilingual site (Arabic + English) keep “All languages” for one creative on both, or tick only ar / only en for separate creatives. Works with WPML, Polylang, and TranslatePress.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-row-target-ids menda-tab-row" data-tab="targeting" style="<?php echo $target_type !== 'pages' ? 'display:none' : ''; ?>">
				<th><label for="menda_ad_target_ids"><?php esc_html_e( 'Target Page/Post IDs', 'menda-ads' ); ?></label></th>
				<td>
					<input type="text" id="menda_ad_target_ids" name="menda_ad_target_ids" value="<?php echo esc_attr( $target_ids ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Comma-separated list of post/page IDs (e.g. 2, 45, 108).', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-row-target-cats menda-tab-row" data-tab="targeting" style="<?php echo $target_type !== 'categories' ? 'display:none' : ''; ?>">
				<th><?php esc_html_e( 'Target Categories', 'menda-ads' ); ?></th>
				<td>
					<div class="menda-cat-grid">
						<?php
						$cats = get_categories( [ 'hide_empty' => false, 'number' => 80 ] );
						foreach ( $cats as $cat ) :
							$mark = in_array( $cat->slug, $selected_cats, true ) || in_array( (string) $cat->term_id, $selected_cats, true );
							?>
							<label>
								<input type="checkbox" name="menda_ad_target_cats[]" value="<?php echo esc_attr( $cat->slug ); ?>" <?php checked( $mark ); ?>>
								<?php echo esc_html( $cat->name ); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'News sections this ad may appear on. Leave all unchecked only if you switch targeting away from categories.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<?php /* COMPAT-B08: WooCommerce pages targeting */ ?>
			<tr class="menda-row-target-woo menda-tab-row" data-tab="targeting" style="<?php echo $target_type !== 'woocommerce' ? 'display:none' : ''; ?>">
				<th><label for="menda_ad_woo_pages"><?php esc_html_e( 'WooCommerce Pages', 'menda-ads' ); ?></label></th>
				<td>
					<select id="menda_ad_woo_pages" name="menda_ad_woo_pages" class="regular-text">
						<option value="all"      <?php selected( $woo_pages, 'all' ); ?>><?php esc_html_e( 'All WooCommerce Pages', 'menda-ads' ); ?></option>
						<option value="shop"     <?php selected( $woo_pages, 'shop' ); ?>><?php esc_html_e( 'Shop Page', 'menda-ads' ); ?></option>
						<option value="category" <?php selected( $woo_pages, 'category' ); ?>><?php esc_html_e( 'Product Category', 'menda-ads' ); ?></option>
						<option value="product"  <?php selected( $woo_pages, 'product' ); ?>><?php esc_html_e( 'Single Product', 'menda-ads' ); ?></option>
						<option value="cart"     <?php selected( $woo_pages, 'cart' ); ?>><?php esc_html_e( 'Cart', 'menda-ads' ); ?></option>
						<option value="checkout" <?php selected( $woo_pages, 'checkout' ); ?>><?php esc_html_e( 'Checkout', 'menda-ads' ); ?></option>
						<option value="account"  <?php selected( $woo_pages, 'account' ); ?>><?php esc_html_e( 'My Account', 'menda-ads' ); ?></option>
					</select>
				</td>
			</tr>
			<?php /* FEAT-A04: Custom taxonomy targeting */ ?>
			<tr class="menda-row-target-taxonomy menda-tab-row" data-tab="targeting" style="<?php echo $target_type !== 'taxonomy' ? 'display:none' : ''; ?>">
				<th><label for="menda_ad_target_taxonomy"><?php esc_html_e( 'Taxonomy', 'menda-ads' ); ?></label></th>
				<td>
					<input type="text" id="menda_ad_target_taxonomy" name="menda_ad_target_taxonomy" value="<?php echo esc_attr( $target_taxonomy ); ?>" class="regular-text" placeholder="e.g. product_cat">
					<p class="description"><?php esc_html_e( 'The taxonomy slug (e.g. product_cat, post_tag, genre).', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-row-target-taxonomy menda-tab-row" data-tab="targeting" style="<?php echo $target_type !== 'taxonomy' ? 'display:none' : ''; ?>">
				<th><label for="menda_ad_target_tax_terms"><?php esc_html_e( 'Terms (slug or ID)', 'menda-ads' ); ?></label></th>
				<td>
					<input type="text" id="menda_ad_target_tax_terms" name="menda_ad_target_tax_terms" value="<?php echo esc_attr( $target_tax_terms ); ?>" class="regular-text" placeholder="e.g. shoes, 42">
					<p class="description"><?php esc_html_e( 'Comma-separated term slugs or IDs.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-row-skin menda-tab-row" data-tab="delivery" style="<?php echo 'skin' !== $type ? 'display:none' : ''; ?>">
				<th><label for="menda_ad_skin_frequency"><?php esc_html_e( 'Takeover frequency', 'menda-ads' ); ?></label></th>
				<td>
					<select id="menda_ad_skin_frequency" name="menda_ad_skin_frequency">
						<option value="once_per_day" <?php selected( $skin_freq, 'once_per_day' ); ?>><?php esc_html_e( 'Once per day', 'menda-ads' ); ?></option>
						<option value="once_per_session" <?php selected( $skin_freq, 'once_per_session' ); ?>><?php esc_html_e( 'Once per session', 'menda-ads' ); ?></option>
						<option value="every_page" <?php selected( $skin_freq, 'every_page' ); ?>><?php esc_html_e( 'Every page load', 'menda-ads' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Once per day / session = first qualifying page only (often the homepage). Choose Every page load if the skin should stay on while they browse.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-row-impression-cap menda-tab-row" data-tab="delivery" style="<?php echo $type !== 'banner' ? 'display:none' : ''; ?>">
				<th><label for="menda_ad_impression_cap"><?php esc_html_e( 'Impression Cap', 'menda-ads' ); ?></label></th>
				<td>
					<input type="number" id="menda_ad_impression_cap" name="menda_ad_impression_cap" value="<?php echo esc_attr( $impression_cap ); ?>" class="small-text" min="0">
					<p class="description"><?php esc_html_e( 'Max impressions per session. 0 for unlimited.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-tab-row" data-tab="delivery">
				<th><label for="menda_ad_target_countries"><?php esc_html_e( 'Target Countries', 'menda-ads' ); ?></label></th>
				<td>
					<input type="text" id="menda_ad_target_countries" name="menda_ad_target_countries" value="<?php echo esc_attr( $target_countries ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Visitor country from Cloudflare / CloudFront headers only. Leave blank unless you are sure those headers exist — a value here can hide the ad for everyone else.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-tab-row" data-tab="billing">
				<th><label for="menda_ad_parent_id"><?php esc_html_e( 'A/B Testing (Parent Ad)', 'menda-ads' ); ?></label></th>
				<td>
					<select id="menda_ad_parent_id" name="menda_ad_parent_id" class="regular-text">
						<option value="0"><?php esc_html_e( 'None (This is the original ad)', 'menda-ads' ); ?></option>
						<?php foreach ( $sibling_ads as $sib ) : ?>
							<option value="<?php echo esc_attr( $sib->ID ); ?>" <?php selected( $parent_id, $sib->ID ); ?>>
								<?php echo esc_html( get_the_title( $sib ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Select the parent ad to split traffic with. If set, this ad acts as a variant.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-tab-row" data-tab="billing">
				<th><label for="menda_ad_pricing_model"><?php esc_html_e( 'Pricing Model', 'menda-ads' ); ?></label></th>
				<td>
					<select id="menda_ad_pricing_model" name="menda_ad_pricing_model" class="regular-text">
						<option value="flat" <?php selected( $pricing_model, 'flat' ); ?>><?php esc_html_e( 'Flat Fee', 'menda-ads' ); ?></option>
						<option value="cpc" <?php selected( $pricing_model, 'cpc' ); ?>><?php esc_html_e( 'Cost Per Click (CPC)', 'menda-ads' ); ?></option>
						<option value="cpm" <?php selected( $pricing_model, 'cpm' ); ?>><?php esc_html_e( 'Cost Per Mille (CPM)', 'menda-ads' ); ?></option>
					</select>
				</td>
			</tr>
			<tr class="menda-tab-row" data-tab="billing">
				<th><label for="menda_ad_pricing_rate"><?php esc_html_e( 'Pricing rate', 'menda-ads' ); ?></label></th>
				<td>
					<input type="number" step="0.001" min="0" id="menda_ad_pricing_rate" name="menda_ad_pricing_rate" value="<?php echo esc_attr( $pricing_rate ); ?>" class="regular-text">
					<select id="menda_ad_currency" name="menda_ad_currency">
						<option value="" <?php selected( $ad_currency, '' ); ?>>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: currency code, e.g. USD */
									__( 'Site default (%s)', 'menda-ads' ),
									I18n::site_currency()
								)
							);
							?>
						</option>
						<?php foreach ( I18n::currencies() as $code => $meta ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $ad_currency, $code ); ?>>
								<?php echo esc_html( $code . ' — ' . $meta['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Flat amount, CPC per click, or CPM per 1000 views. Use USD, SAR, EGP, AED, or any listed currency — do not assume one currency for the whole site.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-tab-row" data-tab="delivery">
				<th><?php esc_html_e( 'Weight', 'menda-ads' ); ?></th>
				<td>
					<input type="number" id="menda_ad_weight" name="menda_ad_weight" value="<?php echo esc_attr( (string) $ad_weight ); ?>" min="1" max="100" class="small-text">
					<p class="description"><?php esc_html_e( 'Higher weight wins more often when several banners share a placement.', 'menda-ads' ); ?></p>
				</td>
			</tr>
			<tr class="menda-tab-row" data-tab="delivery">
				<th><?php esc_html_e( 'Devices', 'menda-ads' ); ?></th>
				<td>
					<input type="hidden" name="menda_ad_show_desktop" value="0">
					<input type="hidden" name="menda_ad_show_mobile" value="0">
					<label><input type="checkbox" name="menda_ad_show_desktop" value="1" <?php checked( $desktop, '1' ); ?>> <?php esc_html_e( 'Desktop', 'menda-ads' ); ?></label>
					&nbsp;&nbsp;
					<label><input type="checkbox" name="menda_ad_show_mobile" value="1" <?php checked( $mobile, '1' ); ?>> <?php esc_html_e( 'Mobile', 'menda-ads' ); ?></label>
				</td>
			</tr>
		</table>
		<div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #f0f0f0;">
			<button type="button" class="button button-secondary button-large menda-preview-btn">
				<?php esc_html_e( 'Preview Ad', 'menda-ads' ); ?>
			</button>
			<span class="menda-skin-preview-actions" style="<?php echo 'skin' !== $type ? 'display:none' : ''; ?>">
			<?php if ( $preview_url ) : ?>
				<a class="button button-primary button-large menda-preview-site" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Preview takeover on site', 'menda-ads' ); ?>
				</a>
			<?php else : ?>
				<button type="button" class="button button-primary button-large" disabled>
					<?php esc_html_e( 'Save first to preview on site', 'menda-ads' ); ?>
				</button>
			<?php endif; ?>
			</span>
		</div>
		<?php
	}

	public function render_sponsored_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'menda_sponsored_meta_nonce', 'menda_sponsored_meta_nonce' );
		$sponsor_name = get_post_meta( $post->ID, '_menda_sponsor_name', true );
		$sponsor_link = get_post_meta( $post->ID, '_menda_sponsor_link', true );
		$sponsor_logo = get_post_meta( $post->ID, '_menda_sponsor_logo_id', true );
		?>
		<p>
			<label><?php esc_html_e( 'Sponsor Name', 'menda-ads' ); ?><br>
				<input type="text" name="menda_sponsor_name" value="<?php echo esc_attr( $sponsor_name ); ?>" class="widefat">
			</label>
		</p>
		<p>
			<label><?php esc_html_e( 'Sponsor URL', 'menda-ads' ); ?><br>
				<input type="url" name="menda_sponsor_link" value="<?php echo esc_url( $sponsor_link ); ?>" class="widefat">
			</label>
		</p>
		<p>
			<label><?php esc_html_e( 'Sponsor Logo', 'menda-ads' ); ?></label><br>
			<input type="hidden" id="menda_sponsor_logo_id" name="menda_sponsor_logo_id" value="<?php echo esc_attr( $sponsor_logo ); ?>">
			<button type="button" class="button menda-upload-btn" data-target="menda_sponsor_logo_id" data-preview="menda_sponsor_logo_preview">
				<?php esc_html_e( 'Upload Logo', 'menda-ads' ); ?>
			</button>
			<div id="menda_sponsor_logo_preview" style="margin-top:6px;">
				<?php if ( $sponsor_logo ) echo wp_get_attachment_image( (int) $sponsor_logo, [ 100, 50 ] ); ?>
			</div>
		</p>
		<?php
	}

	private function render_image_control( string $field_id, int $image_id ): void {
		$full = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'full' ) : '';
		?>
		<input type="hidden" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>">
		<button type="button" class="button menda-upload-btn" data-target="<?php echo esc_attr( $field_id ); ?>" data-preview="<?php echo esc_attr( $field_id ); ?>_preview">
			<?php esc_html_e( 'Upload / Select Image', 'menda-ads' ); ?>
		</button>
		<button type="button" class="button menda-upload-clear" data-target="<?php echo esc_attr( $field_id ); ?>" data-preview="<?php echo esc_attr( $field_id ); ?>_preview">
			<?php esc_html_e( 'Remove', 'menda-ads' ); ?>
		</button>
		<div id="<?php echo esc_attr( $field_id ); ?>_preview" class="menda-img-preview">
			<?php
			if ( $image_id ) {
				echo wp_get_attachment_image(
					$image_id,
					[ 200, 100 ],
					false,
					[ 'data-full' => $full ]
				);
			}
			?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save Meta
	// -------------------------------------------------------------------------

	public function save_ad_meta( int $post_id, \WP_Post $post ): void {
		if (
			! isset( $_POST['menda_ad_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['menda_ad_meta_nonce'] ) ), 'menda_ad_meta_nonce' ) ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			! current_user_can( 'edit_post', $post_id ) ||
			! current_user_can( MENDA_ADS_CAP )
		) {
			return;
		}

		$fields = [
			// SEC-10: Whitelist ad type — reject arbitrary values.
			'_menda_ad_type'             => fn( $v ) => in_array( $v, [ 'banner', 'interstitial', 'sticky_video', 'skin' ], true ) ? $v : 'banner',
			'_menda_ad_link'             => 'esc_url_raw',
			'_menda_ad_video_url'        => 'esc_url_raw',
			'_menda_ad_start'            => 'sanitize_text_field',
			'_menda_ad_end'              => 'sanitize_text_field',
			'_menda_ad_image_id'         => 'absint',
			'_menda_ad_show_mobile'      => 'sanitize_text_field',
			'_menda_ad_show_desktop'     => 'sanitize_text_field',
			// Expanded whitelist includes new targeting types.
			'_menda_ad_target_type'      => fn( $v ) => in_array( $v, [ 'all', 'pages', 'categories', 'taxonomy', 'woocommerce' ], true ) ? $v : 'all',
			'_menda_ad_target_ids'       => 'sanitize_text_field',
			'_menda_ad_impression_cap'   => 'absint',
			'_menda_ad_weight'           => static function ( $v ) {
				return min( 100, max( 1, absint( $v ) ) );
			},
			'_menda_ad_target_countries' => 'sanitize_text_field',
			'_menda_ad_parent_id'        => 'absint',
			// SEC-11: Whitelist pricing model.
			'_menda_ad_pricing_model'    => fn( $v ) => in_array( $v, [ 'flat', 'cpc', 'cpm' ], true ) ? $v : 'flat',
			// SEC-12: Sanitize pricing rate as a non-negative float.
			'_menda_ad_pricing_rate'     => fn( $v ) => number_format( max( 0.0, (float) $v ), 3, '.', '' ),
			// COMPAT-B08: WooCommerce targeting page selector.
			'_menda_ad_woo_pages'        => fn( $v ) => in_array( $v, [ 'all', 'shop', 'category', 'tag', 'product', 'cart', 'checkout', 'account' ], true ) ? $v : 'all',
			// FEAT-A04: Custom taxonomy targeting.
			'_menda_ad_target_taxonomy'  => 'sanitize_key',
			'_menda_ad_target_tax_terms' => 'sanitize_text_field',
			'_menda_ad_skin_left_id'     => 'absint',
			'_menda_ad_skin_right_id'    => 'absint',
			'_menda_ad_skin_top_id'      => 'absint',
			'_menda_ad_skin_mobile_id'   => 'absint',
			'_menda_ad_skin_left_link'   => 'esc_url_raw',
			'_menda_ad_skin_right_link'  => 'esc_url_raw',
			'_menda_ad_skin_top_link'    => 'esc_url_raw',
			'_menda_ad_skin_mobile_link' => 'esc_url_raw',
			'_menda_ad_skin_bg_color'    => static function ( $v ) {
				$c = sanitize_hex_color( (string) $v );
				return $c ? $c : '';
			},
			'_menda_ad_skin_fit'         => static function ( $v ) {
				return in_array( $v, [ 'cover', 'contain', 'width', 'repeat' ], true ) ? $v : 'cover';
			},
			'_menda_ad_skin_layout'      => static function ( $v ) {
				return in_array( $v, [ 'overlay', 'boxed' ], true ) ? $v : 'overlay';
			},
			'_menda_ad_skin_rail'        => static function ( $v ) {
				$n = absint( $v );
				return $n ? min( 400, max( 80, $n ) ) : 160;
			},
			'_menda_ad_skin_frequency'   => static function ( $v ) {
				return in_array( $v, [ 'once_per_session', 'once_per_day', 'every_page' ], true ) ? $v : 'every_page';
			},
			'_menda_ad_skin_mobile_mode' => static function ( $v ) {
				return in_array( $v, [ 'none', 'banner', 'sticky' ], true ) ? $v : 'sticky';
			},
			'_menda_ad_skin_content_width' => static function ( $v ) {
				$n = absint( $v );
				return $n ? min( 1920, max( 600, $n ) ) : 0;
			},
			'_menda_ad_skin_min_vp'      => static function ( $v ) {
				$n = absint( $v );
				return $n ? min( 2560, max( 600, $n ) ) : 0;
			},
			'_menda_ad_skin_z'           => static function ( $v ) {
				$n = absint( $v );
				return $n ? min( 99999, max( 1, $n ) ) : 40;
			},
			'_menda_ad_skin_header_full' => static function ( $v ) {
				return '1' === (string) $v ? '1' : '0';
			},
		];

		foreach ( $fields as $meta_key => $sanitizer ) {
			$post_key = ltrim( $meta_key, '_' );
			if ( isset( $_POST[ $post_key ] ) ) {
				update_post_meta( $post_id, $meta_key, $sanitizer( wp_unslash( $_POST[ $post_key ] ) ) );
			}
		}

		$posted_currency = isset( $_POST['menda_ad_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['menda_ad_currency'] ) ) : '';
		if ( '' === $posted_currency ) {
			update_post_meta( $post_id, '_menda_ad_currency', '' );
		} else {
			update_post_meta( $post_id, '_menda_ad_currency', I18n::sanitize_currency( $posted_currency ) );
		}

		$posted_langs = isset( $_POST['menda_ad_languages'] ) ? wp_unslash( $_POST['menda_ad_languages'] ) : [];
		update_post_meta( $post_id, '_menda_ad_languages', I18n::sanitize_languages( $posted_langs ) );

		if ( isset( $_POST['menda_ad_target_cats'] ) ) {
			$raw_cats = wp_unslash( $_POST['menda_ad_target_cats'] );
			if ( is_array( $raw_cats ) ) {
				$raw_cats = implode( ',', array_map( 'sanitize_title', $raw_cats ) );
			} else {
				$raw_cats = sanitize_text_field( $raw_cats );
			}
			update_post_meta( $post_id, '_menda_ad_target_cats', $raw_cats );
		} elseif ( isset( $_POST['menda_ad_target_type'] ) && 'categories' === $_POST['menda_ad_target_type'] ) {
			update_post_meta( $post_id, '_menda_ad_target_cats', '' );
		}

		if ( isset( $_POST['menda_ad_custom_code'] ) ) {
			$custom_code = wp_unslash( $_POST['menda_ad_custom_code'] );
			if ( current_user_can( 'unfiltered_html' ) && current_user_can( MENDA_ADS_CAP ) ) {
				update_post_meta( $post_id, '_menda_ad_custom_code', $custom_code );
			} else {
				update_post_meta(
					$post_id,
					'_menda_ad_custom_code',
					wp_kses(
						$custom_code,
						[
							'a'      => [ 'href' => true, 'target' => true, 'rel' => true, 'class' => true ],
							'img'    => [ 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'class' => true ],
							'div'    => [ 'class' => true, 'id' => true ],
							'span'   => [ 'class' => true ],
							'p'      => [ 'class' => true ],
							'strong' => [],
							'em'     => [],
							'br'     => [],
						]
					)
				);
			}
		}

		$type = (string) get_post_meta( $post_id, '_menda_ad_type', true );
		if ( 'publish' === $post->post_status ) {
			if ( 'skin' === $type ) {
				update_option( MENDA_ADS_PREFIX . '_skin_enabled', 1 );
			}
			if ( 'interstitial' === $type ) {
				update_option( MENDA_ADS_PREFIX . '_interstitial_enabled', 1 );
			}
			if ( 'sticky_video' === $type ) {
				update_option( MENDA_ADS_PREFIX . '_sticky_video_enabled', 1 );
			}
		}

		$desktop = (string) get_post_meta( $post_id, '_menda_ad_show_desktop', true );
		$mobile  = (string) get_post_meta( $post_id, '_menda_ad_show_mobile', true );
		if ( '0' === $desktop && '0' === $mobile ) {
			update_post_meta( $post_id, '_menda_ad_show_desktop', '1' );
			update_post_meta( $post_id, '_menda_ad_show_mobile', '1' );
		}

		if ( 'banner' === $type ) {
			$terms = wp_get_post_terms( $post_id, 'menda_ad_placement', [ 'fields' => 'ids' ] );
			if ( ( ! $terms || is_wp_error( $terms ) ) && taxonomy_exists( 'menda_ad_placement' ) ) {
				wp_set_object_terms( $post_id, 'in-article-300x250', 'menda_ad_placement', false );
				set_transient( 'menda_ads_notice_default_slot_' . $post_id, 1, 120 );
			}
		}
	}

	public function save_sponsored_meta( int $post_id, \WP_Post $post ): void {
		if (
			! isset( $_POST['menda_sponsored_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['menda_sponsored_meta_nonce'] ) ), 'menda_sponsored_meta_nonce' ) ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			! current_user_can( 'edit_post', $post_id ) ||
			! current_user_can( MENDA_ADS_CAP )
		) {
			return;
		}

		if ( isset( $_POST['menda_sponsor_name'] ) ) {
			update_post_meta( $post_id, '_menda_sponsor_name', sanitize_text_field( wp_unslash( $_POST['menda_sponsor_name'] ) ) );
		}
		if ( isset( $_POST['menda_sponsor_link'] ) ) {
			update_post_meta( $post_id, '_menda_sponsor_link', esc_url_raw( wp_unslash( $_POST['menda_sponsor_link'] ) ) );
		}
		if ( isset( $_POST['menda_sponsor_logo_id'] ) ) {
			update_post_meta( $post_id, '_menda_sponsor_logo_id', absint( $_POST['menda_sponsor_logo_id'] ) );
		}
	}

	// -------------------------------------------------------------------------
	// Front-end badge
	// -------------------------------------------------------------------------

	public function prepend_sponsored_badge( string $content ): string {
		if ( ! is_singular( 'menda_sponsored' ) ) {
			return $content;
		}
		$sponsor_name = get_post_meta( get_the_ID(), '_menda_sponsor_name', true );
		$sponsor_link = get_post_meta( get_the_ID(), '_menda_sponsor_link', true );
		$sponsor_logo = get_post_meta( get_the_ID(), '_menda_sponsor_logo_id', true );

		$badge  = '<div class="menda-partner">';
		$badge .= '<span class="menda-partner__label">' . esc_html__( 'Sponsored', 'menda-ads' ) . '</span>';
		if ( $sponsor_logo ) {
			$badge .= wp_get_attachment_image( (int) $sponsor_logo, [ 80, 40 ], false, [ 'class' => 'menda-partner__logo' ] );
		}
		if ( $sponsor_name ) {
			$inner = esc_html( $sponsor_name );
			if ( $sponsor_link ) {
				$inner = '<a href="' . esc_url( $sponsor_link ) . '" target="_blank" rel="noopener sponsored">' . $inner . '</a>';
			}
			$badge .= '<span class="menda-partner__name">' . $inner . '</span>';
		}
		$badge .= '</div>';

		return $badge . $content;
	}

	public function ad_columns( array $columns ): array {
		$insert = [
			'menda_type'       => __( 'Type', 'menda-ads' ),
			'menda_placement'  => __( 'Placement', 'menda-ads' ),
			'menda_schedule'   => __( 'Schedule', 'menda-ads' ),
			'menda_languages'  => __( 'Languages', 'menda-ads' ),
		];
		$out = [];
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out = array_merge( $out, $insert );
			}
		}
		return $out;
	}

	public function ad_column_content( string $column, $post_id ): void {
		$post_id = (int) $post_id;
		if ( 'menda_type' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, '_menda_ad_type', true ) );
			return;
		}
		if ( 'menda_placement' === $column ) {
			$type = (string) get_post_meta( $post_id, '_menda_ad_type', true );
			if ( 'skin' === $type ) {
				esc_html_e( 'Site overlay', 'menda-ads' );
				return;
			}
			if ( 'interstitial' === $type ) {
				esc_html_e( 'Between pages', 'menda-ads' );
				return;
			}
			if ( 'sticky_video' === $type ) {
				esc_html_e( 'Floating video', 'menda-ads' );
				return;
			}
			$terms = get_the_terms( $post_id, 'menda_ad_placement' );
			if ( ! $terms || is_wp_error( $terms ) ) {
				esc_html_e( 'Needs placement', 'menda-ads' );
				return;
			}
			echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
			return;
		}
		if ( 'menda_schedule' === $column ) {
			$start = get_post_meta( $post_id, '_menda_ad_start', true );
			$end   = get_post_meta( $post_id, '_menda_ad_end', true );
			if ( ! $start && ! $end ) {
				esc_html_e( 'Always', 'menda-ads' );
				return;
			}
			echo esc_html( trim( $start . ' → ' . $end ) );
			return;
		}
		if ( 'menda_languages' === $column ) {
			$langs = I18n::ad_languages( $post_id );
			if ( in_array( 'all', $langs, true ) ) {
				esc_html_e( 'All', 'menda-ads' );
				return;
			}
			echo esc_html( implode( ', ', $langs ) );
		}
	}
}
