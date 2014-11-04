<?php

// module handler.

// A module does any or all of the following:
// - registers new tags with the templating system
// - registers GET/POST query dispatchers

/*
  Register a module like this:
  
  class MyModule extends Module
  { .. your code here..
  };
  Modules::register('auniquename',new MyModule);
*/

class Module
{
  /// returns FALSE if the get should NOT subsequently output the page data (e.g. AJAX response)
  public function handleget() { return TRUE; }
  /// returns FALSE if the get should NOT subsequently output the page data (e.g. AJAX response)
  public function handlepost() { return TRUE; }
};


// modules are in PHP files, and are read in response to a 'modules=' line in a template
// or page. Template modules are read first.
// The lines consist of a comma-separated list of dispositions and modules. The disposition
// is either 'post','get' or 'all'. If the disposition is 'post' or 'get' , the module is only
// loaded if it is requested via the POST or GET interface by setting 'mod' to the module name in the request.
// Modules marked 'all' are always loaded for the page or template.
// Each module file contains the definition of a module object, which it adds to
// Modules object.

class Modules
{
  private static $modules; //!< a list of the module objects, which each module creates.
  public static $handled; //!< set to 'get' or 'post' on successful handlage.
  
  /// register me with the Modules. We provide a unique name.
  public function register($n,$m)
  {
    if(self::$modules[$n])
       throw new ModuleException("cannot register module - a module called '$n' already exists");
    self::$modules[$n] = $m;
  }
  
  /// initialise an empty module array.
  public static function init()
  {
    self::$modules = array();
  }
  
  /// given a 'modules' value, read each module if it is required
  
  public static function readmods($line)
  {
    if(strlen($line))
    {
      $pmod = $_POST['mod'];
      $gmod = $_GET['mod'];
      $line = explode(',',$line);
      foreach($line as $a)
      {
        $b = explode(':',$a);
        $disp = $b[0];$mod=$b[1];
        $do=0;
        if($disp=='all')$do=1;
        elseif($disp=='post' && $pmod==$mod)$do=1;
        elseif($disp=='get' && $gmod==$mod)$do=1;
        if($do)
        {
          $f = SITEPATH."modules/$mod/main.php";
          if(!file_exists($f))throw new ModuleException("cannot find module file for '$f'");
          include_once $f;
        }
      }
    }
  }
  
  /// used by handleget() and handlepost()
  private static function handler($reqtype,$arr)
  {
    $n = $arr['mod'];
    
    if(is_null($n))
    {
      Template::regstring("module:$reqtype".'handled','');
      return TRUE; // no module requested
    }
    if(is_null(self::$modules[$n]))throw new ModuleException("unknown module in $reqtype request: '$n'.");
    
    Template::regstring("module:$reqtype".'handled','1');
    
    // now store the request vars into tags
    foreach($arr as $k=>$v)
    {
      Template::regstring("request:$k",$v);
    }
    // otherwise we handle.
    if($reqtype=='get')
    {
      self::$handled='get';
      return self::$modules[$n]->handleget();
    }
    else
    {
      self::$handled='post';
      return self::$modules[$n]->handlepost();
    }
  }
  
  
  /// if there's a 'mod=' in the GET array, pass it to the appropriate module and
  /// return whether the page should be shown. Not sure how to handle unknown modules..
  /// I'll just ignore them.
  
  public static function handleget()
  {
    return self::handler('get',$_GET);
  }
  
  /// this is identical to handleget(), but for POST requests.
  public static function handlepost()
  {
    return self::handler('post',$_POST);
  }
};

/// this returns TRUE if we should continue with output (i.e. if the handlers didn't run,
/// or returned TRUE.)

function loadandhandlemodules()
{
  // Load template, then page, modules
  
  Modules::readmods(Template::getnocheck('template:modules'));
  Modules::readmods(Template::getnocheck('page:modules'));
  
  // Now run any module dispatcher
  
  $showpage = Modules::handlepost();
  if($showpage)
  {
    $showpage = Modules::handleget();
  }
  return $showpage;
}



/// module:ifposthandledby takes 3 arguments: modname, iftrue, iffalse
/// If the module 'modname' handled the POST request, iftrue is returned,
/// otherwise iffalse is returned. There's a similar function for
/// GET requests.

function modhandled($t,$arr,$reqtype)
{
  $t->assertargc(3);
  $m = Template::process($t->argv[0]);
  if(!is_null($arr['mod']) && !strcmp($arr['mod'],$m) && 
     !strcmp(Modules::$handled,$reqtype))
    $s = $t->argv[1];
  else
    $s = $t->argv[2];
  return Template::process($s);
}
Template::regfunc('module:ifposthandledby','check a POST request was handled by a given module',
                  'module name|use if post handled by this module|use if not',
                  create_function('$t','return modhandled($t,$_POST,"post");'));
Template::regfunc('module:ifgethandledby','check a GET request was handled by a given module',
                  'module name|use if get handled by this module|use if not',
                  create_function('$t','return modhandled($t,$_GET,"get");'));

?>
