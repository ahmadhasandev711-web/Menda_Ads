<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Named inventory for news sites. Terms are seeded into menda_ad_placement.
 */
final class Slots {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'init', [ $this, 'ensure_terms' ], 20 );
	}

	/**
	 * @return array<string, array{label:string, width:int, height:int, device:string}>
	 */
	public static function catalog(): array {
		return [
			'header-728x90' => [
				'label'  => __( 'Header leaderboard', 'menda-ads' ),
				'width'  => 728,
				'height' => 90,
				'device' => 'desktop',
			],
			'mobile-header-320x50' => [
				'label'  => __( 'Mobile header', 'menda-ads' ),
				'width'  => 320,
				'height' => 50,
				'device' => 'mobile',
			],
			'in-article-300x250' => [
				'label'  => __( 'In-article (after paragraph 2)', 'menda-ads' ),
				'width'  => 300,
				'height' => 250,
				'device' => 'all',
			],
			'in-article-2-300x250' => [
				'label'  => __( 'In-article (after paragraph 4)', 'menda-ads' ),
				'width'  => 300,
				'height' => 250,
				'device' => 'all',
			],
			'sidebar-300x250' => [
				'label'  => __( 'Sidebar medium rectangle', 'menda-ads' ),
				'width'  => 300,
				'height' => 250,
				'device' => 'all',
			],
			'sidebar-300x600' => [
				'label'  => __( 'Sidebar half-page', 'menda-ads' ),
				'width'  => 300,
				'height' => 600,
				'device' => 'desktop',
			],
			'below-article' => [
				'label'  => __( 'Below article', 'menda-ads' ),
				'width'  => 300,
				'height' => 250,
				'device' => 'all',
			],
			'mobile-sticky-320x50' => [
				'label'  => __( 'Mobile sticky footer', 'menda-ads' ),
				'width'  => 320,
				'height' => 50,
				'device' => 'mobile',
			],
			'listing-between-posts' => [
				'label'  => __( 'Between posts (archives)', 'menda-ads' ),
				'width'  => 300,
				'height' => 250,
				'device' => 'all',
			],
		];
	}

	public function ensure_terms(): void {
		if ( ! taxonomy_exists( 'menda_ad_placement' ) ) {
			return;
		}
		foreach ( self::catalog() as $slug => $slot ) {
			if ( term_exists( $slug, 'menda_ad_placement' ) ) {
				continue;
			}
			wp_insert_term(
				$slot['label'],
				'menda_ad_placement',
				[
					'slug'        => $slug,
					'description' => $slot['width'] . 'x' . $slot['height'],
				]
			);
		}
	}

	/**
	 * @return array<string, string> slug => label
	 */
	public static function choices(): array {
		$choices = [];
		foreach ( self::catalog() as $slug => $slot ) {
			$choices[ $slug ] = sprintf( '%s (%dx%d)', $slot['label'], $slot['width'], $slot['height'] );
		}
		if ( taxonomy_exists( 'menda_ad_placement' ) ) {
			$terms = get_terms( [
				'taxonomy'   => 'menda_ad_placement',
				'hide_empty' => false,
			] );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( ! isset( $choices[ $term->slug ] ) ) {
						$choices[ $term->slug ] = $term->name;
					}
				}
			}
		}
		return $choices;
	}

	/**
	 * @param string[] $slugs
	 * @return array{width:int, height:int, slug:string}|null
	 */
	public static function size_from_slugs( array $slugs ): ?array {
		$catalog = self::catalog();
		foreach ( $slugs as $slug ) {
			if ( isset( $catalog[ $slug ] ) ) {
				return [
					'width'  => (int) $catalog[ $slug ]['width'],
					'height' => (int) $catalog[ $slug ]['height'],
					'slug'   => $slug,
				];
			}
		}
		return null;
	}
}
