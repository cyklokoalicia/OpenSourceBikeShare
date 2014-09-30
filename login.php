<?php
require("config.php");
require("db.class.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title><? echo $systemName; ?> login</title>
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
          <a class="navbar-brand" href="<?php echo $systemURL; ?>"><?php echo $systemName; ?></a>
        </div>
        <div class="collapse navbar-collapse">
          <ul class="nav navbar-nav">
            <li><a href="<?php echo $systemURL; ?>">Map</a></li>
            <li class="active"><a href="<?php echo $systemURL; ?>/login.php">Log in</a></li>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>
<br />
    <div class="container">

      <div class="page-header">
            <h1>Log in</h1>
            </div>
<?php
if (isset($_GET["error"]) AND $_GET["error"]==1) echo '<div class="alert alert-danger" role="alert"><h3>Phone number or password incorrect! Please, try again.</h3></div>';
elseif (isset($_GET["error"]) AND $_GET["error"]==2) echo '<div class="alert alert-danger" role="alert"><h3>Session timed out! Please, log in again.</h3></div>';
?>
      <form class="container" method="POST" action="command.php?action=login">
         <div class="form-group">
            <label for="number" class="control-label">Phone number:</label> <input type="text" name="number" id="number" class="form-control" placeholder="09XX 123 456" /></div>
         <div class="form-group">
            <label for="fullname">Password:</label> <input type="password" name="password" id="password" class="form-control" /></div>
         <button type="submit" id="register" class="btn btn-lg btn-block btn-primary">Log in</button>
         </form>
   <br />
   <div class="panel panel-default">
  <div class="panel-body">
    <i class="glyphicon glyphicon-copyright-mark"></i> <? echo date("Y"); ?> <a href="<?php echo $systemURL; ?>"><?php echo $systemName; ?></a>
  </div>
  <div class="panel-footer"><strong>Privacy policy:</strong> We will use your details for <?php echo $systemName; ?>-related activities only.</div>
   </div>

    </div><!-- /.container -->
</body>
</html>