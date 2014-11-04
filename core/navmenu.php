<?php
if(!defined('IN_SCMS'))die("call from outside app");

/// the name of the navigation tree cache is generated from
/// - navdata_
/// - the language code
/// - any explicit style (i.e. one passed in by ?style=xxx) if there is one.

function getnavcachename()
{
  $r = TMPPATH.'navdata_'.Language::getcode().'_'.
     $_GET['style'];
  return $r;
}
  

// function to create a collection for the navigation menu

function createnavcollection()
{
  // field spec
  $fields = array('spec','url','name','type','key','lkey','accesskeyattr');
  
  $a = @fopen(SITEPATH.'navigation','r');
  if(!$a)
  throw new BadInstallException("cannot find 'navigation' file in site");
  
  $level=0;
  $levdelta=0;
  $stack = array();
  $output = new Tree($fields);
  
  // This may have been added to fix a problem with save in trees vs. save in collections.
  // It really should not be required, since we're just building a new collection.
  //  Collection::saveall();
  
  while(!feof($a))
  {
    $s = fgets($a,256);
    // delete any comments
    $s = rtrim(preg_replace('/#.*/','',$s));
    // ignore short lengths
    
    if(strlen($s)>2)
    {
      // get the number of hyphens and then delete them. This works by counting the difference
      // before and after.
      
      $t = preg_replace('/^\-+/','',$s);
      $nlevel = strlen($s)-strlen($t);
      $levdelta = $nlevel-$level;
      $level = $nlevel;
      $submenuchar = '';
      
      // extract the options and then delete them. We process
      // optarr later on.
      
      $optarr = preg_split('/\s+/',$t);
      $t = $optarr[0];
      array_shift($optarr);
      
      // Now. If the number of hyphens is >1, we move down a level.
      // That is, we push the current node onto the stack, 
      
      if($levdelta>0)
      {
        // push a reference to the output onto the stack
        array_push($stack,$output);
        unset($output);
        // create a new empty collection to add items to
        $output = new Tree($fields);
      }
      
      while($levdelta<0)
      {
        $levdelta++;
        // the item we just added was the last one, so we
        // need to take the current output list and make it
        //a child of the previous output list
        $tmp = &$output; // copy ref to current output
        unset($output);
        $output = array_pop($stack);  // set previous output
        
        // output is now the last item we made before pushing into the submenu,
        // so we can set the 'sub' field to the collection we just made.
        
        $output->setchild($tmp);
      }
      
      $type = 'N'; // normal type
      if(substr($t,0,1)=='(') // invisible!
      {
        $t = substr($t,1,-1); // remove first and last chars
        $type = 'I'; // invisible
      }
      if(substr($t,0,1)=='[') // menu with no content, just a header for submenus
      {
        $t = substr($t,1,-1); // remove first and last chars
        $type = 'H'; // heading only, no URL
      }
      
      // get the page name by loading the page's template and
      // getting the name value, all in a temporary environment
      // We also get the 'redir' value - that's used when we want
      // to override the navigation URL to provide our own.
      
      Template::push();
      if(Template::readtagdeffile('page:',SITEPATH.'pages',$t,'navmenu'))
      {
        $longname = trim(Template::get('page:name'));
        $redir = Template::getnocheck('page:redir');
      }
      else
      {
        // if there's no page file, we set up name and redir to defaults
        $longname = $t;
        $redir = FALSE;
      }
      Template::pop();
      
      // add the item, even if invisible
      $output->additem();
      $output->setfield('accesskeyattr',''); // set empty access key field
      // parse options
      foreach($optarr as $optitem)
      {
        list($name,$val) = explode('=',$optitem,2);
        if($name == 'key')
        {
          $output->setfield('key',$val);
          $output->setfield('accesskeyattr',"accesskey=\"$val\"");
        }
      }
      
      $output->setfield('lkey','');
      $output->setfield('spec',$t);
      $output->setfield('name',$longname);
      $output->setfield('type',$type);
      if($redir===FALSE){
        $url = getURLforpage($t,false);
      }
      else $url=$redir;
      $output->setfield('url',$url);   
    }
  }
  fclose($a);
  
  // deal with any remaining levels
  
  while(count($stack))
  {
    $tmp = &$output; // copy ref to current output
    unset($output);
    $output = array_pop($stack);  // set previous output
    $output->setchild($tmp); // set current (just added) item's child to tmp
  }
  
  if(!NO_TMP){
    // now write the cache file
    $file = getnavcachename();
    $a = @fopen($file,'w');
    if(!$a)throw new BadInstallException("cannot open '$file' for writing. Check permissions.");
    fwrite($a,$output->serialise());
    fclose($a);
  }
  
  // see the "saveall" above
  // Collection::restoreall(); 
  
  return $output;
}



/// load the navigation menu collection from a file or recreate it if required

