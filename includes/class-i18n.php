<?php
namespace Menda_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Site language (WPML / Polylang / TranslatePress / locale) and money formatting.
 * Ads are not duplicated per language by default — they target languages.
 * Amounts keep their own currency; reports never mix USD + SAR into one "$ total".
 */
final class I18n {

	/**
	 * @return array<string, array{symbol:string, position:string, decimals:int, label:string}>
	 */
	public static function currencies(): array {
		return [
			'USD' => [ 'symbol' => '$',   'position' => 'before', 'decimals' => 2, 'label' => __( 'US Dollar', 'menda-ads' ) ],
			'EUR' => [ 'symbol' => '€',   'position' => 'before', 'decimals' => 2, 'label' => __( 'Euro', 'menda-ads' ) ],
			'GBP' => [ 'symbol' => '£',   'position' => 'before', 'decimals' => 2, 'label' => __( 'British Pound', 'menda-ads' ) ],
			'EGP' => [ 'symbol' => 'E£',  'position' => 'after',  'decimals' => 2, 'label' => __( 'Egyptian Pound', 'menda-ads' ) ],
			'SAR' => [ 'symbol' => 'ر.س', 'position' => 'after',  'decimals' => 2, 'label' => __( 'Saudi Riyal', 'menda-ads' ) ],
			'AED' => [ 'symbol' => 'د.إ', 'position' => 'after',  'decimals' => 2, 'label' => __( 'UAE Dirham', 'menda-ads' ) ],
			'QAR' => [ 'symbol' => 'ر.ق', 'position' => 'after',  'decimals' => 2, 'label' => __( 'Qatari Riyal', 'menda-ads' ) ],
			'KWD' => [ 'symbol' => 'د.ك', 'position' => 'after',  'decimals' => 3, 'label' => __( 'Kuwaiti Dinar', 'menda-ads' ) ],
			'BHD' => [ 'symbol' => 'د.ب', 'position' => 'after',  'decimals' => 3, 'label' => __( 'Bahraini Dinar', 'menda-ads' ) ],
			'OMR' => [ 'symbol' => 'ر.ع', 'position' => 'after',  'decimals' => 3, 'label' => __( 'Omani Rial', 'menda-ads' ) ],
			'JOD' => [ 'symbol' => 'د.أ', 'position' => 'after',  'decimals' => 3, 'label' => __( 'Jordanian Dinar', 'menda-ads' ) ],
			'MAD' => [ 'symbol' => 'د.م', 'position' => 'after',  'decimals' => 2, 'label' => __( 'Moroccan Dirham', 'menda-ads' ) ],
		];
	}

	public static function default_currency_for_locale( string $locale = '' ): string {
		$locale = $locale ?: get_locale();
		$map    = [
			'ar_EG' => 'EGP',
			'ar_SA' => 'SAR',
			'ar_AE' => 'AED',
			'ar_QA' => 'QAR',
			'ar_KW' => 'KWD',
			'ar_BH' => 'BHD',
			'ar_OM' => 'OMR',
			'ar_JO' => 'JOD',
			'ar_MA' => 'MAD',
		];
		return $map[ $locale ] ?? 'USD';
	}

	public static function site_currency(): string {
		$stored = strtoupper( (string) get_option( MENDA_ADS_PREFIX . '_currency', '' ) );
		return self::sanitize_currency( $stored ?: self::default_currency_for_locale() );
	}

	public static function sanitize_currency( $value ): string {
		$code = strtoupper( sanitize_text_field( (string) $value ) );
		return isset( self::currencies()[ $code ] ) ? $code : self::default_currency_for_locale();
	}

	public static function ad_currency( int $ad_id ): string {
		$raw = strtoupper( (string) get_post_meta( $ad_id, '_menda_ad_currency', true ) );
		return $raw && isset( self::currencies()[ $raw ] ) ? $raw : self::site_currency();
	}

	public static function format_money( float $amount, string $code = '' ): string {
		$code  = $code ? self::sanitize_currency( $code ) : self::site_currency();
		$meta  = self::currencies()[ $code ];
		$num   = number_format_i18n( $amount, $meta['decimals'] );
		return 'before' === $meta['position']
			? $meta['symbol'] . $num
			: $num . ' ' . $meta['symbol'];
	}

	public static function format_ad_money( float $amount, int $ad_id ): string {
		return self::format_money( $amount, self::ad_currency( $ad_id ) );
	}

