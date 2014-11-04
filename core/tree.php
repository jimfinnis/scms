<?php

if(!defined('IN_SCMS'))die("call from outside app");
define('NEWLINEREPLSTRING','@NEWy70f0h8498367539');

Tree::$treespec = Collection::makefieldspec('child','ishome','isintrail','issel');

/// a handy method for getting template values. If the value is in the array, return it;
/// if not, return the value set for the key 'default'

function getval($arr,$key)
{
  if(isset($arr[$key]))
  return $arr[$key];
  elseif(isset($arr['default']))
  return $arr['default'];
  else
  {
    print "<pre>";
    var_dump($arr);
    print "</pre>";
    throw new RequirementMissingException('no default key set in array of menu templates');
  }
}

function getvalnocheck($arr,$key)
{
  if(isset($arr[$key]))
  return $arr[$key];
  else return $arr['default'];
}


class Tree extends Collection
{
  public $isroot; //!< true if is root of a tree (used in save/load)
  public $parentc; //!< handle of parent collection
  public $parenti; //!< index of parent node within parent collection
  
  static public $treespec;
  
  /// this constructor DOES NOT work the same way as the collection constructor - you just enter
  /// the field names as an array here, not a fieldspec. That's because we've already used that hack!
  
  function __construct()
  {
    /// set up with minimum required fields for tree
    parent::__construct(self::$treespec);
    
    /// add the others if required
    if(func_num_args()>0)
    {
      foreach(func_get_arg(0) as $a)$this->addfield($a);
    }
    $this->childfieldidx = $this->getfieldidx('child');
  }
  
  /// get() which checks we got a tree
  static function get($key)
  {
    $c = Collection::get($key);
    if(!($c instanceof Tree))throw new InvalidTreeHandleException("handle '$key' refers to a plain Collection, not a Tree");
    return $c;
  }
    
  
  /// special function to add to arrays from serialised data
  private function addfromser()
  {
    $k='tree:'.self::$ct++;
    $this->handle = $k;
//    print "ADDING COLLECTION TO COLLS ARRAY AS $k\n";
    self::$colls[$k]=$this;
  }
    
  
  public $treelevel;// current walk level
  public $treecur; // current collection in walk
  public $treeitem; // current walk item
  private $treevalid; // is collection valid?
  private $treestack; // walk stack
  private $childfieldidx;
  
  private function treevalidcheck()
  {
    if($this->treevalid)
    {
      while(!$this->treecur->valid())
      {
        if($this->treelevel)
        {
          $this->treelevel--;
          $this->treecur = array_pop($this->treestack);
        }
        else
        {
          $this->treevalid=0;
          return;
        }
      }
      $this->treeitem = &$this->treecur->curref();
    } 
  }
  
  
  /// start tree iterator at this collection. 
  
  public function treestart()
  {
    $this->treestack = array();
    $this->treelevel = 0;
    
    $this->treecur = $this;
    $this->treecur->rewind();
    $this->treevalid = 1;
    $this->treevalidcheck();
  }
  
  /// set the child of the given item (or the last item added if omitted)
  /// to the tree passed in. Will set the parent of that tree to me.
  public function setchild(&$tree,$idx=-1)
  {
    if($idx<0)$idx=$this->key();
    $item = &$this->items[$idx];
    $item[$this->childfieldidx]=$tree->handle;
    $tree->parentc=$this->handle;
    $tree->parenti=$idx;
  }
  
  /// get the child of the item passed in
  public function getchild($idx){
    $item = &$this->items[$idx];
    return $item[$this->childfieldidx];
  }
    
  
  /// is current tree iterator valid? If 0, we've come off the end and must stop.
  public function treevalid()
  {
    return $this->treevalid;
  }
  
  /// get current tree node (i.e. item, i.e. field array)
  public function &treecurrent()
  {
    return $this->treeitem;
  }
  
  
  /// go on to next item, or down into subtree
  public function treenext()
  {
    if($this->treevalid)
    {
      $this->treecur->next(); // go onto next item in this collection
      if(isset($this->treeitem[$this->childfieldidx])) // but check previous for child
      {
        // if it has one, go in. We'll return to the next one when we pop.
        array_push($this->treestack,$this->treecur);
        $this->treelevel++;
        $this->treecur = self::get($this->treeitem[$this->childfieldidx]);
        $this->treecur->rewind();
      
      }
      $this->treevalidcheck();
    }
  }
  
