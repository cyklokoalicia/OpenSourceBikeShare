<?php

require("external/rb.php");

R::setup('mysql:host='.$dbserver.';dbname='.$dbname, $dbuser, $dbpassword);
R::freeze(true);
//R::fancyDebug();

R::addDatabase('localdb', 'mysql:host='.$dbserver.';dbname='.$dbname, $dbuser, $dbpassword, true);

?>