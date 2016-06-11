<?php
require("config.php");
require("external/rb.php");
require('actions-web.php');

R::setup('mysql:host='.$dbserver.';dbname='.$dbname, $dbuser, $dbpassword);
R::freeze(true);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title><? echo $systemname; ?> <?php echo _('account activation'); ?></title>
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
            <span class="sr-only"><?php echo _('Toggle navigation'); ?></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="<?php echo $systemURL; ?>"><?php echo $systemname; ?></a>
        </div>
        <div class="collapse navbar-collapse">
          <ul class="nav navbar-nav">
            <li><a href="<?php echo $systemURL; ?>"><?php echo _('Map'); ?></a></li>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>
<br />
<div class="container">
   <div class="page-header">
   <h1><?php echo _('Account activation'); ?></h1>
   </div>
<?php
$userkey="";
if (isset($_GET["key"])) {
    $userkey=$_GET["key"];
}
confirmUser($userkey);
?>
<div class="alert alert-warning" role="alert">
<p><?php echo _('By registering I confirm that I have read:'); ?> <a href="<?php echo $systemrules; ?>"><?php echo _('User Guide'); ?></a></p>
</div>
   <div class="panel panel-default">
  <div class="panel-body">
    <i class="glyphicon glyphicon-copyright-mark"></i> <? echo date("Y"); ?> <a href="<?php echo $systemURL; ?>"><?php echo $systemname; ?></a>
  </div>
  <div class="panel-footer"><strong><?php echo _('Privacy policy:'); ?></strong> <?php echo _('We will use your details for');
    echo $systemname,'-';
    echo _('related activities only'); ?>.</div>
   </div>

    </div><!-- /.container -->
</body>
</html>
