<?php

/// handy functions.

/// This one reads a file into a string, then removes C-style comments from it.
function file_get_contents_remove_comments($name)
{
  $s = file_get_contents($name);
  $s = preg_replace("/\/\*(.*?)\*\//s","",$s);
  return $s;
}

/// get the URL for a named page, preserving
/// all _GET[] query data if the argument is true
/// We can also pass in an array of key,value pairs for changing in the
/// parameters if we use preserveget.

function getURLforpage($page,$preserveget=true,$modarray=NULL)
{
  // the URI is the language code prefixed onto the actual path, followed by any GET data.
  if($preserveget)
  {
    $tmp = $_GET;
    // run the modifications
    if(!is_null($modarray)){
      foreach($modarray as $k=>$v){
        if($v == '(DEL)'){
          unset($tmp[$k]);
        } else {
          $tmp[$k]=$v;
        }
      }
    }
    
    $q = http_build_query($tmp);
    if(strlen($q))$q='?'.$q;
  }
  else
  {
    // we still want any explicit style to be preserved
    // and also fontsize if we're using it!
    if(isset($_GET['style']))$q = '?style='.$_GET['style'];
    if(isset($_GET['fontsize']))$q = '?fontsize='.$_GET['fontsize'];
    else $q='';
  }
  if(Language::languagespecified())
    return ROOTURL.'index.php/'.Language::getcode()."/".$page.$q;
  else
    return ROOTURL."index.php/$page$q";
}

/// hack to get page URL with a given style
/// We can also pass in an array of key,value pairs for changing in the
/// parameters if we use preserveget.

function getURLforpagewithstyle($spec,$style,$preserveget=true,$modarray=NULL)
{
  $oldg = $_GET;
  $_GET['style']=$style;
  $r = getURLforpage($spec,$preserveget,$modarray);
  $_GET=$oldg;
  return $r;
}

/// hack to get page URL without a style
/// We can also pass in an array of key,value pairs for changing in the
/// parameters if we use preserveget.

function getURLforpagewithnostyle($spec,$preserveget=true,$modarray=NULL)
{
  $oldg = $_GET;
  unset($_GET['style']);
  $r = getURLforpage($spec,$preserveget,$modarray);
  $_GET=$oldg;
  return $r;
}


/// This is used in situations where a destination file is generated from a source file
/// in a time consuming process. We only want to produce the destination file when either
/// it doesn't exist, or is older than the source file.

function checkforcaching($src,$dest)
{
  if(!file_exists($dest))return 1; // no file, definitely need to make it
  
  $srcstat = stat($src);
  $deststat = stat($dest);
  
  $srcdate = $srcstat['mtime'];
  $destdate = $deststat['mtime'];
  
  return $srcdate>$destdate; // return true if src newer than dest
}


/// class for spewing debugging data as a table
class Dump
{
  private $s;
  function __construct()
  {
    $this->s = "<table border=0>\n";
    $this->i=0;
  }
  
  public function newrow()
  {
    if($this->i)$this->s .= "</tr>\n";
    
    $this->i++;
    if($this->i & 1)
      $this->s.= "<tr bgcolor=#eeeeed>";
    else
      $this->s.= "<tr bgcolor=#dddddd>";
  }
  
  public function out($a)
  {
    $this->s .= "<td>$a</td> ";
  }
  
  public function end()
  {
    $this->s .= "</tr></table>\n";
    return $this->s;
  }
}
    
    
  
  

?>
