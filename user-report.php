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
<h2>Report uzivatelov</h2>

<?php

error_reporting(-1);

require("config.php");

function report()
{
    global $dbserver, $dbuser, $dbpassword, $dbname;

    $mysqli = new mysqli($dbserver, $dbuser, $dbpassword, $dbname);

    if ($result = $mysqli->query("SELECT users.userId,userName,mail,number,privileges,userLimit,credit,count(bikeNum) as currently_rented from users left join limits on users.userId=limits.userId left join bikes on users.userId=bikes.currentUser left join credit on users.userId=credit.userId
	                group by userId order by userId ")) {
        echo '<table style="width:100%">';
#       echo '<caption>uzivatelia</caption>';

        echo "<tr>";
        $cols = array("userId","userName","mail","number","privileges","userLimit","credit","currently_rented");
        foreach ($cols as $col) {
            echo "<th>$col</th>";
        }
        echo "</tr>";
        foreach ($result as $row) {
                echo "<tr>";
            foreach ($cols as $col) {
                echo "<td>";
                    echo $row[$col];
                    echo "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "problem s sql dotazom";
        error("users bikes not fetched");
    }

}

report();

?>


</body></html>

