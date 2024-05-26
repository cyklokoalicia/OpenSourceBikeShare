<?php
require("common.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title><?= $configuration->get('systemname'); ?> <?= _('registration'); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
<script type="text/javascript" src="js/bootstrapValidator.min.js"></script>
<script type="text/javascript" src="js/translations.php"></script>
<script type="text/javascript" src="js/register.js"></script>
<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
<link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />
<link rel="stylesheet" type="text/css" href="css/bootstrapValidator.min.css" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
<meta name="msapplication-TileColor" content="#da532c">
<meta name="theme-color" content="#ffffff">
<?php if (file_exists("analytics.php")) require("analytics.php"); ?>
</head>
<body>
    <!-- Fixed navbar -->
    <div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only"><?= _('Toggle navigation'); ?></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="<?= $configuration->get('systemURL'); ?>"><?= $configuration->get('systemname'); ?></a>
        </div>
        <div class="collapse navbar-collapse">
          <ul class="nav navbar-nav">
            <li><a href="<?= $configuration->get('systemURL'); ?>">Map</a></li>
            <li class="active"><a href="<?= $configuration->get('systemURL'); ?>register.php"><?= _('Registration'); ?></a></li>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>
<br />
    <div class="container">

      <div class="page-header">
            <h1><?= _('Registration'); ?></h1>
            <div id="console"></div>
            </div>

<?php if (issmssystemenabled()==TRUE): ?>
      <form class="container" id="step1">
       <h2><?= _('Step 1 - Confirm your phone number'); ?></h2>
         <div class="form-group">
            <label for="number" class="control-label"><?= _('Phone number:'); ?></label> <input type="text" name="number" id="number" class="form-control" />
         </div>
         <div class="alert alert-info"><?= _('You will receive SMS code to this phone number.'); ?></div>
         <button type="submit" id="validate" class="btn btn-primary"><?= _('Validate this phone number'); ?></button>
       </form>
      <form class="container" id="step2">
      <h2 id="step2title"><?= _('Step 2 - Create account'); ?></h2>
      <div class="form-group">
            <label for="smscode" class="control-label"><?= _('SMS code (received to your phone):'); ?></label> <input type="text" name="smscode" id="smscode" class="form-control" /></div>
<?php else: ?>
      <form class="container" id="step2">
      <h2 id="step2title"><?= _('Step 1 - Create account'); ?></h2>
<?php endif; ?>
            <div id="regonly">
         <div class="form-group">
            <label for="fullname"><?= _('Fullname:'); ?></label> <input type="text" name="fullname" id="fullname" class="form-control" placeholder="<?= _('Firstname Lastname'); ?>" /></div>
         <div class="form-group">
            <label for="useremail"><?= _('Email:'); ?></label> <input type="text" name="useremail" id="useremail" class="form-control" placeholder="email@domain.com" /></div>
            </div>
         <div class="form-group">
            <label for="password"><?= _('Password:'); ?></label> <input type="password" name="password" id="password" class="form-control" /></div>
         <div class="form-group">
            <label for="password2"><?= _('Password confirmation:'); ?></label> <input type="password" name="password2" id="password2" class="form-control" /></div>
         <input type="hidden" name="validatednumber" id="validatednumber" value="" />
         <input type="hidden" name="checkcode" id="checkcode" value="" />
         <input type="hidden" name="existing" id="existing" value="0" />
         <button type="submit" id="register" class="btn btn-primary"><?= _('Create account'); ?></button>
         </form>
   <br />
   <div class="panel panel-default">
  <div class="panel-body">
    <i class="glyphicon glyphicon-copyright-mark"></i> <? echo date("Y"); ?> <a href="<?= $configuration->get('systemURL'); ?>"><?= $configuration->get('systemname'); ?></a>
  </div>
  <div class="panel-footer"><strong><?= _('Privacy policy'); ?>:</strong> <?= _('We will use your details for'); echo $configuration->get('systemname'),'-'; echo _('related activities only'); ?>.</div>
   </div>

    </div><!-- /.container -->
</body>
</html>