  public static function clearwalktags()
  {
    self::$t_selpref=array(); // how to render selected item, set by {{treeselnode|n|...}}
    self::$t_selsuff=array(); // how to render selected item, set by {{treeselnode|n|...}}
    
    self::$t_unselpref=array(); // how to render unselected item, set by {{treeunselnode|n|...}}
    self::$t_unselsuff=array(); // how to render unselected item, set by {{treeunselnode|n|...}}
  
    self::$t_trailpref=array(); // how to render trail item, set by {{treeunselnode|n|...}}
    self::$t_trailsuff=array(); // how to render trail item, set by {{treeunselnode|n|...}}
  
    self::$t_prefix=array(); // how to prefix walk at level n, set by {{treeprefix|n|...}}
    self::$t_suffix=array(); // how to suffix walk at level n, set by {{treesuffix|n|...}}
  }
    
  
    
  public static $t_selpref=array(); // how to render selected item, set by {{treeselnode|n|...}}
  public static $t_selsuff=array(); // how to render selected item, set by {{treeselnode|n|...}}
  
  public static $t_unselpref=array(); // how to render unselected item, set by {{treeunselnode|n|...}}
  public static $t_unselsuff=array(); // how to render unselected item, set by {{treeunselnode|n|...}}
  
  public static $t_trailpref=array(); // how to render trail item, set by {{treeunselnode|n|...}}
  public static $t_trailsuff=array(); // how to render trail item, set by {{treeunselnode|n|...}}
  
  public static $t_prefix=array(); // how to prefix walk at level n, set by {{treeprefix|n|...}}
  public static $t_suffix=array(); // how to suffix walk at level n, set by {{treesuffix|n|...}}
  
  public static $out; // output of tree renderer
  
  /// serialize an entire tree to a string. We serialize
  /// each collection in the tree, converting any
  /// line breaks to a special string.
  
  public function serialise()
  {
    $this->treestart();
    $level=-1;
    $out='';
    
    $this->isroot = 1;
    
//    foreach(self::$colls as $k=>$c)
//    {
//      print "<pre>";
//      print $c->handle." (handle $k) exists\n";
//    }
    
    while($this->treevalid())
    {
      $item = &$this->treecurrent();
      if($this->treelevel>$level)
      {
        // gone down a level, serialize the 
        // new collection
        
        $s = serialize($this->treecur);
//        print "saved {$this->treecur->handle}\n";
        // substitute for newlines
        $s = str_replace('\n',NEWLINEREPLSTRING,$s);
        $out .= $s."\n"; // append
      }
      $level = $this->treelevel;
      $this->treenext();
    }
    return $out;
  }
  
  /// create a load of new collections, reading
  /// each one from a line of a file. We read
  /// them in, create new handles for them, and
  /// then remap all their child/parentc fields.
  
