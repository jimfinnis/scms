<?php

/// this is the templating system. Do not register any templates here!


if(!defined('IN_SCMS'))die("call from outside app");

require_once('collection.php');
require_once('tree.php');

/// these are the tag types
// - T_FUNC runs a function named in the argument, taking the arguments of the template, the return value is the replacement text
// - T_TEXT just inserts the argument as text
// - T_FILE finds a file in the current theme directory and inserts it
// - T_COLLECTION is a collection, and the arg field is a collection handle
// key in the collections module (so that collections don't necessarily have to be bound to tags).
// - T_ITEM is a collection item, and the arg field is an item handle

define('T_FUNC',0);
define('T_TEXT',1);
define('T_FILE',2);
define('T_COLLECTION',3);
define('T_COLLITEM',4);

// collection modes, used in dodefine() to determine whether we are not defining a collection,
// adding to a collection, or defining a new one
define('COLLMODE_NONE',0);  //!< not defining a collection
define('COLLMODE_ADDORNEW',1);  //!< add to an existing collection, if it doesn't exist make a new one
define('COLLMODE_NEW',2);  //!< always create a new collection


/// This just checks a file exists and throws if it doesn't, returning
/// the original filename. It's like that for historical reasons - 
/// originally it would actually scan a path.

function gettemplatefile($n)
{
  if(file_exists($n))
  {
    return $n;
  }
  throw new TemplateFileNotFoundException($n);
}

/// ugly thing for adding a bit to an exception's message
/// which isn't accessible where the exception is thrown.
/// Needs refactoring.

function decoratemsg($e,$msg)
{
  $m = $e->getMessage()."($msg)";
  $e->setMessage($m);
  die($m);
}
  

/// a tag found during processing which should be replaced.
/// Made by Template::replacetemplates. After this, the text
/// will hold a unique marker which can be replaced by the tag's value.

class TagInText
{
  public $name; //!< template name
  public $argc; //!< arg count
  public $argv; //!< array of arguments
  
  function assertargc($n)
  {
    if($n != $this->argc)
    {
      print("<pre>".print_r($this,true)."</pre>");
      throw new NotEnoughArgumentsException("expected $n args in template $name, got $this->argc");
    }
  }
  
  // handy helper for processing arguments through the templater
  public function get($n){
    return Template::process($this->argv[$n]);
  }
  
  // handy helper for getting integers. Not used everywhere, but should be.
  
  public function getint($n){
    $q=$this->argv[$n];
    return intval(Template::process($this->argv[$n]));
  }
  
  
  public function __toString()
  {
    return "TagInText({$this->name},{$this->argc},{$this->argv})";
  }
};

/// the specification of a template tag, added with Template::register

class TagSpecification
{
  public $name; // repeat of name
  public $type; // the type of the tag
  public $arg; // the value of the tag
  public $desc; // the description of the tag
  public $permitrecurse;
  public $src; // name of the file where it's defined
  public $argspec; // for functions, a bar-separated list of arguments used in debugging and doc generation
  
  /// use this function to handle arguments for a collection tag - done automatically
  /// for a T_COLLECTION, but not for functions that return collections.
  
  static function handlecolltagargs($t,$chandle)
  {
    // if there are no extra arguments, return the collection key.
    // If there is one extra argument, return the 'value' field of the collection at that index 
    // If there are two extra arguments, return the value of field argv[1] at index argv[2].
    if($t->argc==0)
    {
      return $chandle;
    }
    else
    {
      if($t->argc<2)$field='value';
      else $field=$t->argv[1];
      $c = Collection::get($chandle);
      return $c->getfield(intval(Template::process($t->argv[0])),$field);
    }
  }
  
  /// use this function to handle arguments for a collection item tag
  /// in a similar manner to handlecolltagargs
  
  static function handleitemtagargs($t,$key)
  {
    if($t->argc==0)
    {
      return $key;
    }
    else
    {
      $t->assertargc(1); // must be a single arg, the name of the field
      list($h,$i) = explode('$',$key);
      $key=trim($h);
      $c = Collection::get($h); // get collection
      return $c->getfield($i,$t->argv[0]);
    }
  }
    
