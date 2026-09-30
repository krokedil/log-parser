<?php
namespace Krokedil\LogParser\Masking;

use Krokedil\LogParser\Interfaces\LineMaskerInterface;
use Krokedil\WpApi\FieldMasker;
use Krokedil\WpApi\KeyMasker;

/**
 * Masks a WooCommerce log line with the same passes the plugins now run before they log.
 * JSON strings nested in the entry, such as a request body, are decoded and masked too.
 */
class WcLogLineMasker implements LineMaskerInterface {
	/**
	 * The timestamp and level every entry starts with, in the current and the pre 8.6 format.
	 */
	const PREFIX = '/^(?:\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)?|\d{2}-\d{2}-\d{4} @ \d{2}:\d{2}:\d{2})\s+(?:[A-Z]+|-)\s+/';

	/**
	 * What separates the message from the context.
	 */
	const CONTEXT = ' CONTEXT: ';

	/**
	 * How deep JSON strings inside JSON strings are decoded.
	 */
	const MAX_NESTING = 5;

	/**
	 * The flags a masked JSON value is encoded with.
	 */
	const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE;

	/**
	 * The FieldMasker rules.
	 *
	 * @var array
	 */
	private array $fields;

	/**
	 * The masker for text that is not JSON.
	 *
	 * @var TextMasker
	 */
	private TextMasker $text_masker;

	/**
	 * Create the masker. The profile keys are added to KeyMasker, which holds them globally.
	 *
	 * @param MaskingProfile|null $profile The profile to mask with. Defaults to the Krokedil profile.
	 */
	public function __construct( ?MaskingProfile $profile = null ) {
		$profile = $profile ?? MaskingProfile::krokedil();
		KeyMasker::add_keys( $profile->get_keys() );

		$this->fields      = $profile->get_fields();
		$this->text_masker = new TextMasker();
	}

	/**
	 * Mask a log line. If masking fails, the entry is replaced by the failure marker.
	 *
	 * @param string $line The raw log line, including its line ending.
	 * @return string
	 */
	public function mask( string $line ): string {
		$entry  = rtrim( $line, "\r\n" );
		$ending = substr( $line, strlen( $entry ) );
		$prefix = preg_match( self::PREFIX, $entry, $matches ) ? $matches[0] : '';
		$entry  = substr( $entry, strlen( $prefix ) );

		try {
			return $prefix . $this->mask_entry( $entry ) . $ending;
		} catch ( \Throwable $e ) {
			return $prefix . KeyMasker::FAILED . $ending;
		}
	}

	/**
	 * Mask the message and the context of an entry separately.
	 *
	 * @param string $entry The entry, without the timestamp and level.
	 * @return string
	 */
	private function mask_entry( string $entry ): string {
		$position = strrpos( $entry, self::CONTEXT );
		if ( false === $position ) {
			return $this->mask_segment( $entry );
		}

		$message = substr( $entry, 0, $position );
		$context = substr( $entry, $position + strlen( self::CONTEXT ) );

		return $this->mask_segment( $message ) . self::CONTEXT . $this->mask_segment( $context );
	}

	/**
	 * Mask a message or a context. A segment that masking leaves unchanged is returned as it was.
	 *
	 * @param string $segment The segment.
	 * @return string
	 */
	private function mask_segment( string $segment ): string {
		$decoded = json_decode( $segment, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! ( is_array( $decoded ) || is_string( $decoded ) ) ) {
			return $this->mask_string( $segment, 0 );
		}

		$masked = is_array( $decoded ) ? $this->mask_structure( $decoded, 0 ) : $this->mask_string( $decoded, 0 );

		return $masked === $decoded ? $segment : $this->encode( $masked );
	}

	/**
	 * Mask decoded JSON data: first its nested JSON strings, then the field rules, then key names.
	 *
	 * @param array $data    The data.
	 * @param int   $nesting How many JSON strings deep the data is.
	 * @return array
	 */
	private function mask_structure( array $data, int $nesting ): array {
		$data = $this->mask_strings( $data, $nesting, 0 );

		return $this->restore_booleans( $data, KeyMasker::mask( FieldMasker::mask( $data, $this->fields ) ), 0 );
	}

	/**
	 * Put back the booleans the key name pass masked, such as phone_mandatory. A boolean cannot
	 * hold personal data, and masking it only hides settings a support case needs.
	 *
	 * @param array $original The data before masking.
	 * @param array $masked   The masked data.
	 * @param int   $depth    How deep in the data we are.
	 * @return array
	 */
	private function restore_booleans( array $original, array $masked, int $depth ): array {
		if ( $depth > KeyMasker::MAX_DEPTH ) {
			return $masked;
		}

		foreach ( $masked as $key => $value ) {
			if ( ! array_key_exists( $key, $original ) ) {
				continue;
			}

			if ( is_bool( $original[ $key ] ) && KeyMasker::is_placeholder( $value ) ) {
				$masked[ $key ] = $original[ $key ];
			} elseif ( is_array( $original[ $key ] ) && is_array( $value ) ) {
				$masked[ $key ] = $this->restore_booleans( $original[ $key ], $value, $depth + 1 );
			}
		}

		return $masked;
	}

	/**
	 * Mask every string in the data.
	 *
	 * @param array $data    The data.
	 * @param int   $nesting How many JSON strings deep the data is.
	 * @param int   $depth   How deep in the data we are. KeyMasker masks what is left below its limit.
	 * @return array
	 */
	private function mask_strings( array $data, int $nesting, int $depth ): array {
		if ( $depth > KeyMasker::MAX_DEPTH ) {
			return $data;
		}

		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) ) {
				$data[ $key ] = $this->mask_string( $value, $nesting );
			} elseif ( is_array( $value ) ) {
				$data[ $key ] = $this->mask_strings( $value, $nesting, $depth + 1 );
			}
		}

		return $data;
	}

	/**
	 * Mask a string. JSON in the string, on its own or after some text, is decoded and masked.
	 *
	 * @param string $value   The string.
	 * @param int    $nesting How many JSON strings deep the string is.
	 * @return string
	 */
	private function mask_string( string $value, int $nesting ): string {
		if ( KeyMasker::is_placeholder( $value ) ) {
			return $value;
		}

		if ( $nesting < self::MAX_NESTING ) {
			foreach ( $this->json_starts( $value ) as $start ) {
				$decoded = json_decode( substr( $value, $start ), true );
				if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
					continue;
				}

				$text   = $this->text_masker->mask( substr( $value, 0, $start ) );
				$masked = $this->mask_structure( $decoded, $nesting + 1 );

				return $text . ( $masked === $decoded ? substr( $value, $start ) : $this->encode( $masked ) );
			}
		}

		return $this->text_masker->mask( $value );
	}

	/**
	 * Where JSON might start in a string: the first '{' and the first '['.
	 *
	 * @param string $value The string.
	 * @return int[]
	 */
	private function json_starts( string $value ): array {
		$starts = array_filter(
			[ strpos( $value, '{' ), strpos( $value, '[' ) ],
			function ( $start ) {
				return false !== $start;
			}
		);
		sort( $starts );

		return $starts;
	}

	/**
	 * Encode a masked value as JSON.
	 *
	 * @param mixed $value The value.
	 * @return string
	 * @throws \RuntimeException If the value cannot be encoded.
	 */
	private function encode( $value ): string {
		$json = json_encode( $value, self::JSON_FLAGS );
		if ( false === $json ) {
			throw new \RuntimeException( 'Could not encode the masked value.' );
		}

		return $json;
	}
}
