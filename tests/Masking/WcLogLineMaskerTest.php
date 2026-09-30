<?php
namespace Krokedil\LogParser\Tests\Masking;

use Krokedil\LogParser\Masking\WcLogLineMasker;
use PHPUnit\Framework\TestCase;

class WcLogLineMaskerTest extends TestCase {
	const PREFIX = '2026-09-17T11:44:44+00:00 NOTICE ';

	private WcLogLineMasker $masker;

	protected function setUp(): void {
		$this->masker = new WcLogLineMasker();
	}

	private function message( string $line ): array {
		$message = substr( $line, strlen( self::PREFIX ) );
		$message = explode( ' CONTEXT: ', rtrim( $message, "\n" ) )[0];
		return json_decode( $message, true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_masks_personal_data_and_keeps_the_address_location(): void {
		$line   = self::PREFIX . '{"response":{"body":{"billing_address":{"given_name":"Test","email":"a@b.se","street_address":"Testgatan 1","postal_code":"67131","city":"Arvika","country":"se"}}}} CONTEXT: {"_legacy":true}' . "\n";
		$masked = $this->masker->mask( $line );

		$address = $this->message( $masked )['response']['body']['billing_address'];
		$this->assertSame( '[REDACTED]', $address['given_name'] );
		$this->assertSame( '[REDACTED]', $address['email'] );
		$this->assertSame( '[REDACTED]', $address['street_address'] );
		$this->assertSame( '67131', $address['postal_code'] );
		$this->assertSame( 'Arvika', $address['city'] );
		$this->assertStringEndsWith( ' CONTEXT: {"_legacy":true}' . "\n", $masked );
		$this->assertStringStartsWith( self::PREFIX, $masked );
	}

	public function test_masks_inside_a_json_encoded_body_and_keeps_it_a_string(): void {
		$body   = json_encode( [ 'shipping_address' => [ 'family_name' => 'Testsson', 'country' => 'SE' ] ] );
		$line   = self::PREFIX . json_encode( [ 'response' => [ 'body' => [ 'body' => $body ] ] ] ) . "\n";
		$masked = $this->masker->mask( $line );

		$inner = $this->message( $masked )['response']['body']['body'];
		$this->assertIsString( $inner );
		$this->assertSame( [ 'shipping_address' => [ 'family_name' => '[REDACTED]', 'country' => 'SE' ] ], json_decode( $inner, true ) );
	}

	public function test_returns_a_line_without_sensitive_data_unchanged(): void {
		$line = self::PREFIX . '{"title":"KCO get order","request_url":"https:\/\/api.example.test\/orders\/1","headers":{}} CONTEXT: {"_legacy":true}' . "\n";

		$this->assertSame( $line, $this->masker->mask( $line ) );
	}

	public function test_keeps_placeholders_the_plugin_already_wrote(): void {
		$line = self::PREFIX . '{"request":{"headers":{"Authorization":"[MISSING]"}}}' . "\n";

		$this->assertSame( $line, $this->masker->mask( $line ) );
	}

	public function test_masks_a_json_string_message_with_embedded_json(): void {
		$line   = self::PREFIX . '"Checkout Callback received: {\"customer\":{\"email\":\"a@b.se\",\"type\":\"person\"}}" CONTEXT: {"_legacy":true}' . "\n";
		$masked = $this->masker->mask( $line );

		$this->assertStringNotContainsString( 'a@b.se', $masked );
		$this->assertStringContainsString( 'Checkout Callback received: ', $masked );
		$this->assertStringContainsString( '\"type\":\"person\"', $masked );
	}

	public function test_masks_plain_text_and_invalid_json_context(): void {
		$line   = '2026-09-29T11:50:32+00:00 ERROR [CHECKOUT]: Mail to a@b.se from 203.0.113.10 CONTEXT: [{"handler":"Krokedil\Swedbank","email":"a@b.se","order_id":376}]' . "\n";
		$masked = $this->masker->mask( $line );

		$this->assertSame(
			'2026-09-29T11:50:32+00:00 ERROR [CHECKOUT]: Mail to [REDACTED] from [REDACTED] CONTEXT: [{"handler":"Krokedil\Swedbank","email":"[REDACTED]","order_id":376}]' . "\n",
			$masked
		);
	}

	public function test_masks_header_strings(): void {
		$line   = self::PREFIX . '{"headers":["Accept: application\/json","Authorization: Basic dXNlcjpwYXNzd29yZA==","Forwarded: for=203.0.113.10; proto=https"]}' . "\n";
		$masked = $this->message( $this->masker->mask( $line ) )['headers'];

		$this->assertSame( [ 'Accept: application/json', 'Authorization: [REDACTED]', 'Forwarded: for=[REDACTED]; proto=https' ], $masked );
	}

	public function test_masks_tokens_and_order_keys_in_urls(): void {
		$line   = self::PREFIX . '{"url":"https:\/\/shop.example.test\/checkout\/order-received\/1\/?key=wc_order_AbC123","request_url":"https:\/\/api.klarna.com\/payments\/v1\/authorizations\/f00-b4r\/order"}' . "\n";
		$masked = $this->message( $this->masker->mask( $line ) );

		$this->assertSame( 'https://shop.example.test/checkout/order-received/1/?key=wc_order_[REDACTED]', $masked['url'] );
		$this->assertSame( 'https://api.klarna.com/payments/v1/authorizations/[REDACTED]/order', $masked['request_url'] );
	}

	public function test_keeps_booleans_under_sensitive_key_names(): void {
		$line   = self::PREFIX . '{"options":{"phone_mandatory":false,"date_of_birth_mandatory":true,"phone":"+46700000000","isMobileDevice":false}}' . "\n";
		$masked = $this->message( $this->masker->mask( $line ) )['options'];

		$this->assertSame( [ 'phone_mandatory' => false, 'date_of_birth_mandatory' => true, 'phone' => '[REDACTED]', 'isMobileDevice' => false ], $masked );
	}

	public function test_keeps_browser_versions_in_user_agents(): void {
		$line = self::PREFIX . '{"user_agent":"Mozilla\/5.0 Chrome\/154.0.0.0 Safari\/537.36"}' . "\n";

		$this->assertSame( $line, $this->masker->mask( $line ) );
	}

	public function test_masks_lines_in_the_old_log_format(): void {
		$line = '05-16-2025 @ 10:30:00 - {"email":"a@b.se"}' . "\n";

		$this->assertSame( '05-16-2025 @ 10:30:00 - {"email":"[REDACTED]"}' . "\n", $this->masker->mask( $line ) );
	}

	public function test_masks_a_continuation_line_as_text(): void {
		$this->assertSame( "Reply to [REDACTED]\n", $this->masker->mask( "Reply to a@b.se\n" ) );
	}
}
