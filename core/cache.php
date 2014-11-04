<?php

if(!defined('IN_SCMS'))die("call from outside app");

$coreitems = array('action','c','p','page','feed','tagid','pagenumber');

global $cacheitemlist;
if(!is_null($cacheitemlist)){
  $cacheitemlist=array_unique(array_merge($cacheitemlist,$coreitems));
} else {
  $cacheitemlist = $coreitems;
}



class Cache
{
  private static function getfn($key)
  {
    return TMPPATH.'cache/'.$key;
  }
  
  # return the cache key for a given ID - a string - and query, expressed as an array
  # of param/value stuff. The key is generated from this using MD5.
  
  public static function getkey($cacheid,$arr)
  {
    global $cacheitemlist;
    
    $s=$cacheid;
    foreach($cacheitemlist as $i)
    {
      if(!is_null($arr[$i]))
      {
        $s.=$i;
        $s.=addslashes($arr[$i]);
      }
    }
    $s = md5($s);
    return $s;
  }
  
  # given a cache key, return the contents of the cache file if it
  # exists. If it doesn't, return NULL.
  
  public static function get($key)
  {
    $fn = self::getfn($key);
    if(is_file($fn))
    {
      return file_get_contents($fn);
    }
    return NULL;
  }
  
  # output data into the cache given a key and the data

  public static function put($key,$output)
  {
    $fn = self::getfn($key);
    $f = fopen($fn,'w');
    if($f)
    {
      fwrite($f,$output);
      fclose($f);
    }
    else
    {
      die("cannot create cache file $fn");
    }
  }
  
  # wipe the cache clean
  
  public static function clear()
  {
    $dir = TMPPATH.'cache';
    if($dh = @opendir($dir)){
      while($obj = readdir($dh))
      {
        if($obj =='.' || $obj=='..')continue;
        unlink($dir.'/'.$obj);
      }
    }
  }
        
  
};

// This stuff isn't part of the cache class, because it deals with a separate part
// of the caching problem - proxy and browser caching.


/// call for pages which can be cached

function set_cached_headers()
{
  // set some headers, primarily cache control
  header('Pragma:');
  header('Cache-Control: max-age=600, must-validate');
  
  $offset = 60*60*24; // 1 day expiry
  $exp = 'Expires: '.gmdate('D, d M Y H:i:s',time()+$offset).'GMT';
  header($exp);
}

/// call for pages which aren't cached

function set_uncached_headers()
{
  header('Pragma: no-cache');
  header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
}

