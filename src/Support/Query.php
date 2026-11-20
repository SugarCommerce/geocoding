<?php
/**
 * Query
 *
 * @package   SugarCommerce\Geocoding
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Support;

/**
 * Class Query
 *
 * What a geocoder is asked: an address checked before it is sent, and the
 * URL it is sent in.
 *
 * The address is usually somebody's own typing -- a checkout's shipping
 * address, a store locator's search box. WordPress's add_query_arg() puts
 * values in as they are, unencoded, so an address with `&key=...` in it
 * added parameters of its own, and one with `#` cut the rest of the
 * request off. Here every value is encoded, and the address is held to
 * what an address can be.
 *
 * @since 1.0.0
 */
final class Query {

	/**
	 * The longest address asked about, in characters. Longer is not an
	 * address, and costs a request all the same.
	 */
	public const int MAX_ADDRESS_LENGTH = 1000;

	/**
	 * An address fit to send, or null.
	 *
	 * @since 1.0.0
	 *
	 * @param string $address The address, as somebody wrote it.
	 *
	 * @return string|null Trimmed, line breaks and tabs made spaces; null
	 *                     when it is empty, longer than MAX_ADDRESS_LENGTH,
	 *                     or holds other control characters or invalid UTF-8.
	 */
	public static function address( string $address ): ?string {
		$address = trim( (string) preg_replace( '/[\t\r\n]+/', ' ', $address ) );

		if ( '' === $address || 1 !== preg_match( '//u', $address ) || mb_strlen( $address ) > self::MAX_ADDRESS_LENGTH ) {
			return null;
		}

		return 1 === preg_match( '/[\x00-\x1F\x7F]/', $address ) ? null : $address;
	}

	/**
	 * An endpoint with its query, every value encoded.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint The endpoint: a fixed address of the service's.
	 * @param array  $params   The query.
	 *
	 * @return string
	 */
	public static function url( string $endpoint, array $params ): string {
		return array() === $params ? $endpoint : $endpoint . '?' . http_build_query( $params, '', '&' );
	}
}
