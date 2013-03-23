<?php

include(dirname(__FILE__).'/riak_backend_interface.php');

class RiakBackendHTTP implements RiakBackendInterface {
  # TODO: add support for riak node list
  function RiakBackendHTTP($host='127.0.0.1', $port=8098, $prefix='riak', $mapred_prefix='mapred') {
    $this->host = $host;
    $this->port = $port;
    $this->prefix = $prefix;    
    $this->mapred_prefix = $mapred_prefix;
  }

  function buckets(){
    $url = self::buildRestPath($this);
    $response = self::httpRequest('GET', $url.'?buckets=true');
    $response_obj = json_decode($response[1]);
    $buckets = array();
    foreach($response_obj->buckets as $name) {
        $buckets[] = $this->bucket($name);
    }
    return $buckets;
  }

  function isAlive(){
    $url = 'http://' . $this->host . ':' . $this->port . '/ping';
    $response = self::httpRequest('GET', $url);
    return ($response != NULL) && ($response[1] == 'OK');
  }

  # TODO, move logic around RiakObject back to riak.php, but get object contents via $backend
  function getBucketProps($bucket){
    # Run the request...
    $params = array('props' => 'true', 'keys' => 'false');
    $url = self::buildRestPath($this, $bucket, NULL, NULL, $params);
    $response = self::httpRequest('GET', $url);

    # Use a RiakObject to interpret the response, we are just interested in the value.
    $obj = new RiakObject($this, $bucket, NULL);
    $obj->populate($response, array(200));
    if (!$obj->exists()) {
      throw new Exception("Error getting bucket properties.");
    }
    
    $props = $obj->getData();
    $props = $props["props"];

    return $props;
  }
  
  /**
   * Given a RiakClient, RiakBucket, Key, LinkSpec, and Params,
   * construct and return a URL.
   */
  public static function buildRestPath($client, $bucket=NULL, $key=NULL, $spec=NULL, $params=NULL) {
    # Build 'http://hostname:port/prefix/bucket'
    $path = 'http://';
    $path.= $client->host . ':' . $client->port;
    $path.= '/' . $client->prefix;
    
    # Add '.../bucket'
    if (!is_null($bucket) && $bucket instanceof RiakBucket) {
      $path .= '/' . urlencode($bucket->name);
    }
    
    # Add '.../key'
    if (!is_null($key)) {
      $path .= '/' . urlencode($key);
    }

    # Add '.../bucket,tag,acc/bucket,tag,acc'
    if (!is_null($spec)) {
      $s = '';
      foreach($spec as $el) {
	if ($s != '') $s .= '/';
	$s .= urlencode($el[0]) . ',' . urlencode($el[1]) . ',' . $el[2] . '/';
      }
      $path .= '/' . $s;
    }

    # Add query parameters.
    if (!is_null($params)) {
      $s = '';
      foreach ($params as $key => $value) {
	if ($s != '') $s .= '&';
	$s .= urlencode($key) . '=' . urlencode($value);
      }

      $path .= '?' . $s;
    }

    return $path;
  }
  
  /**
   * Given a Method, URL, Headers, and Body, perform and HTTP request,
   * and return an array of arity 2 containing an associative array of
   * response headers and the response body.
   */
  public static function httpRequest($method, $url, $request_headers = array(), $obj = '') {
    # Set up curl
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

    if ($method == 'GET') {
      curl_setopt($ch, CURLOPT_HTTPGET, 1);
    } else if ($method == 'POST') {
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $obj);
    } else if ($method == 'PUT') {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
      curl_setopt($ch, CURLOPT_POSTFIELDS, $obj);
    } else if ($method == 'DELETE') {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    # Capture the response headers...
    $response_headers_io = new RiakStringIO();
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(&$response_headers_io, 'write'));

    # Capture the response body...
    $response_body_io = new RiakStringIO();
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, array(&$response_body_io, 'write'));

    try {
      # Run the request.
      curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      # Get the headers...
      $parsed_headers = self::parseHttpHeaders($response_headers_io->contents());
      $response_headers = array("http_code"=>$http_code);
      foreach ($parsed_headers as $key=>$value) {
        $response_headers[strtolower($key)] = $value;
      }
      
      # Get the body...
      $response_body = $response_body_io->contents();

      # Return a new RiakResponse object.
      return array($response_headers, $response_body);
    } catch (Exception $e) {
      curl_close($ch);
      error_log('Error: ' . $e->getMessage());
      return NULL;
    } 
  }
  
  /**
   * Parse an HTTP Header string into an asssociative array of
   * response headers.
   */
  static function parseHttpHeaders($headers) {
    $retVal = array();
    $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $headers));
    foreach( $fields as $field ) {
      if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
        $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
        if( isset($retVal[$match[1]]) ) {
          $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
        } else {
          $retVal[$match[1]] = trim($match[2]);
        }
      }
    }
    return $retVal;
  }
  
  /**
   * Given a RiakClient, RiakBucket, Key, LinkSpec, and Params,
   * construct and return a URL for searching secondary indexes.
   * @author Eric Stevens <estevens@taglabsinc.com>
   * @param RiakClient $client
   * @param RiakBucket $bucket
   * @param string $index - Index Name & type (eg, "indexName_bin")
   * @param string|int $start - Starting value or exact match if no ending value
   * @param string|int $end - Ending value for range search
   * @return string URL
   */
  public static function buildIndexPath(RiakClient $client, RiakBucket $bucket, $index, $start, $end=NULL) {
    # Build 'http://hostname:port/prefix/bucket'
    $path = array('http:/',$client->host.':'.$client->port,$client->indexPrefix);

    # Add '.../bucket'
    $path[] = urlencode($bucket->name);
    
    # Add '.../index'
    $path[] = 'index';
    
    # Add '.../index_type'
    $path[] = urlencode($index);
    
    # Add .../(start|exact)
    $path[] = urlencode($start);
    
    if (!is_null($end)) {
      $path[] = urlencode($end);
    }
    
    // faster than repeated string concatenations
    $path = join('/', $path);

    return $path;
  }

  /**
   * Set multiple bucket properties in one call. This should only be
   * used if you know what you are doing.
   * @param  array $props - An associative array of $key=>$value.
   */
  function setBucketProps($bucket, $props) {
    # Construct the URL, Headers, and Content...
    $url = self::buildRestPath($this, $bucket);

    $headers = array('Content-Type: application/json');
    $content = json_encode(array("props"=>$props));
    
    # Run the request...
    $response = self::httpRequest('PUT', $url, $headers, $content);

    # Handle the response...
    if ($response == NULL) {
      throw new Exception("Error setting bucket properties.");
    }
    
    # Check the response value...
    $status = $response[0]['http_code'];
    if ($status != 204) {
      throw new Exception("Error setting bucket properties.");
    }
  }
  
  /**
   * Retrieve an array of all keys in this bucket.
   * Note: this operation is pretty slow.
   * @return Array
   */
  function getKeys($props='false',$keys='true') {
    $params = array('props'=>'false','keys'=>'true');
    $url = self::buildRestPath($this->client, $this, NULL, NULL, $params);
    $response = self::httpRequest('GET', $url);

    # Use a RiakObject to interpret the response, we are just interested in the value.
    $obj = new RiakObject($this->client, $this, NULL);
    $obj->populate($response, array(200));
    if (!$obj->exists()) {
      throw new Exception("Error getting bucket properties.");
    }
    $keys = $obj->getData();
    return array_map("urldecode",$keys["keys"]);
  }
} 

?>
