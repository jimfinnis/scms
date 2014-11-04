<?php

if(!defined('IN_SCMS'))die("call from outside app");

require_once("common.php");
require_once("template.php");
require_once("page.php");
require_once("functpls.php");
require_once("navmenu.php");
require_once("langmenu.php");
require_once("modules.php");
require_once("styles.php");

function turl($t)
{
  $oldg = $_GET;
  if($t->argc<1)throw new NotEnoughArgumentsException($t->name);
  $spec = Template::process($t->argv[0]);
  
  if($t->argc==4){
    $k = Template::process($t->argv[2]);
    $v = Template::process($t->argv[3]);
    if($v=='')unset($_GET[$k]);
    else $_GET[$k]=$v;
  }
  if($t->argc>=2)
  {
    $s = Template::process($t->argv[1]);
    if($s!=''){
      if($s == '*')$url = getURLforpagewithnostyle($spec);
      else $url = getURLforpagewithstyle($spec,$s);
    } else {
      $url = getURLforpage($spec);
    }
  }
  else
  {
    $url = getURLforpage($spec);
  }
  $_GET=$oldg;
  return $url;
}
Template::regfunc('url','a URL to the specified page','spec|(style)|(httpget|val)',turl);

function turl2($t)
{
  if($t->argc<1)throw new NotEnoughArgumentsException($t->name);
  $spec = Template::process($t->argv[0]);
  
  if($t->argc==2)
  {
    $s = Template::process($t->argv[1]);
    if($s!=''){
      if($s == '*')$url = getURLforpagewithnostyle($spec,false);
      else $url = getURLforpagewithstyle($spec,$s,false);
    } else  {
      $url = getURLforpage($spec,false);
    }
  }
  else
  {
    $url = getURLforpage($spec,false);
  }
  return $url;
}
Template::regfunc('urlnoget','a URL to the specified page, discard GETs','spec|(style)',turl2);

function turlch($t){
  $ct = $t->argc;
  if($ct<3)throw new NotEnoughArgumentsException($t->name);
  
  $argv=$t->argv;
  $spec = Template::process(array_shift($argv));
  
  $modarray=array();
  $i = 1;
  
  while(count($argv)>1){
    $key=Template::process(array_shift($argv));
    $val=Template::process(array_shift($argv));
    $modarray[$key]=$val;
  }
  
  if(count($argv)>0)
  {
    $s=Template::process(array_shift($argv));
    if($s!=''){
      if($s == '*')$url = getURLforpagewithnostyle($spec,true,$modarray);
      else $url = getURLforpagewithstyle($spec,$s,true,$modarray);
    } else  {
      $url = getURLforpage($spec,true,$modarray);
    }
  }
  else
  {
    $url = getURLforpage($spec,true,$modarray);
  }
  return $url;
}
Template::regfunc('urlchget','a URL to the specified page, changing/adding one GET parameter','spec|param|value|param|value..|(style)',turlch);
  

function tlink($t)
{
  if($t->argc<2)throw new NotEnoughArgumentsException($t->name);
  $spec = Template::process($t->argv[0]);
  $linktext = Template::process($t->argv[1]);
  
  if($t->argc==3)
  {
    $s = Template::process($t->argv[2]);
    if($s != ''){
      if($s == '*')$url = getURLforpagewithnostyle($spec);
      else $url = getURLforpagewithstyle($spec,$s);
    } else {
      $url = getURLforpage($spec);
    }
  }
  else
  {
    $url = getURLforpage($spec);
  }
  return "<a href=\"$url\">$linktext</a>";
}
Template::regfunc('link','a link to the specified page','spec|linktext|(style)',tlink);


