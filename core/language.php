<?php
if(!defined('IN_SCMS'))die("call from outside app");

global $nls;

/// a language entry, read from the langs file, but
/// originally built from the nls files.
class LangEnt
{
  public $code; //!< language code
  public $endonym; //!< the language's name for itself (e.g. Cymraeg)
  public $exonym; //!< the language's name in English (e.g. Welsh)
  public $encoding; //!< the encoding to use (e.g. UTF-8)
};


/// object returned by Language::getlangfiles()

class GetLangFilesData
{
  public $langfile; //!< filename with language
  public $basefile; //!< filename without language
  public $flags; //!< 1 = exists without language, 2=exists with language
};
  

/// system to include the files in the site's language directory
/// These are .nls files, the same system that CMSMS and Horde uses.
/// Data is read into a global $nls array.

class Language
{
  private static $langs=array(); //!< array of LangEnts, one for each language or alias
  private static $curlang; //!< the currently selected LangEnt
  private static $langspec; //!< boolean indicating whether a language was specified in the URL
  /// if the cached list of languages is available, read it in. Otherwise rebuild it.
  /// Note that it may contain a lot of duplicate lines
  /// for language aliases!
  
  public static function init()
  {
    $fn = TMPPATH.'langs';
    
    if(!NO_TMP && file_exists($fn))
    {
      self::$langs=unserialize(file_get_contents($fn));
    }
    else
    {
      // oops, no language file - rebuild it from the NLS files
      // and create the array too.
      self::rebuildcache();
    }
    
    // set the default language - will set langspec
    self::setlang(DEFAULTLANG);
    self::$langspec=0; // clear langspec to indicate this was the default lang
  }
  
  /// set the current language - this will take
  /// alias values.
  
  public static function setlang($lang)
  {
    if(is_null(self::$langs[$lang]))
       throw new UnknownLanguageException($lang);
     self::$curlang = self::$langs[$lang];
     self::$langspec =1; // we specified a language
  }
  
  /// is this name a valid language?
  public static function islang($lang)
  {
    return !is_null(self::$langs[$lang]);
  }
  
  /// return true if a language code was actually
  /// passed in the current URL (the one with which this page
  /// was created)
  public static function languagespecified()
  {
    return self::$langspec;
  }
  
  /// return the current language entry
  public static function getlang()
  {
    return self::$curlang;
  }
  
  /// return the current language code
  public static function getcode()
  {
    return self::$curlang->code;
  }
  
  /// return the current language code
  public static function getencoding()
  {
    return self::$curlang->encoding;
  }
  
  /// function to return an array of language ents
  /// which are not aliases
  public static function getnonaliases()
  {
    $out = array();
    foreach(self::$langs as $name=>$ent)
    {
      if($name == $ent->code)
      {
        // the name is the same as the code; this is a canonical entry. 
         array_push($out,$ent);
      }
    }
    return $out;
  }
  
  
  /// Function to determine whether a language version of a file exists
  /// and/or just the default file. It takes the directory of the file
  /// and the file name. It uses the currently selected language code.

  public static function getlangfiles($dir,$file)
  {
    if(substr($dir,-1)!='/')
       $dir.='/';
  
    $e = new GetLangFilesData;
    $e->flags = 0;
    
    $f = $dir.$file;
    
#    print "<pre>Looking for $f</pre><br>";
    if(file_exists($f))
    {
      $e->basefile = $f;
      $e->flags = 1;
    }
    $f = "$dir".self::getcode()."/$file";
#    print "<pre>Looking for $f</pre><br>";
    if(file_exists($f))
    {
      $e->langfile = $f;
      $e->flags |= 2;
    }
  
    return $e;
  }
  
  /// initialise the language system, and read ALL the language data from the language
  /// data directory.
  
  public static function rebuildcache()
  {
    global $nls;
    $dn = SITEPATH.'languages';
    // include all the language files
    $dir = @opendir(SITEPATH.'languages');
    if($dir)
    {
      while($file = readdir($dir))
      {
        if(preg_match('/.*nls\.php$/',$file))
        {
          include_once SITEPATH.'languages/'.$file;
        }
      }
      @closedir($dir);
    }
    else
    {
      throw new BadInstallException("no site/languages directory $dn");
    }
    
    // register the languages
    
    foreach($nls['language'] as $name=>$l)
    {
      // register the base language
      
      $ent = new LangEnt;
      $ent->code = $name;
      $ent->endonym = $l;
      $ent->exonym = $nls['englishlang'][$name];
      if(strlen($ent->endonym)<1)
      {
        $ent->endonym=$ent->exonym;
      }
      if(strlen($ent->exonym)<1)
      {
        $ent->exonym=$ent->endonym;
      }
      $ent->encoding = $nls['encoding'][$name];
      self::$langs[$name] = $ent;
      
      // now the aliases
      
      foreach($nls['alias'] as $alias=>$code)
      {
        if($code == $name)
        {
          self::$langs[$alias]=$ent;
        }
      }
    }
      
    // and write the array, serialized, to the language file
    
    if(!NO_TMP){
      $fn = TMPPATH.'langs';
      $file = fopen($fn,"w");
      fwrite($file,serialize(self::$langs));
      fclose($file);
    }
  }
};



?>
