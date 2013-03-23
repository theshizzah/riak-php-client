<?php

  /**
   * The RiakLinkPhase object holds information about a Link phase in a
   * map/reduce operation.
   * @package RiakLinkPhase
   */
  class RiakLinkPhase {
    /**
     * Construct a RiakLinkPhase object.
     * @param string $bucket - The bucket name.
     * @param string $tag - The tag.
     * @param boolean $keep - True to return results of this phase.
     */
    function RiakLinkPhase($bucket, $tag, $keep) {
      $this->bucket = $bucket;
      $this->tag = $tag;
      $this->keep = $keep;
    }

    /**
     * Convert the RiakLinkPhase to an associative array. Used
     * internally.
     */
    function to_array() {
      $stepdef = array("bucket"=>$this->bucket,
                       "tag"=>$this->tag,
                       "keep"=>$this->keep);
      return array("link"=>$stepdef);
    }
  }

  /**
   * The RiakMapReducePhase holds information about a Map phase or
   * Reduce phase in a RiakMapReduce operation.
   * @package RiakMapReducePhase
   */
  class RiakMapReducePhase {
    /**
     * Construct a RiakMapReducePhase object.
     * @param string $type - "map" or "reduce"
     * @param mixed $function - string or array() 
     * @param string $language - "javascript" or "erlang"
     * @param boolean $keep - True to return the output of this phase in
     * the results.
     * @param mixed $arg - Additional value to pass into the map or
     * reduce function.
     */
    function RiakMapReducePhase($type, $function, $language, $keep, $arg) {
      $this->type = $type;
      $this->language = $language;
      $this->function = $function;
      $this->keep = $keep;
      $this->arg = $arg;
    }
  
  /**
   * Convert the RiakMapReducePhase to an associative array. Used
   * internally.
   */
  function to_array() {
    $stepdef = array("keep"=>$this->keep,
                     "language"=>$this->language,
                     "arg"=>$this->arg);

    if ($this->language == "javascript" && is_array($this->function)) {
      $stepdef["bucket"] = $this->function[0];      
      $stepdef["key"] = $this->function[1];      
    } else if ($this->language == "javascript" && is_string($this->function)) {
      if (strpos($this->function, "{") == FALSE) 
        $stepdef["name"] = $this->function;      
      else
        $stepdef["source"] = $this->function;      
    } else if ($this->language == "erlang" && is_array($this->function)) {
      $stepdef["module"] = $this->function[0];
      $stepdef["function"] = $this->function[1];
    }
    
    return array(($this->type)=>$stepdef);
  }
}

/**
 * The RiakMapReduce object allows you to build up and run a
 * map/reduce operation on Riak.
 * @package RiakMapReduce
 */
class RiakMapReduce {
  
  /**
   * Construct a Map/Reduce object.
   * @param RiakClient $client - A RiakClient object.
   * @return RiakMapReduce
   */
  function RiakMapReduce($client) {
    $this->client = $client;
    $this->phases = array();
    $this->inputs = array();
    $this->input_mode = NULL;
    $this->key_filters = array();
    $this->index = array();
  }

  /**
   * Add inputs to a map/reduce operation. This method takes three
   * different forms, depending on the provided inputs. You can
   * specify either  a RiakObject, a string bucket name, or a bucket,
   * key, and additional arg.
   * @param mixed $arg1 - RiakObject or Bucket
   * @param mixed $arg2 - Key or blank
   * @param mixed $arg3 - Arg or blank
   * @return RiakMapReduce
   */
  function add($arg1, $arg2=NULL, $arg3=NULL) {
    if (func_num_args() == 1) {
      if ($arg1 instanceof RiakObject) 
        return $this->add_object($arg1);
      else
        return $this->add_bucket($arg1);
    }
    return $this->add_bucket_key_data($arg1, (string) $arg2, $arg3);
  }

  /**
   * Private.
   */
  private function add_object($obj) {
    return $this->add_bucket_key_data($obj->bucket->name, $obj->key, NULL);
  }
  
  /**
   * Private.
   */
  private function add_bucket_key_data($bucket, $key, $data) {
    if ($this->input_mode == "bucket") 
      throw new Exception("Already added a bucket, can't add an object.");
    $this->inputs[] = array($bucket, $key, $data);
    return $this;
  }

