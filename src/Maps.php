<?php
/**
 * Maps
 *
 * @package   SugarCommerce\Geocoding
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding;

/**
 * Class Maps
 *
 * Links that open a point in a map somebody already has: Google, Apple,
 * Bing or OpenStreetMap. Escape them where they are printed.
 *
 * @since 1.0.0
 */
final class Maps {

	/**
	 * A Google Maps link.
	 *
	 * @since 1.0.0
	 *
	 * @param float $latitude  The latitude.
	 * @param float $longitude The longitude.
	 *
	 * @return string
	 */
	public static function google( float $latitude, float $longitude ): string {
		return 'https://www.google.com/maps/search/?api=1&query=' . self::pair( $latitude, $longitude, ',' );
	}

	/**
	 * An Apple Maps link.
	 *
	 * @since 1.0.0
	 *
	 * @param float $latitude  The latitude.
	 * @param float $longitude The longitude.
	 *
	 * @return string
	 */
	public static function apple( float $latitude, float $longitude ): string {
		return 'https://maps.apple.com/?q=' . self::pair( $latitude, $longitude, ',' );
	}

	/**
	 * A Bing Maps link.
	 *
	 * @since 1.0.0
	 *
	 * @param float $latitude  The latitude.
	 * @param float $longitude The longitude.
	 *
	 * @return string
	 */
	public static function bing( float $latitude, float $longitude ): string {
		return 'https://www.bing.com/maps?cp=' . self::pair( $latitude, $longitude, '~' );
	}

	/**
	 * An OpenStreetMap link, with a marker.
	 *
	 * @since 1.0.0
	 *
	 * @param float $latitude  The latitude.
	 * @param float $longitude The longitude.
	 *
	 * @return string
	 */
	public static function openstreetmap( float $latitude, float $longitude ): string {
		return 'https://www.openstreetmap.org/?mlat=' . self::number( $latitude ) . '&mlon=' . self::number( $longitude ) . '&zoom=12';
	}

	/**
	 * All four, by name: `google`, `apple`, `bing`, `osm`.
	 *
	 * @since 1.0.0
	 *
	 * @param float $latitude  The latitude.
	 * @param float $longitude The longitude.
	 *
	 * @return array<string, string>
	 */
	public static function urls( float $latitude, float $longitude ): array {
		return array(
			'google' => self::google( $latitude, $longitude ),
			'apple'  => self::apple( $latitude, $longitude ),
			'bing'   => self::bing( $latitude, $longitude ),
			'osm'    => self::openstreetmap( $latitude, $longitude ),
		);
	}

	/**
	 * Two coordinates, joined.
	 *
	 * @param float  $latitude  The latitude.
	 * @param float  $longitude The longitude.
	 * @param string $glue      What goes between.
	 *
	 * @return string
	 */
	private static function pair( float $latitude, float $longitude, string $glue ): string {
		return self::number( $latitude ) . $glue . self::number( $longitude );
	}

	/**
	 * A coordinate written with a dot whatever the locale, and no exponent.
	 *
	 * @param float $value The coordinate.
	 *
	 * @return string
	 */
	private static function number( float $value ): string {
		return rtrim( rtrim( sprintf( '%.7F', $value ), '0' ), '.' );
	}
}
