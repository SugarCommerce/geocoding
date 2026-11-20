<?php
/**
 * Maps.co response
 *
 * @package   SugarCommerce\Geocoding
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Models\MapsCo;

use SugarCommerce\Geocoding\Distance;
use SugarCommerce\Geocoding\Maps;
use SugarCommerce\Geocoding\Support\Field;
use SugarCommerce\Network\Models\Remote\Response as Base;

/**
 * Class Response
 *
 * Maps.co's answer for one place: OpenStreetMap's coordinates, names and
 * address parts, with map links and the size of its bounding box.
 *
 * @since 1.0.0
 */
class Response extends Base {

	/**
	 * Get the latitude coordinate
	 *
	 * @return float|null
	 */
	public function get_latitude(): ?float {
		return Field::float( $this->data['lat'] ?? null );
	}

	/**
	 * Get the longitude coordinate
	 *
	 * @return float|null
	 */
	public function get_longitude(): ?float {
		return Field::float( $this->data['lon'] ?? null );
	}

	/**
	 * Get coordinates as an array
	 *
	 * @return array|null Returns array with latitude and longitude or null if not available
	 */
	public function get_coordinates(): ?array {
		$lat = $this->get_latitude();
		$lon = $this->get_longitude();

		if ( $lat === null || $lon === null ) {
			return null;
		}

		return [
			'latitude'  => $lat,
			'longitude' => $lon,
		];
	}

	/**
	 * Get Google Maps URL for the coordinates
	 *
	 * @return string|null URL to view location on Google Maps
	 */
	public function get_google_maps_url(): ?string {
		$coordinates = $this->get_coordinates();

		return null === $coordinates ? null : Maps::google( $coordinates['latitude'], $coordinates['longitude'] );
	}

	/**
	 * Get Apple Maps URL for the coordinates
	 *
	 * @return string|null URL to view location on Apple Maps
	 */
	public function get_apple_maps_url(): ?string {
		$coordinates = $this->get_coordinates();

		return null === $coordinates ? null : Maps::apple( $coordinates['latitude'], $coordinates['longitude'] );
	}

	/**
	 * Get Bing Maps URL for the coordinates
	 *
	 * @return string|null URL to view location on Bing Maps
	 */
	public function get_bing_maps_url(): ?string {
		$coordinates = $this->get_coordinates();

		return null === $coordinates ? null : Maps::bing( $coordinates['latitude'], $coordinates['longitude'] );
	}

	/**
	 * Get OpenStreetMap URL for the coordinates
	 *
	 * @return string|null URL to view location on OpenStreetMap
	 */
	public function get_openstreetmap_url(): ?string {
		$coordinates = $this->get_coordinates();

		return null === $coordinates ? null : Maps::openstreetmap( $coordinates['latitude'], $coordinates['longitude'] );
	}

	/**
	 * Get all map URLs for the coordinates
	 *
	 * @return array Array of map service URLs
	 */
	public function get_map_urls(): array {
		return [
			'google' => $this->get_google_maps_url(),
			'apple'  => $this->get_apple_maps_url(),
			'bing'   => $this->get_bing_maps_url(),
			'osm'    => $this->get_openstreetmap_url(),
		];
	}

	/**
	 * Get the formatted display name
	 *
	 * @return string|null
	 */
	public function get_display_name(): ?string {
		return Field::text( $this->data['display_name'] ?? null );
	}

	/**
	 * Get the place ID from OpenStreetMap
	 *
	 * @return int|null
	 */
	public function get_place_id(): ?int {
		return Field::int( $this->data['place_id'] ?? null );
	}

	/**
	 * Get the OSM type (node, way, relation)
	 *
	 * @return string|null
	 */
	public function get_osm_type(): ?string {
		return Field::text( $this->data['osm_type'] ?? null );
	}

	/**
	 * Get the OSM ID
	 *
	 * @return int|null
	 */
	public function get_osm_id(): ?int {
		return Field::int( $this->data['osm_id'] ?? null );
	}

	/**
	 * Get the location class (e.g., office, building, tourism)
	 *
	 * @return string|null
	 */
	public function get_class(): ?string {
		return Field::text( $this->data['class'] ?? null );
	}

	/**
	 * Get the location type (e.g., government, yes, information)
	 *
	 * @return string|null
	 */
	public function get_type(): ?string {
		return Field::text( $this->data['type'] ?? null );
	}

	/**
	 * Get the location importance ranking
	 *
	 * @return float|null
	 */
	public function get_importance(): ?float {
		return Field::float( $this->data['importance'] ?? null );
	}

	/**
	 * Get the license information
	 *
	 * @return string|null
	 */
	public function get_license(): ?string {
		return Field::text( $this->data['licence'] ?? null );
	}

	/**
	 * Get the bounding box coordinates
	 *
	 * @return array|null Array with min/max lat/lon values or null
	 */
	public function get_bounding_box(): ?array {
		$box    = Field::map( $this->data['boundingbox'] ?? null ) ?? [];
		$values = array_map( fn ( $i ) => Field::float( $box[ $i ] ?? null ), [ 0, 1, 2, 3 ] );

		if ( in_array( null, $values, true ) ) {
			return null;
		}

		return [
			'min_lat' => $values[0],
			'max_lat' => $values[1],
			'min_lon' => $values[2],
			'max_lon' => $values[3],
		];
	}

	/**
	 * Check if the location has a bounding box
	 *
	 * @return bool
	 */
	public function has_bounding_box(): bool {
		return null !== $this->get_bounding_box();
	}