  public static function load($filename)
  {
    $array = file($filename,FILE_IGNORE_NEW_LINES);
    
    if($deb){
      print "<pre> Loading\n";
      print "ALL COLLECTIONS BEFORE LOADING\n";
      foreach(Collection::$colls as $k=>$c)
      {
        $c->dumpspec();
      }
      if($deb)print "LOADING, PRINTING OUT NEW KEYS\n";
    }
    
    $colls = array();
    foreach($array as $line)
    {
      $line = str_replace(NEWLINEREPLSTRING,'\n',$line);
      
      $c = unserialize($line);
      
      // create a new handle for each collection, 
      // but keep the old key
      $oldkey = $c->handle;
      unset($c->handle);
      $c->addfromser();
      // and store it in our array keyed by its OLD handle
      $colls[$oldkey]=$c;
      
      if($deb)print "OLDkey $oldkey NEWkey {$c->handle}\n";
    }
    
    
    
    // now go through all the collections we just
    // made. Go through all items in the collection.
    // For each one, find the child
    // field - if it has a value, convert
    // the old key into a new one! Do the same for the parentc 
    // field, which is part of the Tree object.
    
    if($deb)print "REMAPPING EACH NEW COLLECTION\n";
    foreach($colls as $oldh=>&$c)
    {
      if($deb){print "REMAPPING THIS COLLECTION: ";$c->dumpspec();}
      if($c->isroot)
      {
        $root = $c;
        if($deb)print "-- THIS IS THE ROOT\n";
      }
      else if(isset($c->parentc)) // which it should be
      {
        $c->parentc = $colls[$c->parentc]->handle;
      }

      
      $childidx = $c->getfieldidx('child');
      
      foreach($c->items as &$i)
      {
        if(isset($i[$childidx]))
        {
          $h = $colls[$i[$childidx]]->handle;
          if($deb)print "CHANGE CHILD FIELD FROM {$i[$childidx]} TO $h\n";
          $i[$childidx]=$h;
        }
      }
    }
    
    if($deb)
    {
      print "\n\nOUTPUTTING ALL THE COLLECTIONS\n";
    
      foreach(Collection::$colls as $k=>$c)
      {
        print "COLLECTION HANDLE '$k' - IS OF CLASS : ".get_class($c)."\n";
        $c->dumpspec();
        if($c->fieldexists('child'))
        {
          $childidx = $c->getfieldidx('child');
          $k = $c->handle;
          
          print "$k has parent {$c->parentc} index {$c->parenti}\n";
      
          foreach($c->items as &$i)
          {
            if(isset($i[$childidx]))
            {
              if($deb)print "$k has child key {$i[$childidx]}\n";
            }
          }
        }
        else print "--- THIS IS NOT A TREE NODE\n";
      }
      print "OUTPUTTING THE ROOT NODE\n";
      $root->dumpspec();
    }
    
    
    return $root;
  }
  
  /// given a field name and a value, return a handle for the node which
  /// matches or NULL if not found.
  
  public function findnode($namefield,$name)
  {
    $nameidx = $this->getfieldidx($namefield);
    $this->treestart();
    while($this->treevalid())
    {
      $item = $this->treecurrent();
      if($item[$nameidx]==$name)
      {
        $c = $this->treecur;
        return $c->handle.'$'.$c->key();
      }
      $this->treenext();
    }
    return NULL;
  }

  /// set tags for a node in the tree - used in tree iteration templates
  public function settags($item,$prefix,$idx,$level)
  {
    // set up pseudofields
    Template::regstring("$prefix:index",$idx,'collection item index');
    Template::regstring("$prefix:level",$level,'collection item level');
    Template::regstring("$prefix:coll",$this->handle,'collection handle');
    Template::regstring("$prefix:parentc",$this->parentc,'collection parent handle');
    Template::regstring("$prefix:parenti",$this->parenti,'collection parent node index');
    
    $nodehandle = "{$this->handle}\$$idx";
    Template::register("$prefix:handle",T_COLLITEM,$nodehandle,'collection item alias in walk');
    
    // set up fields
    foreach($this->spec as $fname=>$f)
    {
      $v = $item[$f];
      $n = "$prefix:$fname";
      // only register stuff that's set
      if(isset($v))Template::regstring($n,$v,'collection value');
      else Template::remove($n);
    }
  }
  
  
  
