<?php

require_once 'vendor/autoload.php';
require_once 'actions-web.php';

$auth->refreshSession();

$userid = $auth->getUserId();

if ($user->findPrivileges($userid)<=0) {
    $logger->error('User has no privileges to access this page', ['userid' => $userid]);
    exit(_('You need admin privileges to access this page.'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title><?= $configuration->get('systemname'); ?> <?= _('administration'); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="js/bootstrap.min.js"></script>
<script type="text/javascript" src="js/bootstrapValidator.min.js"></script>
<script type="text/javascript" src="js/translations.php"></script>
<script type="text/javascript" src="js/admin.js"></script>
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
<script>
<?php
if ($creditSystem->isEnabled()) {
    echo 'var creditenabled=1;' . PHP_EOL;
    echo 'var creditcurrency="' . $creditSystem->getCreditCurrency() . '"' . PHP_EOL;
    $minRequiredCredit = $creditSystem->getMinRequiredCredit();
} else {
    $minRequiredCredit = 0;
    echo 'var creditenabled=0;', "\n";
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
            <span class="sr-only"><?= _('Toggle navigation'); ?></span>
          </button>
          <a class="navbar-brand" href="<?= $configuration->get('systemURL'); ?>"><?= $configuration->get('systemname'); ?></a>
        </div>
        <div class="collapse navbar-collapse">
          <ul class="nav navbar-nav">
            <li><a href="<?= $configuration->get('systemURL'); ?>"><?= _('Map'); ?></a></li>
            <li class="active"><a href="<?= $configuration->get('systemURL'); ?>admin.php"><?= _('Admin'); ?></a></li>
<?php if ($auth->isLoggedIn()): ?>
            <li><a href="/logout" id="logout"><?= _('Log out'); ?></a></li>
<?php endif; ?>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>
<br />
    <div class="container">

      <div class="page-header">
            <h1><?= _('Administration'); ?></h1>
            </div>

<?php
if ($auth->isLoggedIn()):
?>
            <div role="tabpanel">

  <!-- Nav tabs -->
  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="active"><a href="#fleet" aria-controls="fleet" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span> <?= _('Fleet'); ?></a></li>
    <li role="presentation"><a href="#stands" aria-controls="stands" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-map-marker" aria-hidden="true"></span> <?= _('Stands'); ?></a></li>
    <li role="presentation"><a href="#users" aria-controls="users" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> <?= _('Users'); ?></a></li>
<?php
if ($creditSystem->isEnabled()):
?>
    <li role="presentation"><a href="#credit" aria-controls="credit" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-euro" aria-hidden="true"></span> <?= _('Credit system'); ?></a></li>
<?php endif; ?>
    <li role="presentation"><a href="#reports" aria-controls="reports" role="tab" data-toggle="tab"><span class="glyphicon glyphicon-stats" aria-hidden="true"></span> <?= _('Reports'); ?></a></li>
  </ul>

  <!-- Tab panes -->
  <div class="tab-content">
    <div role="tabpanel" class="tab-pane active" id="fleet">
      <div class="row">
      <div class="col-lg-12">
               <input type="text" name="adminparam" id="adminparam" class="form-control">
               <button class="btn btn-default" type="button" id="where" title="<?= _('Display the bike stand location or name of person using it.'); ?>"><span class="glyphicon glyphicon-screenshot"></span> <?= _('Where is?'); ?></button>
         <button type="button" id="revert" class="btn btn-default" title="<?= _('Be careful! Revert accidentaly rented bike in case of mistake or misread bike number.'); ?>"><span class="glyphicon glyphicon-fast-backward"></span> <?= _('Revert'); ?></button>
         <button type="button" id="last" class="btn btn-default" title="<?= _('Display network usage (blank) or history of bike usage (number entered).'); ?>"><span class="glyphicon glyphicon-stats"></span> <?= _('Last usage'); ?></button>
         <div id="fleetconsole"></div>
         </div>
      </div>

    </div>
    <div role="tabpanel" class="tab-pane" id="stands">
      <div class="row">
         <div class="col-lg-12">
         <!-- button type="button" id="stands" class="btn btn-default" title="Show stand situation."><span class="glyphicon glyphicon-map-marker"></span> <?= _('Stand situation'); ?></button -->
         <div id="standsconsole"></div>
         </div>
      </div>
    </div>
<?php
if ($creditSystem->isEnabled()):
?>
    <div role="tabpanel" class="tab-pane" id="credit">
      <div class="row">
         <div class="col-lg-12">
         <button type="button" id="listcoupons" class="btn btn-default" title="<?= _('Display existing coupons.'); ?>"><span class="glyphicon glyphicon-list-alt"></span> <?= _('List coupons'); ?></button>
         <button type="button" id="generatecoupons1" class="btn btn-success" title="<?= _('Generate new coupons.'); ?>"><span class="glyphicon glyphicon-plus"></span> <?= _('Generate'); echo ' ',$minRequiredCredit,$creditSystem->getCreditCurrency(),' '; echo _('coupons'); ?></button>
         <button type="button" id="generatecoupons2" class="btn btn-success" title="<?= _('Generate new coupons.'); ?>"><span class="glyphicon glyphicon-plus"></span> <?= _('Generate'); echo ' ',$minRequiredCredit*5,$creditSystem->getCreditCurrency(),' '; echo _('coupons'); ?></button>
         <button type="button" id="generatecoupons3" class="btn btn-success" title="<?= _('Generate new coupons.'); ?>"><span class="glyphicon glyphicon-plus"></span> <?= _('Generate'); echo ' ',$minRequiredCredit*10,$creditSystem->getCreditCurrency(),' '; echo _('coupons'); ?></button>
         <div id="creditconsole"></div>
         </div>
      </div>
    </div>
<?php endif; ?>
    <div role="tabpanel" class="tab-pane" id="users">
      <div class="row">
         <div class="col-lg-12">
         <button type="button" id="userlist" class="btn btn-default" title="<?= _('Show list of users.'); ?>"><span class="glyphicon glyphicon-user"></span> <?= _('User list'); ?></button>
         </div>
      </div>
      <form class="container" id="edituser">
         <div class="form-group"><label for="username" class="control-label"><?= _('Fullname:'); ?></label> <input type="text" name="username" id="username" class="form-control" /></div>
         <div class="form-group"><label for="email"><?= _('Email:'); ?></label> <input type="text" name="email" id="email" class="form-control" /></div>
<?php if ($configuration->get('connectors')["sms"]): ?>
         <div class="form-group"><label for="phone"><?= _('Phone number:'); ?></label> <input type="text" name="phone" id="phone" class="form-control" /></div>
<?php endif; ?>
         <div class="form-group"><label for="privileges"><?= _('Privileges:'); ?></label> <input type="text" name="privileges" id="privileges" class="form-control" /></div>
         <div class="form-group"><label for="limit"><?= _('Bike limit:'); ?></label> <input type="text" name="limit" id="limit" class="form-control" /></div>
         <input type="hidden" name="userid" id="userid" value="" />
         <button type="button" id="saveuser" class="btn btn-primary"><?= _('Save'); ?></button>
         or <button type="button" id="addcredit" class="btn btn-success"><?= _('Add'); echo ' ',$minRequiredCredit,$creditSystem->getCreditCurrency(); ?></button>
         <button type="button" id="addcredit2" class="btn btn-success"><?= _('Add'); echo ' ',$minRequiredCredit*5,$creditSystem->getCreditCurrency(); ?></button>
         <button type="button" id="addcredit3" class="btn btn-success"><?= _('Add'); echo ' ',$minRequiredCredit*10,$creditSystem->getCreditCurrency(); ?></button>
      </form>
      <div id="userconsole"></div>
    </div>
    <div role="tabpanel" class="tab-pane" id="reports">
      <div class="row">
         <div class="col-lg-12">
         <button type="button" id="usagestats" class="btn btn-default" title="<?= _('Show usage stats by day.'); ?>"><span class="glyphicon glyphicon-road"></span> <?= _('Daily stats'); ?></button>
         <button type="button" id="userstats" class="btn btn-default" title="<?= _('Show user stats.'); ?>"><span class="glyphicon glyphicon-road"></span> <?= _('User stats'); ?></button>
         <!-- button type="button" id="trips" class="btn btn-default" title="<?= _('Show history of stand to stand bike trips as lines.'); ?>"><span class="glyphicon glyphicon-road"></span> <?= _('Trips overlay'); ?></button -->
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
    <i class="glyphicon glyphicon-copyright-mark"></i> <? echo date("Y"); ?> <a href="<?= $configuration->get('systemURL'); ?>"><?= $configuration->get('systemname'); ?></a>
  </div>
  <div class="panel-footer"><strong><?= _('Privacy policy:'); ?></strong> <?= _('We will use your details for'); echo $configuration->get('systemname'),'-'; echo _('related activities only'); ?>.</div>
   </div>

    </div><!-- /.container -->
</body>
</html>
