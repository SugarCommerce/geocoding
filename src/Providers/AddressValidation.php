<?php
/**
 * Google Address Validation
 *
 * @package   SugarCommerce\Geocoding
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Providers;

use SugarCommerce\Geocoding\Location;
use SugarCommerce\Geocoding\Providers\AddressValidation\Response;
use SugarCommerce\Geocoding\Geocoder;
use SugarCommerce\Network\Remote\Service;
use WP_Error;

/**
 * Class AddressValidation
 *
 * Google's Address Validation API
 * (https://developers.google.com/maps/documentation/address-validation):
 * how much of an address it could confirm, what it guessed at, what it
 * corrected, and where the address is.
 *
 * A valid address is not a verified one, and coverage varies by country.
 * Treat a failed check as a reason to ask the customer to confirm, never as
 * grounds to refuse the order.
 *
 * @since 1.0.0
 */
class AddressValidation extends Service implements Geocoder {

	protected const string ID = 'address_validation';

	protected const string NAME = 'Google Address Validation';

	protected const string CACHE_GROUP = 'geocoding';

	/**
	 * How long an answer is kept, by default: a day. An address does not move.
	 */
	protected const int CACHE_TTL = 86400;

	/**
	 * The endpoint.
	 */
	private const string API_ENDPOINT = 'https://addressvalidation.googleapis.com/v1:validateAddress';

	/**
	 * The postal address fields the API takes.
	 */
	private const array ADDRESS_FIELDS = array( 'revision', 'regionCode', 'languageCode', 'postalCode', 'sortingCode', 'administrativeArea', 'locality', 'sublocality', 'addressLines' );

	/**
	 * Options sent with every validation.
	 *
	 * @var array{enable_usps: bool, language_options: mixed, previous_response: string|null, session_token: string|null}
	 */
	private array $options = array(
		'enable_usps'       => false,
		'language_options'  => null,
		'previous_response' => null,
		'session_token'     => null,
	);

	/**
	 * Run US addresses through USPS CASS as well.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $enable Whether to.
	 *
	 * @return static
	 */
	public function set_usps( bool $enable = true ): static {
		$this->options['enable_usps'] = $enable;

		return $this;
	}

	/**
	 * Whether US addresses go through USPS CASS.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function get_usps(): bool {
		return $this->options['enable_usps'];
	}

	/**
	 * The API's language options; a string is a language code.
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $options The options.
	 *
	 * @return static
	 */
	public function set_language_options( array|string $options ): static {
		$this->options['language_options'] = is_string( $options ) ? array( 'languageCode' => $options ) : $options;

		return $this;
	}

	/**
	 * The language options.
	 *
	 * @since 1.0.0
	 *
	 * @return array|null
	 */
	public function get_language_options(): ?array {
		return $this->options['language_options'];
	}

	/**
	 * The response this one follows, when the customer corrected an address
	 * and it is checked again.
	 *
	 * @since 1.0.0
	 *
	 * @param string $response_id The earlier response's id.
	 *
	 * @return static
	 */
	public function set_previous_response( string $response_id ): static {
		$this->options['previous_response'] = $response_id;

		return $this;
	}

	/**
	 * The response this one follows.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_previous_response(): ?string {
		return $this->options['previous_response'];
	}

	/**
	 * The Places autocomplete session this validation ends.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token The session token.
	 *
	 * @return static
	 */
	public function set_session_token( string $token ): static {
		$this->options['session_token'] = $token;

		return $this;
	}

	/**
	 * The autocomplete session token.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function get_session_token(): ?string {
		return $this->options['session_token'];
	}

	/**
	 * Every option that is set.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_all_options(): array {
		return array_filter( $this->options, fn ( $value ) => null !== $value );
	}

	/**
	 * Forget the options.
	 *
	 * @since 1.0.0
	 *
	 * @return static
	 */
	public function reset_options(): static {
		$this->options = array(
			'enable_usps'       => false,
			'language_options'  => null,
			'previous_response' => null,
			'session_token'     => null,
		);

		return $this;
	}

