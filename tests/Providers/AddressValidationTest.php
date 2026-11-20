<?php
/**
 * Google Address Validation tests.
 *
 * @package SugarCommerce\Geocoding
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Tests\Providers;

use PHPUnit\Framework\TestCase;
use SugarCommerce\Geocoding\Providers\AddressValidation;
use SugarCommerce\Geocoding\Tests\Support\FakeHttp;

/**
 * The request, the verdict, and the common answer.
 */
final class AddressValidationTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		gc_reset();
	}

	/**
	 * Google's answer for a complete UK address.
	 *
	 * @return array
	 */
	private static function answer(): array {
		return array(
			'responseId' => 'resp-1',
			'result'     => array(
				'verdict'  => array(
					'inputGranularity'      => 'PREMISE',
					'validationGranularity' => 'PREMISE',
					'geocodeGranularity'    => 'PREMISE',
					'addressComplete'       => true,
				),
				'address'  => array(
					'formattedAddress'  => '10 Downing Street, London SW1A 2AA, UK',
					'postalAddress'     => array(
						'regionCode'   => 'GB',
						'postalCode'   => 'SW1A 2AA',
						'locality'     => 'London',
						'addressLines' => array( '10 Downing Street' ),
					),
					'addressComponents' => array(
						array( 'componentName' => array( 'text' => '10' ), 'componentType' => 'street_number', 'confirmationLevel' => 'CONFIRMED' ),
						array( 'componentName' => array( 'text' => 'Downing Street' ), 'componentType' => 'route', 'confirmationLevel' => 'CONFIRMED' ),
						array( 'componentName' => array( 'text' => 'United Kingdom' ), 'componentType' => 'country', 'confirmationLevel' => 'CONFIRMED' ),
					),
				),
				'geocode'  => array(
					'location' => array( 'latitude' => 51.5034, 'longitude' => -0.1276 ),
					'placeId'  => 'ChIJ-place',
				),
				'metadata' => array( 'business' => true, 'residential' => false ),
			),
		);
	}

	/**
	 * The body sent, with the options; the verdict read.
	 *
	 * @return void
	 */
	public function test_validate(): void {
		FakeHttp::json( self::answer() );
		$client = ( new AddressValidation( 'key' ) )->set_usps()->set_language_options( 'en' )->set_session_token( 'tok' );

		$response = $client->validate(
			array(
				'regionCode'   => 'GB',
				'addressLines' => '10 Downing Street',
				'ignored'      => 'x',
			)
		);

		$this->assertTrue( $response->is_address_complete() );
		$this->assertSame( 'resp-1', $response->get_response_id() );
		$this->assertSame( 'London', $response->get_locality() );
		$this->assertTrue( $response->is_business() );

		$request = FakeHttp::last();
		$this->assertSame( 'POST', $request['method'] );
		$this->assertSame( 'https://addressvalidation.googleapis.com/v1:validateAddress', $request['url'] );
		$this->assertSame( 'key', $request['args']['headers']['X-Goog-Api-Key'] );
		$this->assertSame(
			array(
				'address'         => array(
					'regionCode'   => 'GB',
					'addressLines' => array( '10 Downing Street' ),
				),
				'enableUspsCass'  => true,
				'languageOptions' => array( 'languageCode' => 'en' ),
				'sessionToken'    => 'tok',
			),
			json_decode( $request['args']['body'], true )
		);

		$this->assertSame( array( 'enable_usps' => false ), $client->reset_options()->get_all_options() );
	}

	/**
	 * Google's error is an error; an empty address is refused unasked.
	 *
	 * @return void
	 */
	public function test_errors(): void {
		FakeHttp::json( array( 'error' => array( 'code' => 400, 'message' => 'Address is missing.' ) ), 400 );

		$this->assertSame( 'address_validation_api_error', ( new AddressValidation( 'k' ) )->validate( 'x' )->get_error_code() );
		$this->assertSame( 'address_validation_invalid_address', ( new AddressValidation( 'k' ) )->validate( ' ' )->get_error_code() );
		$this->assertSame( 'address_validation_unsupported', ( new AddressValidation( 'k' ) )->locate_point( 1, 1 )->get_error_code() );
		$this->assertSame( 1, FakeHttp::count() );
	}

	/**
	 * The same address is validated once.
	 *
	 * @return void
	 */
	public function test_cached(): void {
		FakeHttp::json( self::answer() );
		$client = new AddressValidation( 'k' );

		$client->validate( '10 Downing Street' );
		$client->validate( '10 Downing Street' );

		$this->assertSame( 1, FakeHttp::count() );
	}

	/**
	 * The common answer: the corrected address and its geocode.
	 *
	 * @return void
	 */
	public function test_locate(): void {
		FakeHttp::json( self::answer() );

		$location = ( new AddressValidation( 'k' ) )->locate( '10 downing st london' );

		$this->assertSame( 51.5034, $location->get_latitude() );
		$this->assertSame( '10 Downing Street, London SW1A 2AA, UK', $location->get_formatted_address() );
		$this->assertSame( '10', $location->get_house_number() );
		$this->assertSame( 'Downing Street', $location->get_street() );
		$this->assertSame( 'London', $location->get_city() );
		$this->assertSame( 'United Kingdom', $location->get_country() );
		$this->assertSame( 'GB', $location->get_country_code() );
		$this->assertSame( 'ChIJ-place', $location->get_place_id() );
	}

	/**
	 * An answer is kept a day, under the geocoding family's keys.
	 *
	 * @return void
	 */
	public function test_answers_are_kept_a_day(): void {
		FakeHttp::json( self::answer() );
		$client = new AddressValidation( 'k' );
		$client->validate( '10 downing st london' );

		$this->assertSame( DAY_IN_SECONDS, $client->get_cache_ttl() );
		$this->assertSame( array( $GLOBALS['gc_now'] + DAY_IN_SECONDS ), array_column( $GLOBALS['gc_transients'], 'expires' ) );
		$this->assertStringStartsWith( 'geocoding_address_validation_', (string) array_key_first( $GLOBALS['gc_transients'] ) );
	}
}
