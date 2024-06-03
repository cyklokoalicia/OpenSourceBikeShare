<?php

require_once "common.php";

function response($message,$error=0,$log=1)
{
    global $configuration, $db, $user, $auth;
    if ($log == 1 and $message) {
        $userid = $auth->getUserId();
        $number = $user->findPhoneNumber($userid);
        logresult($number, $message);
        $db->commit();
    }
   echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>',$configuration->get('systemname'),'</title>';
   echo '<base href="' . $configuration->get('systemURL') . '" />';
   echo '<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />';
   echo '<link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />';
   echo '<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">';
   echo '<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">';
   echo '<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">';
   echo '<link rel="manifest" href="/site.webmanifest">';
   echo '<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">';
   echo '<meta name="msapplication-TileColor" content="#da532c">';
   echo '<meta name="theme-color" content="#ffffff">';
   if (file_exists("analytics.php")) require("analytics.php");
   echo '</head><body><div class="container">';
   if ($error)
      {
      echo '<div class="alert alert-danger" role="alert">',$message,'</div>';
      }
   else
      {
      echo '<div class="alert alert-success" role="alert">',$message,'</div>';
      }
   echo '</div></body></html>';
   exit;
}

function showrentform($userId,$bike)
{
    global $db, $configuration;

    $stand = $db->query("SELECT s.* FROM bikes b JOIN stands s ON b.currentStand=s.standId WHERE bikeNum=$bike")->fetchAssoc();

    $result = $db->query("SELECT note FROM notes WHERE bikeNum='$bike' AND deleted IS NULL ORDER BY time DESC");
    $note = '';
    while ($row = $result->fetchAssoc()) {
        $note .= $row['note'] . '; ';
    }
    $note = substr($note, 0, strlen($note) - 2);

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>',$configuration->get('systemname'),'</title>';
    echo '<base href="' . $configuration->get('systemURL') . '" />';
    echo '<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />';
    echo '<link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />';
    echo '<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">';
    echo '<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">';
    echo '<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">';
    echo '<link rel="manifest" href="/site.webmanifest">';
    echo '<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">';
    echo '<meta name="msapplication-TileColor" content="#da532c">';
    echo '<meta name="theme-color" content="#ffffff">';
    if (file_exists("analytics.php")) require("analytics.php");
    echo '</head><body><div class="container">';
    echo '<h3>'. _('Rent bike'). ' ' .$bike. ' '. _('on stand'). ' ' .$stand['standName'].'</h3>';
    if (!empty($note)) {
        echo '<div class="alert alert-warning">' . $note . '</div>';
    }
    echo '<form method="post" action="scan.php/rent/',$bike,'">';
    echo '<input type="hidden" name="rent" value="yes" />';
    echo '<div class="col-lg-12">
            <button class="btn btn-primary" type="submit" id="rent" title="'. _('Choose bike number and rent bicycle. You will receive a code to unlock the bike and the new code to set.'). '">'
                .'<span class="glyphicon glyphicon-log-out"></span>' . _('Rent') .' <span class="bikenumber"> </span>
            </button>
         </div>
    ';
    echo '</form>';
    echo '</div></body></html>';
    exit;
}

function unrecognizedqrcode()
{
   response("<h3>"._('Unrecognized QR code action. Try scanning the code again or report this to the system admins.')."</h3>",ERROR);
}