	/**
	 * Validate an address.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array $address One line, or a postal address: `regionCode`,
	 *                              `addressLines`, `locality`, `postalCode`...
	 * @param array        $options Options for this validation only, as the
	 *                              setters name them.
	 *
	 * @return Response|WP_Error
	 */
	public function validate( string|array $address, array $options = array() ): Response|WP_Error {
		$options = array_merge( $this->get_all_options(), $options );
		$body    = array( 'address' => self::postal_address( $address ) );

		if ( array() === $body['address'] ) {
			return $this->error( 'invalid_address', __( 'The address is empty.', 'sugarcommerce' ) );
		}

		if ( ! empty( $options['previous_response'] ) ) {
			$body['previousResponseId'] = $options['previous_response'];
		}

		if ( isset( $options['enable_usps'] ) ) {
			$body['enableUspsCass'] = (bool) $options['enable_usps'];
		}

		if ( ! empty( $options['language_options'] ) ) {
			$body['languageOptions'] = $options['language_options'];
		}

		if ( ! empty( $options['session_token'] ) ) {
			$body['sessionToken'] = $options['session_token'];
		}

		$json = (string) wp_json_encode( $body );
		$data = $this->remember(
			$json,
			function () use ( $json ) {
				$data = $this->json(
					$this->request(
						'POST',
						add_query_arg( array( 'key' => $this->api_key ), self::API_ENDPOINT ),
						array(
							'headers' => array( 'Content-Type' => 'application/json' ),
							'body'    => $json,
						)
					)
				);

				if ( ! is_wp_error( $data ) && isset( $data['error'] ) ) {
					return $this->error( 'api_error', (string) ( $data['error']['message'] ?? __( 'Address Validation could not answer.', 'sugarcommerce' ) ) );
				}

				return $data;
			}
		);

		return is_wp_error( $data ) ? $data : new Response( $data );
	}

	/**
	 * Where an address is, answered the common way: the corrected address
	 * and its geocode.
	 *
	 * @since 1.0.0
	 *
	 * @param string $address The address.
	 *
	 * @return Location|WP_Error
	 */
	#[\Override]
	public function locate( string $address ): Location|WP_Error {
		$response = $this->validate( $address );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$geocode = $response->get_geocode();

		if ( null === $geocode && null === $response->get_formatted_address() ) {
			return $this->error( 'no_results', __( 'Nothing was found for that address.', 'sugarcommerce' ) );
		}

		$component = fn ( string $type ) => $response->get_address_component( $type )['componentName']['text'] ?? null;

		return new Location(
			self::ID,
			array(
				'latitude'          => $geocode['latitude'] ?? null,
				'longitude'         => $geocode['longitude'] ?? null,
				'formatted_address' => $response->get_formatted_address(),
				'house_number'      => $component( 'street_number' ),
				'street'            => $component( 'route' ),
				'city'              => $response->get_locality(),
				'region'            => $component( 'administrative_area_level_1' ) ?? $response->get_administrative_area(),
				'region_code'       => $response->get_administrative_area(),
				'postal_code'       => $response->get_postal_code(),
				'country'           => $component( 'country' ),
				'country_code'      => $response->get_region_code(),
				'place_id'          => $response->get_place_id(),
			),
			$response
		);
	}

	/**
	 * Address Validation does not look up coordinates.
	 *
	 * @since 1.0.0
	 *
	 * @param float $latitude  The latitude.
	 * @param float $longitude The longitude.
	 *
	 * @return WP_Error
	 */
	#[\Override]
	public function locate_point( float $latitude, float $longitude ): WP_Error {
		return $this->error( 'unsupported', __( 'Address Validation turns addresses into places, not places into addresses.', 'sugarcommerce' ) );
	}

	/**
	 * An address as the API takes it.
	 *
	 * @param string|array $address One line, or a postal address.
	 *
	 * @return array
	 */
	private static function postal_address( string|array $address ): array {
		if ( is_string( $address ) ) {
			return '' === trim( $address ) ? array() : array( 'addressLines' => array( trim( $address ) ) );
		}

		$postal = array();

		foreach ( self::ADDRESS_FIELDS as $field ) {
			if ( isset( $address[ $field ] ) ) {
				$postal[ $field ] = 'addressLines' === $field && is_string( $address[ $field ] ) ? array( $address[ $field ] ) : $address[ $field ];
			}
		}

		return $postal;
	}
}