  /// return a pre/post string for the given level at the given mode
  /// (0=unsel,1=trail,2=sel)
  public function getprefixorsuffix($mode,$pre,$level)
  {
    try
    {
    switch($mode)
    {
    case 0:
      $s = getvalnocheck($pre?self::$t_unselpref:self::$t_unselsuff,$level);
      if(is_null($s))
      {
        // no unselected, so try trail.. if that fails, try sel.
        $s = getvalnocheck($pre?self::$t_trailpref:self::$t_trailsuff,$level);
        if(is_null($s))$s = getvalnocheck($pre?self::$t_selpref:self::$t_selsuff,$level);
        if(is_null($s))throw new RequirementMissingException("no defined value for tree walk template");
      }
      return $s;
    case 1:
      $s = getvalnocheck($pre?self::$t_trailpref:self::$t_trailsuff,$level);
      if(is_null($s))
      {
        // no trail, so try unsel.. if that fails, try sel.
        $s = getvalnocheck($pre?self::$t_unselpref:self::$t_unselsuff,$level);
        if(is_null($s))$s = getvalnocheck($pre?self::$t_selpref:self::$t_selsuff,$level);
        if(is_null($s))throw new RequirementMissingException("no defined value for tree walk template");
      }
      return $s;
    case 2:
      $s = getvalnocheck($pre?self::$t_selpref:self::$t_selsuff,$level);
      if(is_null($s))
      {
        // no selected, so try trail.. if that fails, try unsel.
        $s = getvalnocheck($pre?self::$t_trailpref:self::$t_trailsuff,$level);
        if(is_null($s))$s = getvalnocheck($pre?self::$t_unselpref:self::$t_unselsuff,$level);
        if(is_null($s))throw new RequirementMissingException("no defined value for tree walk template");
      }
      return $s;
    default:throw new InternalException("bad mode");
    }
  }catch(RequirementMissingException $e){
    print "<pre>\n";
    var_dump(self::$t_selpref);
    var_dump(self::$t_selsuff);
    var_dump(self::$t_trailpref);
    var_dump(self::$t_traillsuff);
    var_dump(self::$t_unselpref);
    var_dump(self::$t_unselsuff);
    print "</pre>\n";
    throw $e;
  }
}

};


  
/// this is a clever function which walks a collection and sets fields inside each item, given a unique
/// identifier such as the page specifier used in the navigation menu. It does this by looking through the tree until
/// it finds the a node with a matching identifier.
/// Then it marks that node as selected leaf, and marks all the parents all the way 
/// up as being selected.
///
/// INPUT Fields which must exist in the collection:
/// child - the handle of a sub collection of this node (or unset)
/// parent - the handle of the parent node of this subnode (or unset)
/// some field whose name is given in the args, which is the unique identifier of each node
///
/// OUTPUT Fields which must exist unset, and the function sets:
/// ishome - true if first item at top level
/// issel - true if selected
/// isintrail - true if is in the trail
/// 
/// Arguments:
/// 0 - collection handle
/// 1 - the name of the identifier field to match in the tree
/// 2 - the identifier

function marktree($t)
{
  $t->assertargc(3);
  
  $c = Tree::get(Template::process($t->argv[0]));
  $namefield = Template::process($t->argv[1]);
  $id = Template::process($t->argv[2]);
  
  // let's get the field indices making sure they exist
  
  $nameidx = $c->getfieldidx($namefield);
  $ishomeidx = $c->getfieldidx('ishome');
  $isselidx = $c->getfieldidx('issel');
  $isintrailidx = $c->getfieldidx('isintrail');
  
  Template::push();
  
  // first iter, clear the nodes
  $c->treestart();
  while($c->treevalid())
  {
    $item = &$c->treecurrent();
    $item[$ishomeidx]=0;
    $item[$isselidx]=0;
    $item[$isintrailidx]=0;
    $c->treenext();
  }
  
  // second iter, find our node
  $c->treestart();
  while($c->treevalid())
  {
    $item = &$c->treecurrent(); // get current item
    $t = $item[$nameidx]; // get name of item
    if($item[$nameidx]==$id) // if a match
    {
      $c = $c->treecur; // get tree current item is in
      $item[$isselidx]=1; // set current item selected
      $item[$isintrailidx]=1; // and I'm also in the trail
      
      // and now iterate up to find the parent nodes
      while(isset($c->parentc))
      {
        // go up
        $i = $c->parenti;
        $c = Tree::get($c->parentc);
        $item = &$c->items[$i];
        
        // mark me
        $item[$isintrailidx]=1;
        $s = $item[$nameidx];
      }
      
    }
    $c->treenext();
  }
  
  Template::pop();
  return '';
}

Template::regfunc('marktree','set fields a hierarchy collection indicating selection',
                  'tree handle|name of field|value of field in selected item');

/// render a hierarchy collection, assuming it has 'child' and 'parentc/i' fields
/// INPUT Fields which must exist in the collection:
/// child - the handle of a sub collection of this node (or unset)
/// parent - the handle of the parent node of this subnode (or unset)
/// name - the shortname of the node (as used in the path)
/// ARGUMENTS
/// 0 - the collection handle
/// 1 - namespace prefix for defined values
///
/// also, the following tags must have been use to set the render state up:
/// treeprefix, treesuffix, treeselnode, treeunselnode, treetrailnode
///

