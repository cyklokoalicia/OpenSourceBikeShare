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
<h2>Last Usage Report</h2>

<?php

error_reporting(-1);

require_once 'vendor/autoload.php';
require_once "common.php";

function report()
{
	global $db;

    $result = $db->query("
        SELECT max(time) as lastUsage,history.bikeNum,coalesce(standName,userName) as current
        FROM `history` join bikes on bikes.bikenum=history.bikenum left join stands on bikes.currentStand=stands.standId left join users on bikes.currentUser=users.userid group by bikeNum order by lastusage asc
	");

    if ($result) {
        $data = $result->fetchAllAssoc();
    } else {
        echo "problem s sql dotazom";
        die("users bikes not fetched");
    }
	
	echo '<table style="width:50%">';
#	echo '<caption>last usage</caption>';
		
	echo "<tr>";
	$cols = array("bikeNum","lastUsage","current");
	foreach($cols as $col)
	{
		echo "<th>$col</th>";		
	} 
	echo "</tr>";
	 
	for($i=0; $i<count($data);$i++)
	{	
		echo "<tr>";
		foreach($cols as $col)      
        	{
                	echo "<td>";
			echo $data[$i][$col];
			echo "</td>";           
        	}
	echo "</tr>";
	}
 	echo "</table>";
}

report();

?>


</body></html>

