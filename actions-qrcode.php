<?php
require("common.php");

function response($message,$error=0,$log=1)
{
   global $systemname,$systemURL;
   if ($log==1 AND $message)
      {
      if (isset($_COOKIE["loguserid"]))
         {
         $userid=$_COOKIE["loguserid"];
         }
      else $userid=0;
      $number=getphonenumber($userid);
      logresult($number,$message);
      }
   R::commit();
   echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>',$systemname,'</title>';
   echo '<base href="',$systemURL,'" />';
   echo '<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />';
   echo '<link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />';
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

function status($action,$result,$values=FALSE)
{
if ($action=='RENT')
   {
   if ($result==OK)
      {
      $message='<h3>'._('Bike').' '.$values->bikenum.': <span class="label label-primary">'._('Open with code').' '.$values->bike->currentcode.'.</span></h3>'._('Change code immediately to').' <span class="label label-default">'.$values->newcode.'</span><br />'._('(open, rotate metal part, set new code, rotate metal part back)').'.';
      if (isset($values->note)) $message.="<br />"._('Reported issue:')." <em>".$values->note."</em>";
      response($message);
      }
   elseif ($result==100)
      {
      response(_('You can not rent any bikes. Contact the admins to lift the ban.'),ERROR);
      }
   elseif ($result==101)
      {
      response(_('You can only rent')." ".sprintf(ngettext('%d bike','%d bikes',$values->userlimit),$values->userlimit)." "._('at once').".",ERROR);
      }
   elseif ($result==102)
      {
      response(_('You can only rent')." ".sprintf(ngettext('%d bike','%d bikes',$values->userlimit),$values->userlimit)." "._('at once and you have already rented')." ".$values->userlimit.".",ERROR);
      }
   elseif ($result==110)
      {
      response(_('Bike')." ".$values->bikenum." "._('is not rentable now, you have to rent bike')." ".$values->stacktopbike." "._('from this stand').".",ERROR);
      }
   elseif ($result==120)
      {
      response(_('You have already rented the bike').' '.$values->bikenum.'. '._('Code is').' <span class="label label-primary">'.$values->currentcode.'</span>. '._('Return bike by scanning QR code on a stand').'.',ERROR);
      }
   elseif ($result==121)
      {
      response(_('Bike')." ".$values->bikenum." "._('is already rented by someone else').".",ERROR);
      }
   elseif ($result==130)
      {
      response(_('You are below required credit')." ".$values->requiredcredit.getcreditcurrency().". "._('Please, recharge your credit.'),ERROR);
      }
   }
elseif ($action=='RETURN')
   {
   if ($result==OK)
      {
      $message='<h3>'._('Bike').' '.$values->bikenum.': <span class="label label-primary">'._('Lock with code').' '.$values->currentcode.'.</span></h3>';
      $message.='<br />'._('Please').', <strong>'._('rotate the lockpad to').' <span class="label label-default">0000</span></strong> '._('when leaving').'.';
      if (iscreditenabled() AND isset($values->creditchange)) $message.='<br />'._('Credit change').': -'.$values->creditchange.getcreditcurrency().'.';
      response($message);
      }
   elseif ($result==100)
      {
      response(_('You have no rented bikes currently.'),ERROR);
      }
   elseif ($result==101)
      {
      $message=_('You have').' '.$values->countrented.' '._('rented bikes currently. QR code return can be used only when 1 bike is rented. Please, use web');
      if ($connectors["sms"]) $message.=_(' or SMS');
      $message.=_(' to return the bikes.');
      response($message,ERROR);
      }
   }
response('Unhandled status '.$result.' in '.$action.' in file '.__FILE__.'.',ERROR);

}

function unrecognizedqrcode()
{
   response("<h3>"._('Unrecognized QR code action. Try scanning the code again or report this to the system admins.')."</h3>",ERROR);
}

?>