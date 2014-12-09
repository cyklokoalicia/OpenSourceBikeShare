<?php
require("config.php");
require("db.class.php");
require("actions-web.php");

$db=new Database($dbServer,$dbUser,$dbPassword,$dbName);
$db->connect();
echo '<span style="display:none;"></span>'; // 2014-10-02 weird bug: map not shown, when DOCTYPE tag included, but everything OK, if this line printed here
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><? echo $systemName; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
<script type="text/javascript" src="js/viewportDetect.js"></script>
<script type="text/javascript" src="js/leaflet.js"></script>
<script type="text/javascript" src="js/L.Control.Sidebar.js"></script>
<script type="text/javascript" src="js/functions.js"></script>
<? if (date("m-d")=="04-01") echo '<script type="text/javascript" src="http://maps.stamen.com/js/tile.stamen.js?v1.3.0"></script>'; ?>
<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
<link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />
<link rel="stylesheet" type="text/css" href="css/leaflet.css" />
<link rel="stylesheet" type="text/css" href="css/L.Control.Sidebar.css" />
<link rel="stylesheet" type="text/css" href="css/map.css" />
<script>
var maplat=<?php echo $systemLat; ?>;
var maplon=<?php echo $systemLong; ?>;
var mapzoom=<?php echo $systemZoom; ?>;
var standselected=0;
<?php
if (isloggedin())
   {
   echo 'var loggedin=1;',"\n";
   echo 'var priv=',getprivileges($_COOKIE["loguserid"]),";\n";
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
?>
</script>
<?php if (file_exists("analytics.php")) require("analytics.php"); ?>
</head>
<body>
<div id="map"></div>
<div id="sidebar">
<div class="row">
   <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
   <a href="<?php echo $systemrules; ?>"><span class="glyphicon glyphicon-question-sign"></span> Help</a>
   <h1><?php echo $systemName; ?></h1>
   </div>
   <div class="col-xs-5 col-sm-5 col-md-5 col-lg-5">
   <ul class="nav nav-pills">
   <?php
   if (isloggedin())
      {
      echo '<li><span class="label label-success"><em>',getusername($_COOKIE["loguserid"]),'</em></span>';
      if (iscreditenabled()) echo ' (<span id="usercredit" title="Remaning credit">',getusercredit($_COOKIE["loguserid"]),'</span>',getcreditcurrency(),')';
      echo '<br /><a href="command.php?action=logout" id="logout">Log out</a></li>';
      }
   ?>
   </ul>
   </div>
   <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1">
   </div>
</div>
<?php if (!isloggedin()): ?>
<div id="loginform">
<h1>Log in</h1>
<?php
if (isset($_GET["error"]) AND $_GET["error"]==1) echo '<div class="alert alert-danger" role="alert"><h3>Phone number or password incorrect! Please, try again.</h3></div>';
elseif (isset($_GET["error"]) AND $_GET["error"]==2) echo '<div class="alert alert-danger" role="alert"><h3>Session timed out! Please, log in again.</h3></div>';
?>
      <form method="POST" action="command.php?action=login">
      <div class="row"><div class="col-lg-12">
            <label for="number" class="control-label">Phone number:</label> <input type="text" name="number" id="number" class="form-control" placeholder="09XX 123 456" />
       </div></div>
       <div class="row"><div class="col-lg-12">
            <label for="password">Password:</label> <input type="password" name="password" id="password" class="form-control" />
       </div></div><br />
       <div class="row"><div class="col-lg-12">
         <button type="submit" id="register" class="btn btn-lg btn-block btn-primary">Log in</button>
       </div></div>
         </form>
</div>
<? endif; ?>
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
         <button class="btn btn-primary" type="button" id="rent" title="Choose bike number and rent bicycle. You will receive a code to unlock the bike and the new code to set."><span class="glyphicon glyphicon-log-out"></span> Rent <span class="bikenumber"></span></button>
  </div>
</div>
</div>
<div class="row"><div class="col-lg-12">
<br /></div></div>
<div id="rentedbikes"></div>
<div class="row">
   <div class="input-group">
   <div class="col-lg-12">
   <input type="text" name="notetext" id="notetext" class="form-control" placeholder="Describe problem">
   </div>
   </div>
</div>
<div class="row">
   <div class="btn-group bicycleactions">
   <div class="col-lg-12">
   <button type="button" class="btn btn-primary" id="return" title="Return this bicycle to the selected stand."><span class="glyphicon glyphicon-log-in"></span> Return bicycle <span class="bikenumber"></span></button> (and <a href="#" id="note" title="Use this link to open a text field to write in any issues with the bicycle you are returning (flat tire, chain stuck etc.).">report problem <span class="glyphicon glyphicon-exclamation-sign"></span></a>)
   </div></div>
</div>
<br /><div class="row">
  <div class="btn-group adminactions">
  <div class="col-lg-12">
  <div class="input-group">
         <input type="text" name="adminparam" id="adminparam" class="form-control">
         <span class="input-group-btn">
         <button class="btn btn-default" type="button" id="where" title="Display the bike stand location or name of person using it."><span class="glyphicon glyphicon-screenshot"></span> Where is?</button>
         </span>
  </div>
  </div>
</div>
</div>
<div class="row">
   <div class="btn-group adminactions">
   <div class="col-lg-12">
   <button type="button" id="revert" class="btn btn-default" title="Be careful! Revert accidentaly rented bike in case of mistake or misread bike number."><span class="glyphicon glyphicon-fast-backward"></span> Revert</button>
   <button type="button" id="last" class="btn btn-default" title="Display network usage (blank) or history of bike usage (number entered)."><span class="glyphicon glyphicon-stats"></span> Last usage</button>
   </div>
   </div>
</div>
</div>
</body>
</html>