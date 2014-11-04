<?php
/*
include('mobile_device_detect.php');

if(mobile_device_detect(true,true,true,true,true,true,false,false))
$stylename='mobile';
else
$stylename='default';
*/
$stylename = comparearray($useragent,
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

