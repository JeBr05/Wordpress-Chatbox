<?php
/**
 * Sanitization helpers.
 *
 * @package JeroensChatbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JCB_Sanitizer {

	/**
	 * Sanitize boolean-like value.
	 *
	 * @param mixed $value Input value.
	 */
	public static function bool( $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize integer in a range.
	 *
	 * @param mixed $value Input value.
	 * @param int   $min Minimum.
	 * @param int   $max Maximum.
	 */
	public static function int_range( $value, int $min, int $max ): int {
		$value = absint( $value );
		return max( $min, min( $max, $value ) );
	}

	/**
	 * Sanitize hex color.
	 *
	 * @param string $value Input value.
	 */
	public static function color( string $value ): string {
		$color = sanitize_hex_color( $value );
		return $color ? $color : '#6f5bd6';
	}

	/**
	 * Clean chat text.
	 *
	 * @param string $text Input text.
	 * @param int    $limit Max characters.
	 */
	public static function text( string $text, int $limit = 4000 ): string {
		$text = wp_strip_all_tags( wp_unslash( $text ) );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( (string) $text );
		if ( strlen( $text ) > $limit ) {
			$text = substr( $text, 0, $limit );
		}
		return $text;
	}

	/**
	 * Clean multiline text while preserving line breaks.
	 *
	 * @param string $text Input text.
	 * @param int    $limit Max characters.
	 */
	public static function textarea( string $text, int $limit = 4000 ): string {
		$text = sanitize_textarea_field( wp_unslash( $text ) );
		$text = trim( (string) $text );
		if ( strlen( $text ) > $limit ) {
			$text = substr( $text, 0, $limit );
		}
		return $text;
	}

	/**
	 * Redact common personal data patterns.
	 *
	 * @param string $text Input text.
	 */
	public static function redact( string $text ): string {
		$text = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email redacted]', $text );
		$text = preg_replace( '/\+?\d[\d\s().\-]{7,}\d/', '[phone redacted]', $text );
		return (string) $text;
	}
}
