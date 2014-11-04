<?php

if(!defined('IN_SCMS'))die("call from outside app");


class Collection implements Iterator 
{
  static protected $ct=0; //!< collection key counter
  static protected $colls=array(); //!< collections by key
  public $handle; //!< my key if I've been given one
  public $spec; //!< array of field indices, indexed by name
  public $fieldidx; //!< index of field last defined
  public $items; //!< array of items, indexed by item index. Each item is an array of values, indexed by field idx.
  protected $curitem; //!< item currently being set - don't confuse with curitemidx
  protected $curitemidx; //!< index of current item being read or added - don't confuse with curitem!
  protected $store; //!< see save() and restore()
  
  
  /// get a collection by handle
  static public function get($key)
  {
    list($key,$idx) = explode('$',$key); // strip any node ref
    $key=trim($key);
    if(isset(self::$colls[$key]))return self::$colls[$key];
    else throw new InvalidCollectionHandleException("cannot find '$key' in table");
  }
  
  /// constructor which can take a field specification array
  function __construct()
  {
    if(func_num_args() == 0)
    {
      $this->fieldidx = 0;
      $this->spec = array();
    }
    else
    {
      $this->spec = func_get_arg(0);
      $this->fieldidx = count($this->spec);
    }
    $this->items = array();
    
    if($this instanceof Tree)$prefix='tree';
    else $prefix='coll';
    
    
    $k="$prefix:".self::$ct++;
    $this->handle = $k; 
    self::$colls[$k]=$this;
  }
  
  // store all items in a collection into their store area
  function save()
  {
    $this->store = $this->items;
  }
  
  // store all items in ALL collections
  
  static function saveall()
  {
    foreach(self::$colls as $c)$c->save();
  }
  
  /// restore a collection from its store area
  function restore()
  {
    $this->items = $this->store;
  }
  
  // store all items in ALL collections
  
  static function restoreall()
  {
    foreach(self::$colls as $c)$c->restore();
  }
  
  
    
    
  
  // these methods handle the Iterator interface
  function current()
  {
    return $this->items[$this->curitemidx];
  }
  function rewind()
  {
    $this->curitemidx = 0;
  }
  function key()
  {
    return $this->curitemidx;
  }
  function next()
  {
    $this->curitemidx++;
  }
  function valid()
  {
    return isset($this->items[$this->curitemidx]);
  }
  // End of iterator interface
  
  /// like current, but a reference so we can modify it
  function &curref()
  {
    return $this->items[$this->curitemidx];
  }
  
  
  /// add a field specification, or several
  
  public function addfield()
  {
    foreach(func_get_args() as $i)
    {
      $this->spec[$i] = $this->fieldidx++;
    }
  }
  
  /// to make a reusable field spec array pass a list
  /// of field names into this
  public static function makefieldspec()
  {
    $ct=0;
    $a=array();
    foreach(func_get_args() as $i)
    {
      $a[$i] = $ct++;
    }
    return $a;
  }
  
  public function curitemhandle(){
    return $this->handle . '$' . $this->curitemidx;
  }
  
  
  /// add an item; the values should then be set with 'setfield'.
  /// this sets the current item value.
  
  public function additem()
  {
    $i = array();
    $this->curitem=&$i; // copy reference of new item
    $this->items[]=&$i; // add reference of new item!
    $this->curitemidx = count($this->items)-1; // index of item just added
  }
  
  /// prepend an item; the values should then be set with 'setfield'.
  /// this sets the current item value.
  
  public function prependitem()
  {
    $i = array();
    $this->curitem=&$i; // copy reference of new item
    array_unshift($this->items,'');// add reference of new item!
    $this->items[0]=&$i; // horrible way of unshifting a reference
    $this->curitemidx = count($this->items)-1; // index of item just added
  }
  
  /// set a field in the current item
  
  public function setfield($name,$value)
  {
    $fidx = $this->spec[$name]; // get the field index
    if(!isset($fidx))
    {
      throw new UnknownCollectionFieldSetException($name);

    }
    $this->curitem[$fidx]=$value;
  }
  
