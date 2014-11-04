<?php
define('IN_SCMS',1);
error_reporting(E_ALL ^ E_NOTICE);

ini_set('session.use_trans_sid',1);
ini_set('display_errors',1);
#ini_set('error_reporting',E_NOTICE);
ini_set('log_errors',0);

/// a namespace for storing global data

class GlobalData
{
  /// the URL path without language element or _GET stuff. - e.g. "index.php/en_GB/foo/bar" would be "/foo/bar"
  public static $pathstring; 
  /// the URL path as an array - e.g. index.php/foo/bar" would be {"foo","bar"}. Language element removed.
  public static $patharr; 
  /// the canonical URL for this page, with all languages and default pages explicit
  public static $canonicalURL;
  
  public static $pathstringwdef; //!< path string without default inserted for empty path
  public static $cachekey=0; //!< MD5 key used to access data in the cache
  public static $stylename; //!< currently selected style
};

require_once("site/config.php");
// private things, but dependent on the user-settable stuff in config
define('SITEPATH',ROOTPATH.'site/');
define('TMPPATH',ROOTPATH.'tmp/');

require_once("core/exception.php");
require_once("core/cache.php");
require_once("core/language.php");
require_once("core/styles.php");

session_start();

/// is an explicit style set? If so, use it..
if(isset($_GET['style']))
{
  GlobalData::$stylename = $_GET['style'];
}
else
{
  /// otherwise get the style from the environment
  GlobalData::$stylename = getstylename();
}

/// this is used to store the important
/// part of the URL used to load the page
/// e.g. 'home' or 'rooms/room1' sans the
/// language data

/// initialise the language system and set up the default language
Language::init();

// first get the path data
if(isset($_SERVER['PATH_INFO'])){
  $path = $_SERVER['PATH_INFO'];
} else if(isset($_SERVER['ORIG_PATH_INFO'])){
  $path = $_SERVER['ORIG_PATH_INFO'];
} else {
  $path = DEFAULTPAGE;
}



// split into elements
$path = substr($path,1); // remove leading slash
$arr = explode('/',$path);

  
// remove any trailing slashes
$n = count($arr);
if($n && strlen($arr[$n-1])==0)
   array_pop($arr);
  
// if the first element is a valid language, set the language and remove it
if(Language::islang($arr[0]))
{
  Language::setlang($arr[0]);
  array_shift($arr);
  // rebuild the path string without the language
  $path = implode('/',$arr);
}

GlobalData::$pathstringwdef=$path; // do this before we set the default page

// if the page path is empty, set the default page.
if($arr[0]=='')
{
  $arr[0] = DEFAULTPAGE;
  $path = DEFAULTPAGE;
}

GlobalData::$pathstring=$path;
GlobalData::$patharr = $arr;

// bolt the current language code onto the
// page path (it may not have been in the URL if we
// were using the default language) to get
// a string to check with the cache

$cacheid = Language::getcode()."/$path";

// if browser caching is enabled, send headers telling the browser that
// it can cache our pages. Only do this if the page is in not in the
// cache exceptions list.

if(BROWSERCACHING && !in_array($path,$cache_exceptions) && !count($_POST))
{
  set_cached_headers();
}
else
{
  set_uncached_headers();
}

if(CACHING && NO_TMP){
  throw new BadInstallException("cannot have CACHING and NO_TMP both set");
}
  
// if server caching of pages is enabled, check the cache for prerendered
// content and send it if it's present - but only if the page is not in
// the cache exceptions list

if(CACHING && !count($_POST)) // naturally, we don't cache POST requests.
{
  // if it's in the global exceptions, don't
  // cache 
  
  if(!in_array($path,$cache_exceptions))
  {
    $key = Cache::getkey($cacheid,$_GET);
    if($key)
    {
      $c = Cache::get($key);
    }
  }
  // we put the key into a global so that getpage() can clear it, if necessary.
  GlobalData::$cachekey = $key;
}

    

if($c)
{
  // there was a cache entry; print it.

  echo $c;
}
else
{
  // otherwise we have to actually
  // render the page
  try
  {
    require_once("core/core.php");
    
    // get the page text. If the page isn't found, this will:
    // - if a page "404" exists, that will be returned 
    // - if no such "404" page exists, a short default message will be written.
    // In both cases Status: 404 is added to the headers and the return status is
    // set 404 Not Found. Also, GlobalData::$cachekey will cleared to avoid writing
    // too many files to the cache, at the slight cost of not caching the 404 page.
    
    $contents = getpage();
    
    if(GlobalData::$cachekey)
    {
      // if there was a cache key, write the page
      // text to it.
      Cache::put(GlobalData::$cachekey,$contents);
    }
    echo $contents;
  }
  catch(CustomException $e)
  {
    $e->dump();
  }
  catch(Exception $e)
  {
    $m = $e->getMessage();
    print "exception not caught : $m";
  }
}

?>
