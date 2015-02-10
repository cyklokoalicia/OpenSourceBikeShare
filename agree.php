<?php
require("config.php");
require("db.class.php");
require('actions-web.php');

$db=new Database($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();
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
<script type="text/javascript" src="js/register.js"></script>
<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
<link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />
<link rel="stylesheet" type="text/css" href="css/bootstrapValidator.min.css" />
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
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>
<br />
<div class="container">
   <div class="page-header">
   <h1>Account activation</h1>
   </div>
<?php
$userKey=$_GET["key"];
confirmUser($userKey);
?>
<div class="alert alert-warning" role="alert">
<p>Registráciou potvrdzujem, že som si prečítal: <a href="https://docs.google.com/document/d/1yEHbLEAU9waMiaxTqXFzZP0bLyRg7NMtN2dQUazro9o/edit">Navod</a></p>
<p>By registering I confirm that I have read: <a href="https://docs.google.com/document/d/1RBC4BOyZSaAeoTw4pIlUkWRFZSz16NR6glTAgZWvqTQ/edit">User Guide</a></p>
</div>
   <div class="panel panel-default">
  <div class="panel-body">
    <i class="glyphicon glyphicon-copyright-mark"></i> <? echo date("Y"); ?> <a href="<?php echo $systemURL; ?>"><?php echo $systemname; ?></a>
  </div>
  <div class="panel-footer"><strong>Privacy policy:</strong> We will use your details for <?php echo $systemname; ?>-related activities only.</div>
   </div>

    </div><!-- /.container -->
</body>
</html>