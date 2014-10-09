<?php
require("config.php");
require("db.class.php");
require("actions-web.php");

$db=new Database($dbServer,$dbUser,$dbPassword,$dbName);
$db->connect();

checklongrental();
checktoomany();
?>