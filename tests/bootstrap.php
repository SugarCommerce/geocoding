<?php
/**
 * PHPUnit bootstrap.
 *
 * There is no WordPress to load: the core functions the library touches are
 * stubbed here, and the HTTP API answers from a
 * queue the tests fill (Support\FakeHttp).
 *
 * @package SugarCommerce\Geocoding
 */

declare( strict_types=1 );

use SugarCommerce\Geocoding\Tests\Support\FakeHttp;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'WEEK_IN_SECONDS', 604800 );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Transients, with when each expires (0 = never), and the clock they expire by.
 *
 * @var array<string, array{value: mixed, expires: int}>
 */
$GLOBALS['gc_transients'] = array();
$GLOBALS['gc_now']        = 1_800_000_000;

/**
 * Forget every transient and queued answer.
 *
 * @return void
 */
function gc_reset(): void {
	$GLOBALS['gc_transients'] = array();
	$GLOBALS['gc_now']        = 1_800_000_000;
	$_SERVER                   = array();
	FakeHttp::reset();
}

/**
 * Move the transient clock on.
 *
 * @param int $seconds Seconds.
 *
 * @return void
 */
function gc_advance( int $seconds ): void {
	$GLOBALS['gc_now'] += $seconds;
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Core's error object, near enough.
	 */
	class WP_Error {

		/**
		 * @param string $code    Code.
		 * @param string $message Message.
		 * @param mixed  $data    Data.
		 */
		public function __construct( public string $code = '', public string $message = '', public mixed $data = '' ) {}

		/**
		 * @return string
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}

		/**
		 * @return mixed
		 */
		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}

/**
 * @param mixed $thing Anything.
 *
 * @return bool
 */
function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

/**
 * @param string $text   Text.
 * @param string $domain Domain.
 *
 * @return string
 */
function __( $text, $domain = 'default' ) {
	return $text;
}

/**
 * @param string $text   Text.
 * @param string $domain Domain.
 *
 * @return string
 */
function esc_html__( $text, $domain = 'default' ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * @param string $text Text.
 *
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * @param mixed $value Value.
 *
 * @return string|false
 */
function wp_json_encode( $value ) {
	return json_encode( $value );
}

/**
 * @param array  $args Query arguments.
 * @param string $url  URL.
 *
 * @return string
 */
function add_query_arg( array $args, string $url ) {
	return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
}

/**
 * @param string $key Key.
 *
 * @return mixed
 */
function get_transient( $key ) {
	$entry = $GLOBALS['gc_transients'][ $key ] ?? null;

	if ( null === $entry ) {
		return false;
	}

	if ( 0 !== $entry['expires'] && $entry['expires'] <= $GLOBALS['gc_now'] ) {
		unset( $GLOBALS['gc_transients'][ $key ] );

		return false;
	}

	return $entry['value'];
}

/**
 * @param string $key        Key.
 * @param mixed  $value      Value.
 * @param int    $expiration Seconds.
 *
 * @return bool
 */
function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['gc_transients'][ $key ] = array(
		'value'   => $value,
		'expires' => $expiration > 0 ? $GLOBALS['gc_now'] + (int) $expiration : 0,
	);

	return true;
}

/**
 * @param string $key Key.
 *
 * @return bool
 */
function delete_transient( $key ) {
	$found = isset( $GLOBALS['gc_transients'][ $key ] );
	unset( $GLOBALS['gc_transients'][ $key ] );

	return $found;
}

/**
 * @param string $url  URL.
 * @param array  $args Arguments.
 *
 * @return array|WP_Error
 */
function wp_remote_get( $url, $args = array() ) {
	return FakeHttp::respond( 'GET', $url, $args );
}

/**
 * @param string $url  URL.
 * @param array  $args Arguments.
 *
 * @return array|WP_Error
 */
function wp_remote_post( $url, $args = array() ) {
	return FakeHttp::respond( 'POST', $url, $args );
}

/**
 * @param array|WP_Error $response Response.
 *
 * @return int|string
 */
function wp_remote_retrieve_response_code( $response ) {
	return is_array( $response ) ? $response['response']['code'] : '';
}

/**
 * @param array|WP_Error $response Response.
 *
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	return is_array( $response ) ? $response['body'] : '';
}

/**
 * The options table, for clear_cache() with no key.
 */
$GLOBALS['wpdb'] = new class() {

	/**
	 * @var string
	 */
	public string $options = 'wp_options';

	/**
	 * @param string $text Text.
	 *
	 * @return string
	 */
	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	/**
	 * @param string $query Query.
	 * @param mixed  ...$args Arguments.
	 *
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		return (string) $args[0];
	}

	/**
	 * The transient rows whose names match a prepared LIKE.
	 *
	 * @param string $like The LIKE pattern, as prepare() left it.
	 *
	 * @return string[]
	 */
	public function get_col( $like ) {
		$prefix = stripslashes( substr( $like, 0, -1 ) );
		$names  = array();

		foreach ( array_keys( $GLOBALS['gc_transients'] ) as $key ) {
			if ( str_starts_with( '_transient_' . $key, $prefix ) ) {
				$names[] = '_transient_' . $key;
			}
		}

		return $names;
	}
};