	/**
	 * Get the bounding box dimensions in specified unit
	 *
	 * @param string $unit Unit of measurement (km, m, mi, ft, yd)
	 *
	 * @return array|null Width and height in specified unit, or null if bounding box not available
	 * @throws \InvalidArgumentException If invalid unit specified
	 */
	public function get_bounding_box_dimensions( string $unit = 'km' ): ?array {
		$box = $this->get_bounding_box();
		if ( ! $box ) {
			return null;
		}

		$earth_radius_km = 6371;

		$lat_distance = deg2rad( $box['max_lat'] - $box['min_lat'] );
		$lon_distance = deg2rad( $box['max_lon'] - $box['min_lon'] );
		$lat_center   = deg2rad( ( $box['max_lat'] + $box['min_lat'] ) / 2 );

		$width_km  = $earth_radius_km * $lon_distance * cos( $lat_center );
		$height_km = $earth_radius_km * $lat_distance;

		return [
			'width'  => Distance::from_km( abs( $width_km ), $unit ),
			'height' => Distance::from_km( abs( $height_km ), $unit ),
			'unit'   => $unit,
		];
	}

	/**
	 * Get the approximate radius of the bounding box in specified unit
	 *
	 * @param string $unit Unit of measurement (km, m, mi, ft, yd)
	 *
	 * @return array|null Radius in specified unit and unit type, or null if bounding box not available
	 * @throws \InvalidArgumentException If invalid unit specified
	 */
	public function get_bounding_box_radius( string $unit = 'km' ): ?array {
		$box = $this->get_bounding_box();
		if ( ! $box ) {
			return null;
		}

		$earth_radius_km = 6371;

		$lat_center = ( $box['max_lat'] + $box['min_lat'] ) / 2;
		$lon_center = ( $box['max_lon'] + $box['min_lon'] ) / 2;

		$corner_lat = $box['max_lat'];
		$corner_lon = $box['max_lon'];

		$lat_distance = deg2rad( $corner_lat - $lat_center );
		$lon_distance = deg2rad( $corner_lon - $lon_center );

		$a = sin( $lat_distance / 2 ) ** 2 +
			cos( deg2rad( $lat_center ) ) * cos( deg2rad( $corner_lat ) ) * sin( $lon_distance / 2 ) ** 2;
		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		$radius_km = $earth_radius_km * $c;

		return [
			'radius' => Distance::from_km( $radius_km, $unit ),
			'unit'   => $unit,
		];
	}

	/**
	 * Get all available distance units
	 *
	 * @return array Array of supported distance units
	 */
	public function get_available_distance_units(): array {
		return Distance::units();
	}

	/**
	 * Get all address components
	 *
	 * @return array|null
	 */
	public function get_address(): ?array {
		$address = Field::map( $this->data['address'] ?? null );

		if ( null !== $address ) {
			return $address;
		}

		// For forward geocoding, try to parse from display_name
		if ( null !== $this->get_display_name() ) {
			$parts   = explode( ', ', $this->get_display_name() );
			$address = [];

			// Last part is always country
			if ( count( $parts ) > 0 ) {
				$address['country'] = array_pop( $parts );
			}

			// Second to last is usually postal code
			if ( count( $parts ) > 0 ) {
				$address['postcode'] = array_pop( $parts );
			}

			// Third to last is usually state/region
			if ( count( $parts ) > 0 ) {
				$address['state'] = array_pop( $parts );
			}

			// Fourth to last is usually city
			if ( count( $parts ) > 0 ) {
				$address['city'] = array_pop( $parts );
			}

			// First part usually contains the street info
			if ( count( $parts ) > 0 ) {
				$address['road'] = implode( ', ', $parts );
			}

			return $address;
		}

		return null;
	}

	/**
	 * Get a specific address component
	 *
	 * @param string $component Component name (e.g., 'city', 'country', 'postcode')
	 *
	 * @return string|null
	 */
	public function get_address_component( string $component ): ?string {
		return Field::text( $this->get_address()[ $component ] ?? null );
	}

	/**
	 * Get the house number
	 *
	 * @return string|null
	 */
	public function get_house_number(): ?string {
		return $this->get_address_component( 'house_number' );
	}

	/**
	 * Get the street name
	 *
	 * @return string|null
	 */
	public function get_street(): ?string {
		return $this->get_address_component( 'road' );
	}

	/**
	 * Get the city/town name
	 *
	 * @return string|null
	 */
	public function get_city(): ?string {
		return $this->get_address_component( 'city' );
	}

	/**
	 * Get the state/province name
	 *
	 * @return string|null
	 */
	public function get_state(): ?string {
		return $this->get_address_component( 'state' );
	}

	/**
	 * Get the postal/zip code
	 *
	 * @return string|null
	 */
	public function get_postcode(): ?string {
		return $this->get_address_component( 'postcode' );
	}

	/**
	 * Get the country name
	 *
	 * @return string|null
	 */
	public function get_country(): ?string {
		return $this->get_address_component( 'country' );
	}

	/**
	 * Get the country code (ISO 3166-1 alpha-2)
	 *
	 * @return string|null
	 */
	public function get_country_code(): ?string {
		$code = $this->get_address_component( 'country_code' );

		return $code ? strtoupper( $code ) : null;
	}

	/**
	 * Get the borough/district name
	 *
	 * @return string|null
	 */
	public function get_borough(): ?string {
		return $this->get_address_component( 'borough' );
	}
}
