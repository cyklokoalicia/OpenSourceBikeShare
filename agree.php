<?php
    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
?>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="sk" lang="sk">
    <head><title>White Bikes</title>
	<meta http-equiv="Expire" content="now" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta name="description" content="White Bikes"/>
	<meta name="copyright" content="Copyright (c) 2014 Michal Maly"/>
	<meta name="author" content="Michal Maly"/>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
	<link href="style.css" type="text/css" rel="stylesheet" />
    </head>
<body>
<?php

	require('functions.php');
    error_reporting(0);

$userKey = $_GET["key"];

confirmUser($userKey);

?>
                    <br/>
                    <br/>
                    <br/>
		    Si si to uz ozaj podrobne precital? <a href="https://docs.google.com/document/d/1yEHbLEAU9waMiaxTqXFzZP0bLyRg7NMtN2dQUazro9o/edit">Navod Biele Bicykle</a>
		    <br/>
		    Have you really read that all? <a href="https://docs.google.com/document/d/1RBC4BOyZSaAeoTw4pIlUkWRFZSz16NR6glTAgZWvqTQ/edit">User Guide White Bikes</a>
		    <br/>
</body></html>
		        