/// get the page required, using the _GET parameters
function getpage()
{
  // init module system
  Modules::init();
  
  // saves typing
  $path = GlobalData::$pathstring;
  $arr = GlobalData::$patharr;
  
  Template::regstring('thispage',getURLforpage($path),'the URL for the current page');
  Template::regstring('spec',$path,'the specifier for the current page');
  Template::regstring('langcode',Language::getcode(),'current language code');
  Template::regstring('langencoding',Language::getencoding(),'current language encoding');
  Template::regstring('style',GlobalData::$stylename);
  
  // store the 'canonical' URL - language code and all defaults added in
  
  $q = http_build_query($_GET);
  if(strlen($q))$q='?'.$q;
  GlobalData::$canonicalURL = ROOTURL.'/index.php/'.Language::getcode().'/'.$path.$q;
  
  // write a default template name
  
  Template::regstring('page:template','default');
  
  // now we read the global data - all the .tags files inside the 'globals'
  // directory. Note that readtagdeffile will also read any files
  // of the same name in the language subdirectory too, provided
  // there's a file of that name in the directory above.
  
  $tdir = SITEPATH.'global';
  $dir = @opendir($tdir);
  if($dir!==FALSE)
  {
    while($file = readdir($dir))
    {
      if(preg_match('/\.tags$/',$file))
      {
        Template::readtagdeffile('global:',$tdir,$file,'global');
      }
    }
  }
  
  // now open the page. This involves reading the page tag def files into the 
  // template system with the prefix "page:"
  
  // Page::load() handles clever stuff like reading all the defaults files on
  // the path, and returning 0 if there is no file found. See pages.php for
  // how it works.
  
  if(!Page::load($path))
  {
    // we haven't found the page; we need to output a 404.
    
    GlobalData::$cachekey = 0; // clear the cache key
    
    header($_SERVER["SERVER_PROTOCOL"]."HTTP/1.0 404 Not Found"); // write a 404 header!
    header("Status: 404");
    
    // if that doesn't work, try to open a 404 page.
    if(!Template::readtagdeffile('page:',SITEPATH.'pages','404','404'))
    {
      // There wasn't one of those either -  show a default page and exit -
      // the data will not be written to the cache
      print "<html><body><p>page <b>$path</b> not found - and no 404 provided for SCMS</p></body></html>";
      exit;
    }
  }
  
  // Now do style processing - use the style to access the style map, which might change the template
  // and main file. The new main file will be returned (it's usually main.html) and any change to the
  // template will be done in the registry. It reads the style and template from the tag registry.
  
  $mainfile = dostylemap();
  
  // this should have created a page:template variable and filled in a huge
  // load of template values. Now we need to resolve page:template, which will
  // give us a template name, from which we can get a template directory.
  // Of course, we might not have a template - page:template will be set to 'none'.
  // That's because it might be an Ajax responder, and we just use the 'content' value (if at all, because
  // the module handler will probably respond with FALSE).
  
  $t = Template::get('page:template');
  if(strcmp($t,'none'))
  {
    $tdir = SITEPATH.'templates/'.$t;
  
    // Now to read the template data.
  
    // First, we read in the tags. These are any files in the template
    // directory that end with ".tags"
  
    $dir = @opendir($tdir);
    if(!$dir)throw new TemplateDirectoryNotFoundException("directory $tdir");
     
    while($file = readdir($dir))
    {
      if(preg_match('/\.tags$/',$file))
      {
        Template::readtagdeffile('template:',$tdir,$file,'template');
      }
    }
  
    // Now read the actual HTML template file.
    // Use the language file if that exists,
    // otherwise the base file.
  
    $e = Language::getlangfiles($tdir,$mainfile);
    if($e->flags & 2)$n = $e->langfile;
    elseif($e->flags & 1)$n = $e->basefile;
    else throw new NoMainHtmlInDirectoryException("template dir: $tdir/\nfilename: $mainfile");
    $maintemplate = file_get_contents($n);
    
    // remove ## comments at start of lines if we're doing that
    if(COMMENTS_IN_MAIN)
        $maintemplate = preg_replace('/^##.*\n/m','',$maintemplate);
    
    // register some important tags
    
    Template::regstring('root',ROOTURL,'the root URL');
    Template::regstring('imgroot',ROOTURL.'site/images/','the URL for images (site/images)');
    Template::regstring('templateroot',ROOTURL.'site/templates/'.Template::get('page:template').'/',
                        'the URL for the current template');
    Template::regstring('defaultpage',DEFAULTPAGE,'the spec of default start page');
    
    // Process the template - but only if the module handlers
    // didn't return FALSE. If they returned true, all output
    // was handled by them.
    
    if(loadandhandlemodules()){
      $ret = Template::mainprocess($maintemplate);
    } else {
      $ret = '';
    }
    return $ret;
  }
  // what we do if template is 'none'
  loadandhandlemodules();
  return ''; 
}


/// rebuild all cached temporary data and delete the page cache

function rebuild_all()
{
  Language::rebuildcache();
  Cache::clear();
}
