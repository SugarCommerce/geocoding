# Geocoding

Turn an address into a place on the map, and a place back into an address,
from WordPress -- with Google, Maps.co or Google's Address Validation, and
one answer whichever it was.

## What it does

A store locator, a delivery radius or a "nearest stockist" needs the
coordinates of an address, once, when it is saved. Each service answers in
its own shape: Google in result arrays and address components, Maps.co in
OpenStreetMap's fields, Address Validation in a verdict with a geocode
inside it.

Each has a client here with everything it did, on the WordPress HTTP API.
Each also answers `locate()` with a common `Location` -- coordinates, the
address in parts, the place id -- so the service can change without the
code that uses it. Answers are cached, since an address does not move, and
failures are remembered for a minute so an outage costs one visitor a
timeout rather than all of them.

## Features

* Turn an address into coordinates, and coordinates into an address, with one call whichever service
* Read the address in parts: house number, street, city, county, region, postcode, country
* Work out how far apart two points are, in kilometres, miles, metres, feet or yards
* Link to a point in Google, Apple, Bing or OpenStreetMap without building URLs
* Ask Google how much of an address it could confirm, and what it corrected
* Start on Maps.co without a billing account, and move to Google later

## Installation

```bash
composer require sugarcommerce/geocoding
```

## Quick start

Store coordinates when an address is saved:

```php
use SugarCommerce\Geocoding\Providers\MapsCo;

$location = ( new MapsCo( $key ) )->locate( $store->address );

if ( ! is_wp_error( $location ) && $location->has_coordinates() ) {
	update_post_meta( $store->ID, '_lat', $location->get_latitude() );
	update_post_meta( $store->ID, '_lng', $location->get_longitude() );
}
```

Then, finding what is near:

```php
use SugarCommerce\Geocoding\Distance;

$miles = Distance::between( $lat, $lng, $store_lat, $store_lng, 'mi' );
```

That is a straight line, not a road -- right for "within 10 miles", wrong
for a delivery estimate.

## The geocoders

| Class | Needs | Good for |
|---|---|---|
| `Providers\Google` | a Google Maps key on a billing account | a single building |
| `Providers\MapsCo` | a Maps.co key, no billing account | a town, a postcode, a delivery radius |
| `Providers\AddressValidation` | a Google Maps key | checking an address at checkout, and where it is |

Each takes its key and an array of settings, with setters for them:

```php
new Google( $key, array(
	'cache'       => true,   // set_cache_enabled()
	'cache_ttl'   => 86400,  // set_cache_ttl(); a day, a week for Maps.co
	'failure_ttl' => 60,     // set_failure_ttl(); 0 retries every time
) );

new MapsCo( $key, array( 'format' => 'jsonv2' ) );
```

`clear_cache( $what )` forgets one answer, `clear_cache()` every answer that
provider kept -- with a persistent object cache too, where the answers are
not in the options table: it starts a new generation of cache keys (an
option named `geocoding_{id}_generation`), so nothing kept before is found again. Errors are `WP_Error`s whose codes start with the geocoder's
id: `google_api_error`, `mapsco_no_results`, `address_validation_unsupported`.

The cache, the failure memory and reading an answer are sugarcommerce/network's
`SugarCommerce\Network\Remote\Service` -- the family's one cached-API base,
shared with ip-lookup -- and each answer extends its `Remote\Response`. A
geocoder of your own extends it, implements `Geocoder`, and sets
`CACHE_GROUP = 'geocoding'` to keep its transients with these.

## The common answer

```php
$location = $geocoder->locate( '10 Downing Street, London' );
$location = $geocoder->locate_point( 51.5034, -0.1276 );

$location->get_coordinates();        // [ 'latitude' => 51.5034, 'longitude' => -0.1276 ], or null
$location->get_formatted_address();
$location->get_house_number();       // and get_street(), get_city(), get_county()
$location->get_region();             // and get_region_code(), get_postal_code()
$location->get_country();            // and get_country_code()
$location->get_place_id();
$location->distance_to( $other, 'mi' );
$location->get_map_urls();           // google, apple, bing, osm
$location->get_response();           // the service's own response
```

A part the service did not give is null. Nothing found is an error,
`{id}_no_results`; Address Validation cannot reverse geocode and says
`address_validation_unsupported`.

## Google

```php
$response = $google->geocode( $address, array( 'region' => 'uk' ) );
$response = $google->reverse_geocode( $lat, $lng );

$response->get_location_type();      // ROOFTOP, RANGE_INTERPOLATED, GEOMETRIC_CENTER, APPROXIMATE
$response->is_partial_match();
$response->get_structured_address();
$response->get_address_component( 'postal_town' );
$response->get_results();            // every result, not only the first
```

