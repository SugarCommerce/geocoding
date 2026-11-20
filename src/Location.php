<?php
/**
 * Location
 *
 * @package   SugarCommerce\Geocoding
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding;

/**
 * Class Location
 *
 * Where an address is, and what the address is, in the same shape whichever
 * service answered.
 *
 * A part the service did not give is null. The service's own response, with
 * everything this does not carry, is get_response().
 *
 * @since 1.0.0
 */
final class Location {

	/**
	 * The parts a location can hold.
	 */
	private const array PARTS = array(
		'formatted_address',
		'house_number',
		'street',
		'city',
		'county',
		'region',
		'region_code',
		'postal_code',
		'country',
		'country_code',
		'place_id',
	);

	/**
	 * The latitude.
	 *
	 * @var float|null
	 */
	private ?float $latitude;

	/**
	 * The longitude.
	 *
	 * @var float|null
	 */
	private ?float $longitude;

	/**
	 * The address's parts, each null when not given.
	 *
	 * @var array<string, string|null>
	 */
	private array $parts = array();

	/**
	 * Build a location.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $geocoder The geocoder's id.
	 * @param array       $fields   `latitude`, `longitude`, and the parts:
	 *                              `formatted_address`, `house_number`,
	 *                              `street`, `city`, `county`, `region`,
	 *                              `region_code`, `postal_code`, `country`,
	 *                              `country_code`, `place_id`.
	 * @param object|null $response The service's own response.
	 */
	public function __construct( private readonly string $geocoder, array $fields = array(), private readonly ?object $response = null ) {
		$this->latitude  = self::coordinate( $fields['latitude'] ?? null, 90 );
		$this->longitude = self::coordinate( $fields['longitude'] ?? null, 180 );

		// Half a coordinate is no place.
		if ( null === $this->latitude || null === $this->longitude ) {
			$this->latitude  = null;
			$this->longitude = null;
		}

		foreach ( self::PARTS as $part ) {
			$value = $fields[ $part ] ?? null;
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';

			$this->parts[ $part ] = '' === $value ? null : $value;
		}

		foreach ( array( 'country_code', 'region_code' ) as $code ) {
			$this->parts[ $code ] = null === $this->parts[ $code ] ? null : strtoupper( $this->parts[ $code ] );
		}
	}

	/**
	 * Which geocoder answered.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_geocoder(): string {
		return $this->geocoder;
	}

	/**
	 * Whether there are coordinates.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function has_coordinates(): bool {
		return null !== $this->latitude;
	}

	/**
	 * The latitude.
	 *
	 * @since 1.0.0
	 *
	 * @return float|null
	 */
	public function get_latitude(): ?float {
		return $this->latitude;
	}

	/**
	 * The longitude.
	 *
	 * @since 1.0.0
	 *
	 * @return float|null
	 */
	public function get_longitude(): ?float {
		return $this->longitude;
	}

	/**
	 * Both, as `latitude` and `longitude`.
	 *
	 * @since 1.0.0
	 *
	 * @return array{latitude: float, longitude: float}|null
	 */
	public function get_coordinates(): ?array {
		if ( null === $this->latitude || null === $this->longitude ) {
			return null;
		}

		return array(
			'latitude'  => $this->latitude,
			'longitude' => $this->longitude,
		);
	}

	/**
	 * The whole address on one line, as the service writes it.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_formatted_address(): ?string {
		return $this->parts['formatted_address'];
	}

	/**
	 * The house or building number.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_house_number(): ?string {
		return $this->parts['house_number'];
	}

	/**
	 * The street.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_street(): ?string {
		return $this->parts['street'];
	}

	/**
	 * The town or city.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_city(): ?string {
		return $this->parts['city'];
	}

	/**
	 * The county.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_county(): ?string {
		return $this->parts['county'];
	}

	/**
	 * The state, province or region, by name.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_region(): ?string {
		return $this->parts['region'];
	}

	/**
	 * The state, province or region, as a code: `CA`, `NSW`.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_region_code(): ?string {
		return $this->parts['region_code'];
	}

	/**
	 * The postal code.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_postal_code(): ?string {
		return $this->parts['postal_code'];
	}

	/**
	 * The country, by name.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_country(): ?string {
		return $this->parts['country'];
	}

	/**
	 * The country, as an upper-case ISO code.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_country_code(): ?string {
		return $this->parts['country_code'];
	}

	/**
	 * The service's id for the place.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_place_id(): ?string {
		return $this->parts['place_id'];
	}

	/**
	 * How far it is to another point, as the crow flies.
	 *
	 * @since 1.0.0
	 *
	 * @param Location|array $to   A location, or `lat` and `lng`.
	 * @param string         $unit `km`, `m`, `mi`, `ft` or `yd`.
	 *
	 * @return float|null Null when either has no coordinates.
	 */
	public function distance_to( Location|array $to, string $unit = 'km' ): ?float {
		$to = $to instanceof self ? (array) $to->get_coordinates() : $to;

		return null === $this->get_coordinates() ? null : Distance::between_points( $this->get_coordinates(), $to, $unit );
	}

	/**
	 * Links that open it in Google, Apple, Bing and OpenStreetMap.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Empty without coordinates.
	 */
	public function get_map_urls(): array {
		return null === $this->latitude || null === $this->longitude ? array() : Maps::urls( $this->latitude, $this->longitude );
	}

	/**
	 * The service's own response, with everything it said.
	 *
	 * @since 1.0.0
	 *
	 * @return object|null
	 */
	public function get_response(): ?object {
		return $this->response;
	}

	/**
	 * Everything, by name.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'geocoder'  => $this->geocoder,
			'latitude'  => $this->latitude,
			'longitude' => $this->longitude,
		) + $this->parts;
	}

	/**
	 * A coordinate, or null when it is not one.
	 *
	 * @param mixed $value The value.
	 * @param int   $limit The largest it can be either side of zero.
	 *
	 * @return float|null
	 */
	private static function coordinate( mixed $value, int $limit ): ?float {
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$value = (float) $value;

		return abs( $value ) <= $limit ? $value : null;
	}
}
