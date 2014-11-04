<?php

if(!defined('IN_SCMS'))die("call from outside app");


// this file deals with loading page data. The entry point is Page::read(path)
// For each directory element of the path, it attempts to read a defaults file.
// If that file doesn't exist, no matter. 
// Then, if the lowest node is a directory, if that directory has a defaults
// file in which 'standalone' was set, we'll consider that to be a success and
// return true, otherwise we return false.
// If the lowest node is a file, we try to read it. If the file doesn't exist, we return false.
// Otherwise we return true.

class Page
{
  /// returns 1 if OK, else 0.
  
  public static function load($name,$prefix='page')
  {
    // first, try to get all the defaults files in
    
    $name=rtrim($name,'/');
    $arr = explode('/',$name);
    if($arr[count($arr)-1] == "defaults")return 0;
    $path = "";
    
    self::trydef($path,$prefix);
    foreach($arr as $s)
    {
      $path .= $s.'/';
      self::trydef($path,$prefix);
    }
    
    // is that bottom path element a directory? If so, we can return
    // straightaway provided there was a page:standalone tag in the
    // bottom defaults file.
    
    $k = rtrim(SITEPATH.'pages/'.$path,'/');
    if(is_dir($k))
    {
      // if there's a page:standalone tag defined, return OK
      // otherwise return not OK.
      return Template::exists('page:standalone');
    }
    
    // Now we read the actual page file.
    
    return Template::readtagdeffile("$prefix:",SITEPATH.'pages/',$name,'page');
  }
  
  /// try to load a defaults file from the specifier $path.
  
  static private function trydef($path,$prefix)
  {
    // before we load each one, delete the page:standalone tag
    // so it doesn't inherit
    
    Template::remove("$prefix:standalone");
    
    $k = rtrim(SITEPATH.'pages/'.$path,'/');
    if(is_dir($k))
    {
      $got = Template::readtagdeffile("$prefix:",$k,'defaults','defaults');
    }
    else $got=0;
    return $got;
  }
    
};


?>
