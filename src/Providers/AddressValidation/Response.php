<?php
/**
 * Google Address Validation response
 *
 * @package   SugarCommerce\Geocoding
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Providers\AddressValidation;

use SugarCommerce\Geocoding\Traits\AddressValidationResponse\Assessment;
use SugarCommerce\Geocoding\Traits\AddressValidationResponse\Usps;
use SugarCommerce\Network\Remote\Response as Base;

/**
 * Class Response
 *
 * Google Address Validation's answer: the verdict, the address as Google
 * reads it, its geocode and its metadata. What USPS said is in Usps, the
 * answer judged -- deliverable, shippable, a score -- in Assessment.
 *
 * @since 1.0.0
 */
class Response extends Base {

	use Usps;
	use Assessment;

	/**
	 * Address validation confidence levels
	 *
	 * @var string
	 */
	public const string CONFIDENCE_HIGH = 'HIGH';
	public const string CONFIDENCE_MEDIUM = 'MEDIUM';
	public const string CONFIDENCE_LOW = 'LOW';
	public const string CONFIDENCE_UNCERTAIN = 'UNCERTAIN';

	/**
	 * Address granularity level
	 *
	 * @var string
	 */
	public const string GRANULARITY_PREMISE = 'PREMISE';

	/**
	 * USPS confirmation statuses
	 *
	 * @var string
	 */
	public const string USPS_CONFIRMED = 'Y';
	public const string USPS_NOT_CONFIRMED = 'N';

	/**
	 * Cache for computed values
	 *
	 * @var array<string, mixed>
	 */
	private array $cache = [];

	/**
	 * Get the response ID
	 *
	 * @return string|null The unique identifier for this validation response
	 */
	public function get_response_id(): ?string {
		return $this->data['responseId'] ?? null;
	}

	/**
	 * Get verdict information
	 *
	 * @return array|null The complete verdict information from the API
	 */
	public function get_verdict(): ?array {
		return $this->data['result']['verdict'] ?? null;
	}

	/**
	 * Get input granularity level
	 *
	 * @return string|null The granularity level of the input address
	 */
	public function get_input_granularity(): ?string {
		return $this->data['result']['verdict']['inputGranularity'] ?? null;
	}

	/**
	 * Get validation granularity level
	 *
	 * @return string|null The granularity level of the validation
	 */
	public function get_validation_granularity(): ?string {
		return $this->data['result']['verdict']['validationGranularity'] ?? null;
	}

	/**
	 * Get geocode granularity level
	 *
	 * @return string|null The granularity level of the geocoding
	 */
	public function get_geocode_granularity(): ?string {
		return $this->data['result']['verdict']['geocodeGranularity'] ?? null;
	}

	/**
	 * Check if address is complete
	 *
	 * @return bool True if the address is complete, false otherwise
	 */
	public function is_address_complete(): bool {
		return $this->data['result']['verdict']['addressComplete'] ?? false;
	}

	/**
	 * Check if address has unconfirmed components
	 *
	 * @return bool True if the address has unconfirmed components
	 */
	public function has_unconfirmed_components(): bool {
		return $this->data['result']['verdict']['hasUnconfirmedComponents'] ?? false;
	}

	/**
	 * Check if address has inferred components
	 *
	 * @return bool True if the address has inferred components
	 */
	public function has_inferred_components(): bool {
		return $this->data['result']['verdict']['hasInferredComponents'] ?? false;
	}

	/**
	 * Check if address has replaced components
	 *
	 * @return bool True if the address has replaced components
	 */
	public function has_replaced_components(): bool {
		return $this->data['result']['verdict']['hasReplacedComponents'] ?? false;
	}

	/**
	 * Get formatted address
	 *
	 * @return string|null The complete formatted address string
	 */
	public function get_formatted_address(): ?string {
		return $this->data['result']['address']['formattedAddress'] ?? null;
	}

	/**
	 * Get postal address object
	 *
	 * @return array|null The complete postal address data
	 */
	public function get_postal_address(): ?array {
		return $this->data['result']['address']['postalAddress'] ?? null;
	}

	/**
	 * Get address lines
	 *
	 * @return array The address lines array
	 */
	public function get_address_lines(): array {
		return $this->get_postal_address()['addressLines'] ?? [];
	}

	/**
	 * Get administrative area (state/province)
	 *
	 * @return string|null The administrative area
	 */
	public function get_administrative_area(): ?string {
		return $this->get_postal_address()['administrativeArea'] ?? null;
	}

	/**
	 * Get region code (country)
	 *
	 * @return string|null The region code
	 */
	public function get_region_code(): ?string {
		return $this->get_postal_address()['regionCode'] ?? null;
	}

	/**
	 * Get locality (city)
	 *
	 * @return string|null The locality name
	 */
	public function get_locality(): ?string {
		return $this->get_postal_address()['locality'] ?? null;
	}

	/**
	 * Get sublocality
	 *
	 * @return string|null The sublocality name
	 */
	public function get_sublocality(): ?string {
		return $this->get_postal_address()['sublocality'] ?? null;
	}

	/**
	 * Get postal code
	 *
	 * @return string|null The postal code
	 */
	public function get_postal_code(): ?string {
		return $this->get_postal_address()['postalCode'] ?? null;
	}

	/**
	 * Get sorting code
	 *
	 * @return string|null The sorting code
	 */
	public function get_sorting_code(): ?string {
		return $this->get_postal_address()['sortingCode'] ?? null;
	}

