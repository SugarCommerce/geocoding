<?php
/**
 * Google Geocoding tests.
 *
 * @package SugarCommerce\Geocoding
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Tests\Providers;

use PHPUnit\Framework\TestCase;
use SugarCommerce\Geocoding\Providers\Google;
use SugarCommerce\Geocoding\Providers\Google\Response;
use SugarCommerce\Geocoding\Tests\Support\FakeHttp;

/**
 * Forward, reverse, statuses, the cache and the common answer.
 */
final class GoogleTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		gc_reset();
	}

	/**
	 * Google's answer for 10 Downing Street.
	 *
	 * @return array
	 */
	private static function downing_street(): array {
		return array(
			'status'  => 'OK',
			'results' => array(
				array(
					'formatted_address'  => '10 Downing St, London SW1A 2AA, UK',
					'place_id'           => 'ChIJRxzRQcUEdkgRGVaKyzmkgvg',
					'types'              => array( 'premise', 'establishment' ),
					'geometry'           => array(
						'location'      => array( 'lat' => 51.5033635, 'lng' => -0.1276248 ),
						'location_type' => 'ROOFTOP',
					),
					'address_components' => array(
						array( 'long_name' => '10', 'short_name' => '10', 'types' => array( 'street_number' ) ),
						array( 'long_name' => 'Downing Street', 'short_name' => 'Downing St', 'types' => array( 'route' ) ),
						array( 'long_name' => 'London', 'short_name' => 'London', 'types' => array( 'postal_town' ) ),
						array( 'long_name' => 'England', 'short_name' => 'England', 'types' => array( 'administrative_area_level_1', 'political' ) ),
						array( 'long_name' => 'United Kingdom', 'short_name' => 'GB', 'types' => array( 'country', 'political' ) ),
						array( 'long_name' => 'SW1A 2AA', 'short_name' => 'SW1A 2AA', 'types' => array( 'postal_code' ) ),
					),
				),
			),
		);
	}

	/**
	 * An address, read by name, with the key in the query.
	 *
	 * @return void
	 */
	public function test_geocode(): void {
		FakeHttp::json( self::downing_street() );

		$response = ( new Google( 'key' ) )->geocode( ' 10 Downing Street, London ', array( 'region' => 'uk' ) );

		$this->assertInstanceOf( Response::class, $response );
		$this->assertSame( 51.5033635, $response->get_latitude() );
		$this->assertSame( 'ROOFTOP', $response->get_location_type() );
		$this->assertSame( 'Downing Street', $response->get_street_name() );
		$this->assertSame( 'GB', $response->get_country_short() );
		$this->assertTrue( $response->is_business_location() );
		$this->assertFalse( $response->is_partial_match() );
		$this->assertSame( 'OK', $response->get_status() );

		$url = FakeHttp::last()['url'];
		$this->assertStringStartsWith( 'https://maps.googleapis.com/maps/api/geocode/json?address=10+Downing+Street%2C+London&region=uk&key=key', $url );
	}

	/**
	 * A refusal is an error, with Google's message; nothing found is an
	 * answer with no results.
	 *
	 * @return void
	 */
	public function test_statuses(): void {
		FakeHttp::json( array( 'status' => 'REQUEST_DENIED', 'error_message' => 'The provided API key is invalid.' ) );
		FakeHttp::json( array( 'status' => 'ZERO_RESULTS', 'results' => array() ) );

		$error = ( new Google( 'bad' ) )->geocode( 'x' );
		$this->assertSame( 'google_api_error', $error->get_error_code() );
		$this->assertSame( 'REQUEST_DENIED: The provided API key is invalid.', $error->get_error_message() );

		$nothing = ( new Google( 'key' ) )->geocode( 'nowhere at all' );
		$this->assertSame( array(), $nothing->get_results() );
		$this->assertSame( 'google_no_results', ( new Google( 'key' ) )->locate( 'nowhere at all' )->get_error_code() );
		$this->assertSame( 2, FakeHttp::count() );
	}

	/**
	 * An empty address and impossible coordinates are refused before any
	 * request.
	 *
	 * @return void
	 */
	public function test_refused_before_asking(): void {
		$this->assertSame( 'google_invalid_address', ( new Google( 'k' ) )->geocode( '  ' )->get_error_code() );
		$this->assertSame( 'google_invalid_coordinates', ( new Google( 'k' ) )->reverse_geocode( 91, 0 )->get_error_code() );
		$this->assertSame( 0, FakeHttp::count() );
	}

	/**
	 * Reverse geocoding, cached, and failures remembered.
	 *
	 * @return void
	 */
	public function test_reverse_and_cache(): void {
		FakeHttp::json( self::downing_street() );
		FakeHttp::fail();
		$client = new Google( 'key' );

		$client->reverse_geocode( 51.5033635, -0.1276248 );
		$client->reverse_geocode( 51.5033635, -0.1276248 );
		$this->assertStringContainsString( 'latlng=51.5033635%2C-0.1276248', FakeHttp::last()['url'] );
		$this->assertSame( 1, FakeHttp::count() );

		$this->assertSame( 'google_http_error', $client->reverse_geocode( 1, 1 )->get_error_code() );
		$this->assertSame( 'google_recent_failure', $client->reverse_geocode( 1, 1 )->get_error_code() );
		$this->assertSame( 2, FakeHttp::count() );
		$this->assertSame( DAY_IN_SECONDS, $client->get_cache_ttl() );
	}

	/**
	 * Two keys never share an answer.
	 *
	 * @return void
	 */
	public function test_cache_is_per_key(): void {
		FakeHttp::json( array( 'status' => 'ZERO_RESULTS', 'results' => array() ) );
		FakeHttp::json( self::downing_street() );

		( new Google( 'one' ) )->geocode( '10 Downing Street' );

		$this->assertSame( 'OK', ( new Google( 'two' ) )->geocode( '10 Downing Street' )->get_status() );
	}

	/**
	 * The common answer.
	 *
	 * @return void
	 */
	public function test_locate(): void {
		FakeHttp::json( self::downing_street() );

		$location = ( new Google( 'key' ) )->locate( '10 Downing Street' );

		$this->assertSame( 'google', $location->get_geocoder() );
		$this->assertSame( '10', $location->get_house_number() );
		$this->assertSame( 'Downing Street', $location->get_street() );
		$this->assertSame( 'SW1A 2AA', $location->get_postal_code() );
		$this->assertSame( 'GB', $location->get_country_code() );
		$this->assertSame( 'England', $location->get_region() );
		$this->assertSame( 'ChIJRxzRQcUEdkgRGVaKyzmkgvg', $location->get_place_id() );
		$this->assertInstanceOf( Response::class, $location->get_response() );
	}
}