	/**
	 * Front-end language slug: ar, en, …
	 * WPML directory /en/ on news.meenda.com is covered here.
	 */
	public static function current_language(): string {
		$wpml = apply_filters( 'wpml_current_language', null );
		if ( is_string( $wpml ) && '' !== $wpml && 'all' !== $wpml ) {
			return self::normalize_lang( $wpml );
		}
		if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE && 'all' !== ICL_LANGUAGE_CODE ) {
			return self::normalize_lang( (string) ICL_LANGUAGE_CODE );
		}
		if ( function_exists( 'pll_current_language' ) ) {
			$pll = pll_current_language( 'slug' );
			if ( is_string( $pll ) && '' !== $pll ) {
				return self::normalize_lang( $pll );
			}
		}
		if ( ! empty( $GLOBALS['TRP_LANGUAGE'] ) ) {
			return self::normalize_lang( (string) $GLOBALS['TRP_LANGUAGE'] );
		}
		if ( function_exists( 'weglot_get_current_language' ) ) {
			$weglot = weglot_get_current_language();
			if ( is_string( $weglot ) && '' !== $weglot ) {
				return self::normalize_lang( $weglot );
			}
		}
		return self::normalize_lang( determine_locale() );
	}

	/**
	 * Languages the operator can target. Plugin list first; always include ar + en
	 * so a Meenda-style bilingual news site works even before we detect the plugin.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function active_languages(): array {
		$langs = [];

		$wpml = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
		if ( is_array( $wpml ) ) {
			foreach ( $wpml as $code => $row ) {
				$slug = self::normalize_lang( is_array( $row ) ? ( $row['code'] ?? (string) $code ) : (string) $code );
				$label = is_array( $row ) ? (string) ( $row['native_name'] ?? $row['translated_name'] ?? $slug ) : $slug;
				if ( $slug ) {
					$langs[ $slug ] = $label;
				}
			}
		}

		if ( empty( $langs ) && function_exists( 'pll_the_languages' ) ) {
			$pll = pll_the_languages( [ 'raw' => 1, 'hide_if_empty' => 0 ] );
			if ( is_array( $pll ) ) {
				foreach ( $pll as $row ) {
					$slug = self::normalize_lang( (string) ( $row['slug'] ?? '' ) );
					if ( $slug ) {
						$langs[ $slug ] = (string) ( $row['name'] ?? $slug );
					}
				}
			}
		}

		if ( empty( $langs ) ) {
			$trp = get_option( 'trp_settings', [] );
			if ( is_array( $trp ) && ! empty( $trp['publish-languages'] ) && is_array( $trp['publish-languages'] ) ) {
				foreach ( $trp['publish-languages'] as $locale ) {
					$slug = self::normalize_lang( (string) $locale );
					if ( $slug ) {
						$langs[ $slug ] = $slug;
					}
				}
			}
		}

		$langs['ar'] = $langs['ar'] ?? 'العربية';
		$langs['en'] = $langs['en'] ?? 'English';

		$site = self::normalize_lang( get_locale() );
		if ( $site && ! isset( $langs[ $site ] ) ) {
			$langs[ $site ] = $site;
		}

		return $langs;
	}

	public static function sanitize_languages( $value ): string {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return 'all';
		}
		$clean = [];
		foreach ( $value as $item ) {
			$item = strtolower( trim( (string) $item ) );
			if ( 'all' === $item ) {
				return 'all';
			}
			$item = self::normalize_lang( $item );
			if ( ! $item || 'al' === $item || ! preg_match( '/^[a-z]{2}$/', $item ) ) {
				continue;
			}
			$clean[] = $item;
		}
		$clean = array_values( array_unique( $clean ) );
		return empty( $clean ) ? 'all' : implode( ',', $clean );
	}

	public static function ad_languages( int $ad_id ): array {
		$raw = strtolower( trim( (string) get_post_meta( $ad_id, '_menda_ad_languages', true ) ) );
		if ( '' === $raw || 'all' === $raw || 'al' === $raw ) {
			return [ 'all' ];
		}
		$parts = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
		if ( in_array( 'all', $parts, true ) || in_array( 'al', $parts, true ) ) {
			return [ 'all' ];
		}
		return $parts;
	}

	public static function ad_matches_language( int $ad_id ): bool {
		$langs = self::ad_languages( $ad_id );
		if ( in_array( 'all', $langs, true ) ) {
			return true;
		}
		return in_array( self::current_language(), $langs, true );
	}

	public static function translate_id( int $id, string $type = '' ): int {
		if ( $id <= 0 ) {
			return $id;
		}
		if ( '' === $type ) {
			$type = get_post_type( $id ) ?: 'post';
		}
		$translated = apply_filters( 'wpml_object_id', $id, $type, true );
		if ( is_numeric( $translated ) && (int) $translated > 0 ) {
			return (int) $translated;
		}
		if ( function_exists( 'pll_get_post' ) ) {
			$pll = pll_get_post( $id );
			if ( $pll ) {
				return (int) $pll;
			}
		}
		return $id;
	}

	public static function normalize_lang( string $code ): string {
		$code = strtolower( str_replace( '_', '-', trim( $code ) ) );
		if ( '' === $code || 'all' === $code ) {
			return $code;
		}
		return substr( $code, 0, 2 );
	}
}