function dorendertree($c,$childidx,$isselidx,$isintrailidx,$prefix,$level)
{
  Tree::$out .= Template::process(getval(Tree::$t_prefix,$level));      
  
  foreach($c as $item)
  {
    // determine selection
    if(intval($item[$isselidx]))$mode = 2;
    else if(intval($item[$isintrailidx])) $mode=1;
    else $mode=0;
    
    $suff = $c->getprefixorsuffix($mode,0,$level);
    $pref = $c->getprefixorsuffix($mode,1,$level);
    
    $c->settags($item,$prefix,$c->key(),$level);
    
    // output first part of text
      
    Tree::$out .= Template::process($pref);
    
    // output any subtree
    if(isset($item[$childidx]))
    {
      $child = Tree::get($item[$childidx]);
      $out .= dorendertree($child,$childidx,$isselidx,$isintrailidx,$prefix,$level+1);
    }
    
    // output last part of text
    Tree::$out .= Template::process($suff);
  }
  Tree::$out .= Template::process(getval(Tree::$t_suffix,$level));
}

function rendertree($t)
{
  $t->assertargc(2);
  $c = Tree::get(Template::process($t->argv[0]));
  $prefix = $t->argv[1];
  
  // let's get the field indices making sure they exist
  
  $childidx = $c->getfieldidx('child');
  $isselidx = $c->getfieldidx('issel');
  $trailidx = $c->getfieldidx('isintrail');
  
  Template::push();
  Tree::$out = '';
  dorendertree($c,$childidx,$isselidx,$trailidx,$prefix,0);
  Template::pop();
  Tree::clearwalktags(); // clear out the old definitions
  return Tree::$out;
}

Template::regfunc('rendertree','walk a tree with current walk renderer params',
                  'tree handle|*prefix');


/// set the 'selected node' template for walks, given
/// a walk node level or 'default' and the node string
function treeselnode($t)
{
  $t->assertargc(3);
  $level = $t->argv[0];
  Tree::$t_selpref[$level] = $t->argv[1];
  Tree::$t_selsuff[$level] = $t->argv[2];
  return "";
}
Template::regfunc('treeselnode','set the selected walk node renderer for a level','*level|*pre-text|*post-text');

/// set the 'unselected node' template for walks, given
/// a walk node level or 'default' and the node string
function treeunselnode($t)
{
  $t->assertargc(3);
  $level = $t->argv[0];
  Tree::$t_unselpref[$level] = $t->argv[1];
  Tree::$t_unselsuff[$level] = $t->argv[2];
  return "";
}
Template::regfunc('treeunselnode','set the unselected walk node renderer for a level:','*level|*pre-text|*post-text');

/// set the 'trail node' template for walks, given
/// a walk node level or 'default' and the node string
function treetrailnode($t)
{
  $t->assertargc(3);
  $level = $t->argv[0];
  Tree::$t_trailpref[$level] = $t->argv[1];
  Tree::$t_trailsuff[$level] = $t->argv[2];
  return "";
}
Template::regfunc('treetrailnode','set the trail walk node renderer for a level','*level|*pre-text|*post-text');

/// set the prefix template for walks of a given level (or 'default')
function treeprefix($t)
{
  $t->assertargc(2);
  $level = $t->argv[0];
  $text = $t->argv[1];
  Tree::$t_prefix[$level] = $text;
  return "";
}
Template::regfunc('treeprefix','set the walk prefix for a level','*level|*text');

/// set the suffix template for walks of a given level (or 'default')
function treesuffix($t)
{
  $t->assertargc(2);
  $level = $t->argv[0];
  $text = $t->argv[1];
  Tree::$t_suffix[$level] = $text;
  return "";
}
Template::regfunc('treesuffix','set the walk prefix for a level','*level|*text');


// given a node name, and a tree, find the collection which contains that node.

function parentofnode($c,$namefield,$name)
{
  $nameidx = $c->getfieldidx($namefield);
  
  $c->treestart();
  while($c->treevalid())
  {
    $item = $c->treecurrent();
    if($item[$nameidx]==$name)return $c->treecur;
    $c->treenext();
  }
}

/// tag interface to parentofnode
function tparentofnode($t)
{
  $t->assertargc(3);
  return parentofnode(Tree::get(Template::process($t->argv[0])),
                      Template::process($t->argv[1]),
                      Template::process($t->argv[2])
                      )->handle;
}
Template::regfunc('findcollection','find collection in tree containing specified node',
                  'tree|name of field|value of field in required node',tparentofnode);

