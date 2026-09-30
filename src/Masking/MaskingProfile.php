<?php
namespace Krokedil\LogParser\Masking;

/**
 * The key names and field rules used to mask a log.
 *
 * The key names are added to the wp-api KeyMasker defaults and are matched case insensitively
 * as substrings, with '-' read as '_'. The field rules use the wp-api FieldMasker format.
 */
class MaskingProfile {
	/**
	 * Key names masked in every Krokedil plugin log, in both snake and camel case. Containers
	 * such as billing_address are not listed, so their postal code, city and country stay readable.
	 */
	const KROKEDIL_KEYS = [
		// Names.
		'given_name',
		'givenname',
		'family_name',
		'familyname',
		'first_name',
		'firstname',
		'last_name',
		'lastname',
		'full_name',
		'fullname',
		'organization_name',
		// Contact details.
		'email',
		'phone',
		'mobile',
		'msisdn',
		// Street addresses.
		'street',
		'address_1',
		'address_2',
		'address1',
		'address2',
		'address_line',
		'careof',
		'house_number',
		'housenumber',
		'door_code',
		'doorcode',
		// Identity.
		'birth',
		'national_identification',
		'personal_number',
		'personalnumber',
		'ssn',
		'organization_registration_id',
		'vat_id',
		'gender',
		'loyalty',
		'customer_ip',
		'ip_address',
		'ipaddress',
		// Payment cards.
		'cardexpiration',
		'card_expiration',
		// Shipment tracking identifies a delivery, and with it a person.
		'tracking',
		// Hosted checkout URLs and snippets are single use capabilities.
		'redirect_url',
		'distribution_url',
		'qr_code_url',
		'session_url',
		'html_snippet',
		'htmlsnippet',
		// Keys and codes not covered by the package defaults.
		'signing_key',
		'order_key',
		'orderkey',
		'access_code',
		'access_link',
		'jwt',
	];

	/**
	 * Field rules for every Krokedil plugin log.
	 */
	const KROKEDIL_FIELDS = [
		// The Klarna EMD attachment repeats the customer details.
		'attachment' => 'mask',
	];

	/**
	 * Key names to mask.
	 *
	 * @var string[]
	 */
	private array $keys;

	/**
	 * Field rules.
	 *
	 * @var array
	 */
	private array $fields;

	/**
	 * Create a profile.
	 *
	 * @param string[] $keys   Key names to mask, on top of the wp-api KeyMasker defaults.
	 * @param array    $fields Field rules in the wp-api FieldMasker format.
	 */
	public function __construct( array $keys = [], array $fields = [] ) {
		$this->keys   = $keys;
		$this->fields = $fields;
	}

	/**
	 * The profile for logs from any Krokedil plugin.
	 *
	 * @return self
	 */
	public static function krokedil(): self {
		return new self( self::KROKEDIL_KEYS, self::KROKEDIL_FIELDS );
	}

	/**
	 * The key names to mask.
	 *
	 * @return string[]
	 */
	public function get_keys(): array {
		return $this->keys;
	}

	/**
	 * The field rules.
	 *
	 * @return array
	 */
	public function get_fields(): array {
		return $this->fields;
	}
}
