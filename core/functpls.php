<?php
if(!defined('IN_SCMS'))die("call from outside app");

//////////////////////////////////////////////////////////////////
// general purpose function templates
//////////////////////////////////////////////////////////////////


/// returns argv[1] if the string argv[0] is longer than 0, else argv[2]

function ifnotempty($t)
{
    $t->assertargc(3);
    
    $s = Template::process($t->argv[0]);
    return Template::process($t->argv[(strlen($s)>0)?1:2]);
}
Template::regfunc('ifnotempty','returns argv[1] if the string argv[0] is longer than 0, else argv[2]',
                  'string|use if longer than zero length|use if zero length');

/// binary comparison of numbers:
/// 0 - comparator (eq,neq,gt,lt,gte,lte)
/// 1 - value 1
/// 2 - value 2
/// 3 - do if true
/// 4 - do if false

function cmpn($t)
{
    $t->assertargc(5);
    
    $v1 = intval(Template::process($t->argv[1]));
    $v2 = intval(Template::process($t->argv[2]));
    
    switch($t->argv[0])
    {
        case 'eq':$r = ($v1 == $v2);break;
        case 'neq':$r = ($v1 != $v2);break;
        case 'gt':$r = ($v1 > $v2);break;
        case 'lt':$r = ($v1 < $v2);break;
        case 'gte':$r = ($v1 >= $v2);break;
        case 'lte':$r = ($v1 <= $v2);break;
    default:
        throw new UnknownBinopException($t->argv[0]);
    }
    return Template::process($t->argv[$r?3:4]);
}
Template::regfunc('cmpn','binary numeric comparision','condition|val1|val2|use if true|use if false');


function streplace($t){
  $t->assertargc(3);
  $s = Template::process($t->argv[0]); // source
  $r = Template::process($t->argv[1]); // thing to replace
  $d = Template::process($t->argv[2]); // replacement
  
  return str_replace($r,$d,$s);
}
Template::regfunc('streplace','string replace','target|search|replace');

///
/// Is the number is arg0 between those in arg1 and arg2 inclusive? If so, arg3, else arg4
/// 
function between($t){
  $t->assertargc(5);
  $v = intval(Template::process($t->argv[0]));
  $mn = intval(Template::process($t->argv[1]));
  $mx = intval(Template::process($t->argv[2]));
  
  $b = ($mn <= $v) && ($v <= $mx);
  return Template::process($t->argv[$b?3:4]);
}
Template::regfunc('between','is number in range (inclusive)','val|min|max|use if true|use if false');


// string comparison

function _stringeq($t,$casedep)
{
  $t->assertargc(4);
    
  $v1 = trim(Template::process($t->argv[0]));
  $v2 = trim(Template::process($t->argv[1]));
  
  if($casedep)
  {
    $different = strcasecmp($v1,$v2);
  }
  else
  {
    $different = strcmp($v1,$v2);
  }
  
  $argtoreturn = $different ? 3 : 2;
  
  return Template::process($t->argv[$argtoreturn]);
}
    
Template::regfunc('streq','case-dependent string compare','str1|str2|resultifsame|resultifdifferent',
                  create_function('$t','return _stringeq($t,1);'));
Template::regfunc('strieq','case-independent string compare','str1|str2|resultifsame|resultifdifferent',
                  create_function('$t','return _stringeq($t,0);'));

function tcontains($t)
{
  $t->assertargc(4);
  
  $haystack = trim(Template::process($t->argv[0]));
  $needle = trim(Template::process($t->argv[1]));
  $argtoreturn = (strpos($haystack,$needle)===false) ? 3 : 2;
  
  return Template::process($t->argv[$argtoreturn]);
}
Template::regfunc('contains','test if str1 contains str2','str1|str2|resultiftrue|resultiffalse',tcontains);

function tswitch($t)
{
  $n2 = $t->argc-1;
  $n = $n2/2;
  
  if($n<1)throw new NotEnoughArgumentsException($t->name);
  
  $input = trim(Template::process($t->argv[0]));
  for($i=1;$i<$t->argc;$i+=2)
  {
    $condition = trim($t->argv[$i]);
    if($condition=='default' || $input==trim(Template::process($condition)))
    {
      return Template::process($t->argv[$i+1]);
    }
  }
  return '';
}
Template::regfunc('switch','switch statement - compare a string with a set of values, substituting a corresponding string if a value matches','str|value1|string to use if str=value1|'
                  +'value2|string to use if str=value2|(more pairs can follow)|*default|string to use if no match',
                  tswitch);

    
// template pushing, popping and assigning templates!

Template::regfunc('push','push the template registry','',
                  create_function('$t',"Template::push();"));
Template::regfunc('pop','pop the template registry','',
                  create_function('$t',"Template::pop();"));


/// set a tag to a string. No prefix is used, and the string argument is not tag processed
/// until the defined tag is used (i.e. it isn't processed during the definition). This means
/// you can {{set|sometag|some string depending on being used inside a walk}} outside the walk.

