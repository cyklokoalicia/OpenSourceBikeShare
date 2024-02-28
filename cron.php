<?php

use BikeShare\Db\DbInterface;
use BikeShare\Db\MysqliDb;

require_once 'vendor/autoload.php';
require("config.php");
require("actions-web.php");

/**
 * @var DbInterface $db
 */
$db=new MysqliDb($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();

checklongrental();
?>