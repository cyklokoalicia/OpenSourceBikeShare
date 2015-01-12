<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="sk" lang="sk">
    <head><title>Report</title>
	<meta http-equiv="Expire" content="now" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta name="description" content="DATANEST na mape"/>
	<meta name="copyright" content="Copyright (c) 2011 Michal Maly"/>
	<meta name="author" content="Michal Maly"/>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
	<link href="style.css" type="text/css" rel="stylesheet" />
    </head>
<body>
<h2>Report pouzitia</h2>

<?php

error_reporting(-1);

require("config.php");

function report()
{
	global $dbServer, $dbUser, $dbPassword, $dbName;
	
	$mysqli = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);

	if ($result = $mysqli->query("
	SELECT bikes.bikeNum,userName,standName,note
        FROM bikes left join users on bikes.currentUser=users.userId left join notes on notes.bikeNum=bikes.BikeNum left
        join stands on bikes.currentStand=stands.standId order by
        standName,bikeNum
        LIMIT 100")) {
		$rentedBikes = $result->fetch_all(MYSQLI_ASSOC);
	} else error("rented bikes not fetched");

	for($i=0; $i<count($rentedBikes);$i++)
	{
		echo $rentedBikes[$i]["bikeNum"],"&nbsp;",$rentedBikes[$i]["userName"],$rentedBikes[$i]["standName"],"&nbsp;",$rentedBikes[$i]["note"],"<br/>";
		
	}
}

report();




?>
</body></html>

