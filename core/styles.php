<?php

// a handy function, comparing an array in the form
//   pattern,result
//   pattern,result...
// The result is returned for the first pattern that matches.
// If no pattern matches, 'default' is returned.

function comparearray($input,$array)
{
  $c = count($array);
  for($i=0;$i<$c;$i+=2)
  {
    if(preg_match($array[$i],$input))return $array[$i+1];
  }
  return 'default';
}

function getstylename()
{
  // set vars available in hook
  
  $useragent = $_SERVER['HTTP_USER_AGENT'];
  
  if(file_exists(SITEPATH.'getstylename.php'))
  {
    include(SITEPATH.'getstylename.php');
  }
  else
  {
    $stylename= comparearray($useragent,
                        array(
'/(i[Pp][oa]d|i[Pp]hone)/','iphone',
'/(textmode|[lL]ynx)/','textmode',
'/[fF]irefox/','firefox',
'/Opera/','opera',
'/Safari/','safari',
'/MSIE 8/','ie8',
'/MSIE 7/','ie7',
'/MSIE 6/','ie6',
'/MSIE/','ie-old'));
  }
  return $stylename;
}


/// the stylemap file contains lines of the form
///    style/oldtemplate=>[template newtemplate] [mainfile newmainfile.html]
/// either of the RHS sections can be omitted, which is what the square brackets mean.
/// Also, oldtemplate can be *, which matches any template.
///
/// Consider a page which has set the template to 'news'. Let the style be 'iphone'.
/// The lines:
///    iphone/*=>template iphone
///    iphone/news => template iphone
/// will set the template to 'iphone'. The line
///    iphone/default => template iphone
/// will not, because the template set by the page isn't 'default'. The line
///    iphone/news => mainfile iphone.html
/// will leave the template 'news' but will use the file 'iphone.html' instead of
/// main.html

/// This function returns the new main file name. It may change
/// the page:template tag, and it reads the current template and style names
/// from the tag registry.

function dostylemap()
{
  $fn = SITEPATH.'stylemap';
  $main = 'main.html';
  
  
  if(is_file($fn))
  {
    $curtemp = Template::get('page:template');
    $style = Template::get('style');
    
    $ff = file($fn,FILE_SKIP_EMPTY_LINES|FILE_IGNORE_NEW_LINES);
    
    foreach($ff as $s)
    {
      if($s[0]!='#')
      {
        $a = explode('=>',$s);
        
        $lhs = trim($a[0]);
        $lhs = explode('/',$lhs);
        $rhs = trim($a[1]);
        
        $mapstyle = $lhs[0];
        $oldtemp = $lhs[1];
        
        if($mapstyle == $style && ($oldtemp=='*' || $oldtemp==$curtemp))
        {
          $rhs = preg_split('/\s+/', $rhs);
          for($i=0;$i<count($rhs);$i++)
          {
            $e = $rhs[$i];
            if($e == 'template')
            {
              $i++;
              $newtemp = $rhs[$i];
              Template::regstring('page:template',$newtemp);
            }
            else if($e == 'mainfile')
            {
              $i++;
              $main = $rhs[$i];
            }
            else throw new BadStyleMapSyntaxException("keyword '$e' unknown in line:\n $s");
          }
          return $main;
        }
      }
    }
  }
  return $main;
}
