<?php

require_once 'vendor/autoload.php';
require_once "actions-web.php";

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= $configuration->get('systemname'); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
<script type="text/javascript" src="js/viewportDetect.js"></script>
<script type="text/javascript" src="js/leaflet.js"></script>
<script type="text/javascript" src="js/L.Control.Sidebar.js"></script>
<script type="text/javascript" src="js/translations.php"></script>
<script type="text/javascript" src="js/functions.js"></script>
<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
<link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />
<link rel="stylesheet" type="text/css" href="css/leaflet.css" />
<link rel="stylesheet" type="text/css" href="css/L.Control.Sidebar.css" />
<link rel="stylesheet" type="text/css" href="css/map.css" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
<meta name="msapplication-TileColor" content="#da532c">
<meta name="theme-color" content="#ffffff">
<script>
var maplat=<?= $configuration->get('systemlat'); ?>;
var maplon=<?= $configuration->get('systemlong'); ?>;
var mapzoom=<?= $configuration->get('systemzoom'); ?>;
<?php
$userid = $auth->getUserId();

if ($configuration->get('cities') && $auth->isLoggedIn()) {
	$usercity = $user->findCity($userid);
}
if ($configuration->get('citiesGPS') && $auth->isLoggedIn()) {
    echo 'maplat=' . $configuration->get('citiesGPS')[$usercity][0] . ";\n";
    echo 'maplon=' . $configuration->get('citiesGPS')[$usercity][1] . ";\n";
}
?>
var standselected=0;
<?php
if ($auth->isLoggedIn()) {
    echo 'var loggedin=1;', "\n";
    echo 'var priv=', $user->findPrivileges($userid), ";\n";
} else {
    echo 'var loggedin=0;', "\n";
    echo 'var priv=0;', "\n";
}
if ($creditSystem->isEnabled()) {
    echo 'var creditsystem=1;', "\n";
} else {
    echo 'var creditsystem=0;', "\n";
}
if (issmssystemenabled() == true) {
    echo 'var sms=1;', "\n";
} else {
    echo 'var sms=0;', "\n";
}
?>
var freeTimeSeconds=<?= $configuration->get('watches')['freetime'] * 60; ?>; // and convert to seconds
var serverTimeSeconds=<?= time(); ?>; // using the server timestamp for time difference calculation
</script>
<?php if (file_exists('analytics.php')) {
    require 'analytics.php';
}
?>
</head>
<body>
<?php
if ($auth->isLoggedIn()) {
    echo '<div id="map"></div>';
} else {
    echo '<img src="img/wbsLogo.png" alt="White bikes - Biele bicykle" style="margin: auto; display: block; margin-top: 1em;" >';
}

?>
<div id="sidebar"><div id="overlay"></div>
<div class="row text-center" style="margin-top: 0.5em;">
   <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
   <ul class="list-inline">
      <li><a href="<?= $configuration->get('systemrules'); ?>" target="_blank"><span class="glyphicon glyphicon-question-sign"></span><?= _('Help'); ?></a></li>
<?php
if ($auth->isLoggedIn() && $user->findPrivileges($userid) > 0) {
    echo '<li><a href="admin.php"><span class="glyphicon glyphicon-cog"></span> ', _('Admin'), '</a></li>';
}

if ($auth->isLoggedIn()) {
    echo '<li><span class="glyphicon glyphicon-user"></span> <small>', $user->findUserName($userid), '</small>';
    if ($creditSystem->isEnabled()) {
        $userRemainingCredit = $creditSystem->getUserCredit($userid);
        echo ' (<span id="usercredit" title="', _('Remaining credit'), '">' . $userRemainingCredit . '</span> ' . $creditSystem->getCreditCurrency() . ' <button type="button" class="btn btn-success btn-xs" id="opencredit" title="', _('Add credit'), '"><span class="glyphicon glyphicon-plus"></span></button>)<span id="couponblock"><br /><span class="form-inline"><input type="text" class="form-control input-sm" id="coupon" placeholder="XXXXXX" /><button type="button" class="btn btn-primary btn-sm" id="validatecoupon" title="', _('Confirm coupon'), '"><span class="glyphicon glyphicon-plus"></span></button></span></span></li>';
    }
	if ($configuration->get('cities')) {
		echo '<li>','<select class="form-control input-sm" id="citychange" title="', _('My City'), '">';
		foreach ($configuration->get('cities') as $city) {
			if ($usercity == $city) echo '<option value="',$city,'" selected>';
			else echo '<option  value="',$city,'">';
			echo $city,'</option>';
		}
		echo '</select></li>';
	}
	
	echo '<li><a href="command.php?action=logout" id="logout"><span class="glyphicon glyphicon-log-out"></span> ', _('Log out'), '</a></li>';
}
?>
   </ul>
   </div>
   <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1">
   </div>
