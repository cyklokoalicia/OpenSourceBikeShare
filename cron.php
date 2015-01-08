<?php
require("config.php");
require("db.class.php");
require("actions-web.php");

$db=new Database($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();

checklongrental();
?>