  /**
   * Private.
   * @return $this
   */
  private function add_bucket($bucket) {
    $this->input_mode = "bucket";
    $this->inputs = $bucket;
    return $this;
  }

  /**
   * Begin a map/reduce operation using a Search. This command will 
   * return an error unless executed against a Riak Search cluster.
   * @param string $bucket - The Bucket to search.  @param string
   * query - The Query to execute. (Lucene syntax.)  @return
   * RiakMapReduce
   */
  function search($bucket, $query) {
    $this->inputs = array("module"=>"riak_search", "function"=>"mapred_search", "arg"=>array($bucket, $query));
    return $this;
  }

  /**
   * Add a link phase to the map/reduce operation.
   * @param string $bucket - Bucket name (default '_', which means all
   * buckets)
   * @param string $tag - Tag (default '_', which means all buckets)
   * @param boolean $keep - Flag whether to keep results from this
   * stage in the map/reduce. (default FALSE, unless this is the last
   * step in the phase)
   * @return $this
   */
  function link($bucket='_', $tag='_', $keep=FALSE) {
    $this->phases[] = new RiakLinkPhase($bucket, $tag, $keep);
    return $this;
  }

  /**
   * Add a map phase to the map/reduce operation.
   * @param mixed $function - Either a named Javascript function (ie:
   * "Riak.mapValues"), or an anonymous javascript function (ie:
   * "function(...) { ... }" or an array ["erlang_module",
   * "function"].
   * @param array() $options - An optional associative array
   * containing "language", "keep" flag, and/or "arg".
   * @return $this
   */
  function map($function, $options=array()) {
    $language = is_array($function) ? "erlang" : "javascript";
    $this->phases[] = new RiakMapReducePhase("map",
                                             $function,
                                             RiakUtils::get_value("language", $options, $language),
                                             RiakUtils::get_value("keep", $options, FALSE),
                                             RiakUtils::get_value("arg", $options, NULL));
    return $this;
  }

  /**
   * Add a reduce phase to the map/reduce operation.
   * @param mixed $function - Either a named Javascript function (ie:
   * "Riak.mapValues"), or an anonymous javascript function (ie:
   * "function(...) { ... }" or an array ["erlang_module",
   * "function"].
   * @param array() $options - An optional associative array
   * containing "language", "keep" flag, and/or "arg".
   * @return $this
   */
  function reduce($function, $options=array()) {
    $language = is_array($function) ? "erlang" : "javascript";
    $this->phases[] = new RiakMapReducePhase("reduce", 
                                             $function,
                                             RiakUtils::get_value("language", $options, $language),
                                             RiakUtils::get_value("keep", $options, FALSE),
                                             RiakUtils::get_value("arg", $options, NULL));
    return $this;
  }
  
  /**
   * Add a key filter to the map/reduce operation.  If there are already
   * existing filters, an "and" condition will be used to combine them.
   * Alias for key_filter_and
   * @param array $filter - a key filter (ie: 
   * ->key_filter(
   * 	array("tokenize", "-", 2), 
   * 	array("between", "20110601", "20110630")
   * )
   * @return $this
   */
  function key_filter(array $filter /*. ,$filter .*/) {
    $args = func_get_args();
    array_unshift($args, 'and');
    return call_user_func_array(array($this, 'key_filter_operator'), $args);
  }
  
  /**
   * Add a key filter to the map/reduce operation.  If there are already
   * existing filters, an "and" condition will be used to combine them.
   * @param array $filter - a key filter (ie: 
   * ->key_filter(
   * 	array("tokenize", "-", 2), 
   * 	array("between", "20110601", "20110630")
   * )
   * @return $this
   */
  function key_filter_and(array $filter) {
    $args = func_get_args();
    array_unshift($args, 'and');
    return call_user_func_array(array($this, 'key_filter_operator'), $args);
  }
  
  /**
   * Adds a key filter to the map/reduce operation.  If there are already
   * existing filters, an "or" condition will be used to combine with the
   * existing filters.
   * @param array $filter
   * @return $this
   */
  function key_filter_or(array $filter /*. ,$filter .*/) {
    $args = func_get_args();
    array_unshift($args, 'or');
    return call_user_func_array(array($this, 'key_filter_operator'), $args);
  }
  
