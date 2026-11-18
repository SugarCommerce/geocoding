<?php
/**
 * Maps.co tests.
 *
 * @package SugarCommerce\Geocoding
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Tests\Providers;

use PHPUnit\Framework\TestCase;
use SugarCommerce\Geocoding\Providers\MapsCo;
use SugarCommerce\Geocoding\Tests\Support\FakeHttp;

/**
 * Search, reverse, the most important match, and the common answer.
 */
final class MapsCoTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		gc_reset();
	}

	/**
	 * The most important of several matches is the answer, and it is kept
	 * for a week.
	 *
	 * @return void
	 */
	public function test_geocode_picks_the_most_important(): void {
		FakeHttp::json(
			array(
				array( 'place_id' => 1, 'lat' => '1', 'lon' => '1', 'importance' => 0.2, 'display_name' => 'A minor road' ),
				array(
					'place_id'     => 2,
					'lat'          => '51.501',
					'lon'          => '-0.1419',
					'importance'   => 0.9,
					'display_name' => 'Buckingham Palace, London, SW1A 1AA, United Kingdom',
					'osm_type'     => 'way',
					'osm_id'       => '77',
					'boundingbox'  => array( '51.49', '51.51', '-0.15', '-0.13' ),
				),
			)
		);
		$client = new MapsCo( 'key' );

		$response = $client->geocode( 'Buckingham Palace' );

		$this->assertSame( 2, $response->get_place_id() );
		$this->assertSame( 'way', $response->get_osm_type() );
		$this->assertSame( 'https://www.google.com/maps/search/?api=1&query=51.501,-0.1419', $response->get_google_maps_url() );
		$this->assertSame( 'https://www.openstreetmap.org/?mlat=51.501&mlon=-0.1419&zoom=12', $response->get_map_urls()['osm'] );
		$this->assertSame( 'km', $response->get_bounding_box_dimensions()['unit'] );
		$this->assertSame( array( 'km', 'm', 'mi', 'ft', 'yd' ), $response->get_available_distance_units() );
		$this->assertStringStartsWith( 'https://geocode.maps.co/search?q=Buckingham+Palace&format=json&api_key=key', FakeHttp::last()['url'] );

		$client->geocode( 'Buckingham Palace' );
		$this->assertSame( 1, FakeHttp::count() );
		$this->assertSame( WEEK_IN_SECONDS, $client->get_cache_ttl() );
	}

	/**
	 * Nothing found is its own error.
	 *
	 * @return void
	 */
	public function test_no_results(): void {
		FakeHttp::json( array() );

		$this->assertSame( 'mapsco_no_results', ( new MapsCo( 'key' ) )->geocode( 'qwertyuiop' )->get_error_code() );
		$this->assertSame( 'mapsco_invalid_address', ( new MapsCo( 'key' ) )->geocode( '' )->get_error_code() );
	}

	/**
	 * The API's error is an error.
	 *
	 * @return void
	 */
	public function test_api_error(): void {
		FakeHttp::json( array( 'error' => 'Unable to geocode' ) );
		FakeHttp::raw( 'Too many requests', 429 );

		$this->assertSame( 'Unable to geocode', ( new MapsCo( 'k' ) )->reverse_geocode( 0.5, 0.5 )->get_error_message() );
		$this->assertSame( 'mapsco_api_error', ( new MapsCo( 'k' ) )->reverse_geocode( 1.5, 0.5 )->get_error_code() );
		$this->assertSame( 'mapsco_invalid_coordinates', ( new MapsCo( 'k' ) )->reverse_geocode( 0, 181 )->get_error_code() );
	}

	/**
	 * Reverse geocoding, answered the common way.
	 *
	 * @return void
	 */
	public function test_locate_point(): void {
		FakeHttp::json(
			array(
				'place_id'     => 9,
				'lat'          => '48.8584',
				'lon'          => '2.2945',
				'display_name' => 'Tour Eiffel, 5, Avenue Anatole France, Paris, 75007, France',
				'address'      => array(
					'house_number' => '5',
					'road'         => 'Avenue Anatole France',
					'city'         => 'Paris',
					'state'        => 'Ile-de-France',
					'postcode'     => '75007',
					'country'      => 'France',
					'country_code' => 'fr',
				),
			)
		);

		$location = ( new MapsCo( 'k', array( 'format' => 'jsonv2' ) ) )->locate_point( 48.8584, 2.2945 );

		$this->assertSame( 'mapsco', $location->get_geocoder() );
		$this->assertSame( '5', $location->get_house_number() );
		$this->assertSame( 'Avenue Anatole France', $location->get_street() );
		$this->assertSame( 'Paris', $location->get_city() );
		$this->assertSame( 'FR', $location->get_country_code() );
		$this->assertSame( '75007', $location->get_postal_code() );
		$this->assertSame( '9', $location->get_place_id() );
		$this->assertStringContainsString( 'format=jsonv2', FakeHttp::last()['url'] );
	}

	/**
	 * Clearing one answer asks again.
	 *
	 * @return void
	 */
	public function test_clear_cache(): void {
		FakeHttp::json( array( array( 'lat' => '1', 'lon' => '1' ) ) );
		FakeHttp::json( array( array( 'lat' => '2', 'lon' => '2' ) ) );
		$client = new MapsCo( 'k' );

		$client->geocode( 'Here' );
		$client->clear_cache( 'forward_Here|json' );

		$this->assertSame( 2.0, $client->geocode( 'Here' )->get_latitude() );
		$this->assertTrue( $client->clear_cache() );
		$this->assertSame( array(), $GLOBALS['gc_transients'] );
	}

	/**
	 * With a persistent object cache the transients are not in the options
	 * table, so finding them by name finds nothing -- clearing everything
	 * must still mean the next ask goes to the provider.
	 *
	 * @return void
	 */
	public function test_clear_cache_with_a_persistent_object_cache(): void {
		$GLOBALS['gc_object_cache'] = true;
		FakeHttp::json( array( array( 'lat' => '1', 'lon' => '1' ) ) );
		FakeHttp::json( array( array( 'lat' => '2', 'lon' => '2' ) ) );
		$client = new MapsCo( 'k' );

		$this->assertSame( 1.0, $client->geocode( 'Here' )->get_latitude() );
		$this->assertTrue( $client->clear_cache() );
		$this->assertSame( 2.0, $client->geocode( 'Here' )->get_latitude() );
		$this->assertSame( 2.0, $client->geocode( 'Here' )->get_latitude() );
		$this->assertSame( 2, FakeHttp::count() );
	}

	/**
	 * Answers kept before anything was ever cleared are still found.
	 *
	 * @return void
	 */
	public function test_keys_are_unchanged_until_the_first_clear(): void {
		FakeHttp::json( array( array( 'lat' => '1', 'lon' => '1' ) ) );
		( new MapsCo( 'k' ) )->geocode( 'Here' );

		$this->assertSame( array( 'geocoding_mapsco_' . md5( 'forward_Here|json|k' ) ), array_keys( $GLOBALS['gc_transients'] ) );
	}
}
