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
	global $dbserver, $dbuser, $dbpassword, $dbname;

	$mysqli = new mysqli($dbserver, $dbuser, $dbpassword, $dbname);

	if ($result = $mysqli->query("SELECT bikes.bikeNum,userName,standName,note FROM bikes left join users on
	        bikes.currentUser=users.userId left join (select * from notes
	        where deleted is null ) as notes on notes.bikeNum=bikes.BikeNum left
	        join stands on bikes.currentStand=stands.standId
	                order by standName,bikeNum LIMIT 100"))
        {
		while($row=$result->fetch_assoc())
		{
                echo $row["bikeNum"],"&nbsp;",$row["userName"],$row["standName"],"&nbsp;",$row["note"],"<br/>";
                }
	}
	else
	{
	    echo "problem s sql dotazom";
	    error("rented bikes not fetched");
	}

}

function reportStands()
{
	global $dbServer, $dbUser, $dbPassword, $dbName;
	
	$mysqli = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);

	if ($result = $mysqli->query("
SELECT stands.standName,note FROM stands join (select * from notes
	        where deleted is null ) as notes on notes.standId=stands.standId
	                order by standName,note LIMIT 100	")) 
        {
		$data = $result->fetch_all(MYSQLI_ASSOC);
	} 
	else 
	{
	    echo "problem s sql dotazom";
	    error("users bikes not fetched");
	}
	
	echo '<table style="width:50%">';
#	echo '<caption>last usage</caption>';
		
	echo "<tr>";
	$cols = array("standName","note");
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

reportStands();




?>
</body></html>