</div>
<?php if ($auth->isLoggedIn()): ?>
<div class="row">
   <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
   <h1 class="pull-left"><?= $configuration->get('systemname'); ?></h1>
   </div>
   <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1">
   </div>
</div>
<?php endif;?>
<?php if (!$auth->isLoggedIn()): ?>
<div id="loginform">
<h3>Log in</h3>
<?php
if (isset($_GET['error']) and $_GET['error'] == 1) {
    echo '<div class="alert alert-danger" role="alert"><h3>', _('User / phone number or password incorrect! Please, try again.'), '</h3></div>';
} elseif (isset($_GET['error']) and $_GET['error'] == 2) {
    echo '<div class="alert alert-danger" role="alert"><h3>', _('Session timed out! Please, log in again.'), '</h3></div>';
}

?>
      <form method="POST" action="command.php?action=login">
      <div class="row"><div class="col-lg-12">
            <label for="number" class="control-label"><?php if (issmssystemenabled()==TRUE) echo _('Phone number:'); else echo _('User number:'); ?></label> <input type="text" name="number" id="number" class="form-control" />
       </div></div>
       <div class="row"><div class="col-lg-12">
            <label for="password"><?= _('Password:'); ?> <small id="passwordresetblock">(<a id="resetpassword"><?= _('Forgotten? Reset password'); ?></a>)</small></label> <input type="password" name="password" id="password" class="form-control" />
       </div></div><br />
       <div class="row"><div class="col-lg-12">
         <button type="submit" id="register" class="btn btn-lg btn-block btn-primary"><?= _('Log in'); ?></button>
       </div></div>
         </form>
</div>
<div class="row">
<div class="col-lg-12" style="font-size:1.5em;padding-top:30px">
<ul class="list-unstyled text-center">
	<li><a href="http://wiki.whitebikes.info/index.php/Ako_to_funguje%3F" target="_blank" class="btn btn-info">Ako to funguje?</a></li>
	<li><a href="http://wiki.whitebikes.info/index.php/Ako_sa_zapoj%C3%ADm%3F" target="_blank" class="btn btn-info" style="margin-top: 0.5em">Ako sa zapojím?</a></li>
	<li><a href="http://wiki.whitebikes.info/index.php/Nie%C4%8Do_mi_nejde" target="_blank" class="btn btn-info" style="margin-top: 0.5em">Niečo mi nejde</a></li>
	<li><a href="http://wiki.whitebikes.info/index.php/Podrobn%C3%BD_manu%C3%A1l" target="_blank" class="btn btn-info" style="margin-top: 0.5em">Podrobný manuál</a></li>
</ul>

</div>
</div>
<?php else :?>
<h2 id="standname"><select id="stands"></select><span id="standcount"></span></h2>
<div id="standinfo"></div>
<div id="standbikes"></div>
<div class="row">
   <div class="col-lg-12">
   <div id="console">
   </div>
   </div>
</div>
<div class="row">
<div id="standactions" class="btn-group">
  <div class="col-lg-12">
         <button class="btn btn-primary" type="button" id="rent" title="<?= _('Choose bike number and rent bicycle. You will receive a code to unlock the bike and the new code to set.'); ?>"><span class="glyphicon glyphicon-log-out"></span> <?= _('Rent'); ?> <span class="bikenumber"></span></button>
  </div>
</div>
</div>
<div class="row"><div class="col-lg-12">
<br /></div></div>
<div id="rentedbikes"></div>
<div class="row">
   <div class="input-group">
   <div class="col-lg-12">
   <input type="text" name="notetext" id="notetext" class="form-control" placeholder="<?= _('Describe problem'); ?>">
   </div>
   </div>
</div>
<div class="row">
   <div class="btn-group bicycleactions">
   <div class="col-lg-12">
   <button type="button" class="btn btn-primary" id="return" title="<?= _('Return this bicycle to the selected stand.'); ?>"><span class="glyphicon glyphicon-log-in"></span> <?= _('Return bicycle'); ?> <span class="bikenumber"></span></button> (and <a href="#" id="note" title="<?= _('Use this link to open a text field to write in any issues with the bicycle you are returning (flat tire, chain stuck etc.).'); ?>"><?= _('report problem'); ?> <span class="glyphicon glyphicon-exclamation-sign"></span></a>)
   </div></div>
</div>

<div id="standphoto"></div>
<?php endif;?>
</div>
</body>
</html>
