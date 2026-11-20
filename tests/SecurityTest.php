<?php
/**
 * Security tests: what an address from a visitor or an answer from a
 * service must not be able to do.
 *
 * @package SugarCommerce\Geocoding
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Tests;

use PHPUnit\Framework\TestCase;
use SugarCommerce\Geocoding\Location;
use SugarCommerce\Geocoding\Providers\AddressValidation;
use SugarCommerce\Geocoding\Providers\Google;
use SugarCommerce\Geocoding\Providers\MapsCo;
use SugarCommerce\Geocoding\Tests\Support\FakeHttp;
use WP_Error;

/**
 * Input is validated, answers are untrusted data.
 */
final class SecurityTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		gc_reset();
	}

	/**
	 * The query a request was sent with.
	 *
	 * @return array{query: array, fragment: string|null}
	 */
	private static function sent(): array {
		$url = (string) FakeHttp::last()['url'];

		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );

		return array(
			'query'    => $query,
			'fragment' => parse_url( $url, PHP_URL_FRAGMENT ),
		);
	}

	/**
	 * An address is one parameter, however it is written: it cannot add
	 * parameters of its own, replace the key, or cut the request short
	 * with a `#`.
	 *
	 * @return void
	 */
	public function test_address_cannot_add_to_the_query(): void {
		$address = '1 Main St&key=someone-elses&components=country:FR#';

		FakeHttp::json( array( 'status' => 'ZERO_RESULTS', 'results' => array() ) );
		( new Google( 'real-key' ) )->geocode( $address );

		$sent = self::sent();

		$this->assertSame( $address, $sent['query']['address'] ?? null );
		$this->assertSame( 'real-key', $sent['query']['key'] ?? null );
		$this->assertArrayNotHasKey( 'components', $sent['query'] );
		$this->assertNull( $sent['fragment'] );

		FakeHttp::json( array() );
		( new MapsCo( 'real-key' ) )->geocode( '10 Rue de Rivoli&api_key=x&format=xml#' );

		$sent = self::sent();

		$this->assertSame( '10 Rue de Rivoli&api_key=x&format=xml#', $sent['query']['q'] ?? null );
		$this->assertSame( 'real-key', $sent['query']['api_key'] ?? null );
		$this->assertSame( 'json', $sent['query']['format'] ?? null );
		$this->assertNull( $sent['fragment'] );
	}

	/**
	 * An address that cannot be one -- too long, control characters,
	 * broken UTF-8 -- is refused before a request is spent on it, by every
	 * geocoder. A line break is a space.
	 *
	 * @return void
	 */
	public function test_address_is_checked_before_it_is_sent(): void {
		$geocoders = array( new Google( 'k' ), new MapsCo( 'k' ), new AddressValidation( 'k' ) );
		$refused   = array( str_repeat( 'a', 1001 ), "1 Main\x00 St", "1 Main St\x1b[31m", "1 Main St \xC3\x28" );

		foreach ( $geocoders as $geocoder ) {
			foreach ( $refused as $address ) {
				$error = $geocoder->locate( $address );

				$this->assertInstanceOf( WP_Error::class, $error );
				$this->assertSame( $geocoder->get_id() . '_invalid_address', $error->get_error_code() );
			}
		}

		$this->assertSame( 0, FakeHttp::count() );

		FakeHttp::json( array( 'status' => 'ZERO_RESULTS', 'results' => array() ) );
		( new Google( 'k' ) )->geocode( "1 Main St\r\nLondon" );

		$this->assertSame( '1 Main St London', self::sent()['query']['address'] ?? null );
	}

	/**
	 * A field of the wrong type is no answer, not a fatal error on the
	 * page that asked: Google.
	 *
	 * @return void
	 */
	public function test_google_field_of_the_wrong_type_is_no_answer(): void {
		FakeHttp::json(
			array(
				'status'  => 'OK',
				'results' => array(
					array(
						'formatted_address'  => array( 'nested' ),
						'place_id'           => 42,
						'types'              => 'premise',
						'geometry'           => array( 'location' => array( 'lat' => '51.5', 'lng' => array() ) ),
						'address_components' => array(
							array( 'long_name' => 'Downing Street', 'types' => 'route' ),
							'not a component',
							array( 'long_name' => array( 'London' ), 'short_name' => 7, 'types' => array( 'locality', 'country' ) ),
						),
					),
				),
			)
		);

		$client   = new Google( 'k' );
		$location = $client->locate( '10 Downing Street' );

		$this->assertInstanceOf( Location::class, $location );
		$this->assertFalse( $location->has_coordinates() );
		$this->assertNull( $location->get_formatted_address() );

		$response = $location->get_response();

		$this->assertNull( $response->get_latitude() );
		$this->assertNull( $response->get_street_name() );
		$this->assertNull( $response->get_city() );
		$this->assertSame( '7', $response->get_country_short() );
		$this->assertSame( '42', $response->get_place_id() );
		$this->assertSame( array(), $response->get_types() );
		$this->assertFalse( $response->is_business_location() );
		$this->assertCount( 0, $response->get_address_components_by_types( array( 'route' ) ) );
	}

	/**
	 * The same for Maps.co.
	 *
	 * @return void
	 */
	public function test_mapsco_field_of_the_wrong_type_is_no_answer(): void {
		FakeHttp::json(
			array(
				array(
					'lat'          => 'north',
					'lon'          => '2.29',
					'display_name' => array( 'Tour Eiffel' ),
					'type'         => 7,
					'address'      => 'Paris',
					'boundingbox'  => array( '48.85' ),
					'importance'   => 'high',
				),
			)
		);

		$location = ( new MapsCo( 'k' ) )->locate( 'Tour Eiffel' );

		$this->assertInstanceOf( Location::class, $location );
		$this->assertFalse( $location->has_coordinates() );

		$response = $location->get_response();

		$this->assertNull( $response->get_latitude() );
		$this->assertNull( $response->get_display_name() );
		$this->assertSame( '7', $response->get_type() );
		$this->assertNull( $response->get_address() );
		$this->assertNull( $response->get_city() );
		$this->assertNull( $response->get_bounding_box() );
	}

	/**
	 * The same for Address Validation.
	 *
	 * @return void
	 */
	public function test_address_validation_field_of_the_wrong_type_is_no_answer(): void {
		FakeHttp::json(
			array(
				'result' => array(
					'verdict'    => 'complete',
					'address'    => array(
						'formattedAddress'  => array( 'x' ),
						'postalAddress'     => array( 'regionCode' => array( 'GB' ), 'locality' => 5, 'addressLines' => 'one line' ),
						'addressComponents' => array( 'not a component', array( 'componentType' => array( 'route' ) ) ),
						'missingComponentTypes' => 'none',
					),
					'geocode'    => array( 'location' => array( 'latitude' => 'x', 'longitude' => 1 ), 'placeId' => array() ),
					'metadata'   => 'none',
					'uspsData'   => array( 'dpvConfirmation' => array( 'Y' ), 'standardizedAddress' => 'x' ),
				),
				'responseId' => 7,
			)
		);

		$response = ( new AddressValidation( 'k' ) )->validate( '10 Downing Street' );

		$this->assertNull( $response->get_formatted_address() );
		$this->assertNull( $response->get_region_code() );
		$this->assertSame( '5', $response->get_locality() );
		$this->assertSame( array(), $response->get_address_lines() );
		$this->assertNull( $response->get_address_component( 'route' ) );
		$this->assertSame( array(), $response->get_missing_component_types() );
		$this->assertNull( $response->get_geocode() );
		$this->assertNull( $response->get_place_id() );
		$this->assertNull( $response->get_metadata() );
		$this->assertFalse( $response->is_business() );
		$this->assertNull( $response->get_dpv_confirmation() );
		$this->assertSame( '7', $response->get_response_id() );
		$this->assertIsInt( $response->get_score() );
		$this->assertIsString( $response->get_rating() );
		$this->assertIsArray( $response->check_validity() );
		$this->assertIsArray( $response->get_standardized_address() );
		$this->assertIsBool( $response->is_shippable() );
	}
}

