<?php
namespace Krokedil\LogParser\Tests;

use Krokedil\LogParser\LogDataProviders\StringLogDataProvider;
use Krokedil\LogParser\LogParser;
use Krokedil\LogParser\Masking\NullLineMasker;
use Krokedil\LogParser\OutputLoggers\NullOutputLogger;
use Krokedil\LogParser\ResultHandlers\StringResultHandler;
use PHPUnit\Framework\TestCase;

class LogParserTest extends TestCase {
	const LOG = '2026-09-17T11:44:44+00:00 NOTICE {"id":"order-1","email":"a@b.se"} CONTEXT: {"_legacy":true}' . "\n"
		. '2026-09-17T11:44:45+00:00 NOTICE {"id":"order-2","email":"c@d.se"} CONTEXT: {"_legacy":true}' . "\n";

	private function parse( array $terms, $line_masker = null ): string {
		$logger  = new NullOutputLogger();
		$handler = new StringResultHandler( $logger );
		$parser  = new LogParser( new StringLogDataProvider( self::LOG ), $handler, $logger, $terms, false, 1000, $line_masker );
		$parser->parse();

		return $handler->get_result();
	}

	public function test_matches_the_raw_line_and_returns_it_masked(): void {
		$result = $this->parse( [ 'a@b.se' ] );

		$this->assertSame( '2026-09-17T11:44:44+00:00 NOTICE {"id":"order-1","email":"[REDACTED]"} CONTEXT: {"_legacy":true}' . "\n", $result );
	}

	public function test_returns_every_line_without_search_terms(): void {
		$result = $this->parse( [], new NullLineMasker() );

		$this->assertSame( self::LOG, $result );
	}

	public function test_returns_the_raw_line_with_the_null_masker(): void {
		$result = $this->parse( [ 'order-2' ], new NullLineMasker() );

		$this->assertStringContainsString( 'c@d.se', $result );
	}
}