function tregister($t)
{
  $a = $t->argv[1];
  if($t->argc==3)
  {
    if($t->argv[2]!='p')throw new BadArgumentException("optional 3rd argument of set must be 'p'");
    $a = Template::process($a);
  }
  else
    $t->assertargc(2);
  Template::regstring(Template::process($t->argv[0]),$a);
}

Template::regfunc('set','set a template value','name|val|(*p)',tregister);

function tload($t)
{
  $t->assertargc(3);
  $prefix = Template::process($t->argv[0]);
  $dir = SITEPATH.Template::process($t->argv[1]);
  $file = Template::process($t->argv[2]);
  Template::readtagdeffile($prefix,$dir,$file,'load tag');
  return '';
}

Template::regfunc('load','load a TDL file, defining templates with a given prefix','prefix|dir with trailing /|filename',tload);

function loadpage($t)
{
  $t->assertargc(2);
  $spec = Template::process($t->argv[0]);
  $prefix = Template::process($t->argv[1]);
  Page::load($spec,$prefix);
  return '';
}

Template::regfunc('loadpage','load a page definition file, using the tag prefix given to define the tags','page specifier|prefix');

function tencodehtml($t)
{
  $t->assertargc(1);
  $q = Template::process($t->argv[0]);
  return htmlspecialchars($q,ENT_QUOTES);
}

Template::regfunc('htmlentities','encode html entities','string to encode',tencodehtml);

