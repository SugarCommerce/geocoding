<?php
/**
 * Geocoder
 *
 * @package   SugarCommerce\Geocoding
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding;

use SugarCommerce\Geocoding\Models\Location;
use WP_Error;

/**
 * Interface Geocoder
 *
 * A service that turns a written address into a place on the map, and a
 * place back into an address. Each answers in its own shape; these two
 * methods bring that down to one Location, so the service behind a store
 * locator or a delivery radius can change without the code that uses it.
 *
 * @since 1.0.0
 */
interface Geocoder {

	/**
	 * The geocoder's id: `google`, `mapsco`, `address_validation`.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * The geocoder's name, for people.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Where an address is.
	 *
	 * @since 1.0.0
	 *
	 * @param string $address The address, as somebody wrote it.
	 *
	 * @return Location|WP_Error `{id}_no_results` when nothing matched.
	 */
	public function locate( string $address ): Location|WP_Error;

	/**
	 * What is at a place.
	 *
	 * @since 1.0.0
	 *
	 * @param float $latitude  -90 to 90.
	 * @param float $longitude -180 to 180.
	 *
	 * @return Location|WP_Error `{id}_unsupported` from a service that
	 *                           cannot.
	 */
	public function locate_point( float $latitude, float $longitude ): Location|WP_Error;
}
