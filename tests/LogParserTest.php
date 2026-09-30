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

	const MULTI_LINE_LOG = '2026-09-29T11:50:32+00:00 WARNING [PROCESS PAYMENT]: Failed for order #1: Not allowed!' . "\n"
		. 'PaymentOrderOperation: UpdateOrder is not valid.' . "\n"
		. ' CONTEXT: [{"order_id":376,"email":"a@b.se"}]' . "\n"
		. '2026-09-29T11:50:33+00:00 INFO Next entry' . "\n";

	private function parse( array $terms, $line_masker = null, string $log = self::LOG ): string {
		$logger  = new NullOutputLogger();
		$handler = new StringResultHandler( $logger );
		$parser  = new LogParser( new StringLogDataProvider( $log ), $handler, $logger, $terms, false, 1000, $line_masker );
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

	public function test_returns_a_multi_line_entry_whole_and_masked(): void {
		$result = $this->parse( [ 'Failed for order' ], null, self::MULTI_LINE_LOG );

		$this->assertSame(
			'2026-09-29T11:50:32+00:00 WARNING [PROCESS PAYMENT]: Failed for order #1: Not allowed!' . "\n"
			. 'PaymentOrderOperation: UpdateOrder is not valid.' . "\n"
			. ' CONTEXT: [{"order_id":376,"email":"[REDACTED]"}]' . "\n",
			$result
		);
	}

	public function test_matches_a_term_on_a_continuation_line(): void {
		$result = $this->parse( [ 'UpdateOrder' ], new NullLineMasker(), self::MULTI_LINE_LOG );

		$this->assertStringStartsWith( '2026-09-29T11:50:32+00:00 WARNING', $result );
		$this->assertStringNotContainsString( 'Next entry', $result );
	}

	public function test_returns_the_raw_line_with_the_null_masker(): void {
		$result = $this->parse( [ 'order-2' ], new NullLineMasker() );

		$this->assertStringContainsString( 'c@d.se', $result );
	}
}