Template::register('nl',T_TEXT,'
','newline');


// function for getting GET values

function httpgetparam($t)
{
  $t->assertargc(1);
  $name = Template::process($t->argv[0]);
  return $_GET[$name];
}

Template::regfunc('httpgetparam','get an HTTP GET parameter','parameter name');

// function for getting POST values

function httppostparam($t)
{
  $t->assertargc(1);
  $name = Template::process($t->argv[0]);
  return $_POST[$name];
}

Template::regfunc('httppostparam','get an HTTP POST parameter','parameter name');


// simple arithmetic

function add($t)
{
  $t->assertargc(2);
  $a = floatval(Template::process($t->argv[0]));
  $b = floatval(Template::process($t->argv[1]));
  
  return $a+$b;
}
Template::regfunc('add','add two numbers','val1|val2');
function sub($t)
{
  $t->assertargc(2);
  $a = floatval(Template::process($t->argv[0]));
  $b = floatval(Template::process($t->argv[1]));
  
  return $a-$b;
}
Template::regfunc('sub','subtract two numbers','val1|val2');
function mul($t)
{
  $t->assertargc(2);
  $a = floatval(Template::process($t->argv[0]));
  $b = floatval(Template::process($t->argv[1]));
  
  return $a*$b;
}
Template::regfunc('mul','mult two numbers','val1|val2');
function div($t)
{
  $t->assertargc(2);
  $a = floatval(Template::process($t->argv[0]));
  $b = floatval(Template::process($t->argv[1]));
  if(!$b)return 1000000;
  return $a/$b;
}
Template::regfunc('div','divide two numbers','val1|val2');
function mod($t)
{
  $t->assertargc(2);
  $a = floatval(Template::process($t->argv[0]));
  $b = floatval(Template::process($t->argv[1]));
  
  return $a%$b;
}
Template::regfunc('mod','modulo two numbers','val1|val2');
function intv($t)
{
  $t->assertargc(1);
  return intval(Template::process($t->argv[0]));
}
Template::regfunc('int','return int value','string','intv');

/// argument is an strftime specification. The current
/// time is inserted.

function ftime($t)
{
  $t->assertargc(1);
  return strftime($t->argv[0]);
}
Template::regfunc('ftime','format the current time with strftime','*format string');



/// timezone

function settimezone($t)
{
  $t->assertargc(1);
  date_default_timezone_set(Template::process($t->argv[0]));
  return '';
}
Template::regfunc('settimezone','set the time zone','time zone string');

/// if tag named in arg 0 is defined, use arg 1, else arg 2

function iftagexists($t)
{
  $t->assertargc(3);
  
  return Template::exists(Template::process($t->argv[0])) ? 
  Template::process($t->argv[1]) :Template::process( $t->argv[2]);
}
Template::regfunc('iftagexists','is tag defined','name|iftrue|iffalse');

/// if the tag named in arg 0 is defined, use it, otherwise use arg 1
function useifexists($t)
{
  $t->assertargc(2);
  $k = Template::getnocheck(Template::process($t->argv[0]));
  
  return ($k!==FALSE)?$k:Template::process($t->argv[1]);
}

Template::regfunc('useifexists','if tag is defined, use it, otherwise an alternative string',
                  'tagname|string to use if tag not defined');

/// if a tag named in arg 0 exists and evaluates as a non-zero integer
/// use arg 1, else use arg 2

function iftagtrue($t)
{
  $t->assertargc(3);
  $k = Template::getnocheck(Template::process($t->argv[0]));
  return Template::process($t->argv[($k!==FALSE && intval($k))?1:2]);
}
Template::regfunc('iftagtrue','is tag exists and is a nonzero integer','*name|useiftrue|useiffalse');

Template::regstring('scmstag','<!-- Generated by SCMS, $Rev: 976 $ $Date: 2013-11-26 19:37:07 +0000 (Tue, 26 Nov 2013) $ (of functpls) -->');

/// splitstring : {{splitstring|mode|delimiter|string|template1|template2...}}

function tsplit($t)
{
  Template::push();
  if($t->argc<2)throw new NotEnoughArgumentsException('split requires at least 4 arguments');
  
  $i=0;
  $mode = $t->argv[$i++]; // the mode to use
  $d = $t->argv[$i++]; // the delimiter to use
  $v = Template::process($t->argv[$i++]); // the string to split into N parts
  
  $out="";
  $base=$i;
  $strnum = $base;
  $maxtemp = $t->argc-1;
  foreach(explode($d,$v) as $s)
  {
    if($strnum>$maxtemp)
    {
      if($mode == 'wrap')$strnum=$base;
      else if($mode == 'clip')$strnum=$maxtemp;
      else $strnum=-1;
    }
    $template = $strnum>=0 ? $t->argv[$strnum] : '';
    $strnum++;
    
    Template::regstring('splitindex',$i-$base,'split item index');
    Template::regstring('splitvalue',$s,'split item value');
    $out .= Template::process($template);
    $i++;
  }
  Template::pop();
  return $out;
}
Template::regfunc('splitstring','split a string by delimiter and process each substring with different templates, concatenating the results','see definition',tsplit);

/// substring arg2-arg3 of arg0 (as per the PHP substr func)
function tsubstr($t){
  $t->assertargc(3);
  $s = Template::process($t->argv[0]);
  $a = intval(Template::process($t->argv[1]));
  $b = intval(Template::process($t->argv[2]));
  return substr($s,$a,$b);
}
Template::regfunc('substr','substring (using PHP substr)','str|a|b',tsubstr);

// simple logical functions

/// if arg0 is nonzero, use arg1 else use arg2
function t_if($t)
{
  $t->assertargc(3);
  $a = intval(Template::process($t->argv[0]));
  
  return Template::process($t->argv[$a?1:2]);
}
Template::regfunc('if','if arg0 is nonzero, use arg1 else use arg2','integer value|useiftrue|useiffalse',t_if);

/// AND operation

function t_and($t)
{
  $t->assertargc(2);
  $a = intval(Template::process($t->argv[0]));
  $b = intval(Template::process($t->argv[1]));
  
  return ($a && $b)?1:0;
}
Template::regfunc('and','logical AND operation:','val1|val2',t_and);

/// OR operation

function t_or($t)
{
  $t->assertargc(2);
  $a = intval(Template::process($t->argv[0]));
  $b = intval(Template::process($t->argv[1]));
  
  return ($a || $b)?1:0;
}
Template::regfunc('or','logical OR operation','val1|val2',t_or);


/// NOT operation
  
function t_not($t)
{
  $t->assertargc(1);
  $a = intval(Template::process($t->argv[0]));
  
  return $a?0:1;
}
Template::regfunc('not','NOT operation','value',t_not);

/// counter function - takes a prefix and the optional command word.
/// Prefix allows you to nest counters. Use {{count|prefix|start}} to start the counter, then
/// use just {{count|prefix}} to increment and return an empty string.
/// {{count|prefix|ct}} will increment and return the counter.
/// {{count|prefix|tog}} will increment and return a toggle
/// {{count|prefix|alt|a|b}} will return 'a' if the toggle is 0 and 'b' if 1. (zebra striping!)
/// Then use {{prefix:count}} to read the counter and {{prefix:toggle}} read a toggle again.

function t_count($t)
{
  static $counters = array();
  if($t->argc==0)throw new NotEnoughArgumentsException('counter takes at least 1 argument');
   
  $prefix = $t->argv[0];
  if($t->argc>=2)
  {
    $tog = ($counters[$prefix] & 1) ? 1 : 0;
    if($t->argv[1] == 'start'){$counters[$prefix]=0;return '';}
    else if($t->argv[1] == 'ct')$out = $counters[$prefix];
    else if($t->argv[1]=='tog')$out= $tog;
    else if($t->argv[1]=='alt')
    {
      $t->assertargc(4);
      $out = $tog?$t->argv[3]:$t->argv[2];
    }
    else throw new BadArgumentException('counter command should be start,ct,alt or tog');
  }
  else $out='';
  
  Template::regstring("$prefix:ct",$counters[$prefix]);
  Template::regstring("$prefix:tog",$tog);
  
  $counters[$prefix]++;
  
  return $out;
}

Template::regfunc('count','counter function','prefix|*command: start,ct,alt or tog|options (see docs)',t_count);

/// for loop.
/// {{for|i|a|b|template}} loops from a to b, setting i to the loop value and running the template each time through
function t_for($t)
{
  $t->assertargc(4);
  $var = $t->argv[0];
  $a = intval(Template::process($t->argv[1]));
  $b = intval(Template::process($t->argv[2]));
  $template = $t->argv[3];
  
  $out = "";
  for($i=$a;$i<=$b;$i++)
  {
    Template::regstring($var,$i);
    $out .= Template::process($template);
  }
  
  return $out;
}
Template::regfunc('for','numeric for loop','var|start|end|template',t_for);


?>
