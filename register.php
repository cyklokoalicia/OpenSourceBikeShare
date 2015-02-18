<?php
require("config.php");
require("db.class.php");
require("common.php");
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
            <li class="active"><a href="<?php echo $systemURL; ?>register.php">Registration</a></li>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>
<br />
    <div class="container">

      <div class="page-header">
            <h1>Registration</h1>
            <div id="console"></div>
            </div>

<?php if (issmssystemenabled()==TRUE): ?>
      <form class="container" id="step1">
       <h2>Step 1 - Confirm your phone number</h2>
         <div class="form-group">
            <label for="number" class="control-label">Phone number:</label> <input type="text" name="number" id="number" class="form-control" placeholder="09XX 123 456" />
         </div>
         <div class="alert alert-info">You will receive SMS code to this phone number.</div>
         <button type="submit" id="validate" class="btn btn-primary">Validate this phone number</button>
       </form>
      <form class="container" id="step2">
      <h2 id="step2title">Step 2 - Create account</h2>
      <div class="form-group">
            <label for="smscode" class="control-label">SMS code (received to your phone):</label> <input type="text" name="smscode" id="smscode" class="form-control" /></div>
<?php else: ?>
      <form class="container" id="step2">
      <h2 id="step2title">Step 1 - Create account</h2>
         <input type="hidden" name="validatednumber" id="validatednumber" value="" />
         <input type="hidden" name="checkcode" id="checkcode" value="" />
         <input type="hidden" name="existing" id="existing" value="0" />
<?php endif; ?>
            <div id="regonly">
         <div class="form-group">
            <label for="fullname">Fullname:</label> <input type="text" name="fullname" id="fullname" class="form-control" placeholder="Firstname Lastname" /></div>
         <div class="form-group">
            <label for="email">Email:</label> <input type="text" name="email" id="email" class="form-control" placeholder="email@domain.com" /></div>
            </div>
         <div class="form-group">
            <label for="password">Password:</label> <input type="password" name="password" id="password" class="form-control" /></div>
         <div class="form-group">
            <label for="password2">Password confirmation:</label> <input type="password" name="password2" id="password2" class="form-control" /></div>
         <button type="submit" id="register" class="btn btn-primary">Create account</button>
         </form>
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