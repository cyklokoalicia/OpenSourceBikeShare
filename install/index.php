<?php
//@TODO ANaLYTICS!!!

$configfilename="../config.php.example";
require($configfilename);
require("../db.class.php");
require("../external/htmlpurifier/HTMLPurifier.standalone.php");
$htmlpurconfig=HTMLPurifier_Config::createDefault();
$purifier=new HTMLPurifier($htmlpurconfig);
$purifier->purify($_GET);
$purifier->purify($_POST);
$purifier->purify($_COOKIE);
$purifier->purify($_FILES);
$purifier->purify($_SERVER);

function changeconfigvalue($configvar, $postvar)
{
    global $configfile;
    $lineno=array_filter($configfile, function ($el) use ($configvar) {
        return (strpos($el, $configvar)===1); // variable name should always start on line at 1st position (2nd character)
    });
    $key=array_keys($lineno);
    $lineno=$key[0];
    $comment=explode('//', $configfile[$lineno]);
    $configfile[$lineno]='$'.$configvar.'="'.$postvar.'";';
    if (isset($comment[1])) {
        $configfile[$lineno].=" // ".trim($comment[1]);
    }
    $configfile[$lineno].="\n";
}

function error($message)
{
    global $db,$error;
    echo '<div class="alert alert-danger" role="alert">'.$message.'</div>';
    $error=1;
}

