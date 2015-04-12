<?php
require("config.php");
require("db.class.php");
require('actions-web.php');

$db=new Database($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();

checksession();
if (getprivileges($_COOKIE["loguserid"])<=0) exit('You need admin privileges to access this page.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title><? echo $systemname; ?> registration</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
<script type="text/javascript" src="js/bootstrapValidator.min.js"></script>
<script type="text/javascript" src="js/admin.js"></script>
<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
<link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />
<link rel="stylesheet" type="text/css" href="css/bootstrapValidator.min.css" />
<?php if (file_exists("analytics.php")) require("analytics.php"); ?>
<script>
<?php
if (iscreditenabled())
   {
   echo 'var creditenabled=1;',"\n";
   echo 'var creditcurrency="',$credit["currency"],'"',";\n";
   }
else
   {
   echo 'var creditenabled=0;',"\n";
   }
?>
</script>
</head>
<body>
    <!-- Fixed navbar -->
    <div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="<?php echo $systemURL; ?>"><?php echo $systemname; ?></a>
        </div>
        <div class="collapse navbar-collapse">
          <ul class="nav navbar-nav">
            <li><a href="<?php echo $systemURL; ?>">Map</a></li>
            <li class="active"><a href="<?php echo $systemURL; ?>admin.php">Admin</a></li>
<?php if (isloggedin()): ?>
            <li><a href="command.php?action=logout" id="logout">Log out</a></li>
<?php endif; ?>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>
<br />
    <div class="container">

      <div class="page-header">
            <h1>Administration</h1>
            </div>

<?php
if (isloggedin()):
?>
            <div role="tabpanel">

  <!-- Nav tabs -->
  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="active"><a href="#fleet" aria-controls="fleet" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span> Fleet</a></li>
    <li role="presentation"><a href="#stands" aria-controls="stands" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-map-marker" aria-hidden="true"></span> Stands</a></li>
    <li role="presentation"><a href="#users" aria-controls="users" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> Users</a></li>
    <li role="presentation"><a href="#reports" aria-controls="reports" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-stats" aria-hidden="true"></span> Reports</a></li>
  </ul>

  <!-- Tab panes -->
  <div class="tab-content">
    <div role="tabpanel" class="tab-pane active" id="fleet">
      <div class="row">
      <div class="col-lg-12">
               <input type="text" name="adminparam" id="adminparam" class="form-control">
               <button class="btn btn-default" type="button" id="where" title="Display the bike stand location or name of person using it."><span class="glyphicon glyphicon-screenshot"></span> Where is?</button>
         <button type="button" id="revert" class="btn btn-default" title="Be careful! Revert accidentaly rented bike in case of mistake or misread bike number."><span class="glyphicon glyphicon-fast-backward"></span> Revert</button>
         <button type="button" id="last" class="btn btn-default" title="Display network usage (blank) or history of bike usage (number entered)."><span class="glyphicon glyphicon-stats"></span> Last usage</button>
         <div id="fleetconsole"></div>
         </div>
      </div>

    </div>
    <div role="tabpanel" class="tab-pane" id="stands">
      <div class="row">
         <div class="col-lg-12">
         <!-- button type="button" id="stands" class="btn btn-default" title="Show stand situation."><span class="glyphicon glyphicon-map-marker"></span> Stand situation</button -->
         <div id="standsconsole"></div>
         </div>
      </div>
    </div>
    <div role="tabpanel" class="tab-pane" id="users">
      <div class="row">
         <div class="col-lg-12">
         <button type="button" id="userlist" class="btn btn-default" title="Show list o users."><span class="glyphicon glyphicon-user"></span> User list</button>
         </div>
      </div>
      <form class="container" id="edituser">
         <div class="form-group"><label for="username" class="control-label">Fullname:</label> <input type="text" name="username" id="username" class="form-control" /></div>
         <div class="form-group"><label for="email">Email:</label> <input type="text" name="email" id="email" class="form-control" /></div>
<?php if ($connectors["sms"]): ?>
         <div class="form-group"><label for="phone">Phone number:</label> <input type="text" name="phone" id="phone" class="form-control" /></div>
<? endif; ?>
         <div class="form-group"><label for="privileges">Privileges:</label> <input type="text" name="privileges" id="privileges" class="form-control" /></div>
         <div class="form-group"><label for="limit">Bike limit:</label> <input type="text" name="limit" id="limit" class="form-control" /></div>
         <input type="hidden" name="userid" id="userid" value="" />
         <button type="submit" id="saveuser" class="btn btn-primary">Save</button>
         <?php if (iscreditenabled()):
            $requiredcredit=$credit["min"]+$credit["rent"]+$credit["longrental"];
         ?>
         or <button type="submit" id="addcredit" class="btn btn-success">Add <?php echo $requiredcredit,$credit["currency"]; ?></button>
         <button type="submit" id="addcredit2" class="btn btn-success">Add <?php echo $requiredcredit*5,$credit["currency"]; ?></button>
         <button type="submit" id="addcredit3" class="btn btn-success">Add <?php echo $requiredcredit*10,$credit["currency"]; ?></button>
         <? endif; ?>
      </form>
      <div id="userconsole"></div>
    </div>
    <div role="tabpanel" class="tab-pane" id="reports">
      <div class="row">
         <div class="col-lg-12">
         <button type="button" id="userstats" class="btn btn-default" title="Show user stats."><span class="glyphicon glyphicon-road"></span> User stats</button>
         <!-- button type="button" id="trips" class="btn btn-default" title="Show history of stand to stand bike trips as lines."><span class="glyphicon glyphicon-road"></span> Trips overlay</button -->
         <div id="reportsconsole"></div>
         </div>
      </div>
    </div>
  </div>

   </div>

<?php endif; ?>

            <br />
   <div class="panel panel-default">
  <div class="panel-body">
    <i class="glyphicon glyphicon-copyright-mark"></i> <? echo date("Y"); ?> <a href="<?php echo $systemURL; ?>"><?php echo $systemname; ?></a>
  </div>
  <div class="panel-footer"><strong>Privacy policy:</strong> We will use your details for <?php echo $systemname; ?>-related activities only.</div>
   </div>

    </div><!-- /.container -->
</body>
</html>