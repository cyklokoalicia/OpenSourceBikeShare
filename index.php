<?php
require("config.php");
require("db.class.php");
require("actions-web.php");

$db=new Database($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo $systemname; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
<script type="text/javascript" src="js/viewportDetect.js"></script>
<script type="text/javascript" src="js/leaflet.js"></script>
<script type="text/javascript" src="js/L.Control.Sidebar.js"></script>
<script type="text/javascript" src="js/L.Control.Locate.min.js"></script>
<script type="text/javascript" src="js/translations.php"></script>
<script type="text/javascript" src="js/functions.js"></script>
<?php
if (isset($geojson))
   {
   foreach($geojson as $url)
      {
      echo '<link rel="points" type="application/json" href="',$url,'">'."\n";
      }
   }
?>
<?php if (date("m-d")=="04-01") echo '<script type="text/javascript" src="http://maps.stamen.com/js/tile.stamen.js?v1.3.0"></script>'; ?>
<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
<link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />
<link rel="stylesheet" type="text/css" href="css/leaflet.css" />
<link rel="stylesheet" type="text/css" href="css/L.Control.Sidebar.css" />
<link rel="stylesheet" type="text/css" href="css/L.Control.Locate.css" />
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" type="text/css" href="css/map.css" />
<script>
var maplat=<?php echo $systemlat; ?>;
var maplon=<?php echo $systemlong; ?>;
var mapzoom=<?php echo $systemzoom; ?>;
var standselected=0;
<?php
if (isset($_COOKIE["loguserid"])) {
    $userid = $db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
} else {
    $userid = 0;
}

if (isloggedin())
   {
   echo 'var loggedin=1;',"\n";
   echo 'var priv=',getprivileges($userid),";\n";
   }
else
   {
   echo 'var loggedin=0;',"\n";
   echo 'var priv=0;',"\n";
   }
if (iscreditenabled())
   {
   echo 'var creditsystem=1;',"\n";
   }
else
   {
   echo 'var creditsystem=0;',"\n";
   }
if (issmssystemenabled()==TRUE)
   {
   echo 'var sms=1;',"\n";
   }
else
   {
   echo 'var sms=0;',"\n";
   }
?>
</script>
<?php if (file_exists("analytics.php")) require("analytics.php"); ?>
</head>
<body>
<div id="map"></div>
<div id="sidebar"><div id="overlay"></div>
<div class="row">
   <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
   <ul class="list-inline">
      <li><a href="<?php echo $systemrules; ?>"><span class="glyphicon glyphicon-question-sign"></span> <?php echo _('Help'); ?></a></li>
<?php
if (isloggedin() AND getprivileges($userid)>0) echo '<li><a href="admin.php"><span class="glyphicon glyphicon-cog"></span> ',_('Admin'),'</a></li>';
if (isloggedin())
   {
   echo '<li><span class="glyphicon glyphicon-user"></span> <small>',getusername($userid),'</small>';
   if (iscreditenabled()) echo ' (<span id="usercredit" title="',_('Remaining credit'),'">',getusercredit($userid),'</span> ',getcreditcurrency(),' <button type="button" class="btn btn-success btn-xs" id="opencredit" title="',_('Add credit'),'"><span class="glyphicon glyphicon-plus"></span></button>)<span id="couponblock"><br /><span class="form-inline"><input type="text" class="form-control input-sm" id="coupon" placeholder="XXXXXX" /><button type="button" class="btn btn-primary btn-sm" id="validatecoupon" title="',_('Confirm coupon'),'"><span class="glyphicon glyphicon-plus"></span></button></span></span></li>';
   echo '<li><a href="command.php?action=logout" id="logout"><span class="glyphicon glyphicon-log-out"></span> ',_('Log out'),'</a></li>';
   }
?>
   </ul>
   </div>
   <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1">
   </div>
</div>
<div class="row">
   <div class="col-xs-11 col-sm-11 col-md-11 col-lg-11">
   <h1 class="pull-left"><?php echo $systemname; ?></h1>
   </div>
   <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1">
   </div>
</div>
<?php if (!isloggedin()): ?>
<div id="loginform">
<h1>Log in</h1>
<?php
if (isset($_GET["error"]) AND $_GET["error"]==1) echo '<div class="alert alert-danger" role="alert"><h3>',_('User / phone number or password incorrect! Please, try again.'),'</h3></div>';
elseif (isset($_GET["error"]) AND $_GET["error"]==2) echo '<div class="alert alert-danger" role="alert"><h3>',_('Session timed out! Please, log in again.'),'</h3></div>';
?>
      <form method="POST" action="command.php?action=login">
      <div class="row"><div class="col-lg-12">
            <label for="number" class="control-label"><?php if (issmssystemenabled()==TRUE) echo _('Phone number:'); else echo _('User number:'); ?></label> <input type="text" name="number" id="number" class="form-control" />
       </div></div>
       <div class="row"><div class="col-lg-12">
            <label for="password"><?php echo _('Password:'); ?> <small id="passwordresetblock">(<a id="resetpassword"><?php echo _('Forgotten? Reset password'); ?></a>)</small></label> <input type="password" name="password" id="password" class="form-control" />
       </div></div><br />
       <div class="row"><div class="col-lg-12">
         <button type="submit" id="register" class="btn btn-lg btn-block btn-primary"><?php echo _('Log in'); ?></button>
       </div></div>
         </form>
</div>
<?php endif; ?>
<h2 id="standname"><select id="stands"></select><span id="standcount"></span></h2>
<div id="standinfo"></div>
<div id="standphoto"></div>
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
         <button class="btn btn-primary btn-large col-lg-12" type="button" id="rent" title="<?php echo _('Choose bike number and rent bicycle. You will receive a code to unlock the bike and the new code to set.'); ?>"><span class="glyphicon glyphicon-log-out"></span> <?php echo _('Rent'); ?> <span class="bikenumber"></span></button>
  </div>
</div>
</div>
<div class="row"><div class="col-lg-12">
<br /></div></div>
<div id="rentedbikes"></div>
<div class="row">
   <div class="input-group">
   <div class="col-lg-12">
   <input type="text" name="notetext" id="notetext" class="form-control" placeholder="<?php echo _('Describe problem'); ?>">
   </div>
   </div>
</div>
<div class="row">
   <div class="btn-group bicycleactions">
   <div class="col-lg-12">
   <button type="button" class="btn btn-primary" id="return" title="<?php echo _('Return this bicycle to the selected stand.'); ?>"><span class="glyphicon glyphicon-log-in"></span> <?php echo _('Return bicycle'); ?> <span class="bikenumber"></span></button> (<?php echo _('and'); ?> <a href="#" id="note" title="<?php echo _('Use this link to open a text field to write in any issues with the bicycle you are returning (flat tire, chain stuck etc.).'); ?>"><?php echo _('report problem'); ?> <span class="glyphicon glyphicon-exclamation-sign"></span></a>)
   </div></div>
</div>

</div>
</body>
</html>
