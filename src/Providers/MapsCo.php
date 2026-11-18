<?php
/**
 * Maps.co
 *
 * @package   SugarCommerce\Geocoding
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Providers;

use SugarCommerce\Geocoding\Location;
use SugarCommerce\Geocoding\Providers\MapsCo\Response;
use SugarCommerce\Geocoding\Service;
use WP_Error;

/**
 * Class MapsCo
 *
 * Maps.co (https://geocode.maps.co): geocoding on OpenStreetMap's data,
 * without a billing account. Less precise than Google on a single building,
 * entirely adequate for a town, a postcode or a delivery radius. It will not
 * reliably find a flat, or a street built last year.
 *
 * @since 1.0.0
 */
class MapsCo extends Service {

	protected const ID = 'mapsco';

	protected const NAME = 'Maps.co';

	protected const CACHE_TTL = 604800;

	/**
	 * The API.
	 */
	private const API_BASE = 'https://geocode.maps.co/';

	/**
	 * The response format asked for.
	 *
	 * @var string
	 */
	private string $format = 'json';

	/**
	 * Build the client.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key The key.
	 * @param array  $args    The settings every geocoder takes (`cache`,
	 *                        `cache_ttl`, `failure_ttl`), and `format`.
	 */
	public function __construct( #[\SensitiveParameter] string $api_key = '', array $args = array() ) {
		parent::__construct( $api_key, $args );

		if ( isset( $args['format'] ) ) {
			$this->set_format( (string) $args['format'] );
		}
	}

	/**
	 * The response format to ask for: `json`, `jsonv2`, `geojson`,
	 * `geocodejson`.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format The format.
	 *
	 * @return static
	 */
	public function set_format( string $format ): static {
		$this->format = $format;

		return $this;
	}

	/**
	 * The response format asked for.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_format(): string {
		return $this->format;
	}

	/**
	 * Where an address is: the most important of the matches.
	 *
	 * @since 1.0.0
	 *
	 * @param string $address The address.
	 *
	 * @return Response|WP_Error `mapsco_no_results` when nothing matched.
	 */
	public function geocode( string $address ): Response|WP_Error {
		$address = trim( $address );

		if ( '' === $address ) {
			return $this->error( 'invalid_address', __( 'The address is empty.', 'sugarcommerce' ) );
		}

		$data = $this->remember(
			'forward_' . $address . '|' . $this->format,
			function () use ( $address ) {
				$results = $this->answer( 'search', array( 'q' => $address ) );

				if ( is_wp_error( $results ) || array() === $results ) {
					return $results;
				}

				// Several matches, most relevant first.
				usort( $results, fn ( $a, $b ) => ( (float) ( $b['importance'] ?? 0 ) ) <=> ( (float) ( $a['importance'] ?? 0 ) ) );

				return is_array( $results[0] ?? null ) ? $results[0] : array();
			}
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return array() === $data ? $this->error( 'no_results', __( 'Nothing was found for that address.', 'sugarcommerce' ) ) : new Response( $data );
	}

	/**
	 * What is at a place.
	 *
	 * @since 1.0.0
	 *
	 * @param float $latitude  The latitude.
	 * @param float $longitude The longitude.
	 *
	 * @return Response|WP_Error
	 */
	public function reverse_geocode( float $latitude, float $longitude ): Response|WP_Error {
		if ( abs( $latitude ) > 90 || abs( $longitude ) > 180 ) {
			return $this->error( 'invalid_coordinates', __( 'Those are not coordinates.', 'sugarcommerce' ) );
		}

		$data = $this->remember(
			"reverse_{$latitude}_{$longitude}|{$this->format}",
			fn () => $this->answer(
				'reverse',
				array(
					'lat' => $latitude,
					'lon' => $longitude,
				)
			)
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return array() === $data ? $this->error( 'no_results', __( 'Nothing was found there.', 'sugarcommerce' ) ) : new Response( $data );
	}

	/**
	 * Where an address is, answered the common way.
	 *
	 * @since 1.0.0
	 *
	 * @param string $address The address.
	 *
	 * @return Location|WP_Error
	 */
	public function locate( string $address ): Location|WP_Error {
		return $this->location( $this->geocode( $address ) );
	}

	/**
	 * What is at a place, answered the common way.
	 *
	 * @since 1.0.0
	 *
	 * @param float $latitude  The latitude.
	 * @param float $longitude The longitude.
	 *
	 * @return Location|WP_Error
	 */
	public function locate_point( float $latitude, float $longitude ): Location|WP_Error {
		return $this->location( $this->reverse_geocode( $latitude, $longitude ) );
	}

	/**
	 * A result as a Location.
	 *
	 * @param Response|WP_Error $response The answer.
	 *
	 * @return Location|WP_Error
	 */
	private function location( Response|WP_Error $response ): Location|WP_Error {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new Location(
			self::ID,
			array(
				'latitude'          => $response->get_latitude(),
				'longitude'         => $response->get_longitude(),
				'formatted_address' => $response->get_display_name(),
				'house_number'      => $response->get_house_number(),
				'street'            => $response->get_street(),
				'city'              => $response->get_city(),
				'county'            => $response->get_address_component( 'county' ),
				'region'            => $response->get_state(),
				'postal_code'       => $response->get_postcode(),
				'country'           => $response->get_country(),
				'country_code'      => $response->get_country_code(),
				'place_id'          => $response->get_place_id(),
			),
			$response
		);
	}

	/**
	 * Ask the API.
	 *
	 * @param string $endpoint `search` or `reverse`.
	 * @param array  $params   The query.
	 *
	 * @return array|WP_Error
	 */
	private function answer( string $endpoint, array $params ): array|WP_Error {
		$params += array(
			'format'  => $this->format,
			'api_key' => $this->api_key,
		);

		$data = $this->json( $this->request( 'GET', add_query_arg( $params, self::API_BASE . $endpoint ) ) );

		if ( ! is_wp_error( $data ) && isset( $data['error'] ) ) {
			return $this->error( 'api_error', is_string( $data['error'] ) ? $data['error'] : __( 'Maps.co could not answer.', 'sugarcommerce' ) );
		}

		return $data;
	}
}
