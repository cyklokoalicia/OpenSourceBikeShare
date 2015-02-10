<?php
/*
CSS bubbles from:
https://stackoverflow.com/questions/19400183/how-to-style-chat-bubble-in-iphone-classic-style-using-css-only
*/
$usenumber="";
if ($usenumber=="") exit('Please, edit this script and set $usenumber variable to number existing in the system.');
if (isset($_POST["text"]))
   {
   fopen("http://localhost/OpenSourceBikeShare/receive.php?sms_text=".urlencode($_POST["text"])."&sms_uuid=test&sender=".$usenumber."&receive_time=".urlencode(date("Y-m-d H:i:s")),"r");
   }

$sms=file_get_contents("loopback.log");
$lines=explode("\n",$sms);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Dummy phone (SMS loopback tester)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="../../css/bootstrap.min.css" />
<link rel="stylesheet" type="text/css" href="../../css/bootstrap-theme.min.css" />
<link rel="stylesheet" type="text/css" href="loopback.css" />
</head>
<body>
<div class="container">
<div class="commentArea col-xs-12 col-sm-12 col-md-12 col-lg-12">
<?php
$lines=array_slice($lines,-5);
foreach ($lines as $text)
   {
   $parts=explode("|~",urldecode($text));
   $parts[2]=wordwrap($parts[2],50, "<br />",TRUE);
   if ($parts[0]=="<") echo '<div class="bubbledRight"><em>',$parts[1],'</em><br />',$parts[2],'</div>';
   if ($parts[0]==">") echo '<div class="bubbledLeft"><em>',$parts[1],'</em><br />',$parts[2],'</div>';
   }
?>
   </div>
   <form method="post" id="message" action="phone.php">
   <input type="text" class="form-control" name="text"></textarea>
   <input type="submit" value="Send message" class="btn btn-primary">
   </form>
   <form action="phone.php">
   <input type="submit" value="Refresh" class="btn btn-success">
   </form>
</div>
</body>
</html>