  /// get a field's index
  public function getfieldidx($name)
  {
    $f = $this->spec[$name]; // get the field  var_dump($c);
    if(!isset($f)){print $this->dump();throw new UnknownCollectionFieldSetException($name);}
    return $f;
  }
  
  public function fieldexists($name)
  {
    $f = $this->spec[$name]; // get the field
    return isset($f);
  }
  
  /// get a field's value given the item index and the field name. Syntactic sugar.
  public function getfield($i,$name)
  {
    return $this->items[$i][$this->getfieldidx($name)];
  }
  
  /// return a dump of the entire collection
  public function dump()
  {
    $d = new Dump();
    
    print "<h2>Dump of collection {$this->handle}</h2>";
    
    $d->newrow();
    foreach($this->spec as $k=>$i)$d->out("<b>$i: $k</b>");
    
    foreach($this->items as $item)
    {
      $d->newrow();
      foreach($this->spec as $k=>$i)
      {
        $d->out($item[$i]);
      }
    }
    print "<pre>\n";
    var_dump($this);
    print "</pre>\n";
    return $d->end();
  }
};

// clever stuff - if the thingy is an object, we also create
// tags for the members; and this continues right down the tree
// of subobjects
function recurseCreateTags($name,$obj){
  Template::regstring($name,$obj,"collection value");
  if(is_object($obj)){
    $arr = get_object_vars($obj);
    foreach($arr as $k=>$v){
      recurseCreateTags("$name:$k",$v);
    }
  }
}

function tforeach($t)
{
  $t->assertargc(3);
  
  //  Template::push();
  
  $c = Collection::get(Template::process($t->argv[0])); // get collection key
  
  $out = "";
  $prefix = $t->argv[1];
  $template = $t->argv[2];
  
  foreach($c as $i=>$item)
  {
    Template::regstring("$prefix:index",$i,'collection item index');
    Template::regstring("$prefix:indexisodd",$i%2,'index is odd');
    $handle = "{$c->handle}\$$i";
    Template::register("$prefix:handle",T_COLLITEM,$handle,'collection item alias in foreach');
    foreach($c->spec as $fname=>$f)
    {
      $v = $item[$f];
      recurseCreateTags("$prefix:$fname",$v);
    }
    $out .= Template::process($template);
  }
//  Template::pop();
  return $out;
}

Template::regfunc('foreach','iterate a collection','collection handle|*prefix|template','tforeach');

function iffieldexists($t)
{
  $t->assertargc(4);
  $c = Collection::get(Template::process($t->argv[0])); // get collection key
  $f = Template::process($t->argv[1]);
  $a = $c->fieldexists($f)?2:3;
  return Template::process($t->argv[$a]);
}

Template::regfunc('iffieldexists','check a collection field exists',
                  'coll handle|fieldname|do this if true|do this if false');

function setcoll($t)
{
  $t->assertargc(2);
  $collname = Template::process($t->argv[0]);
  $handle = Template::process($t->argv[1]);
  
  Template::register($collname,T_COLLECTION,$handle,'collection alias');
}
Template::regfunc('setcoll','set a tag to alias to a collection','tagname|coll handle');  
function setitem($t)
{
  $t->assertargc(2);
  $alias = Template::process($t->argv[0]);
  $handle = Template::process($t->argv[1]);
  
  Template::register($alias,T_COLLITEM,$handle,'collection item alias');
}
Template::regfunc('setitem','set a tag to alias to a collection item','tagname|item handle');
Template::regfunc('setnode','set a tag to alias to a collection item','tagname|node handle',setitem);



function dumpcoll($t)
{
  $t->assertargc(1);
  $c = Collection::get(Template::process($t->argv[0]));
  
  return $c->dump();
}
Template::regfunc('dumpcoll','dump a collection as an HTML table','coll handle');



function collsize($t)
{
  $t->assertargc(1);
  $c = Collection::get(Template::process($t->argv[0]));
  
  return count($c->items);
  
}
Template::regfunc('collsize','return the size of a collection','coll handle');
