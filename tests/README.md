# Tests

`composer test` runs the PHPUnit suite. There is no WordPress to load: the
core functions the library touches are stubbed in `bootstrap.php`,
transients keep to a clock the tests can move, and the HTTP API answers from
a queue (`Support/FakeHttp.php`) that records every request.

- **DistanceTest** -- distances, units, map links and the `Location` shape.
- **Providers/** -- each geocoder's requests, statuses, cache and common answer.
