<?php 

// this is a module which doesn't have any handlers - it just
// defines useful templates for Flash object embedding.

/// embed an SWF object with the wrapper specified
/// in http://www.adobe.com/devnet/activecontent/articles/devletter.html
/// You'll need to have AC_RunActiveContent.js included in your head.
/// arguments:
/// 0 - width
/// 1 - height
/// 2 - alignment
/// 3 - movie name (without the trailing .swf)

function embedswf($t)
{
  $t->assertargc(4);
  
  $w = intval(Template::process($t->argv[0]));
  $h = intval(Template::process($t->argv[1]));
  $a = Template::process($t->argv[2]);
  $n = Template::process($t->argv[3]);
  
  return <<<EOT
<script type="text/javascript">
AC_FL_RunContent(
    'codebase', 'http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=7,0,0,0',
    'width','$w',
    'height','$h',
    'bgcolor,','#000000',
    'align','$a',
    'src','$n',
    'quality', 'high',
    'pluginspage','http://www.macromedia.com/go/getflashplayer',
    'movie','$n');
</script>

<noscript>
<object width="$w" height="$h" type="application/x-shockwave-flash" data="$n.swf">
<param name="movie" value="$n.swf" /><img src="dummy.jpg" alt="Shockwave Flash Object" width="$w" height="$h" align="$a" />
</object>
</noscript>
EOT;

}

Template::regfunc('flash:embedswf','embed a flash object','name',embedswf);



?>