  /// get the replacement value, given a TagInText
  function getvalue($t)
  {
    switch($this->type)
    {
    case T_FUNC:
      
      $r = call_user_func($this->arg,$t);
      
      break;
    case T_TEXT:
      // first, we define tag arguments
      for($i=0;$i<$t->argc;$i++)
      {
        $j=$i+1;
        $tt = Template::process($t->argv[$i]);
        Template::regstring("\$$j",$tt,'tag argument');
      }
      $r = Template::process($this->arg);
      break;
    case T_FILE:
      $f = gettemplatefile($this->arg);
      if(!is_file($f))throw new TemplateFileNotFoundException("cannot find tag replacement file $f referenced by tag '{$t->name}'");
      $r = file_get_contents_remove_comments($f);
      break;
    case T_COLLECTION:
      return self::handlecolltagargs($t,$this->arg);
    case T_COLLITEM:
      return self::handleitemtagargs($t,$this->arg);
    default:
      throw new InternalException("bad tag type in tag '{$t->name}'");
    }
    return $r;
  }
};


function debug($s)
{
  print "<pre>$s</pre>\n";
}


class Template
{
  private static $registry = array(); // the templates I understand
  private static $regstack = array(); // a stack of registries
  public static $settrim = 0; // do we trim whitespace from tags
  public static $currentfile = "(internal)"; // current source file
  
  public static $debugstack = array(); // a stack for keeping template names for exception handling
  public static $debugloads = 0;
  
  public static $subststack = array(); // tag name substitution stack
  
  // register a tag and something to do with it
  
  // type can be 
  // - T_FUNC runs a function named in the argument, taking the arguments of the template, the return value is the replacement text
  // - T_TEXT just inserts the argument as text (although TAG ARGUMENTS are defined)
  // - T_FILE finds a file in the current theme directory and inserts it
  
  public static function register($tname,$type,$arg,$desc,$recurse=TRUE)
  {
    if($type>T_FILE)
      throw new BadArgumentException("reg type out of range");
    $t = new TagSpecification;
    $t->name = $tname;
    $t->type = $type;
    $t->arg = $arg;
    $t->permitrecurse = $recurse;
    $t->desc = $desc;
    $t->src =self::$currentfile;
    self::$registry[$tname] = $t;
    return $t;
  }
  
  /// remove a tag from the registry
  public static function remove($name)
  {
    unset(self::$registry[$name]);
  }
  
  /// we don't use this at the moment, but it 
  /// provides a way to set lots of tags from a CSV.
  /// It's there for historical reasons.
  
  private static function registerstringfile($filename)
  {
    self::$currentfile = $filename;
    $a = fopen(gettemplatefile($filename),"rb");
    if($a !== FALSE)
    {
      while($row = fgetcsv($a,1000,'|'))
      {
        if(substr($row[0],0,2) != '##' && strlen($row[0])>0)
        {
          self::register($row[0],T_TEXT,$row[2],$row[1]);
        }
      }
      fclose($a);
    }
    self::$currentfile = "(internal)";
  }
  
  
  // shortcut for registering (func,T_FUNC,func), can be used
  // for lambdas or named functions. If no function is passed in,
  // uses the name given. The argument specification is a bar-separated
  // list of strings.
  public static function regfunc($name,$desc,$args,$func=NULL)
  {
    if(is_null($func))$func=$name;
    if(!is_callable($func))throw new BadDefineException("invalid function passed to Template::regfunc('$name')");
    
    $t=self::register($name,T_FUNC,is_null($func)?$name:$func,$desc);
    $t->argspec=$args;
  }
  
  // shortcut for registering a string
  public static function regstring($name,$str,$desc='',$erc=true)
  {
    self::register($name,T_TEXT,$str,$desc,$erc);
  }
  
  /// return FALSE if a tag isn't registered, or the
  /// value if it is. Consider using get(), which throws
  /// an exception.
  
  public static function getnocheck($tname)
  {
    if(is_null(self::$registry[$tname]))return FALSE;
    else
    {
      return self::$registry[$tname]->getvalue(0);
    }
  }
  
  /// return whether a tag is registered or not
  public static function exists($tname)
  {
    return !is_null(self::$registry[$tname]);
  }
  
  
  // get the value of a tag without substituting any templates within
  public static function get($tname)
  {
    if(is_null(self::$registry[$tname]))
    {
      throw new UnknownTagException($tname);
    }
    return self::$registry[$tname]->getvalue(0);
  }
  
