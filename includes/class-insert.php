<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Auto-inject named slots into article content, archives, and a mobile dock.
 */
final class Insert {

	private static ?self $instance = null;
	private bool $content_done     = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_filter( 'the_content', [ $this, 'filter_content' ], 12 );
		add_filter( 'elementor/frontend/the_content', [ $this, 'filter_content' ], 12 );
		add_action( 'the_post', [ $this, 'between_posts' ], 10, 2 );
		add_action( 'wp_footer', [ $this, 'render_mobile_dock' ], 8 );
	}

	public function filter_content( string $content ): string {
		if ( $this->content_done || ! $this->can_inject_article() ) {
			return $content;
		}
		$this->content_done = true;

		$inject = [];
		if ( $this->enabled( 'insert_p2', true ) && ! $this->content_has_slot( $content, 'in-article-300x250' ) ) {
			$html = $this->unit( 'in-article-300x250' );
			if ( $html ) {
				$inject[2] = $html;
			}
		}
		if ( $this->enabled( 'insert_p4', true ) && ! $this->content_has_slot( $content, 'in-article-2-300x250' ) ) {
			$html = $this->unit( 'in-article-2-300x250' );
			if ( $html ) {
				$inject[4] = $html;
			}
		}

		if ( $inject ) {
			$content = $this->after_paragraphs( $content, $inject );
		}

		if ( $this->enabled( 'insert_below', true ) && ! $this->content_has_slot( $content, 'below-article' ) ) {
			$html = $this->unit( 'below-article' );
			if ( $html ) {
				$content .= $html;
			}
		}

		return $content;
	}

	/**
	 * @param \WP_Post  $post
	 * @param \WP_Query $query
	 */
	public function between_posts( $post, $query ): void {
		if ( ! $this->enabled( 'insert_archive', false ) ) {
			return;
		}
		if ( ! ( $query instanceof \WP_Query ) || ! $query->is_main_query() || is_admin() || is_singular() || is_feed() ) {
			return;
		}
		if ( Context::should_skip_overlays() ) {
			return;
		}
		$n = (int) $query->current_post;
		if ( $n < 1 || ( $n % 3 ) !== 2 ) {
			return;
		}
		$html = $this->unit( 'listing-between-posts' );
		if ( $html ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $html;
		}
	}

	public function render_mobile_dock(): void {
		if ( is_admin() || Context::is_amp() || Context::is_builder_preview() ) {
			return;
		}
		if ( ! $this->enabled( 'insert_mobile_sticky', true ) ) {
			return;
		}
		$html = Display::instance()->shortcode( [ 'placement' => 'mobile-sticky-320x50' ] );
		if ( '' === $html ) {
			return;
		}
		echo '<div id="menda-dock" class="menda-dock" role="complementary" aria-label="' . esc_attr__( 'Advertisement', 'menda-ads' ) . '">';
		echo '<button type="button" class="menda-dock__close" aria-label="' . esc_attr__( 'Close', 'menda-ads' ) . '">&times;</button>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
		echo '</div>';
	}

	private function enabled( string $key, bool $default ): bool {
		$val = get_option( MENDA_ADS_PREFIX . '_' . $key, $default ? 1 : 0 );
		if ( is_bool( $val ) ) {
			return $val;
		}
		return (int) $val === 1;
	}

	private function can_inject_article(): bool {
		if ( is_admin() || is_feed() || is_embed() || Context::is_amp() || Context::is_builder_preview() ) {
			return false;
		}
		if ( ! is_singular( [ 'post', 'menda_sponsored' ] ) ) {
			return false;
		}
		if ( post_password_required() ) {
			return false;
		}
		return in_the_loop() || doing_filter( 'elementor/frontend/the_content' ) || is_main_query();
	}

	private function content_has_slot( string $content, string $slug ): bool {
		return str_contains( $content, 'data-menda-slot="' . $slug . '"' )
			|| str_contains( $content, 'placement="' . $slug . '"' );
	}

	private function unit( string $placement ): string {
		$html = Display::instance()->shortcode( [ 'placement' => $placement ] );
		return $html ? '<div class="menda-insert menda-insert--' . esc_attr( $placement ) . '">' . $html . '</div>' : '';
	}

	/**
	 * @param array<int, string> $inject paragraph_index => html
	 */
	private function after_paragraphs( string $content, array $inject ): string {
		$chunks = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $chunks ) ) {
			return $content . implode( '', $inject );
		}
		$out = '';
		$p   = 0;
		foreach ( $chunks as $chunk ) {
			$out .= $chunk;
			if ( 0 === strcasecmp( $chunk, '</p>' ) ) {
				++$p;
				if ( isset( $inject[ $p ] ) ) {
					$out .= $inject[ $p ];
					unset( $inject[ $p ] );
				}
			}
		}
		if ( $inject ) {
			$out .= implode( '', $inject );
		}
		return $out;
	}
}
