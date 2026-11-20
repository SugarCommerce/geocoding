<?php
/**
 * Address Validation answer: assessment
 *
 * @package   SugarCommerce\Geocoding
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Traits\AddressValidationResponse;

use SugarCommerce\Geocoding\Support\Field;

/**
 * Trait Assessment
 *
 * The answer judged: complete, deliverable, shippable, how confident,
 * what is wrong with it, and a score and rating out of it.
 *
 * @since 1.0.0
 */
trait Assessment {

	/**
	 * Get complete standardized address components
	 *
	 * @return array{
	 *     address_lines: array,
	 *     administrative_area: string|null,
	 *     language_code: string|null,
	 *     locality: string|null,
	 *     postal_code: string|null,
	 *     region_code: string|null,
	 *     sorting_code: string|null,
	 *     sublocality: string|null,
	 *     formatted_address: string|null
	 * } Structured address components
	 */
	public function get_standardized_address(): array {
		return [
			'address_lines'       => $this->get_address_lines(),
			'administrative_area' => $this->get_administrative_area(),
			'language_code'       => Field::text( $this->get_postal_address()['languageCode'] ?? null ),
			'locality'            => $this->get_locality(),
			'postal_code'         => $this->get_postal_code(),
			'region_code'         => $this->get_region_code(),
			'sorting_code'        => $this->get_sorting_code(),
			'sublocality'         => $this->get_sublocality(),
			'formatted_address'   => $this->get_formatted_address(),
		];
	}

	/**
	 * Check the validity of the address with detailed analysis
	 *
	 * @return array{
	 *     is_valid: bool,
	 *     confidence_level: string,
	 *     issues: array<string>
	 * } Validation details including validity status, confidence level, and any issues
	 */
	public function check_validity(): array {
		// Use cache if available
		if ( isset( $this->cache['validity'] ) ) {
			return $this->cache['validity'];
		}

		$validity = [
			'is_valid'         => $this->is_address_complete() && ! $this->has_unconfirmed_components(),
			'confidence_level' => $this->determine_confidence_level(),
			'issues'           => $this->get_validity_issues(),
		];

		// Cache the result
		$this->cache['validity'] = $validity;

		return $validity;
	}

	/**
	 * Determine the confidence level of the address validation
	 *
	 * @return string Confidence level (HIGH, MEDIUM, LOW, or UNCERTAIN)
	 */
	private function determine_confidence_level(): string {
		if ( $this->is_address_complete() && ! $this->has_unconfirmed_components() && ! $this->has_inferred_components() ) {
			return self::CONFIDENCE_HIGH;
		} elseif ( $this->is_address_complete() && ! $this->has_unconfirmed_components() ) {
			return self::CONFIDENCE_MEDIUM;
		} elseif ( ! $this->has_unconfirmed_components() ) {
			return self::CONFIDENCE_LOW;
		}

		return self::CONFIDENCE_UNCERTAIN;
	}

	/**
	 * Get a list of validity issues with the address
	 *
	 * @return array<string> List of validation issues
	 */
	private function get_validity_issues(): array {
		$issues = [];

		if ( ! $this->is_address_complete() ) {
			$issues[] = __( 'Address is incomplete', 'sugarcommerce' );

			$missing = $this->get_missing_component_types();
			if ( ! empty( $missing ) ) {
				$issues[] = sprintf(
				/* translators: %s: comma-separated list of missing components */
					__( 'Missing components: %s', 'sugarcommerce' ),
					implode( ', ', $missing )
				);
			}
		}

		if ( $this->has_unconfirmed_components() ) {
			$unconfirmed = $this->get_unconfirmed_component_types();
			$issues[]    = sprintf(
			/* translators: %s: comma-separated list of unconfirmed components */
				__( 'Has unconfirmed components: %s', 'sugarcommerce' ),
				implode( ', ', $unconfirmed )
			);
		}

		if ( $this->has_inferred_components() ) {
			$issues[] = __( 'Contains inferred components', 'sugarcommerce' );
		}

		if ( $this->has_replaced_components() ) {
			$issues[] = __( 'Contains replaced components', 'sugarcommerce' );
		}

		return $issues;
	}

	/**
	 * Check if the address is from the United States
	 *
	 * @return bool True if US address
	 */
	public function is_us_address(): bool {
		return $this->get_region_code() === 'US';
	}

	/**
	 * Check if the address is fully validated
	 *
	 * @return bool True if fully validated
	 */
	public function is_fully_validated(): bool {
		return $this->is_address_complete() &&
				! $this->has_unconfirmed_components() &&
				! $this->has_inferred_components() &&
				! $this->has_replaced_components();
	}

	/**
	 * Check if the address has high confidence validation
	 *
	 * @return bool True if high confidence
	 */
	public function is_high_confidence(): bool {
		return $this->determine_confidence_level() === self::CONFIDENCE_HIGH;
	}