  /// quick way of registering infrequently used files
  /// which contain a single tag's value each. Each time the template
  /// resolves it will read the file in again! Note that the sitepath
  /// is prepended.
  
  public static function registertextfile($tag,$filename)
  {
    self::register($tag,T_FILE,SITEPATH.$filename,'',true);
  }
  
  
  
  /// quick way of registering a bunch of frequently used tags,
  /// which just replace to text,
  /// in files which contain multiple templates in colon-notation.
  /// In this notation, templates look like:
  /// dummy data
  /// dummy data
  /// :templatename:description
  /// template data
  /// template data
  /// template data
  /// :end
  /// dummy data
  /// dummy data
  /// :templatename:description
  /// template data
  /// template data
  /// template data
  /// :end
  
  public static function registerfile($file)
  {
    self::$currentfile = $filename;
    $f = gettemplatefile($file);
    $a = fopen($f,"rb");
    if(!is_file($f))throw new TemplateFileNotFoundException($f);    
    $mode=0;
    while(!feof($a))
    {
      $s = fgets($a,65536);
      
      if(!$mode)
      {
        if($s[0]==':')
        {
          $arr = explode(":",substr($s,1));
          $newname = $arr[0];
          $newdesc = $arr[1];
          $mode = 1;
          $text = "";
        }
      }
      else
      {
        if($s[0]==':')
        {
          $text = preg_replace("/\/\*(.*?)\*\//s","",$text); // remove C style comments
          self::register($newname,T_TEXT,$text,$newdesc);
          $mode = 0;
        }
        else
        {
          $text .= $s;
        }
      }
    }
    self::$currentfile = "(internal)";
  }
  
  // register several files with registerfile()
  
  public static function registermultifiles($array)
  {
    foreach ($array as $t)
    {
      self::registerfile($t);
    }
  }
  
  
  /// Read in a tag definition file in modern format!
  /// takes a directory (with trailing / optional)
  /// and a filename within that directory. That's
  /// so we can insert the language code if required.
  /// The namespace string is prepended to any
  /// variables defined in the templater. A typical
  /// example is 'page:'
  /// It returns 0 if a page was not read.
  
  public static function readtagdeffile($namespace,$dir,$name,$note)
  {
    $readcount = 0;
    
    if(self::$debugloads)
    {
      print "<pre>LOAD $note: $namespace:$dir/$name</pre>";
    }
    
    if(substr($dir,-1)!='/')
        $dir.='/';
    
    // get the file data
    
    $e = Language::getlangfiles($dir,$name);
    
    if($e->flags & 1)
    {
      $readcount += self::doread($e->basefile,$namespace);
    }
    // then the language-specific, which will
    // override values just read.
    if($e->flags & 2)
    {
      $readcount += self::doread($e->langfile,$namespace);
    }
    self::$currentfile = "(internal)";
    return $readcount;
  }
  
  /// do definitions inside doread()
  private static function dodefine(
                                   $curcol, //!< if non-NULL, a complex collection we are defining fields within
                                   $collmode, //!< 0=not defining a collection/ 1=we are adding to or defining a new collection  / 2=we are defining a new collection always
                                   $namespace, //!< the current name space, e.g. 'page:' or 'template:'
                                   $name, //!< the name of the item
                                   $def //!< the value of the item
                                   )
  {
    if(!is_null($curcol))
    {
      if($iscoll)throw new SyntaxException("cannot define a collection inside a collection (yet)");
      else
      {
        $curcol->setfield($name,$def);
      }
    }
    else
    {
      if($collmode!=COLLMODE_NONE)
      self::registeroraddtocollection($namespace.$name,$def,$collmode==COLLMODE_NEW);
      else self::regstring($namespace.$name,$def);
    }
  }
      
     
  
