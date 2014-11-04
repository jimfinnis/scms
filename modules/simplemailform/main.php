<?php

define('USE_CAPTCHA',False);

require_once(SITEPATH.'modules/captchas/captcha.php');




//
//

class MailModule extends Module
{
  // a POST mod=mail request consists of the following other arguments:
  // - name
  // - email/email2 (must agree)
  // - telephone
  // - subject
  // - text
  // Result is module:success which is true or false.
  // If it's false, module:error will hold an error code which must
  // be translated! Codes are:
  // 0 - email/email2 disagree
  // 1 - mail send error
  // 2 - mail sent ok, but mail to sender failed
  // The mail destination is in the MAILDEST define.
  
  
  public function handleget()
  {
    return TRUE;
  }
  public function handlepost()
  {
    $cap = $_POST['captcha'];
    $rand = $_POST['random'];
    if((!USE_CAPTCHA) || Captcha::check(urldecode($cap),urldecode($rand)))
    {
      if(!filter_var($_POST['email'],FILTER_VALIDATE_EMAIL)){
        Template::regstring('module:success',0);
        Template::regstring('module:error',4); // not a valid address
        return;
      }
      
      if(!filter_var($_POST['email2'],FILTER_VALIDATE_EMAIL)){
        Template::regstring('module:success',0);
        Template::regstring('module:error',4); // not a valid address
        return;
      }
      
      $text = $_POST['text'];
      $subject = $_POST['subject'];
      $name = htmlspecialchars($_POST['name']);
      $phone = htmlspecialchars($_POST['phone']);
      $email = filter_var($_POST['email'],FILTER_SANITIZE_EMAIL);
      $email2 = filter_var($_POST['email2'],FILTER_SANITIZE_EMAIL);
      
  
      
      
      $sendcopy = isset($_POST['sendcopy']) && intval($_POST['sendcopy']);
    
      $text = <<<EOT
Some email was sent to you via your web site
contact form. The details were :
From $name
Email address: $email
Telephone number: $phone
----------------------------
$text
EOT;
      Template::regstring('module:text',$text);

      if(strcmp($email,$email2))
      {
        Template::regstring('module:success','0');
        Template::regstring('module:error','0');
        return TRUE; // continue processing page
      }
    
      $headers = "From: $name <$email>";
    
      $rv=mail(MAILDEST,"Email from Website Form: $subject",$text,$headers);

      if($rv)
      {
        $succ = '1';
        // now try to send to sender if desired
        if($sendcopy)
        {
          $rv2=mail($email,"Email to Website: $subject",$text,$headers);
          if(!$rv2)
          {
            $succ='0';
            Template::regstring('module:error','2');
          }
        }
      }
      else
      {
        $succ = '0';
        Template::regstring('module:error','1');
      }
      Template::regstring('module:success',$succ);
      
    }
    else
    {
      Template::regstring('module:success',0);
      Template::regstring('module:error',3); // captcha fail
      Template::regstring('module:debug',"[$cap] [$rand]");
    }
    return TRUE;
  }    
};

Captcha::Init();

// the name here must be the same as the filename!
Modules::register('simplemailform',new MailModule);

function t_smfinitcaptcha($t)
{
  Captcha::registertemplates();
}

Template::regfunc('simplemailform:initcaptcha','initialise a captcha','',t_smfinitcaptcha);

?>