	/**
	 * Check if the address has minimal valid components
	 *
	 * @return bool True if has minimal components
	 */
	public function has_minimal_components(): bool {
		return $this->get_postal_code() !== null &&
				$this->get_locality() !== null &&
				! empty( $this->get_address_lines() );
	}

	/**
	 * Check if the address is an exact match
	 *
	 * @return bool True if exact match
	 */
	public function is_exact_match(): bool {
		return ! $this->has_inferred_components() &&
				! $this->has_replaced_components();
	}

	/**
	 * Check if the address is a minimal valid address
	 *
	 * @return bool True if minimally valid
	 */
	public function is_minimal_valid(): bool {
		return $this->get_formatted_address() !== null &&
				$this->get_geocode() !== null;
	}

	/**
	 * Check if the address is deliverable
	 *
	 * @return bool True if deliverable
	 */
	public function is_deliverable(): bool {
		// If we have USPS data
		if ( $this->get_usps_data() !== null ) {
			return $this->get_dpv_confirmation() === self::USPS_CONFIRMED;
		}

		// For non-USPS addresses
		return $this->is_address_complete() &&
				! $this->has_unconfirmed_components() &&
				$this->get_geocode() !== null;
	}

	/**
	 * Check if the address has precise coordinates
	 *
	 * @return bool True if precisely located
	 */
	public function is_precisely_located(): bool {
		return $this->get_geocode() !== null &&
				$this->get_geocode_granularity() === self::GRANULARITY_PREMISE;
	}

	/**
	 * Check if the address is standardized
	 *
	 * @return bool True if standardized
	 */
	public function is_standardized(): bool {
		$standardized = $this->get_standardized_address();

		return ! empty( $standardized['postal_code'] ) &&
				! empty( $standardized['locality'] ) &&
				! empty( $standardized['formatted_address'] );
	}

	/**
	 * Check if the address is a valid shipping destination
	 *
	 * @return bool True if shippable
	 */
	public function is_shippable(): bool {
		// If we have USPS data
		if ( $this->get_usps_data() !== null ) {
			return $this->is_deliverable();
		}

		// For non-USPS addresses, check if we have the minimum required components
		return $this->get_postal_code() !== null &&
				$this->get_locality() !== null &&
				! empty( $this->get_address_lines() ) &&
				! $this->has_unconfirmed_components();
	}

	/**
	 * Check if the address needs verification
	 *
	 * @return bool True if verification needed
	 */
	public function is_verification_needed(): bool {
		return $this->has_unconfirmed_components() ||
				$this->has_inferred_components() ||
				! $this->is_address_complete();
	}

	/**
	 * Calculate a validation score for the address (0-100)
	 *
	 * Provides a numerical score indicating how likely the address is to be valid
	 * based on various validation factors.
	 *
	 * Scoring factors:
	 * - Address completeness: 30 points
	 * - No unconfirmed components: 20 points
	 * - No inferred components: 15 points
	 * - No replaced components: 10 points
	 * - Has geocode data: 10 points
	 * - Has postal code: 5 points
	 * - Has precise location: 5 points
	 * - USPS validation (if applicable): 5 points
	 *
	 * @return int Score from 0 to 100
	 */
	public function get_score(): int {
		$score = 0;

		// Address completeness (30 points)
		if ( $this->is_address_complete() ) {
			$score += 30;
		}

		// No unconfirmed components (20 points)
		if ( ! $this->has_unconfirmed_components() ) {
			$score += 20;
		}

		// No inferred components (15 points)
		if ( ! $this->has_inferred_components() ) {
			$score += 15;
		}

		// No replaced components (10 points)
		if ( ! $this->has_replaced_components() ) {
			$score += 10;
		}

		// Has geocode data (10 points)
		if ( $this->get_geocode() !== null ) {
			$score += 10;
		}

		// Has postal code (5 points)
		if ( $this->get_postal_code() !== null ) {
			$score += 5;
		}

		// Has precise location (5 points)
		if ( $this->is_precisely_located() ) {
			$score += 5;
		}

		// USPS validation bonus (5 points)
		if ( $this->get_usps_data() !== null && $this->get_dpv_confirmation() === self::USPS_CONFIRMED ) {
			$score += 5;
		}

		return $score;
	}

	/**
	 * Get a descriptive rating based on the validation score
	 *
	 * @return string Rating description
	 */
	public function get_rating(): string {
		$score = $this->get_score();

		if ( $score >= 90 ) {
			return __( 'Excellent - Highly reliable address', 'sugarcommerce' );
		} elseif ( $score >= 75 ) {
			return __( 'Good - Reliable address with minor issues', 'sugarcommerce' );
		} elseif ( $score >= 50 ) {
			return __( 'Fair - Address may need verification', 'sugarcommerce' );
		} else {
			return __( 'Poor - Address needs significant verification', 'sugarcommerce' );
		}
	}
}
