<?php
/**
 * Distance, map link and Location tests.
 *
 * @package SugarCommerce\Geocoding
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SugarCommerce\Geocoding\Distance;
use SugarCommerce\Geocoding\Maps;
use SugarCommerce\Geocoding\Models\Location;

/**
 * The pieces that need no service.
 */
final class DistanceTest extends TestCase {

	/**
	 * London to Paris is about 344 km, 213.7 miles.
	 *
	 * @return void
	 */
	public function test_london_to_paris(): void {
		$this->assertEqualsWithDelta( 343.5, Distance::between( 51.5074, -0.1278, 48.8566, 2.3522 ), 0.5 );
		$this->assertEqualsWithDelta( 213.4, Distance::between( 51.5074, -0.1278, 48.8566, 2.3522, 'MI' ), 0.5 );
		$this->assertEqualsWithDelta( 343500, Distance::between( 51.5074, -0.1278, 48.8566, 2.3522, 'm' ), 500 );
		$this->assertSame( Distance::between( 1, 2, 3, 4 ), Distance::between( 1, 2, 3, 4, 'furlongs' ) );
	}

	/**
	 * The same point is no distance; opposite sides of the Earth are half its
	 * circumference, not NAN.
	 *
	 * @return void
	 */
	public function test_edges(): void {
		$this->assertSame( 0.0, Distance::between( 10, 10, 10, 10 ) );
		$this->assertEqualsWithDelta( M_PI * Distance::RADIUS['km'], Distance::between( 0, 0, 0, 180 ), 0.001 );

		// Rounding puts these two a hair over the top of asin().
		$this->assertEqualsWithDelta( M_PI * Distance::RADIUS['km'], Distance::between( 47.81677, 43.07114, -47.81677, -136.92886 ), 0.001 );
	}

	/**
	 * Points as arrays, either way of naming them; null without coordinates.
	 *
	 * @return void
	 */
	public function test_between_points(): void {
		$this->assertSame( Distance::between( 1, 2, 3, 4 ), Distance::between_points( array( 'lat' => 1, 'lng' => 2 ), array( 'latitude' => '3', 'longitude' => 4 ) ) );
		$this->assertNull( Distance::between_points( array( 'lat' => 1 ), array( 'lat' => 3, 'lng' => 4 ) ) );
	}

	/**
	 * Units convert from kilometres, and an unknown one is refused.
	 *
	 * @return void
	 */
	public function test_units(): void {
		$this->assertSame( 1000.0, Distance::from_km( 1, 'M' ) );
		$this->assertSame( array( 'km', 'm', 'mi', 'ft', 'yd' ), Distance::units() );

		$this->expectException( InvalidArgumentException::class );
		Distance::from_km( 1, 'parsec' );
	}

	/**
	 * Map links, written with a dot and no exponent.
	 *
	 * @return void
	 */
	public function test_map_links(): void {
		$this->assertSame(
			array(
				'google' => 'https://www.google.com/maps/search/?api=1&query=51.5,-0.0000001',
				'apple'  => 'https://maps.apple.com/?q=51.5,-0.0000001',
				'bing'   => 'https://www.bing.com/maps?cp=51.5~-0.0000001',
				'osm'    => 'https://www.openstreetmap.org/?mlat=51.5&mlon=-0.0000001&zoom=12',
			),
			Maps::urls( 51.5, -0.0000001 )
		);
	}

	/**
	 * A location cleans what it is given: half a coordinate is none, codes
	 * are upper case, empty parts are null.
	 *
	 * @return void
	 */
	public function test_location(): void {
		$location = new Location(
			'test',
			array(
				'latitude'     => '51.5',
				'longitude'    => -0.12,
				'city'         => ' London ',
				'street'       => '',
				'country_code' => 'gb',
				'region_code'  => 'eng',
				'ignored'      => 'x',
			)
		);

		$this->assertTrue( $location->has_coordinates() );
		$this->assertSame( array( 'latitude' => 51.5, 'longitude' => -0.12 ), $location->get_coordinates() );
		$this->assertSame( 'London', $location->get_city() );
		$this->assertNull( $location->get_street() );
		$this->assertSame( 'GB', $location->get_country_code() );
		$this->assertSame( 'ENG', $location->get_region_code() );
		$this->assertArrayNotHasKey( 'ignored', $location->to_array() );
		$this->assertCount( 4, $location->get_map_urls() );
		$this->assertEqualsWithDelta( 343.5, $location->distance_to( array( 'lat' => 48.8566, 'lng' => 2.3522 ) ), 1 );
		$this->assertEqualsWithDelta( 0.0, $location->distance_to( $location ), 0.0001 );

		$half = new Location( 'test', array( 'latitude' => 51.5, 'longitude' => 'x' ) );
		$this->assertFalse( $half->has_coordinates() );
		$this->assertNull( $half->get_latitude() );
		$this->assertSame( array(), $half->get_map_urls() );
		$this->assertNull( $half->distance_to( $location ) );

		$this->assertNull( ( new Location( 'test', array( 'latitude' => 91, 'longitude' => 0 ) ) )->get_coordinates() );
	}
}