/// find a node by searching for it, returns a node handle or empty string
function findnode($t)
{
  $t->assertargc(3);
  $c = Tree::get(Template::process($t->argv[0]));
  $namefield = Template::process($t->argv[1]);
  $name = Template::process($t->argv[2]);
  
  $n = $c->findnode($namefield,$name);
  return $n;
}
Template::regfunc('findnode','find a node given a field and string',
                  'tree|name of field|value of field in required node');

function withnode($t)
{
  $t->assertargc(3);
  $k = Template::process($t->argv[0]);
  list($handle,$idx) = explode('$',$k);
  $c = Tree::get($handle);
  $i = intval($idx);
  $prefix = $t->argv[1];
  $template = $t->argv[2];
  
  $item = $c->items[$i];
  
  $c->settags($item,$prefix,$i,-1);
  return Template::process($template);
}
Template::regfunc('withnode','render a single node in a tree with a given template',
                  'node handle|*prefix for field values|*template to use');


/// find the collection of a node
function coll($t) {
  $t->assertargc(1);
  $c = Tree::get(Template::process($t->argv[0]));
  if(isset($c->handle))
    return $c->handle;
  else return '';
}
Template::regfunc('coll','find collection a node','node handle');
  

/// find the parent of a collection if there is one, else empty string
function parentc($t)
{
  $t->assertargc(1);
  $c = Tree::get(Template::process($t->argv[0]));
  if(isset($c->parentc))
    return $c->parentc;
  else return '';
}
Template::regfunc('parentc','find parent collection of tree node','node handle');

/// find index of parent node in the parent of a collection if there is one, else -1
function parenti($t)
{
  $t->assertargc(1);
  $c = Tree::get(Template::process($t->argv[0]));
  if(isset($c->parentc))
    return $c->parenti;
  else return '';
}
Template::regfunc('parenti','find index of node of parent collection of tree node','node handle');

/// find node address of parent node
function parent($t)
{
  $t->assertargc(1);
  $c = Tree::get(Template::process($t->argv[0])); // will strip any node idx
  if(isset($c->parentc))
    return $c->parentc.'$'.$c->parenti;
  else return '';
}
Template::regfunc('parent','find parent node of a tree node','node handle');

/// get index from node reference
function indexof($t)
{
  $t->assertargc(1);
  $c = Template::process($t->argv[0]); // will strip any node idx
  list($dummy,$idx) = explode('$',$c);
  return $idx;
}
Template::regfunc('indexof','find the index of a tree node','node handle');

/// get the value of a field in a node
function getfield($t)
{
  $t->assertargc(2);
  $k = Template::process($t->argv[0]);
  list($handle,$idx) = explode('$',$k);
  $c = Tree::get($handle);
  $f = $c->getfieldidx(Template::process($t->argv[1]));
  $i = intval($idx);
  $item = $c->items[$i]; 
  return $item[$f];
}
Template::regfunc('getfield','get value of a field in a node','node handle|field name');


/// get the value of a field in a node
function iffieldset($t)
{
  $t->assertargc(4);
  $k = Template::process($t->argv[0]); // will strip any node idx
  list($handle,$idx) = explode('$',$k);
  $c = Tree::get($handle);
  $f = $c->getfieldidx(Template::process($t->argv[1]));
  $i = intval($idx);
  
  if(isset($c->items[$i]) && isset($c->items[$i][$f]))$k=2;
  else $k=3;
//  print "testing {$items[$i]}";
  
  return Template::process($t->argv[$k]);
}
Template::regfunc('iffieldset','test if a field has been set with a value','node|field name|useifset|useifnotset');

function splitcoll($t)
{
  if($t->argc<2)throw new NotEnoughArgumentsException('splitcoll requires at least 2 arguments');
  
  $str = trim(Template::process(array_shift($t->argv)));
  $delim = array_shift($t->argv);
  $t->argc-=2;
  $c = new Tree;
  $c->addfield('value');
  
  foreach(explode($delim,$str) as $s)
  {
    $newitem = $c->additem();
    $c->setfield('value',$s);
  }
  
  return TagSpecification::handlecolltagargs($t,$c->handle);
}
Template::regfunc('split','split a string by delimiter character, return a collection of strings','string|*delimiter',splitcoll);
  
