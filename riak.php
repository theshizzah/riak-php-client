<?php

/* 
   This file is provided to you under the Apache License,
   Version 2.0 (the "License"); you may not use this file
   except in compliance with the License.  You may obtain
   a copy of the License at
   
   http://www.apache.org/licenses/LICENSE-2.0
   
   Unless required by applicable law or agreed to in writing,
   software distributed under the License is distributed on an
   "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
   KIND, either express or implied.  See the License for the
   specific language governing permissions and limitations
   under the License.    
*/


/**
 * The Riak API for PHP allows you to connect to a Riak instance,
 * create, modify, and delete Riak objects, add and remove links from
 * Riak objects, run Javascript (and
 * Erlang) based Map/Reduce operations, and run Linkwalking
 * operations.
 *
 * See the unit_tests.php file for example usage.
 * 
 * @author Rusty Klophaus (@rklophaus) (rusty@basho.com)
 * @package RiakAPI
 */

include(dirname(__FILE__)."/lib/riak_bucket.php");
include(dirname(__FILE__)."/lib/riak_link.php");
include(dirname(__FILE__)."/lib/riak_mapreduce.php");
include(dirname(__FILE__)."/lib/riak_object.php");
include(dirname(__FILE__)."/lib/riak_utils.php");
include(dirname(__FILE__)."/lib/riak_backend_http.php");

/**
 * The RiakClient object holds information necessary to connect to
 * Riak. The Riak API uses HTTP, so there is no persistent
 * connection, and the RiakClient object is extremely lightweight.
 * @package RiakClient
 */
class RiakClient {
  /**
   * Construct a new RiakClient object.
   * @param string $host - Hostname or IP address (default '127.0.0.1')
   * @param int $port - Port number (default 8098)
   * @param string $prefix - Interface prefix (default "riak")
   * @param string $mapred_prefix - MapReduce prefix (default "mapred")
   */
  public $backend;

  function RiakClient($host='127.0.0.1', $port=8098, $prefix='riak', $mapred_prefix='mapred') {
    $this->indexPrefix='buckets';
    $this->clientid = 'php_' . base_convert(mt_rand(), 10, 36);
    $this->r = 2;
    $this->w = 2;
    $this->dw = 2;
    
    $this->host = $host;
    $this->port = $port;
    $this->prefix = $prefix;    
    $this->mapred_prefix = $mapred_prefix;

    $this->backend = new RiakBackendHTTP($host, $port, $prefix, $mapred_prefix); 
  }

  /**
   * Get the R-value setting for this RiakClient. (default 2)
   * @return integer
   */
  function getR() { 
    return $this->r; 
  }

  /**
   * Set the R-value for this RiakClient. This value will be used
   * for any calls to get(...) or getBinary(...) where where 1) no
   * R-value is specified in the method call and 2) no R-value has
   * been set in the RiakBucket.  
   * @param integer $r - The R value.
   * @return $this
   */
  function setR($r) { 
    $this->r = $r; 
    return $this; 
  }

  /**
   * Get the W-value setting for this RiakClient. (default 2)
   * @return integer
   */
  function getW() { 
    return $this->w; 
  }

  /**
   * Set the W-value for this RiakClient. See setR(...) for a
   * description of how these values are used.
   * @param integer $w - The W value.
   * @return $this
   */
  function setW($w) { 
    $this->w = $w; 
    return $this; 
  }

  /**
   * Get the DW-value for this ClientOBject. (default 2)
   * @return integer
   */
  function getDW() { 
    return $this->dw; 
  }

  /**
   * Set the DW-value for this RiakClient. See setR(...) for a
   * description of how these values are used.
   * @param  integer $dw - The DW value.
   * @return $this
   */
  function setDW($dw) { 
    $this->dw = $dw; 
    return $this; 
  }

  /**
   * Get the clientID for this RiakClient.
   * @return string
   */
  function getClientID() { 
    return $this->clientid; 
  }


  /**
   * Set the clientID for this RiakClient. Should not be called
   * unless you know what you are doing.
   * @param string $clientID - The new clientID.
   * @return $this
   */
  function setClientID($clientid) { 
    $this->clientid = $clientid; 
    return $this;
  }

  /**
   * Get the bucket by the specified name. Since buckets always exist,
   * this will always return a RiakBucket.
   * @return RiakBucket
   */
  function bucket($name) {
    return new RiakBucket($this, $name);
  }

  /**
   * Get all buckets.
   * @return array() of RiakBucket objects
   */
  function buckets() {
    return $this->backend->buckets(); 
  }

  /**
   * Check if the Riak server for this RiakClient is alive.
   * @return boolean
   */
  function isAlive() {
    return $this->backend->isAlive(); 
  }

  # MAP/REDUCE/LINK FUNCTIONS

  /**
   * Start assembling a Map/Reduce operation.
   * @see RiakMapReduce::add()
   * @return RiakMapReduce
   */
  
  function add($params) {
    $mr = new RiakMapReduce($this);
    $args = func_get_args();
    return call_user_func_array(array(&$mr, "add"), $args);
  }

  /**
   * Start assembling a Map/Reduce operation. This command will 
   * return an error unless executed against a Riak Search cluster.
   * @see RiakMapReduce::search()
   * @return RiakMapReduce
   */
  function search($params) {
    $mr = new RiakMapReduce($this);
    $args = func_get_args();
    return call_user_func_array(array(&$mr, "search"), $args);
  }
  
  # TODO, move logic around RiakObject back to riak.php, but get object contents via $backend
  function getBucketProps(){
    return $this->backend->getBucketProps();  
  }

  function setProperties($props){
    return $this->backend->setProperties($props); 
  }
}

/**
 * Private class used to accumulate a CURL response.
 * @package RiakStringIO
 */
class RiakStringIO {
  function RiakStringIO() {
    $this->contents = '';
  }

  function write($ch, $data) {
    $this->contents .= $data;
    return strlen($data);
  }

  function contents() {
    return $this->contents;
  }
}