  /**
   * Adds a key filter to the map/reduce operation.  If there are already
   * existing filters, the provided conditional operator will be used
   * to combine with the existing filters.
   * @param string $operator - Operator (usually "and" or "or")
   * @param array $filter
   * @return $this
   */
  function key_filter_operator($operator,  $filter /*. ,$filter .*/) {
    $filters = func_get_args();
    array_shift($filters);
    if ($this->input_mode != 'bucket')
      throw new Exception("Key filters can only be used in bucket mode");
    if (count($this->index))
      throw new Exception("You cannot use index search and key filters on the same operation");
    
    if (count($this->key_filters) > 0) {
      $this->key_filters = array(array(
        $operator,
        $this->key_filters,
        $filters
      ));
    } else {
      $this->key_filters = $filters;
    }
    return $this;
  }
  
  /**
   * Performs an index search as part of a Map/Reduce operation
   * Note that you can only do index searches on a bucket, so
   * this is incompatible with object or key operations, as well
   * as key filter operations.
   * @param string $indexName The name of the index to search. 
   * @param string $indexType The index type ('bin' or 'int')
   * @param string|int $startOrExact Start value to search for, or
   * exact value if no end value specified.
   * @param string|int optional $end End value to search for during
   * a range search
   * @return $this 
   */
  function indexSearch($indexName, $indexType, $startOrExact, $end=NULL) {
    // Check prerequisites
    if (count($this->key_filters))
      throw new Exception("You cannot use index search and key filters on the same operation");
    if ($this->input_mode != 'bucket')
      throw new Exception("Key filters can only be used in bucket mode");
    
    if ($end === NULL) {
      $this->index = array(
        'index' => "{$indexName}_{$indexType}",
        'key' => urlencode($startOrExact)
      );
    } else {
      $this->index = array(
        'index' => "{$indexName}_{$indexType}",
        'start' => urlencode($startOrExact),
        'end' => urlencode($end)
      );
    }
    return $this;
    
  }
  

  /**
   * Run the map/reduce operation. Returns an array of results, or an
   * array of RiakLink objects if the last phase is a link phase. 
   * @param integer $timeout - Timeout in seconds.
   * @return array()
   */
  function run($timeout=NULL) {
    $num_phases = count($this->phases);

    $linkResultsFlag = FALSE;

    # If there are no phases, then just echo the inputs back to the user.
    if ($num_phases == 0) {
      $this->reduce(array("riak_kv_mapreduce", "reduce_identity"));
      $num_phases = 1;
      $linkResultsFlag = TRUE;
    }

    # Convert all phases to associative arrays. Also,
    # if none of the phases are accumulating, then set the last one to
    # accumulate.
    $keep_flag = FALSE;
    $query = array();
    for ($i = 0; $i < $num_phases; $i++) {
      $phase = $this->phases[$i];
      if ($i == ($num_phases - 1) && !$keep_flag)
        $phase->keep = TRUE;
      if ($phase->keep) $keep_flag = TRUE;
      $query[] = $phase->to_array();
    }
    
    # Add key filters if applicable
    if ($this->input_mode == 'bucket' && count($this->key_filters) > 0) {
      $this->inputs = array(
        'bucket' => $this->inputs,
        'key_filters' => $this->key_filters
      );
    }
    
    # Add index search if applicable
    if ($this->input_mode == 'bucket' && count($this->index) > 0) {
      $this->inputs = array_merge(array('bucket'=>$this->inputs), $this->index);
    }

    # Construct the job, optionally set the timeout...
    $job = array("inputs"=>$this->inputs, "query"=>$query);
    if ($timeout != NULL) $job["timeout"] = $timeout;
    $content = json_encode($job); 
    
    # Do the request...
    $url = "http://" . $this->client->host . ":" . $this->client->port . "/" . $this->client->mapred_prefix;
    $response = RiakUtils::httpRequest('POST', $url, array('Content-type: application/json'), $content);
    $result = json_decode($response[1]);

    # If the last phase is NOT a link phase, then return the result.
    $linkResultsFlag |= (end($this->phases) instanceof RiakLinkPhase);

    # If we don't need to link results, then just return.
    if (!$linkResultsFlag) return $result;

    # Otherwise, if the last phase IS a link phase, then convert the
    # results to RiakLink objects.
    $a = array();
    foreach ($result as $r) {
      $tag = isset($r[2]) ? $r[2] : null;
      $link = new RiakLink($r[0], $r[1], $tag);
      $link->client = $this->client;
      $a[] = $link;
    }
    return $a;
  }
}

?>
