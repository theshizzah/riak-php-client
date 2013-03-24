<img src="http://docs.basho.com/shared/1.2.1/images/riak-logo.png">

## OVERVIEW 
My fork of Basho's PHP client for Riak with additional features added. 

## DESCRIPTION 
While Basho's PHP client is a great starting point for using Riak, I found it lacking in several key ways:

  * no support for Riak's PBC API (REST-only)
  * only connects to a single Riak host
  * tight coupling between Riak object/bucket classes and REST API
  * unit tests have no setup/teardown phases, prone to fail due to state left around by previous failed tests


## Changelog 
  * refactored REST API-specific code into RiakBackendHTTP
  * created interface RiakBackendInterface (lib/riak_backend_interface.php)  to define required interface for the REST and (forthcoming) PBC backends
  * moved inner classes from riak.php into
      * lib/riak_object.php
      * lib/riak_link.php
      * lib/riak_utils.php
      * lib/riak_bucket.php
      * lib/riak_mapreduce.php

## TODOs 

## Official Riak PHP Client 
The original PHP client, and documentation on usage, can be found at

<http://basho.github.com/riak-php-client/>

