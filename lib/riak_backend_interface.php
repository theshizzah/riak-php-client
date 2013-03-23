<?php

interface RiakBackendInterface{
  public function isAlive(); 
  public function buckets();
  public function getKeys();
  public function getBucketProps($bucket); 
  public function setBucketProps($bucket, $props); 
}

?>
