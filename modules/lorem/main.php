<?php 

// this module just inserts a block of Lorem Ipsum, or kittens.
//
// lorem:insert
// argument 0 : number of paragraph
// argument 1 : paragraph tag (e.g. "p", "div class="foo""
//
// lorem:kitten - produces a URL
// argument 0 : width
// argument 1 : height
// lorem:kittenbw (greyscale)
// argument 0 : width
// argument 1 : height

$loremtext = <<<EOT
Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum eu auctor urna. In hac habitasse platea dictumst. Donec ac magna at ante varius ornare. Donec mattis est quis lorem sodales in consectetur mauris vehicula. Vestibulum a eros est, a vestibulum diam. Donec at odio tortor. Suspendisse potenti. Donec sit amet nisl eros, a sagittis leo.
Aliquam erat volutpat. Vivamus id odio ante, in rhoncus lacus. Cras mi orci, molestie at eleifend eu, volutpat ac mauris. Sed rhoncus interdum odio, quis iaculis tellus malesuada in. Sed sit amet erat odio, eu condimentum neque. In mattis nisl vel lacus imperdiet sed varius urna rutrum. Curabitur eu volutpat odio. Cras nisi metus, suscipit sit amet ullamcorper sagittis, porttitor eu orci. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Phasellus enim orci, scelerisque id sodales in, aliquet nec nunc. In suscipit, quam id egestas semper, dolor purus volutpat turpis, sit amet posuere nibh dui at quam. Fusce aliquet auctor felis semper scelerisque.
Maecenas ligula odio, malesuada a ultrices at, ultrices in orci. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Aliquam erat volutpat. Nullam iaculis pharetra lorem, nec molestie risus eleifend et. Sed quis augue libero. Donec interdum magna eget erat placerat sodales. Proin viverra adipiscing neque non blandit.
Aliquam imperdiet interdum neque, egestas faucibus mi ornare ut. Quisque adipiscing iaculis neque, id molestie arcu sollicitudin et. Curabitur aliquet lacinia fringilla. Suspendisse potenti. Sed urna orci, mattis quis laoreet vel, condimentum et risus. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Aliquam metus mi, laoreet non viverra eget, aliquet et ante. Nulla facilisi. Nam gravida mi id diam euismod vel convallis sapien aliquet. Aliquam ut justo vitae lectus dictum malesuada. Aliquam erat volutpat. Ut nec accumsan metus. Fusce non nibh non purus semper scelerisque sit amet ac magna. Etiam porta dignissim nisl, vitae interdum justo suscipit sed.
Morbi non mauris in velit hendrerit sagittis. Duis ante ipsum, interdum vitae tincidunt et, dignissim ut quam. Maecenas euismod lacus eu erat rutrum in mollis nibh auctor. Donec ut nulla id erat posuere viverra. Integer lacinia turpis ac felis facilisis egestas. Cras lacinia justo eu lorem ultricies in viverra mauris faucibus. Suspendisse aliquet pellentesque dignissim. Mauris vel orci ante.
Phasellus a mi a diam posuere laoreet eget facilisis odio. Integer nisl elit, molestie in porttitor quis, ultrices non lorem. Duis lorem risus, sagittis eu lobortis et, facilisis nec ligula. Proin ac purus sed diam volutpat volutpat. Nullam ornare mollis dui. Nullam rutrum, lorem id rhoncus venenatis, nisi orci venenatis ligula, vel mattis diam augue et quam. Cras ut justo eget dui aliquet dictum. Proin ultrices lacinia ante vitae lobortis. Sed orci felis, dapibus sed porttitor id, posuere eget dolor. Nunc elementum luctus blandit. Morbi euismod, leo non lacinia vestibulum, diam libero vehicula neque, malesuada aliquet tellus tellus a odio. Cras a massa nunc.
Fusce in eros tellus. Proin sodales lobortis eleifend. Sed blandit odio vel leo convallis et gravida leo imperdiet. Aliquam mi felis, ultrices vel adipiscing at, viverra sed libero. Integer blandit augue et erat molestie vehicula. Nullam egestas, nisi quis dignissim volutpat, velit erat lobortis magna, eu porta mi mi vel ipsum. Duis augue nisi, posuere a laoreet in, lacinia in nisi. Sed quam libero, dictum id malesuada nec, malesuada iaculis lacus. Curabitur adipiscing posuere sollicitudin. Nulla dignissim elit in dolor posuere et vehicula nibh tincidunt. Donec enim turpis, molestie quis commodo quis, blandit in ipsum.
Nunc fringilla justo sed orci pretium at placerat sem congue. Nam sed augue leo. Suspendisse convallis metus id lorem placerat gravida. Donec dapibus dictum lacus, vel congue ante varius in. Curabitur vel sagittis risus. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus augue nunc, tincidunt et commodo et, molestie non sem. Mauris id massa purus. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Donec tincidunt consequat arcu sit amet consectetur. Donec et nisl et justo faucibus molestie at a nibh. Quisque posuere est eu lacus volutpat laoreet. Sed ut nulla nisi, in consequat velit. Vestibulum commodo elit non leo dignissim id fringilla ipsum laoreet. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Integer feugiat pulvinar mi, eu condimentum leo cursus ut.
Pellentesque ac lacus eget felis vestibulum hendrerit. Curabitur arcu tortor, semper et fringilla at, vehicula eget magna. Vestibulum ut eros non nulla rutrum mollis quis nec lectus. In pellentesque, purus sed tristique adipiscing, mi felis egestas lectus, ut rutrum libero nisi iaculis tortor. Pellentesque non orci vel nisi porta aliquam vitae eu sapien. Sed nec ligula et erat bibendum fringilla a sit amet lacus. Donec tincidunt dui sapien, id laoreet mi. Aenean ligula metus, ultricies id sagittis eu, molestie at magna. Curabitur vehicula elementum ante id eleifend. Nunc sit amet dignissim libero. Suspendisse nec urna vitae orci lobortis pulvinar. Donec faucibus neque a enim porttitor eu posuere tortor euismod. Vestibulum quis nisi nulla, id molestie ligula. Nulla facilisi.
Maecenas malesuada diam in tellus pharetra ut fringilla sem luctus. Duis in quam mi, pellentesque posuere neque. Cras posuere sollicitudin molestie. Nulla semper viverra mi, ac semper leo facilisis in. Mauris et mauris arcu. Suspendisse potenti. Aliquam aliquam ultricies felis vitae vulputate. Nam at leo vel velit commodo feugiat in ac erat. Sed non nibh tellus. Vivamus condimentum sagittis convallis.
Aliquam quis neque erat, id commodo massa. Aenean facilisis est gravida tortor interdum sed imperdiet turpis bibendum. Aliquam mi magna, congue eget congue id, convallis pretium est. Aliquam placerat volutpat mattis. Sed ac augue eget nulla volutpat volutpat. Mauris sit amet sapien sem. Pellentesque in dolor eros. Phasellus ornare pulvinar ligula, in viverra lectus imperdiet a. Nullam justo dui, laoreet non porta sit amet, viverra eu lectus.
Sed tristique convallis eros, ac interdum eros egestas fermentum. Fusce venenatis arcu id est mollis aliquam. Nulla volutpat adipiscing dapibus. Nulla consequat ultrices turpis eu facilisis. Vivamus lacinia egestas ullamcorper. Aenean condimentum rutrum lorem quis fermentum. In ullamcorper consequat lobortis. Integer eu nunc id augue rutrum lobortis.
EOT;

global $loremarray;
$loremarray = explode("\n",$loremtext);

function loreminsert($t){
  global $loremarray;
  $t->assertargc(2);
  $n = intval(Template::process($t->argv[0]));
  $tag = Template::process($t->argv[1]);
  
  $s = "";
  for($i=0;$i<$n;$i++) { 
    $q = $loremarray[$i];
    $s .= "<$tag>$q</$tag>\n";
  }
  return $s;
}

function loremkitten($t) {
  $t->assertargc(2);
  $w = intval(Template::process($t->argv[0]));
  $h = intval(Template::process($t->argv[1]));
  
  return "http://placekitten.com/$w/$h";
}
function loremkittenbw($t) {
  $t->assertargc(2);
  $w = intval(Template::process($t->argv[0]));
  $h = intval(Template::process($t->argv[1]));
  
  return "http://placekitten.com/g/$w/$h";
}

Template::regfunc('lorem:insert','insert lorem ipsum','paras|tag,',loreminsert);
Template::regfunc('lorem:kitten','insert kitten URL','w|h,',loremkitten);
Template::regfunc('lorem:kittenbw','insert kitten URL','w|h,',loremkittenbw);



?>