  /// this actually reads the files for readfile.
  private static function doread($name,$namespace)
  {
    self::$currentfile = $name;
    $file = @fopen($name,"r");
    $inmultiline = 0;
    $startdelim = "[[";
    
    // 0=not a collection, 1=adding to collection, 2=defining new collection
    $collectionmode=COLLMODE_NONE;
    
    $enddelim = "]]";
    // if we are defining inside a collection with '++', is that Collection. Else NULL.
    $curcollection=NULL; 
    
    
    if($file)
    {
      while(!feof($file))
      {
        $s = fgets($file);
        if(substr($s,0,2)!='##') // ignore comments
        {
          if($inmultiline==1)
          {
            // we're reading a multiline definition. Is
            // there an end delimiter?
            $p = strpos($s,$enddelim);
            if($p!==FALSE)
            {
              // Yes. Append, write the definition and leave
              // this mode.
              $s = substr($s,0,$p); // chop off the delim
              $currentmultidef .= $s; // append
              
              // define it.
              self::dodefine($curcollection,
                             $collectionmode,$namespace,$currentmultiname,$currentmultidef);
              $inmultiline=0; // leave multiline
            }
            else
            {
              // nope, just append to the multiline
              $currentmultidef .= $s;
            }
          }
          else if($s[0]=='!')
          {
            // special commands
            
            $arr = explode(' ',substr($s,1),2);
            $command=$arr[0];$args=$arr[1];
            if($command=='delim')
            {
              // redefining the delimiters
              $arr = explode(',',$args);
              $startdelim=$arr[0];
              $enddelim=$arr[1];
            }
          }
          else if(substr($s,0,2)=='++')
          {
            $s = trim($s);
            if(strlen($s)==2)
            {
              // we're ending a collection!
              if(!is_null($curcollection))
              {
                $curcollection = NULL;
              }
              else throw new SyntaxException('using ++ to end a complex collection when one isn\'t open');
            }
            else
            {
              // ah, right, we're starting a complex collection. Split the name
              // and the field names.
              $s = trim(substr($s,2)); // get rid of ++
              $arr = explode(':',$s); // split name from fields
              $collname = $namespace.$arr[0];
              if(strlen($arr[1]))
              {
                $fields = explode(',',$arr[1]); // split fields
                // create the collection and store it away
                $curcollection = new Collection;
                // register it
                self::register($collname,T_COLLECTION,$curcollection->handle,'collection');
                // now add the fields
                foreach($fields as $f)
                {
                  $curcollection->addfield($f);
                }
              }
              else
              {
                // make sure it's a collection
                if(is_null(self::$registry[$collname]))
                    throw new SyntaxException("trying to use ++ without defining the collection '$collname'");
                $k = Template::getnocheck($collname); // get the handle
                $curcollection = Collection::get($k); // get the collction
              }
              // get ready for the new item
              $curcollection->additem();
            }
          }
          else
          {
            // do we have an equals sign?
            $p = strpos($s,'=');
            if($p!==FALSE && $p!=0)
            {
              $c = $s[$p-1];
              if($c=='+')
              {
                // adding to a collection with '+='
                $collectionmode=COLLMODE_ADDORNEW;
                $left = trim(substr($s,0,$p-1));
              } else if ($c==':') {
                // new collection with ':='
                $collectionmode=COLLMODE_NEW;
                $left = trim(substr($s,0,$p-1));
              } else {
                // just '=' so not a collection
                $collectionmode=COLLMODE_NONE;
                $left = trim(substr($s,0,$p));
              }
              $right = trim(substr($s,$p+1));
              
              // if it's the start delimiter of a
              // multiline, start the definition
              if(substr($right,0,2)==$startdelim)
              {
                $currentmultiname=$left;
                // put the first bit of the text in.
                // We add a \n because the rtrim will
                // have stripped it.
                $currentmultidef=substr($right,2)."\n";
                $inmultiline = 1;
              }
              else
              {
                // just an ordinary definition
                self::dodefine($curcollection,$collectionmode,$namespace,$left,$right);
              }
            }
          }
        }
      }
      fclose($file);
      return 1;
    }
    return 0;
  }
  
  /// register a new collection or add to an existing one. If forcenew is true,
  /// any previous collection is overwritten and a new one started
  public static function registeroraddtocollection($name,$val,$forcenew)
  {
    $t = self::$registry[$name];
    if($forcenew || is_null($t))
    {
      $c = new Collection;
      
      $c->addfield('value'); // add the default 'value' field
      $t = self::register($name,T_COLLECTION,$c->handle,'collection');
    }
    else
    {
      if($t->type != T_COLLECTION)
      {
        throw new SyntaxException("attempt to add to non-collection tag '$name'");
      }
      $c = Collection::get($t->arg);
    }
    // create a new item, and set its 'value' field
    $newitem = $c->additem();
    $c->setfield('value',$val);
  }
  
  // push the entire registry definition set, so that any
  // definitions made subsequently can be reverted to original or
  // removed with pop()
  