Nothing found is an answer -- `get_status()` is `ZERO_RESULTS` -- and is
cached like one. A refusal (`REQUEST_DENIED`, `OVER_QUERY_LIMIT`) is an
error with Google's message.

## Maps.co

```php
$response = $mapsco->geocode( $address );   // the most important match
$response->get_display_name();
$response->get_osm_type();                  // node, way, relation
$response->get_bounding_box_dimensions( 'mi' );
$response->get_google_maps_url();           // and the other three
```

It will not reliably find a flat in a building, or a street built last year.

## Address Validation

Ask the customer to confirm rather than silently correcting them:

```php
$result = $validation->validate( array( 'regionCode' => 'GB', 'addressLines' => array( $line1, $line2 ) ) );

if ( ! is_wp_error( $result ) && ! $result->is_address_complete() ) {
	// Show $result->get_formatted_address() and ask.
}
```

`has_inferred_components()`, `has_replaced_components()`,
`get_missing_component_types()`, `is_shippable()`, `get_score()` and
`get_rating()` say how far to trust it; `set_usps()`,
`set_language_options()`, `set_previous_response()` and
`set_session_token()` shape the request. A valid address is not a verified
one: treat a failed check as a reason to ask, never to refuse the order.

## Distances and map links

```php
use SugarCommerce\Geocoding\{Distance, Maps};

Distance::between( $lat1, $lng1, $lat2, $lng2, 'km' );           // km, m, mi, ft, yd
Distance::between_points( array( 'lat' => 1, 'lng' => 2 ), $to );
Distance::from_km( 12.5, 'mi' );

Maps::google( $lat, $lng );   // and apple(), bing(), openstreetmap(), urls()
```

Escape a link where it is printed.

## Moving from arraypress/wp-*

This library is `wp-google-geocoding`, `wp-mapsco-geocoding` and
`wp-google-address-validation` in one.

| Was | Now |
|---|---|
| `ArrayPress\Google\Geocoding\Client` | `SugarCommerce\Geocoding\Providers\Google` |
| `ArrayPress\Google\Geocoding\Response` | `Providers\Google\Response` |
| `ArrayPress\Google\Geocoding\Distance` | `SugarCommerce\Geocoding\Distance` |
| `ArrayPress\MapsCo\Geocoding\Client` | `Providers\MapsCo` |
| `ArrayPress\MapsCo\Geocoding\Response` | `Providers\MapsCo\Response` |
| `ArrayPress\Google\AddressValidation\Client` | `Providers\AddressValidation` |
| `ArrayPress\Google\AddressValidation\Response` | `Providers\AddressValidation\Response` |
| `new Client( $key, $enable_cache, $expiration )` | `new Google( $key, [ 'cache' => $enable_cache, 'cache_ttl' => $expiration ] )`, the same for the others |
| `set_cache_expiration()`, `get_cache_expiration()` | `set_cache_ttl()`, `get_cache_ttl()` |
| `get_cache_settings()` | `is_cache_enabled()`, `get_cache_ttl()`, `get_failure_ttl()` |
| `Response::get_all()` (Google), `get_raw_data()` (Maps.co) | `to_array()`; one field is `get( 'field' )` |
| Maps.co `Response::get_*_maps_url()`, `get_map_urls()` | unchanged, built by `Maps` |
| Maps.co `Response::get_available_distance_units()` | unchanged, or `Distance::units()` |
| `clear_cache( 'forward_' . $address )` (Maps.co) | `clear_cache( 'forward_' . $address . '|json' )` -- the format is part of the question |
| `SugarCommerce\Geocoding\{Service, Response}` | `SugarCommerce\Network\Remote\{Service, Response}` (sugarcommerce/network) -- one copy, shared with ip-lookup; keys and behaviour unchanged |

Answers that changed:

* Error codes start with the geocoder's id. They were `api_error`, `json_error`, `invalid_address`, `no_results` with no prefix.
* Google's refusal carries its `error_message`, not only the status. Google refuses an empty address and impossible coordinates before asking, as Maps.co did.
* Google's `geocode()` and `reverse_geocode()` take the API's other parameters -- `region`, `language`, `components` -- as a second or third argument.
* Maps.co keeps "nothing found" for the cache lifetime instead of asking again every time.
* Address Validation refuses an empty address before asking.
* Text domain `arraypress` is `sugarcommerce`.

New: `Geocoder`, `Location`, `locate()` and `locate_point()`, `Maps`, and
`Distance::from_km()` and `units()`.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later
* sugarcommerce/network (and through it sugarcommerce/plugin)
* A key for the service you use

## License

GPL-2.0-or-later
