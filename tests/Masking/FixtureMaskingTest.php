<?php
namespace Krokedil\LogParser\Tests\Masking;

use Krokedil\LogParser\Masking\WcLogLineMasker;
use PHPUnit\Framework\TestCase;

/**
 * Masks every line of the real plugin log fixtures.
 */
class FixtureMaskingTest extends TestCase {
	/**
	 * The personal data the fixtures still hold, see tests/fixtures/logs/README.md.
	 */
	const PERSONAL_DATA = [
		'customer@example.com',
		'Testsson',
		'Testgatan',
		'+4670000000',
		'+46 70 000 00 00',
		'203.0.113.10',
		'Gunbritt',
		'+46739000001',
		'1979-01-12',
	];

	const ENTRY = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2} [A-Z]+ (.*?)(?: CONTEXT: (.*))?$/';

	public function fixtures(): array {
		$fixtures = [];
		foreach ( glob( __DIR__ . '/../fixtures/logs/*/*.log' ) as $path ) {
			$fixtures[ basename( dirname( $path ) ) . '/' . basename( $path ) ] = [ $path ];
		}
		return $fixtures;
	}

	/**
	 * @dataProvider fixtures
	 */
	public function test_masks_the_personal_data_in_every_line( string $path ): void {
		$masker = new WcLogLineMasker();

		foreach ( file( $path ) as $number => $line ) {
			$masked = $masker->mask( $line );

			$this->assertStringNotContainsString( '[MASKING FAILED]', $masked, "Line $number" );
			foreach ( self::PERSONAL_DATA as $value ) {
				$this->assertStringNotContainsString( $value, $masked, "Line $number" );
			}
		}
	}

	/**
	 * @dataProvider fixtures
	 */
	public function test_keeps_the_log_format( string $path ): void {
		$masker = new WcLogLineMasker();

		foreach ( file( $path ) as $number => $line ) {
			$masked = $masker->mask( $line );

			$this->assertSame( substr( $line, -1 ), substr( $masked, -1 ), "Line $number keeps its line ending" );
			$this->assertSame( preg_match( self::ENTRY, $line, $raw ), preg_match( self::ENTRY, $masked, $parts ), "Line $number keeps its prefix" );
			if ( empty( $raw ) ) {
				continue;
			}

			foreach ( [ 1, 2 ] as $segment ) {
				if ( isset( $raw[ $segment ] ) && null !== json_decode( $raw[ $segment ] ) ) {
					$this->assertNotNull( json_decode( $parts[ $segment ] ), "Line $number segment $segment is still JSON" );
				}
			}
		}
	}
}