  public static function push()
  {
    array_push(self::$regstack,self::$registry);
  }
  
  // pop the registry stack
  
  public static function pop()
  {
    self::$registry = array_pop(self::$regstack);
    assert(!is_null(self::$registry)); //stack underflow??
  }
  
  public static function lispdump()
  {
    $s= "<pre>";
    foreach(self::$registry as $k=>$t)
    {
      $s.= "(\"$k\" \"$t->desc\" (";
      if(strlen($t->argspec))
      {
        foreach(explode('|',$t->argspec) as $a)
        {
          $opts="";
          if($a[0] == '(')
          {
            $opts.=":optional ";
            $a=substr($a,1,-1);
          }
          if($a[0] == '*')
          {
            $opts.=":noproc ";
            $a=substr($a,1);
          }
          $s.= "(\"$a\" $opts)";
          
        }
      }
      $s.= "))\n";
    }
    return $s."</pre>";
  }
    
  
  public static function dump()
  {
    $d = new Dump();
    
    foreach(self::$registry as $k=>$t)
    {
      $d->newrow();
      $d->out($k);
      
      switch($t->type)
      {
      case T_FUNC:
        $d->out("func {$t->arg}");
        break;
      case T_TEXT:
        $d->out("text");
        $s.= "<td>text</td>";
        break;
      case T_FILE:
        $d->out("file {$t->arg}");
        break;
      }
      
      $d->out($t->desc);
      $d->out($t->src);
    }
    return $d->end();
  }
  
  // This is the function which does substitution. See also 'mainprocess'.
  
  public static function process($text,$argsin=null)
  {
    // quickly skip the null case - no tags in here.
    if(!preg_match("/{{/",$text))
    return self::doescapes($text);
    
    $n = strlen($text);
    $lastc = -1;
    
    for($i=0;$i<$n;$i++)
    {
      $c = $text[$i];
      
      if($c == '{' && $lastc == '{')
      {
        // both this and the last character are {, so that
        // means we've got the start of a tag name.
        
        // We now note the start of this tag name
        
        $tstart = $i-1;
        
        // and now we need to skip ahead to the end of the name
        // at the same level, while recording argument places
        
        $level = 0;
        
        $args = array();
        $lastspacer = 0;
        
        $lastc = -1;
        for(;$i<$n;$i++)
        {
          $c = $text[$i];
          if($c == '{' && $lastc == '{')
          {
            $c = '~'; // to stop {{{ being processed as {{ {{
            $level++;
          }
          elseif($c == '|' && !$level)
          {
            // found an argument break at the top level
            
            if($lastspacer)
            {
              // already got the name, add the argument
              array_push($args,substr($text,$lastspacer+1,$i-$lastspacer-1));
            }
            else
            {
              // this must be the name
              $name = substr($text,$tstart+2,$i-($tstart+2));
            }
            $lastspacer = $i;
          }
          elseif($c == '}' && $lastc == '}')
          {
            // found an end of tag name
            $level--;
            $c = '~'; // to stop }}} being processed as }} }}
          }
          
          if($level<0)
          {
            // out of tag! Get last arg and leave the loop
            if($lastspacer)
            {
              // already got the name, add the argument
              array_push($args,substr($text,$lastspacer+1,$i-$lastspacer-2));
            }
            else
            {
              // this must be the name
              
              $name = substr($text,$tstart+2,$i-($tstart+3));
              $lastspacer = $tstart+1;
            }
            break;
          }
          $lastc = $c;
        }
        
        // we are out of the loop and have a full tag structure.
        
        $tend = $i;
        
        
        // then we process the tag. Usually the tag NAME cannot have
        // tags embedded in it, but it can if we prefix it with
        // '!'
        
        if($name[0]=='!')
        {
          $name = Template::process(substr($name,1));
        }
        
        // run substitutions
        foreach(self::$subststack as $s)
        {
          $name = str_replace($s[0],$s[1],$name);
        }
        
        // get the TagSpecification
          
        if($name[0]=='@')
        {
          // initial @ means return empty string if not there
          // instead of failing
          $t = self::$registry[substr($name,1)];
        }
        else
        {
          $t = self::$registry[$name];
          if(is_null($t))
          {
            $q = htmlspecialchars($text);
            throw new UnknownTagException("unknown tag '$name' in $q>");
          }
        }
        
        // we now have $t, the tag's data in the registry.
          
          
        // it is the responsibility of any function tag
        // to run the templater on any of its args it needs to!
        // It doesn't need to run it on the return value,
        // that's done automatically.
            
            
        // construct a TagInText
        $tt = new TagInText;
        $tt->name = $name;
        $tt->argc = count($args);
        $tt->argv = &$args;
          
        // fetch the result
          
        if(!is_null($t))
        {
          if(count(self::$debugstack)>20)throw new StackOverflowException();
            
          array_unshift(self::$debugstack,array($name,$args));
          $result= $t->getvalue($tt);
          array_shift(self::$debugstack);
          
          //          dp("result is : $result");
          
          // if we're not allowed to process tags in the result, replace the tags with
          // a code.
          
          if(!$t->permitrecurse)
          {
            $result = str_replace("{{","XTPX94",$result);
            $result = str_replace("}}","XTPX95",$result);
          }
            
          // and do tag processing on the result
          $result = self::process($result,$tt);
        }
        else
        {
          // case only happens when @... is used
          $result = '';
        }
        
        // and replace the result, but keep a record of the difference in character counts
        // between text and replacement
        
        $oldlength = $tend-$tstart+1;
        $newlength = strlen($result);
        
        //                dump("template",substr($text,$tstart,$oldlength));
        
        $text = substr_replace($text,$result,$tstart,$oldlength);
        
        //                dump("name",$name);
        //                dump("args",$args);
        //                dump("template meaning",$text);
        
        //                echo "<p>Substitution done, string index was $i - ";
        
        // change the string count to account for different string lengths
        $i += $newlength-$oldlength;
        
        $n = strlen($text);
        $zzz = substr($text,$i);
        //                echo "is now $i. String length is $lll, rest of string is $n</p>";
        
      }
      $lastc = $c;
    }
    return self::doescapes(self::$settrim ? trim($text) : $text);
  }
  
