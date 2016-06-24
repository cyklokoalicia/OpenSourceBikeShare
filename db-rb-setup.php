<?php

require("external/rb.php");

R::setup('mysql:host='.$dbserver.';dbname='.$dbname, $dbuser, $dbpassword);
R::freeze(true);

// R::debug(true, 1); //select MODE 2 to see parameters filled in
// R::fancyDebug();   //since 4.2

// R::addDatabase('localdb', 'mysql:host='.$dbserver.';dbname='.$dbname, $dbuser, $dbpassword, true);
// R::begin();


?>
