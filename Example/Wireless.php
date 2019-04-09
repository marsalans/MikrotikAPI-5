<!DOCTYPE html>
<!--[if IE 8]> <html lang="en" class="ie8 no-js"> <![endif]-->
<!--[if IE 9]> <html lang="en" class="ie9 no-js"> <![endif]-->
<!--[if !IE]><!-->
<html lang="en">
<!--<![endif]-->

<head>
	<meta charset="utf-8">
	<title>Mikrotik API - Wireless</title>
	
	<meta content="" name="keywords">
	<meta content="" name="author">
	
	<link href="https://fonts.googleapis.com/css?family=Open+Sans" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="style.css"/>
</head>
<body>

<h1>Mikrotik API - Wireless</h1>

<?php

include "./../Router.php";
$RouterApi = new MikrotikApi\Router();

if ($RouterApi->connect('192.168.0.1', 'user', 'password'))
{
   $RouterApi->write('/interface/wireless/registration-table/print', false);
   $RouterApi->write('=stats=');
   
   $data = $RouterApi->read(false);
   $array = $RouterApi->parseResponse($data);
   
   echo '<pre>'.print_r($array, 1).'</pre>';
  
   $RouterApi->disconnect();
}

?>

</body>
</html>