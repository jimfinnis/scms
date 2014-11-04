<?php

require 'CaptchasDotNet.php';

class CaptchaException extends Exception {};

class Captcha
{
  private static $cap;
  
  public static function Init()
  {
    self::$cap = new CaptchasDotNet('jcfinnis','bVjuGhOuhBoTqRrd2THJ37GoLZYeBQ5eIa7ozOF4',SITEPATH."../tmp/captchas");
  }
  
  public static function check($password,$key)
  {
    if(self::$cap)
    {
        if(!self::$cap->validate($key))
        {
          throw new CaptchaException('each captcha key can only be used once');
        }
        if(!self::$cap->verify($password))
        {
          return false;
        }
      }
    return true;
  }
  
  public static function registertemplates()
  {
    if(self::$cap)
    {
      $key = self::$cap->random();
      $image = self::$cap->image();
      
      Template::regstring('captchakey',$key);
      Template::regstring('captchaimage',$image);
    }
  }
  
  public static function isvalid()
  {
    return (self::$cap)?true:false;
  }
};

function ifcaptchaexists($t)
{
  if(Captcha::isvalid())
  {
    return Template::process($t->argv[0]);
  }
  else
  {
    return Template::process($t->argv[1]);
  }
}

Template::regfunc('ifcaptchaexists','do first arg if a captcha module has been set up, else do second arg','');
  
  

?>
