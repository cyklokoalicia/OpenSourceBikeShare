<?php
require("config.php");
require("external/rb.php");
require("actions-web.php");

R::setup('mysql:host='.$dbserver.';dbname='.$dbname, $dbuser, $dbpassword);
R::freeze(true);

checklongrental();
