<?php
if(!defined('IN_SCMS'))die("call from outside app");

function createlangtree()
{
  $fields = array('code','endonym','exonym','encoding','url');
  
  // it's not really a tree, but we can walk it like a tree
  // if we use this.
  
  $output = new Tree($fields);
  
  foreach(Language::getnonaliases() as $e)
  {
    $output->additem();
    $output->setfield('code',$e->code);
    $output->setfield('endonym',$e->endonym);
    $output->setfield('exonym',$e->exonym);
    $output->setfield('encoding',$e->encoding);
    
    
    // now make the URL
    
    // get the HTTP get data, which we'll append to the end.
    $q = http_build_query($_GET);
    if(strlen($q))
       $q='?'.$q;
       
    // return the root level, followed by the desired language code,
    // followed by the current path string and any HTTP GET query data.
    $url = ROOTURL.'index.php/'.$e->code.'/'.GlobalData::$pathstring.$q;
    $output->setfield('url',$url);
  }
  
  return $output;
}

/// return a handle to the language tree, creating if required
function langtree($t)
{
  static $c;
  if(!isset($c))
  {
    $c = createlangtree();
  }
  return TagSpecification::handlecolltagargs($t,$c->handle);
}
Template::regfunc('langtree','get the language collection pseudotree','');
  