	/**
	 * Get the primary type/classification of the address
	 *
	 * @return string One of 'po_box', 'residential', 'business' or 'unknown'.
	 */
	public function get_address_type(): string {
		// Check PO Box first as it's most specific
		if ( $this->is_po_box() ) {
			return 'po_box';
		}

		/*
		 * Only Google's own metadata classifies an address. AddressMetadata
		 * carries exactly three booleans -- business, poBox and residential --
		 * and nothing else in the response says what kind of place this is.
		 *
		 * This used to test is_valid_landmark() first, which was true for any
		 * address that resolved cleanly with a geocode and a place id. That is
		 * nearly every successful validation, so 'residential' and 'business'
		 * were unreachable and every valid address came back 'landmark'.
		 */
		if ( $this->is_residential() ) {
			return 'residential';
		}

		if ( $this->is_business() ) {
			return 'business';
		}

		// If no specific type is determined
		return 'unknown';
	}

	/**
	 * Get a human-readable description of the address type
	 *
	 * @return string Translated description of the address type
	 */
	public function get_address_type_label(): string {
		$type = $this->get_address_type();

		switch ( $type ) {
			case 'residential':
				return __( 'Residential Address', 'sugarcommerce' );
			case 'business':
				return __( 'Business Address', 'sugarcommerce' );
			case 'po_box':
				return __( 'PO Box', 'sugarcommerce' );
			default:
				return __( 'Unknown Address Type', 'sugarcommerce' );
		}
	}

	/**
	 * Get coordinates
	 *
	 * Returns an array containing latitude and longitude
	 *
	 * @return array{lat: float|null, lng: float|null} Coordinates array
	 */
	public function get_coordinates(): array {
		$geocode = $this->get_geocode();

		return [
			'lat' => $geocode['latitude'] ?? null,
			'lng' => $geocode['longitude'] ?? null,
		];
	}

	/**
	 * Get address components
	 *
	 * @return array Array of address components
	 */
	public function get_address_components(): array {
		return $this->data['result']['address']['addressComponents'] ?? [];
	}

	/**
	 * Get specific address component
	 *
	 * @param string $type Component type to retrieve
	 *
	 * @return array|null Component data or null if not found
	 */
	public function get_address_component( string $type ): ?array {
		foreach ( $this->get_address_components() as $component ) {
			if ( $component['componentType'] === $type ) {
				return $component;
			}
		}

		return null;
	}

	/**
	 * Get missing component types
	 *
	 * @return array Array of missing component types
	 */
	public function get_missing_component_types(): array {
		return $this->data['result']['address']['missingComponentTypes'] ?? [];
	}

	/**
	 * Get unconfirmed component types
	 *
	 * @return array Array of unconfirmed component types
	 */
	public function get_unconfirmed_component_types(): array {
		return $this->data['result']['address']['unconfirmedComponentTypes'] ?? [];
	}

	/**
	 * Get unresolved tokens
	 *
	 * @return array Array of unresolved tokens
	 */
	public function get_unresolved_tokens(): array {
		return $this->data['result']['address']['unresolvedTokens'] ?? [];
	}

	/**
	 * Get geocode data
	 *
	 * @return array|null The geocode data including latitude and longitude
	 */
	public function get_geocode(): ?array {
		$location = $this->data['result']['geocode']['location'] ?? null;
		if ( $location ) {
			return [
				'latitude'  => $location['latitude'] ?? null,
				'longitude' => $location['longitude'] ?? null,
			];
		}

		return null;
	}

	/**
	 * Get plus code
	 *
	 * @return array|null The plus code data
	 */
	public function get_plus_code(): ?array {
		return $this->data['result']['geocode']['plusCode'] ?? null;
	}

	/**
	 * Get viewport bounds
	 *
	 * @return array|null The viewport bounds data
	 */
	public function get_viewport(): ?array {
		return $this->data['result']['geocode']['bounds'] ?? null;
	}

	/**
	 * Get feature size in meters
	 *
	 * @return float|null The feature size in meters
	 */
	public function get_feature_size_meters(): ?float {
		return $this->data['result']['geocode']['featureSizeMeters'] ?? null;
	}

	/**
	 * Get place ID
	 *
	 * @return string|null The Google Place ID
	 */
	public function get_place_id(): ?string {
		return $this->data['result']['geocode']['placeId'] ?? null;
	}

	/**
	 * Get place types
	 *
	 * @return array Array of place types
	 */
	public function get_place_types(): array {
		return $this->data['result']['geocode']['placeTypes'] ?? [];
	}

	/**
	 * Get address metadata
	 *
	 * @return array|null The address metadata
	 */
	public function get_metadata(): ?array {
		return $this->data['result']['metadata'] ?? null;
	}

	/**
	 * Check if address is a business
	 *
	 * @return bool True if the address is a business location
	 */
	public function is_business(): bool {
		return $this->data['result']['metadata']['business'] ?? false;
	}

	/**
	 * Check if address is a PO Box
	 *
	 * @return bool True if the address is a PO Box
	 */
	public function is_po_box(): bool {
		return $this->data['result']['metadata']['poBox'] ?? false;
	}

	/**
	 * Check if address is residential
	 *
	 * @return bool True if the address is residential
	 */
	public function is_residential(): bool {
		return $this->data['result']['metadata']['residential'] ?? false;
	}

	/**
	 * Get English Latin Address (if requested)
	 *
	 * @return array|null The address in English Latin format
	 */
	public function get_english_latin_address(): ?array {
		return $this->data['result']['englishLatinAddress'] ?? null;
	}
}
