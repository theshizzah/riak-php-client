### OVERVIEW 
My fork of Basho's PHP client for Riak with additional features added. Still a work in progress, but making improvements at a quick pace. See Changelog/TODOs to see what's still in development.

### DESCRIPTION 
While Basho's PHP client is a great starting point for using Riak, I found it lacking in several key ways:

  * no support for Riak's PBC API (REST-only)
  * only connects to a single Riak host
  * tight coupling between Riak object/bucket classes and REST API
  * unit tests have no setup/teardown phases, prone to fail due to state left around by previous failed tests
  * no support for new [riak_dt](https://github.com/basho/riak_dt) CRDTs

### Changelog 
  * created interface RiakBackendInterface (`lib/riak_backend_interface.php`)  to define required interface for the REST and (forthcoming) PBC backends
  * moved inner classes from riak.php into
      * `lib/riak_object.php`
      * `lib/riak_link.php`
      * `lib/riak_utils.php`
      * `lib/riak_bucket.php`
      * `lib/riak_mapreduce.php`
  * refactored REST API-specific code into RiakBackendHTTP

### TODOs 
  * proper namespacing and class autoloading
  * implement PBC API backend in RiakBackendPBC
  * add multi-host support and connection management
  * reorganize `unit_tests.php` into a PHPUnit test suite
  * support fo Riak CRDTs

### Official Riak PHP Client 
The original PHP client, and documentation on usage, can be found at

<http://basho.github.com/riak-php-client/>