function return_bytes($val)
{
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch ($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}
$step=$_GET["step"];
if (!$step) {
    $step=0;
}

if ($step==5) {
    $uploads=array();
    $maxsize=return_bytes(ini_get('post_max_size'));
    foreach ($_FILES["standphoto"]["size"] as $standid => $size) {
        if ($size>$maxsize) {
            $uploads[$standid]["errorsize"]=1;
        }
        if ($_FILES["standphoto"]["type"][$standid]<>"image/jpeg" and $_FILES["standphoto"]["type"][$standid]<>"image/pjpeg" and $_FILES["standphoto"]["type"][$standid]<>"image/png" and $_FILES["standphoto"]["type"][$standid]<>"image/gif" and $_FILES["standphoto"]["type"][$standid]<>"") {
            $uploads[$standid]["errortype"]=1;
        }
        if ($_FILES["standphoto"]["name"][$standid] and !isset($uploads[$standid]["errorsize"]) and !isset($uploads[$standid]["errortype"])) {
            move_uploaded_file($_FILES["standphoto"]["tmp_name"][$standid], '../img/uploads/'.$_FILES["standphoto"]["name"][$standid]);
            $uploads[$standid]["filename"]=$systemURL."img/uploads/".$_FILES["standphoto"]["name"][$standid];
        }
    }
}

$files=scandir("../connectors/");
$files=array_diff($files, array('..','.','loopback','smsGateway.me.class.php'));
foreach ($files as $key => $value) {
    if (strpos($value, "disabled.php")!==false) {
        unset($files[$key]);
    } else {
        $files[$key]=str_replace(".php", "", $value);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title><?php echo $systemname; ?> <?php echo _('Installation'); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="../js/bootstrap.min.js"></script>
<script type="text/javascript" src="../js/bootstrapValidator.min.js"></script>
<script type="text/javascript" src="../js/leaflet.js"></script>
<?php if (file_exists("js/step".$step.".js")) {
    echo '<script type="text/javascript" src="js/step',$step,'.js"></script>';
} ?>
<link rel="stylesheet" type="text/css" href="../css/bootstrap.min.css" />
<link rel="stylesheet" type="text/css" href="../css/bootstrap-theme.min.css" />
<link rel="stylesheet" type="text/css" href="../css/bootstrapValidator.min.css" />
<link rel="stylesheet" type="text/css" href="../css/leaflet.css" />
<link rel="stylesheet" type="text/css" href="install.css" />
</head>
<body>
    <!-- Fixed navbar -->
    <div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only"><?php echo _('Toggle navigation'); ?></span>
          </button>
          <a class="navbar-brand" href="<?php echo $systemURL; ?>"><?php echo $systemname; ?></a>
        </div>
      </div>
    </div>
<br />
    <div class="container">

      <div class="page-header">
            <h1><?php echo _('Installation'); ?> <span class="label label-default"><?php echo _('Step');
            echo " ",$step; ?></span> <?php echo _('out of'); ?> <span class="label label-default">6</span></h1>
            </div>
<?php if (!$step) : ?>
       <h2><?php echo _('System requirements check'); ?></h2>
      <form class="container" method="post" action="index.php?step=1">
<?php
$check["phpversion"]=explode("-", phpversion());
$check["phpversion"]=$check["phpversion"][0];
$check["hash"]=array_search("hash", get_loaded_extensions());
$check["mysqli"]=array_search("mysqli", get_loaded_extensions());
$check["gettext"]=array_search("gettext", get_loaded_extensions());
$check["json"]=array_search("json", get_loaded_extensions());
$check["config"]=is_writable($configfilename);
$check["uploads"]=is_writable("../img/uploads");
$check["purifier"]=is_writable("../external/htmlpurifier/standalone/HTMLPurifier/DefinitionCache/Serializer");

$error=0;
if (version_compare($check["phpversion"], "5")==-1) {
    error(_('Only PHP 5.0+ supported. You are using version').' '.$check["phpversion"].'. '._('Base requirement').'.');
}
if (!$check["hash"]) {
    error(_('Hash module not loaded! Compile or load hash module into PHP. Required for password encryption.'));
}
if (!$check["mysqli"]) {
    error(_('MySQLi / MariaDB module not loaded! Compile or load MySQL module into PHP. Required for database connections.'));
}
if (!$check["gettext"]) {
    error(_('gettext module not loaded! Compile or load gettext module into PHP. Required for translations.'));
}
if (!$check["json"]) {
    error(_('JSON module not loaded! Compile or load JSON module into PHP. Required for AJAX (PHP<->JS communication).'));
}
if ($check["uploads"]===false) {
    error('img/uploads '._('directory is not writable! Set permissions (chmod it) to 777. Required for uploads to work.'));
}
if ($check["config"]===false) {
    error($configfilename.' '._('is not writable! Set permissions (chmod it) to 777. Required for settings configuration values during the install process.'));
}
if ($check["purifier"]===false) {
    error('external/htmlpurifier/standalone/HTMLPurifier/DefinitionCache/Serializer '._('directory is not writable! Set permissions (chmod it) to 777. Required for security purposes.'));
}
if (!$error) {
    echo '<div class="alert alert-success" role="alert">'._('All fine.').'</div>';
    echo '<button type="submit" class="btn btn-primary">'._('Continue').'</button> '._('to step').' 1';
}
?>
         </form>
<?php endif; ?>
<?php if ($step==1) : ?>
       <h2><?php echo _('Set basic system and database options'); ?></h2>
      <form class="container" method="post" action="index.php?step=2">
      <fieldset><legend><?php echo _('System'); ?></legend>
         <div class="form-group"><label for="systemname" class="control-label"><?php echo _('System name:'); ?></label> <input type="text" name="systemname" id="systemname" class="form-control" /></div>
         <div class="row"><div class="col-lg-6">
         <div class="form-group"><label for="systemlat" class="control-label"><?php echo _('System latitude center point:'); ?></label> <input type="text" name="systemlat" id="systemlat" class="form-control" /></div>
         <div class="form-group"><label for="systemlong" class="control-label"><?php echo _('System longitude center point:'); ?></label> <input type="text" name="systemlong" id="systemlong" class="form-control" /></div>
         </div><div class="col-lg-6">
         <div id="map"></div>
         </div></div>
         <div class="form-group"><label for="systemrules" class="control-label"><?php echo _('System rules URL:'); ?></label> <input type="text" name="systemrules" id="systemrules" class="form-control" /></div>
         <div class="form-group"><label for="smsconnector" class="control-label"><?php echo _('SMS system connector:'); ?></label>
            <select class="form-control" name="smsconnector" id="smsconnector">
            <option value=""><?php echo _('Disable SMS system'); ?></option>
            <?php foreach ($files as $value) {
                echo '<option value="',$value,'">',$value,'</option>';
} ?>
            </select></div>
         <div class="form-group" id="countrycodeblock"><label for="countrycode" class="control-label"><?php echo _('International dialing code (no plus, no zeroes):'); ?></label> <input type="text" name="countrycode" id="countrycode" class="form-control" /></div>
      </fieldset>
      <fieldset><legend><?php echo _('Database'); ?></legend>
         <div class="form-group"><label for="dbserver" class="control-label"><?php echo _('Database server:'); ?></label> <input type="text" name="dbserver" id="dbserver" class="form-control" /></div>
         <div class="form-group"><label for="dbuser"><?php echo _('Database user:'); ?></label> <input type="text" name="dbuser" id="dbuser" class="form-control" /></div>
         <div class="form-group"><label for="dbpassword"><?php echo _('Database password:'); ?></label> <input type="password" name="dbpassword" id="dbpassword" class="form-control" /></div>
         <div class="form-group"><label for="dbname"><?php echo _('Database name:'); ?></label> <input type="text" name="dbname" id="dbname" class="form-control" /></div>
      </fieldset>
         <button type="submit" id="register" class="btn btn-primary"><?php echo _('Create database'); ?></button> <?php echo _('and continue to step'); ?> 2
         </form>
<?php endif; ?>
<?php if ($step==2) :
    $systemURL=$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
    $installpos=strpos($systemURL, "install/");
    $systemURL="http://".substr($systemURL, 0, $installpos);
    $configfile=file($configfilename);
    changeconfigvalue('systemname', $_POST["systemname"]);
    changeconfigvalue('systemURL', $systemURL);
    changeconfigvalue('systemlat', $_POST["systemlat"]);
    changeconfigvalue('systemlong', $_POST["systemlong"]);
    changeconfigvalue('systemrules', $_POST["systemrules"]);
    changeconfigvalue('dbserver', $_POST["dbserver"]);
    changeconfigvalue('dbuser', $_POST["dbuser"]);
    changeconfigvalue('dbpassword', $_POST["dbpassword"]);
    changeconfigvalue('dbname', $_POST["dbname"]);
    changeconfigvalue('connectors["sms"]', $_POST["smsconnector"]);
    if ($_POST["smsconnector"]) {
        changeconfigvalue('countrycode', $_POST["countrycode"]);
    } else {
        changeconfigvalue('countrycode', "");
    }
    $newconfig=implode($configfile);
    file_put_contents($configfilename, $newconfig);
    $db=new Database($_POST["dbserver"], $_POST["dbuser"], $_POST["dbpassword"], $_POST["dbname"]);
    $db->connect();
    $sql=file_get_contents("../create-database.sql");
    $sql=explode(";", $sql);
    foreach ($sql as $value) {
        $value=trim($value);
        if (strpos("--", $value)===false) {
            $result=R::exec($value);
           //echo $value,'<br />';
        }
    }
    require($configfilename);
?>
       <h2><?php echo _('Create admin user'); ?></h2>
        <?php echo '<div class="alert alert-success" role="alert">',_('Config file values set and database created.'),'</div>'; ?>
      <form class="container" method="post" action="index.php?step=3">
         <div class="form-group"><label for="username" class="control-label"><?php echo _('Fullname:'); ?></label> <input type="text" name="username" id="username" class="form-control" /></div>
         <div class="form-group"><label for="password"><?php echo _('Password:'); ?></label> <input type="text" name="password" id="password" class="form-control" /></div>
         <div class="form-group"><label for="email"><?php echo _('Email:'); ?></label> <input type="text" name="email" id="email" class="form-control" /></div>
<?php if ($connectors["sms"]) : ?>
         <div class="form-group"><label for="phone"><?php echo _('Phone number:'); ?></label> <input type="text" name="phone" id="phone" class="form-control" /></div>
<?php endif; ?>
         <button type="submit" id="register" class="btn btn-primary"><?php echo _('Create admin user'); ?></button> <?php echo _('and continue to step'); ?> 3
         </form>
<?php endif; ?>
<?php if ($step==3) :
    $db=new Database($dbserver, $dbuser, $dbpassword, $dbname);
    $db->connect();
    R::exec("REPLACE INTO users SET userName=?, password=SHA2(?, 512),mail=?, number=?, privileges=7", [
                   $_POST["username"], $_POST["password"], $_POST["email"], $_POST["phone"]])

    $userid= R::getInsertID();
    if (!$connectors["sms"]) {
        $result=R::exec("UPDATE users SET number=:userid WHERE id=:userid", [':userid' => $userid]);
    }
    $result=R::exec("REPLACE INTO limits SET userId=':userid', userLimit='100'", [':userid' => $userid]);
    $db->conn->commit();
?>
      <h2><?php echo _('Create bicycles and stands'); ?></h2>
        <?php echo '<div class="alert alert-success" role="alert">',_('Admin user'),' ',$_POST["username"],' ',_('created with password:'),' ',$_POST["password"];
        if (!$connectors["sms"]) {
            echo '. ',_('Use number'),' <span class="label label-default">',$userid,'</span> ',_('for login'),'.';

        } echo '</div>'; ?>
      <form class="container" method="post" action="index.php?step=4">
         <div class="form-group"><label for="bicyclestotal" class="control-label"><?php echo _('How many bicycles to create:'); ?></label> <input type="text" name="bicyclestotal" id="bicyclestotal" class="form-control" /></div>
         <div class="form-group"><label for="stands" class="control-label"><?php echo _('Stands to create (comma separated):'); ?></label> <input type="text" name="stands" id="stands" class="form-control" /></div>
         <button type="submit" id="register" class="btn btn-primary"><?php echo _('Create bicycles and stands'); ?></button> <?php echo _('and continue to step'); ?> 4
         </form>

<?php endif; ?>
<?php if ($step==4) :
    R::setup("mysql:host=$dbserver;dbname=$dbname", $dbuser, $dbpassword);
    R::begin()
    $stands=explode(",", $_POST["stands"]);
    foreach ($stands as $stand) {
        $stand=trim(strtoupper($stand));
        $result=R::exec("REPLACE INTO stands SET standName=:stand, serviceTag=0, placeName=:stand", [':stand' => $stands]);
    }
    for ($i=1; $i<=$_POST["bicyclestotal"]; $i++) {
        $code=sprintf("%04d", rand(100, 9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).
        $result=R::exec("REPLACE INTO bikes SET bikeNum=:bikeNum, currentStand=1, currentCode=:code", [':bikeNum' => $i, ':code' => $code]);
    }
    R::commit()
?>
      <h2><?php echo _('Set up stands'); ?></h2>
<?php
echo '<div class="alert alert-success" role="alert">',sprintf(ngettext('%d bicycle', '%d bicycles', $_POST["bicyclestotal"]), $_POST["bicyclestotal"]),' ',_('and the following stands created'),': ',strtoupper(str_replace(",", ", ", $_POST["stands"])),'.</div>';
echo '<div class="alert alert-info" role="alert"><form class="container" method="get" action="generate.php"><button type="submit" id="generate" class="btn btn-primary">',_('Download QR codes'),'</button> ',_('for the bikes and the stands for printing or laser engraving'),'.</form></div>';
if ($connectors["sms"]) {
    echo '<div class="alert alert-warning" role="alert">',_('Note'),': <span class="label label-default">',_('Place'),'</span> ',_('is used instead of'),' <span class="label label-default">',_('Stand'),'</span> ',_('for SMS'),' <span class="label label-default">FREE</span> ',_('command only. Multiple stands close to each other can be grouped in this way to send total number of bicycles available at that location.'),'</div>';
}
?>
<form class="container" method="post" enctype="multipart/form-data" action="index.php?step=5">
<script type="text/javascript">
var maplat=<?php echo $systemlat; ?>;
var maplon=<?php echo $systemlong; ?>;
</script>
<?php
$result=R::getAll("SELECT * FROM stands ORDER BY standName");
while ($row=$result->fetch_assoc()) {
    $standid=$row["standId"];
?>
         <fieldset><legend><?php echo _('Stand');
            echo ' ',$row["standName"]; ?></legend>
         <div class="row"><div class="col-lg-6">
<?php
if ($connectors["sms"]) : ?>
    <div class="form-group">
        <label for ="placename-<?php echo $standid; ?>" class="control-label"><?php echo _('Place:')  ?></label>
        <input type="text" name="placename[<?php echo $standid; ?>]" id="placename-<?php echo $standid; ?>" class="form-control" value="<?php echo $row["placeName"]; ?>" />
    </div>
<?php endif; ?>
         <div class="form-group"><label for="standdesc-<?php echo $standid; ?>" class="control-label"><?php echo _('Stand description:'); ?></label><textarea name="standdesc[<?php echo $standid; ?>]" id="standdesc-<?php echo $standid; ?>" class="form-control" rows="3" cols="39"></textarea></div>
         <div class="form-group"><label for="standphoto-<?php echo $standid; ?>" class="control-label"><?php echo _('Stand photo:'); ?></label><input type="file" name="standphoto[<?php echo $standid; ?>]" id="standphoto-<?php echo $standid; ?>" class="form-control" /></div>
         <div class="form-group"><label for="servicetag-<?php echo $standid; ?>" class="control-label"><?php echo _('Stand status:'); ?></label>
         <select name="servicetag[<?php echo $standid; ?>]" id="servicetag-<?php echo $standid; ?>" class="form-control" />
         <option value="0"><?php echo _('Active'); ?></option>
         <option value="1"><?php echo _('Not used / hidden'); ?></option>
         </select></div>
         </div>
         <div class="col-lg-6">
         <label for="map<?php echo $standid; ?>" class="control-label"><?php echo _('Stand location:'); ?></label>
         <div id="map<?php echo $standid; ?>" class="map"></div>
         <input type="hidden" name="standlat[<?php echo $standid; ?>]" id="standlat-<?php echo $standid; ?>" value="" />
         <input type="hidden" name="standlong[<?php echo $standid; ?>]" id="standlong-<?php echo $standid; ?>" value="" />
         </div>
         </div>
         </fieldset>

<?php
}
?>
         <button type="submit" id="register" class="btn btn-primary"><?php echo _('Set up stands'); ?></button> <?php echo _('and continue to step'); ?> 5
         </form>
<?php endif; ?>
<?php if ($step==5):
    $db=new Database($dbserver, $dbuser, $dbpassword, $dbname);
    $db->connect();
?>
      <h2>Set system options</h2>
<?php
$uploadtotal=0;
foreach ($_POST["standdesc"] as $standid => $value) {
    $result=R::exec("UPDATE stands SET standDescription=?, serviceTag=?, latitude=?, longitude=? WHERE id='$standid'",
                   [$_POST["standdesc"][$standid], $_POST["servicetag"][$standid], $_POST["standlat"][$standid], $_POST["standlong"][$standid]]);

    if (isset($uploads[$standid]["filename"])) {
        $result=R::exec("UPDATE stands SET standPhoto='".$uploads[$standid]["filename"]."' WHERE id='$standid'");
        $uploadtotal++;
    }
    if (isset($_POST["placename"][$standid])) {
        $result=R::exec("UPDATE stands SET placeName='".$_POST["placename"][$standid]."' WHERE id='$standid'");
    }
}
$db->conn->commit();
echo '<div class="alert alert-success" role="alert">',sprintf(ngettext('%d stand', '%d stands', count($_POST["standdesc"])), count($_POST["standdesc"])),' ',_('set up and'),' ',sprintf(ngettext('%d photo', '%d photos', $uploadtotal), $uploadtotal),' ',_('uploaded'),'</div>';
?>
      <form class="container" method="post" action="index.php?step=6">
<?php
// @TODO watches"freetime" dissapears, $credit["rent"]=2 without quotes, so it is not used?
$configfile=file($configfilename);
foreach ($configfile as $line) {
    if (strpos($line, '$watches')!==false or strpos($line, '$limits')!==false or strpos($line, '$credit')!==false or strpos($line, '$forcestack')!==false or strpos($line, '$notifyuser')!==false or strpos($line, '$email')!==false) {
        unset($variable);
        unset($arraykey);
        unset($variablevalue);
        unset($comment);
        $tokens=token_get_all('<?php '.$line.' ?>');
        //T_VARIABLE: $watches: T_CONSTANT_ENCAPSED_STRING: "freetime": : T_LNUMBER: 30: T_WHITESPACE: T_COMMENT: // in minutes (rental changes from free to paid after this time and $credit["rent"] is deducted)
        foreach ($tokens as $token) {
            $tokenname=token_name($token[0]);
            $tokenparam=$token[1];
            if ($tokenname=="T_VARIABLE") {
                $variable=$tokenparam;
                $previoustoken="var";
            } elseif ($tokenname=="T_CONSTANT_ENCAPSED_STRING" and $previoustoken=="var" and (strpos($line, '$watches["')!==false or strpos($line, '$limits["')!==false or strpos($line, '$credit["')!==false or strpos($line, '$email["')!==false)) {
                $arraykey=$tokenparam;
                unset($previoustoken);
            } elseif ($tokenname=="T_LNUMBER" or $tokenname=="T_DNUMBER" or ($tokenname=="T_CONSTANT_ENCAPSED_STRING" and !$previoustoken)) {
                $variablevalue=$tokenparam;
                unset($previoustoken);
            } elseif ($tokenname=="T_COMMENT") {
                $comment=trim(str_replace("//", "", $tokenparam));
                unset($previoustoken);
            }
        }
        $cleanvar=str_replace('$', '', $variable);
        if ($arraykey) {
            $cleanvar.='['.str_replace('"', "", $arraykey).']';
        }
        echo '<div class="form-group"><label for="',$cleanvar,'" class="control-label">',$variable;
        if ($arraykey) {
            echo '[',$arraykey,']';
        }
        if ($comment) {
            echo ' (',$comment,')';
        }
        echo ':</label> <input type="text" name="',$cleanvar,'" id="',$cleanvar,'" class="form-control" value="',$variablevalue,'" />';
        echo '</div>';
    }
}
?>
         <button type="submit" id="register" class="btn btn-primary"><?php echo _('Set system options'); ?></button> <?php echo _('and finish'); ?>
         </form>
<?php endif; ?>
<?php if ($step==6) :
    $db=new Database($dbserver, $dbuser, $dbpassword, $dbname);
    $db->connect();
    $configfile=file($configfilename);
    foreach ($_POST as $variable => $value) {
        if (is_array($value)) {
            foreach ($value as $arrayvariable => $arrayvalue) {
                changeconfigvalue($variable.'["'.$arrayvariable.'"]', $_POST[$variable][$arrayvariable]);
            }
        } else {
            changeconfigvalue($variable, $_POST[$variable]);
        }
    }
    $newconfig=implode($configfile);
    file_put_contents($configfilename, $newconfig);
    $configfile=file($configfilename);
    if ($credit["enabled"]==1) {
        $newcredit=($credit["min"]+$credit["rent"]+$credit["longrental"])*10;
        $result=R::getAll("SELECT id FROM users WHERE privileges='7'");
        $row=$result->fetch_assoc();
        $result=R::exec("REPLACE INTO credit SET userId='".$row["userId"]."',credit='$newcredit'");
    }
    $db->conn->commit();
?>
        <h2>Installation finished</h2>
        <div class="alert alert-success" role="alert"><?php echo _('System options set.'); ?></div>
        <div class="alert alert-warning" role="alert"><strong><?php echo _('Rename');
        echo ' ',$configfilename,' ';
        echo _('to'); ?> config.php.</strong></div>
        <form class="container" method="post" action="../">
            <button type="submit" id="register" class="btn btn-primary">
            <?php echo _('Launch'),' ',$systemname; ?>!
            </button>
        </form>
<?php endif; ?>
        <br />
        <div class="panel panel-default">
        <div class="panel-body">
            <i class="glyphicon glyphicon-copyright-mark"></i> <?php echo date("Y"); ?> <a href="<?php echo $systemURL; ?>"><?php echo $systemname; ?></a>
        </div>
        <div class="panel-footer"><strong><?php echo _('Privacy policy'); ?>:</strong> <?php echo _('We will use your details for'); ?> <?php echo $systemname; ?>-<?php echo _('related activities only'); ?>.</div>
        </div>

    </div><!-- /.container -->
</body>
</html>
