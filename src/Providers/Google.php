<?php
/**
 * Google Geocoding
 *
 * @package   SugarCommerce\Geocoding
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Providers;

use SugarCommerce\Geocoding\Models\Location;
use SugarCommerce\Geocoding\Models\Google\Response;
use SugarCommerce\Geocoding\Geocoder;
use SugarCommerce\Geocoding\Support\Field;
use SugarCommerce\Geocoding\Support\Query;
use SugarCommerce\Network\Remote\Service;
use WP_Error;

/**
 * Class Google
 *
 * Google's Geocoding API (https://developers.google.com/maps/documentation/geocoding):
 * the most precise of the three on a single building, and the one that
 * needs a billing account.
 *
 * @since 1.0.0
 */
class Google extends Service implements Geocoder {

	protected const string ID = 'google';

	protected const string NAME = 'Google Geocoding';

	protected const string CACHE_GROUP = 'geocoding';

	/**
	 * How long an answer is kept, by default: a day. An address does not move.
	 */
	protected const int CACHE_TTL = 86400;

	/**
	 * The endpoint.
	 */
	private const string API_ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

	/**
	 * Where an address is.
	 *
	 * Nothing found is still an answer -- `get_status()` is `ZERO_RESULTS`
	 * and there are no results -- and is cached like one.
	 *
	 * @since 1.0.0
	 *
	 * @param string $address The address.
	 * @param array  $params  More of the API's parameters: `region`,
	 *                        `language`, `components`, `bounds`.
	 *
	 * @return Response|WP_Error
	 */
	public function geocode( string $address, array $params = array() ): Response|WP_Error {
		$address = Query::address( $address );

		if ( null === $address ) {
			return $this->error( 'invalid_address', __( 'That is not an address: empty, too long, or with characters no address has.', 'sugarcommerce' ) );
		}

		return $this->ask( 'geocode_' . $address, array( 'address' => $address ) + $params );
	}

	/**
	 * What is at a place.
	 *
	 * @since 1.0.0
	 *
	 * @param float $lat    The latitude.
	 * @param float $lng    The longitude.
	 * @param array $params More of the API's parameters: `result_type`,
	 *                      `location_type`, `language`.
	 *
	 * @return Response|WP_Error
	 */
	public function reverse_geocode( float $lat, float $lng, array $params = array() ): Response|WP_Error {
		if ( abs( $lat ) > 90 || abs( $lng ) > 180 ) {
			return $this->error( 'invalid_coordinates', __( 'Those are not coordinates.', 'sugarcommerce' ) );
		}

		return $this->ask( "reverse_{$lat}_{$lng}", array( 'latlng' => "{$lat},{$lng}" ) + $params );
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
	#[\Override]
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
	#[\Override]
	public function locate_point( float $latitude, float $longitude ): Location|WP_Error {
		return $this->location( $this->reverse_geocode( $latitude, $longitude ) );
	}

	/**
	 * The first result as a Location.
	 *
	 * @param Response|WP_Error $response The answer.
	 *
	 * @return Location|WP_Error
	 */
	private function location( Response|WP_Error $response ): Location|WP_Error {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( null === $response->get_first_result() ) {
			return $this->error( 'no_results', __( 'Nothing was found there.', 'sugarcommerce' ) );
		}

		return new Location(
			self::ID,
			array(
				'latitude'          => $response->get_latitude(),
				'longitude'         => $response->get_longitude(),
				'formatted_address' => $response->get_formatted_address(),
				'house_number'      => $response->get_street_number(),
				'street'            => $response->get_street_name(),
				'city'              => $response->get_city(),
				'county'            => $response->get_county(),
				'region'            => $response->get_state(),
				'region_code'       => $response->get_state_short(),
				'postal_code'       => $response->get_postal_code(),
				'country'           => $response->get_country(),
				'country_code'      => $response->get_country_short(),
				'place_id'          => $response->get_place_id(),
			),
			$response
		);
	}

	/**
	 * Ask the API, through the cache.
	 *
	 * @param string $identifier What is asked.
	 * @param array  $params     The query.
	 *
	 * @return Response|WP_Error
	 */
	private function ask( string $identifier, array $params ): Response|WP_Error {
		$extra = array_diff_key( $params, array_flip( array( 'address', 'latlng' ) ) );
		$data  = $this->remember(
			$identifier . ( array() === $extra ? '' : '|' . md5( serialize( $extra ) ) ),
			function () use ( $params ) {
				$data = $this->json( $this->request( 'GET', Query::url( self::API_ENDPOINT, array_merge( $params, array( 'key' => $this->api_key ) ) ) ) );

				if ( is_wp_error( $data ) ) {
					return $data;
				}

				$status = Field::text( $data['status'] ?? null ) ?? '';

				if ( 'OK' !== $status && 'ZERO_RESULTS' !== $status ) {
					$status  = 1 === preg_match( '/^[A-Z_]{1,40}$/', $status ) ? $status : '';
					$message = Field::message( $data['error_message'] ?? null, '' );

					return $this->error(
						'api_error',
						match ( true ) {
							'' === $status  => __( 'Google Geocoding could not answer.', 'sugarcommerce' ),
							'' === $message => $status,
							default         => $status . ': ' . $message,
						},
						array( 'status' => $status )
					);
				}

				return $data;
			}
		);

		return is_wp_error( $data ) ? $data : new Response( $data );
	}
}
