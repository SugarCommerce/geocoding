<?php
/**
 * Address Validation answer: USPS
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
 * Trait Usps
 *
 * What USPS CASS said about a US address, when it was asked: the
 * standardised address, the delivery point, the carrier route, and whether
 * the address is vacant, active or a commercial mail receiver.
 *
 * @since 1.0.0
 */
trait Usps {

	/**
	 * Get USPS data
	 *
	 * @return array|null The USPS-specific address data
	 */
	public function get_usps_data(): ?array {
		return Field::map( $this->at( 'result', 'uspsData' ) );
	}

	/**
	 * Get USPS standardized address
	 *
	 * @return array|null The USPS standardized address
	 */
	public function get_usps_standardized_address(): ?array {
		return Field::map( $this->at( 'result', 'uspsData', 'standardizedAddress' ) );
	}

	/**
	 * Get delivery point code
	 *
	 * @return string|null The USPS delivery point code
	 */
	public function get_delivery_point_code(): ?string {
		return Field::text( $this->at( 'result', 'uspsData', 'deliveryPointCode' ) );
	}

	/**
	 * Get carrier route
	 *
	 * @return string|null The USPS carrier route
	 */
	public function get_carrier_route(): ?string {
		return Field::text( $this->at( 'result', 'uspsData', 'carrierRoute' ) );
	}

	/**
	 * Check if address is a Commercial Mail Receiving Agency
	 *
	 * @return bool True if the address is a CMRA
	 */
	public function is_commercial_mail_receiver(): bool {
		return self::USPS_CONFIRMED === $this->at( 'result', 'uspsData', 'dpvCmra' );
	}

	/**
	 * Check if address is vacant
	 *
	 * @return bool True if the address is vacant
	 */
	public function is_vacant(): bool {
		return self::USPS_CONFIRMED === $this->at( 'result', 'uspsData', 'dpvVacant' );
	}

	/**
	 * Check if address is active
	 *
	 * @return bool True if the address is active
	 */
	public function is_active(): bool {
		return self::USPS_NOT_CONFIRMED === $this->at( 'result', 'uspsData', 'dpvNoStat' );
	}

	/**
	 * Get DPV confirmation status
	 *
	 * @return string|null The USPS DPV confirmation status
	 */
	public function get_dpv_confirmation(): ?string {
		return Field::text( $this->at( 'result', 'uspsData', 'dpvConfirmation' ) );
	}
}
