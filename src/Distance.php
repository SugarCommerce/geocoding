<?php
/**
 * Distance
 *
 * @package   SugarCommerce\Geocoding
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding;

use InvalidArgumentException;

/**
 * Class Distance
 *
 * How far apart two points are, as the crow flies.
 *
 * Right for "within 10 miles", wrong for a delivery estimate: roads are
 * longer than straight lines.
 *
 * @since 1.0.0
 */
final class Distance {

	/**
	 * The Earth's mean radius in each unit.
	 */
	public const RADIUS = array(
		'km' => 6371.0088,
		'mi' => 3958.7613,
	);

	/**
	 * Each unit, as how many of it make a kilometre.
	 */
	public const UNITS = array(
		'km' => 1.0,
		'm'  => 1000.0,
		'mi' => 0.621371,
		'ft' => 3280.84,
		'yd' => 1093.61,
	);

	/**
	 * The distance between two points, by the haversine formula.
	 *
	 * @since 1.0.0
	 *
	 * @param float  $lat1 The first latitude.
	 * @param float  $lng1 The first longitude.
	 * @param float  $lat2 The second latitude.
	 * @param float  $lng2 The second longitude.
	 * @param string $unit `km`, `m`, `mi`, `ft` or `yd`; anything else is km.
	 *
	 * @return float
	 */
	public static function between( float $lat1, float $lng1, float $lat2, float $lng2, string $unit = 'km' ): float {
		$a = sin( deg2rad( $lat2 - $lat1 ) / 2 ) ** 2
			+ cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( deg2rad( $lng2 - $lng1 ) / 2 ) ** 2;

		// asin() of anything over 1 is NAN, and floating point can put two
		// antipodal points a hair over it.
		$km = 2 * self::RADIUS['km'] * asin( min( 1.0, sqrt( $a ) ) );

		return 'mi' === strtolower( $unit ) ? 2 * self::RADIUS['mi'] * asin( min( 1.0, sqrt( $a ) ) ) : self::from_km( $km, self::known( $unit ) ? $unit : 'km' );
	}

	/**
	 * The distance between two points given as arrays.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $from `lat` and `lng`, or `latitude` and `longitude`.
	 * @param array  $to   The same.
	 * @param string $unit The unit.
	 *
	 * @return float|null Null when either point has no coordinates.
	 */
	public static function between_points( array $from, array $to, string $unit = 'km' ): ?float {
		$a = self::coordinates( $from );
		$b = self::coordinates( $to );

		if ( null === $a || null === $b ) {
			return null;
		}

		return self::between( $a[0], $a[1], $b[0], $b[1], $unit );
	}

	/**
	 * Kilometres in another unit.
	 *
	 * @since 1.0.0
	 *
	 * @param float  $km   Kilometres.
	 * @param string $unit `km`, `m`, `mi`, `ft` or `yd`.
	 *
	 * @return float
	 *
	 * @throws InvalidArgumentException For a unit it does not know.
	 */
	public static function from_km( float $km, string $unit ): float {
		$unit = strtolower( $unit );

		if ( ! self::known( $unit ) ) {
			throw new InvalidArgumentException( 'Not a distance unit: ' . esc_html( $unit ) );
		}

		return $km * self::UNITS[ $unit ];
	}

	/**
	 * The units it knows.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function units(): array {
		return array_keys( self::UNITS );
	}

	/**
	 * Whether a unit is known.
	 *
	 * @param string $unit The unit.
	 *
	 * @return bool
	 */
	private static function known( string $unit ): bool {
		return isset( self::UNITS[ strtolower( $unit ) ] );
	}

	/**
	 * A point's coordinates.
	 *
	 * @param array $point The point.
	 *
	 * @return array{float, float}|null
	 */
	private static function coordinates( array $point ): ?array {
		$lat = $point['lat'] ?? $point['latitude'] ?? null;
		$lng = $point['lng'] ?? $point['longitude'] ?? null;

		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return null;
		}

		return array( (float) $lat, (float) $lng );
	}
}
