<?php 
if(!defined('IN_SCMS'))die("call from outside app");

/* This block of code determines ROOTPATH and ROOTURL based on
  server variables. It should suffice for most needs, but you can
  tweak it if you have to.
  
  ROOTURL is the URL of the top level directory.
  ROOTPATH is the filesystem path of the top level directory.
*/  

// the root path is the path of the current script sans the index.php
//$rp = str_replace('index.php','',$_SERVER['SCRIPT_FILENAME']);
$rp = dirname($_SERVER['SCRIPT_FILENAME'])."/";
define('ROOTPATH',$rp);

// now making the root url. Start with the host name plus the script name,
// then strip the index.php, leaving the host name and the directory.
$rp = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}";
$rp = str_replace('index.php','',$rp);

define('ROOTURL',$rp);
#print ROOTPATH."<br>".ROOTURL."<br>\n";

define('DEFAULTLANG','en_US');
define('DEFAULTPAGE','home');

define('CACHING',0); // SERVER CACHING MODE
define('BROWSERCACHING',0); // CLIENT CACHING MODE

define('COMMENTS_IN_MAIN',1); // comments permitted in template main file

define('NO_TMP',0); // set if there is no temporary storage at all!

$cache_exceptions=array();

