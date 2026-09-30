<?php
namespace Krokedil\LogParser\Masking;

use Krokedil\WpApi\KeyMasker;

/**
 * Masks sensitive data in text that could not be decoded as JSON, such as plain-text messages,
 * header strings, URLs and the invalid JSON some plugins write.
 */
class TextMasker {
	/**
	 * Patterns for values that are sensitive wherever they appear, and their replacements.
	 */
	const PATTERNS = [
		// An email address.
		'/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}/' => KeyMasker::REDACTED,
		// An Authorization header value, with the scheme left readable.
		'/\b(Basic|Bearer)\s+[A-Za-z0-9._~+\/=-]{8,}/i' => '$1 ' . KeyMasker::REDACTED,
		// A JWT.
		'/\bey[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}(?:\.[A-Za-z0-9_-]+)?/' => KeyMasker::REDACTED,
		// A WooCommerce order key, which grants access to the order.
		'/\bwc_order_[A-Za-z0-9]+/'                     => 'wc_order_' . KeyMasker::REDACTED,
		// A token in a Klarna authorization URL.
		'#/authorizations/[^/?\s"\\\\]+#'               => '/authorizations/' . KeyMasker::REDACTED,
		// An IPv4 address. Not after a slash, so browser versions like Chrome/154.0.0.0 are kept.
		'/(?<![\/\d.])\b(?:\d{1,3}\.){3}\d{1,3}\b(?![\d.])/' => KeyMasker::REDACTED,
	];

	/**
	 * A "Key: value" header string, as some plugins log their request headers.
	 */
	const HEADER = '/^([A-Za-z0-9_-]+):\s*(.+)$/s';

	/**
	 * A "key":value pair in raw JSON text.
	 */
	const PAIR = '/"([^"\\\\]{1,64})"\s*:\s*("(?:[^"\\\\]|\\\\.)*"|-?\d[\d.eE+-]*)/';

	/**
	 * A \"key\":value pair in JSON text that was itself encoded into a string.
	 */
	const ESCAPED_PAIR = '/\\\\"([^"\\\\]{1,64})\\\\"\s*:\s*(\\\\"(?:(?!\\\\").)*\\\\"|-?\d[\d.eE+-]*)/';

	/**
	 * Mask the sensitive data in a text.
	 *
	 * @param string $text The text.
	 * @return string
	 * @throws \RuntimeException If a pattern fails.
	 */
	public function mask( string $text ): string {
		if ( '' === $text || KeyMasker::is_placeholder( $text ) ) {
			return $text;
		}

		if ( preg_match( self::HEADER, $text, $matches ) && $this->is_sensitive_key( $matches[1] ) ) {
			return KeyMasker::is_placeholder( trim( $matches[2] ) ) ? $text : $matches[1] . ': ' . KeyMasker::REDACTED;
		}

		$text = $this->mask_pairs( self::PAIR, $text, '"' );
		$text = $this->mask_pairs( self::ESCAPED_PAIR, $text, '\\"' );

		foreach ( self::PATTERNS as $pattern => $replacement ) {
			$masked = preg_replace( $pattern, $replacement, $text );
			if ( null === $masked ) {
				throw new \RuntimeException( 'Could not mask the text.' );
			}
			$text = $masked;
		}

		return $text;
	}

	/**
	 * Whether a key name is one that KeyMasker masks, using the same matching as KeyMasker.
	 *
	 * @param string $key The key name.
	 * @return bool
	 */
	public function is_sensitive_key( string $key ): bool {
		$key = str_replace( '-', '_', strtolower( $key ) );
		foreach ( KeyMasker::get_keys() as $needle ) {
			if ( false !== strpos( $key, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Replace the value of every sensitive key/value pair a pattern finds.
	 *
	 * @param string $pattern The pair pattern, capturing the key and the value.
	 * @param string $text    The text.
	 * @param string $quote   The quote the pattern uses around keys and string values.
	 * @return string
	 * @throws \RuntimeException If the pattern fails.
	 */
	private function mask_pairs( string $pattern, string $text, string $quote ): string {
		$masked = preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $quote ) {
				$value = trim( $matches[2], '\\"' );
				if ( ! $this->is_sensitive_key( $matches[1] ) || KeyMasker::is_placeholder( $value ) ) {
					return $matches[0];
				}

				$placeholder = '' === $value ? KeyMasker::MISSING : KeyMasker::REDACTED;
				return $quote . $matches[1] . $quote . ':' . $quote . $placeholder . $quote;
			},
			$text
		);

		if ( null === $masked ) {
			throw new \RuntimeException( 'Could not mask the text.' );
		}

		return $masked;
	}
}
