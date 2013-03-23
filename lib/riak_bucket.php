<?php

/**
 * The RiakBucket object allows you to access and change information
 * about a Riak bucket, and provides methods to create or retrieve
 * objects within the bucket.
 * @package RiakBucket
 */
class RiakBucket {
  function RiakBucket($client, $name) {
    $this->client = $client;
    $this->name = $name;
    $this->r = NULL;
    $this->w = NULL;
    $this->dw = NULL;
  }

  /**
   * Get the bucket name.
   */
  function getName() {
    return $this->name;
  }

  /** 
   * Get the R-value for this bucket, if it is set, otherwise return
   * the R-value for the client.
   * @return integer
   */
  function getR($r=NULL)     { 
    if ($r != NULL) return $r;
    if ($this->r != NULL) return $this->r;
    return $this->client->getR();
  }
  
  /**
   * Set the R-value for this bucket. get(...) and getBinary(...)
   * operations that do not specify an R-value will use this value.
   * @param integer $r - The new R-value.
   * @return $this
   */
  function setR($r)   { 
    $this->r = $r; 
    return $this;
  }

  /**
   * Get the W-value for this bucket, if it is set, otherwise return
   * the W-value for the client.
   * @return integer
   */
  function getW($w)     { 
    if ($w != NULL) return $w;
    if ($this->w != NULL) return $this->w;
    return $this->client->getW();
  }

  /**
   * Set the W-value for this bucket. See setR(...) for more information.
   * @param  integer $w - The new W-value.
   * @return $this
   */
  function setW($w)   { 
    $this->w = $w; 
    return $this;
  }

  /**
   * Get the DW-value for this bucket, if it is set, otherwise return
   * the DW-value for the client.
   * @return integer
   */
  function getDW($dw)    { 
    if ($dw != NULL) return $dw;
    if ($this->dw != NULL) return $this->dw;
    return $this->client->getDW();
  }

  /**
   * Set the DW-value for this bucket. See setR(...) for more information.
   * @param  integer $dw - The new DW-value
   * @return $this
   */
  function setDW($dw) { 
    $this->dw = $dw; 
    return $this;
  }

  /**
   * Create a new Riak object that will be stored as JSON.
   * @param  string $key - Name of the key.
   * @param  object $data - The data to store. (default NULL)
   * @return RiakObject
   */
  function newObject($key, $data=NULL) {
    $obj = new RiakObject($this->client, $this, $key);
    $obj->setData($data);
    $obj->setContentType('application/json');
    $obj->jsonize = TRUE;
    return $obj;
  }

  /**
   * Create a new Riak object that will be stored as plain text/binary.
   * @param  string $key - Name of the key.
   * @param  object $data - The data to store.
   * @param  string $content_type - The content type of the object. (default 'application/json')
   * @return RiakObject
   */
  function newBinary($key, $data, $content_type='application/json') {
    $obj = new RiakObject($this->client, $this, $key);
    $obj->setData($data);
    $obj->setContentType($content_type);
    $obj->jsonize = FALSE;
    return $obj;
  }

  /**
   * Retrieve a JSON-encoded object from Riak.
   * @param  string $key - Name of the key.
   * @param  int    $r   - R-Value of the request (defaults to bucket's R)
   * @return RiakObject
   */
  function get($key, $r=NULL) {
    $obj = new RiakObject($this->client, $this, $key);
    $obj->jsonize = TRUE;
    $r = $this->getR($r);
    return $obj->reload($r);
  }

  /**
   * Retrieve a binary/string object from Riak.
   * @param  string $key - Name of the key.
   * @param  int    $r   - R-Value of the request (defaults to bucket's R)
   * @return RiakObject
   */
  function getBinary($key, $r=NULL) {
    $obj = new RiakObject($this->client, $this, $key);
    $obj->jsonize = FALSE;
    $r = $this->getR($r);
    return $obj->reload($r);
  }

  /**
   * Set the N-value for this bucket, which is the number of replicas
   * that will be written of each object in the bucket. Set this once
   * before you write any data to the bucket, and never change it
   * again, otherwise unpredictable things could happen. This should
   * only be used if you know what you are doing.
   * @param integer $nval - The new N-Val.
   */
  function setNVal($nval) {
    return $this->setProperty("n_val", $nval);
  }

  /**
   * Retrieve the N-value for this bucket.
   * @return integer
   */
  function getNVal() {
    return $this->getProperty("n_val");
  }

  /**
   * If set to true, then writes with conflicting data will be stored
   * and returned to the client. This situation can be detected by
   * calling hasSiblings() and getSiblings(). This should only be used
   * if you know what you are doing.
   * @param  boolean $bool - True to store and return conflicting writes.
   */
  function setAllowMultiples($bool) {
    return $this->setProperty("allow_mult", $bool);
  }

  /**
   * Retrieve the 'allow multiples' setting.
   * @return Boolean
   */
  function getAllowMultiples() {
    return "true" == $this->getProperty("allow_mult");
  }

  /**
   * Set a bucket property. This should only be used if you know what
   * you are doing.
   * @param  string $key - Property to set.
   * @param  mixed  $value - Property value.
   */
  function setProperty($key, $value) {
    return $this->setProperties(array($key=>$value));
  }

  /**
   * Retrieve a bucket property.
   * @param string $key - The property to retrieve.
   * @return mixed
   */
  function getProperty($key) {
    $props = $this->client->backend->getBucketProps($this);
    if (array_key_exists($key, $props)) {
      return $props[$key];
    } else {
      return NULL;
    }
  }

  /**
   * Set multiple bucket properties in one call. This should only be
   * used if you know what you are doing.
   * @param  array $props - An associative array of $key=>$value.
   */
  function setProperties($props) {
    return $this->client->backend->setBucketProps($this, $props); 
  }

  /**
   * Retrieve an array of all keys in this bucket.
   * Note: this operation is pretty slow.
   * @return Array
   */
  function getKeys() {
    return $this->client->backend->getKeys(); 
  }
  
  /**
   * Search a secondary index
   * @author Eric Stevens <estevens@taglabsinc.com>
   * @param string $indexName - The name of the index to search
   * @param string $indexType - The type of index ('int' or 'bin')
   * @param string|int $startOrExact
   * @param string|int optional $end
   * @param bool optional $dedupe - whether to eliminate duplicate entries if any
   * @return array of RiakLinks
   */
  function indexSearch($indexName, $indexType, $startOrExact, $end=NULL, $dedupe=false) {
    $url = RiakUtils::buildIndexPath($this->client, $this, "{$indexName}_{$indexType}", $startOrExact, $end);
    $response = RiakUtils::httpRequest('GET', $url);
    
    $obj = new RiakObject($this->client, $this, NULL);
    $obj->populate($response, array(200));
    if (!$obj->exists()) {
      throw new Exception("Error searching index.");
    }
    $data = $obj->getData();
    $keys = array_map("urldecode",$data["keys"]);
    
    $seenKeys = array();
    foreach($keys as $id=>&$key) {
      if ($dedupe) {
        if (isset($seenKeys[$key])) {
          unset($keys[$id]);
          continue;
        }
        $seenKeys[$key] = true;
      }
      $key = new RiakLink($this->name, $key);
      $key->client = $this->client;
    }
    return $keys;
  }

}

?>