  // replace \{ and \} with the characters themselves
  private static function doescapes($s)
  {
    $s=str_replace('\{','{',$s);
    $s=str_replace('\}','}',$s);
    return $s;
  }
    
  
  ////////////////////////////////////////////////////////////////////    
  // This is the version of process which is called from the main code -
  // it just substitutes the special placemarkers with open and close template codes.
  // No further template processing should be done on the string after this, or
  // templates marked as norecurse might run.
  
  
  public static function mainprocess($text)
  {
    $r = self::process($text);
    $r = str_replace("XTPX94","{{",$r);
    $r = str_replace("XTPX95","}}",$r);
    
    return $r;
  }
  
  public static function dumpdebugstack()
  {
    $out = "";
    while($d = array_shift(self::$debugstack))
    {
      $src = self::$registry[$d[0]]->src;
      
      $out .= "<p>Tag <b>{$d[0]} defined in $src</b>";
      $n=0;
      foreach($d[1] as $a)
      {
        $out .= "<pre>$n)   $a</pre>";
        $n++;
      }
      $out .="\n";
    }
    return $out."</p>";
  }
  
};

function fdump($t)
{
  if($t->argc==1)
  return Template::lispdump();
  else return Template::dump();
}

Template::regfunc('dump','dump all templates','',fdump);

function settrim($t)
{
  $t->assertargc(1);
  Template::$settrim = intval(Template::process($t->argv[0]));
  return '';
}
Template::regfunc('settrim','set whether whitespace is trimmed from tags','*0 or 1');

function ttrim($t)
{
  $t->assertargc(1);
  $text = $t->argv[0];
  return trim(Template::process($t->argv[0]));
}

Template::regfunc('trim','trim whitespace from a string','string',ttrim);


function setdeb($t)
{
  $t->assertargc(1);
  Template::$debugloads = intval(Template::process($t->argv[0]));
  return '';
}
Template::regfunc('debugloads','set whether load debugging is on','*0 or 1',setdeb);


function pushsubst($t)
{
  $t->assertargc(2);
  array_unshift(Template::$subststack,array($t->argv[0],$t->argv[1]));
  return '';
}
Template::regfunc('pushsubst','push a tag name substitution','*search|*replace',pushsubst);

function popsubst($t)
{
  $t->assertargc(0);
  array_shift(Template::$subststack);
  return '';
}
Template::regfunc('popsubst','pop a tag name substitution','',popsubst);
  
?>