function loadorcreatenavcollection()
{
  // if the navdata file doesn't exist, or the navigation file is newer, rebuild it.
  // Unless we're running without temporary data in which case just build it.
  
  if(NO_TMP){
    $c = createnavcollection();
  } else {
    
    // get the navdata name
    $file = getnavcachename();
  
    
    /*
    
      OH GOD HORRIBLE HORRIBLE
    
      Caching is currently breaking persistent get parameters (fontsize, style) so
      I've had to disable it - and the only way I can get that to work sensibly is
      like this. I'm so, so sorry. I have no idea why, when I try to get it to just
      skip the write/read this does, it fails, and I have no time to check now.
      
      */
  
    if(1 || checkforcaching(SITEPATH.'navigation',$file))
    {
      // if the navdata file doesn't exist, or is stale we should rebuild it
      // from the 'navigation' file and the page files.
      createnavcollection();
    }
    
    // now read the navdata file, which is a serialised array
    
    if(!file_exists($file))
        throw new BadInstallException("cannot open navdata file");
    
    $c = Tree::load($file);
  }
  
    // return a collection handle
  return $c;
}



/// use this to access the nav menu collection
function getnavmenucollection()
{
  static $c;
  
  if(!isset($c))
  {
    $c = loadorcreatenavcollection();
  }
  
  return $c;
}

/// get a node handle to the current node
function curnavnode($t)
{
  static $n;
  
  if(!isset($n))
  {
    $c = getnavmenucollection();
    $n = $c->findnode('spec',GlobalData::$pathstring);
    if(is_null($n))throw new InvalidTreeHandleException("this page is not in the navigation tree");
  }
  
  // call this function to correctly handle the arguments to a tag which represents
  // a node
  return TagSpecification::handleitemtagargs($t,$n);
}
Template::regfunc('curnavnode','get the current node in the navigation tree','');

/// use this tag to get a handle to the nav menu collection
function navtree($t)
{
  // call this function to correctly handle the arguments to a tag which represents
  // a collection
  return TagSpecification::handlecolltagargs($t,getnavmenucollection()->handle);
}
Template::regfunc('navtree','get the navigation menu tree','');

// used to produce link rel tags.
function outputrel($linkname,$titletemplate,$prefix,$key)
{
  $url = Template::get("$prefix:url");
  if(is_null($titletemplate))$titletemplate="{{".$prefix.":name}}";
  $title = Template::process($titletemplate);
  return "<link rel=\"$linkname\" title=\"$title\" href=\"$url\"/>\n";
}

/// template function for producing the navigation "link rel" tags
/// for accessibility. Optional args : navlinks|startkey|nextkey|prevkey
/// This just creates key labels though.
function navlinks($t)
{
  $startkey=NULL;
  $nextkey=NULL;
  $prevkey=NULL;
  $template=NULL;
  $prefix='navlinktemplate';
  
  if($t->argc>=1)$prefix = $t->argv[0];
  if($t->argc>=2)$template = $t->argv[1];
  if($t->argc>=3)$startkey = Template::process($t->argv[2]);
  if($t->argc>=4)$nextkey = Template::process($t->argv[3]);
  if($t->argc>=5)$prevkey = Template::process($t->argv[4]);
  
  $coll = getnavmenucollection(); // get the nav menu root
  // find the current level of the nav menu
  $coll = parentofnode($coll,'spec',GlobalData::$pathstring);
  
  if(is_null($coll)){
    $q = GlobalData::$pathstring;
    throw new BadNavigationException("Page \"$q\" does not exist in the navigation file");
  }
  
  
  
  // now get the item's index and number of items in that collection
  
  $nameidx = $coll->getfieldidx('spec');
  $count = count($coll->items);
  
  // we're also going to dynamically add to the access key attribute for
  // the nodes, just on this page.
  $akeyidx = $coll->getfieldidx('accesskeyattr');
  $lkeyidx = $coll->getfieldidx('lkey');
  
  // we walk through to find the next and previous nodes
  
  for($idx=0;$idx<$count;$idx++)
  {
    $item = &$coll->items[$idx];
    
    if($item[$nameidx]==GlobalData::$pathstring)
    {
      // that's the one.
      if($idx>0)$prev = &$coll->items[$idx-1];
      else $prev=null;
      
      $n = $idx+1;
      if($n<$count)$next=&$coll->items[$n];
      else $next = null;
      
      if(isset($coll->parentc))$start = null;
      else $start = &$coll->items[0];
    
      $s='';
      
      if(!is_null($start))
      {
        if(!is_null($startkey)){$start[$akeyidx] .= " accesskey=\"$startkey\" ";}
        $start[$lkeyidx]=$startkey;
        $coll->settags($start,$prefix,$idx,0);
        $s.= outputrel('start',$template,$prefix,$startkey);
      }
      
      if(!is_null($prev))
      {
        if(!is_null($prevkey))$prev[$akeyidx] .= " accesskey=\"$prevkey\" ";
        $prev[$lkeyidx]=$prevkey;
        $coll->settags($prev,$prefix,$idx,0);
        $s.= outputrel('prev',$template,$prefix,$prevkey);
      }
      if(!is_null($next))
      {
        if(!is_null($nextkey))$next[$akeyidx] .= " accesskey=\"$nextkey\" ";
        $next[$lkeyidx]=$nextkey;
        $coll->settags($next,$prefix,$idx,0);
        $s.= outputrel('next',$template,$prefix,$nextkey);
      }
      return $s;
    }
  }
  return '';
}

Template::regfunc('navlinks','accessibility link rel tag generator','(startkey)|(nextkey)|(prevkey)');

?>
