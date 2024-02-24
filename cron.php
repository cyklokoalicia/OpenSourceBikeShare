<?php

require_once 'vendor/autoload.php';
require("config.php");
require("actions-web.php");

/**
 * @var \Bikeshare\Db\DbInterface
 */
$db=new \Bikeshare\Db\MysqliDb($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();

checklongrental();
?>