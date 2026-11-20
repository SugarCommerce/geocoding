<?php
/**
 * Security tests: what an address from a visitor or an answer from a
 * service must not be able to do.
 *
 * @package SugarCommerce\Geocoding
 */

declare( strict_types=1 );

namespace SugarCommerce\Geocoding\Tests;

use PHPUnit\Framework\TestCase;
use SugarCommerce\Geocoding\Providers\AddressValidation;
use SugarCommerce\Geocoding\Providers\Google;
use SugarCommerce\Geocoding\Providers\MapsCo;
use SugarCommerce\Geocoding\Tests\Support\FakeHttp;
use WP_Error;

/**
 * Input is validated, answers are untrusted data.
 */
final class SecurityTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		gc_reset();
	}

	/**
	 * The query a request was sent with.
	 *
	 * @return array{query: array, fragment: string|null}
	 */
	private static function sent(): array {
		$url = (string) FakeHttp::last()['url'];

		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );

		return array(
			'query'    => $query,
			'fragment' => parse_url( $url, PHP_URL_FRAGMENT ),
		);
	}

	/**
	 * An address is one parameter, however it is written: it cannot add
	 * parameters of its own, replace the key, or cut the request short
	 * with a `#`.
	 *
	 * @return void
	 */
	public function test_address_cannot_add_to_the_query(): void {
		$address = '1 Main St&key=someone-elses&components=country:FR#';

		FakeHttp::json( array( 'status' => 'ZERO_RESULTS', 'results' => array() ) );
		( new Google( 'real-key' ) )->geocode( $address );

		$sent = self::sent();

		$this->assertSame( $address, $sent['query']['address'] ?? null );
		$this->assertSame( 'real-key', $sent['query']['key'] ?? null );
		$this->assertArrayNotHasKey( 'components', $sent['query'] );
		$this->assertNull( $sent['fragment'] );

		FakeHttp::json( array() );
		( new MapsCo( 'real-key' ) )->geocode( '10 Rue de Rivoli&api_key=x&format=xml#' );

		$sent = self::sent();

		$this->assertSame( '10 Rue de Rivoli&api_key=x&format=xml#', $sent['query']['q'] ?? null );
		$this->assertSame( 'real-key', $sent['query']['api_key'] ?? null );
		$this->assertSame( 'json', $sent['query']['format'] ?? null );
		$this->assertNull( $sent['fragment'] );
	}

	/**
	 * An address that cannot be one -- too long, control characters,
	 * broken UTF-8 -- is refused before a request is spent on it, by every
	 * geocoder. A line break is a space.
	 *
	 * @return void
	 */
	public function test_address_is_checked_before_it_is_sent(): void {
		$geocoders = array( new Google( 'k' ), new MapsCo( 'k' ), new AddressValidation( 'k' ) );
		$refused   = array( str_repeat( 'a', 1001 ), "1 Main\x00 St", "1 Main St\x1b[31m", "1 Main St \xC3\x28" );

		foreach ( $geocoders as $geocoder ) {
			foreach ( $refused as $address ) {
				$error = $geocoder->locate( $address );

				$this->assertInstanceOf( WP_Error::class, $error );
				$this->assertSame( $geocoder->get_id() . '_invalid_address', $error->get_error_code() );
			}
		}

		$this->assertSame( 0, FakeHttp::count() );

		FakeHttp::json( array( 'status' => 'ZERO_RESULTS', 'results' => array() ) );
		( new Google( 'k' ) )->geocode( "1 Main St\r\nLondon" );

		$this->assertSame( '1 Main St London', self::sent()['query']['address'] ?? null );
	}
}

