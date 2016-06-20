<?php

define('OK', 1);

function notification($result, $values = false)
{
    if ($result==10) {
        notifyAdmins(_('Bike')." ".$values->bikenum." "._('rented out of stack by')." ".$values->username.". ".$values->stacktopbike." "._('was on the top of the stack at')." ".$values->currentstand.".", ERROR);
    } elseif ($result==20) {
        notifyAdmins(_('Note')." b.".$values->bikenum." (".$values->bikestatus.") "._('by')." ".$values->username."/".$values->phone.":".$values->note);
    }
    response('Unhandled notification '.$result.'.', ERROR);

}

function rentbike($userid, $bikenum, $force = false)
{

    global $forcestack,$watches;
    $values=new stdClass;
    $stacktopbike=false;
    $requiredcredit=getrequiredcredit();

    if ($force==false) {
        $creditcheck=checkrequiredcredit($userid);
        if ($creditcheck===false) {
            $credit=R::findOne('credit', 'userid=?', [$userid]);
            $values->credit=$credit->credit;
            $values->requiredcredit=$requiredcredit;
            status('RENT', 130, $values);
        }
        checktoomany(0, $userid);

        $countrented=R::count('bikes', 'currentuser=?', [$userid]);

        $limit=R::findOne('limits', 'userid=?', [$userid]);

        if ($countrented>=$limit->userlimit) {
            if ($limit->userlimit==0) {
                status('RENT', 100);
            } elseif ($limit->userlimit==1) {
                $values->userlimit=$limit->userlimit;
                status('RENT', 101, $values);
            } else {
                $values->userlimit=$limit->userlimit;
                status('RENT', 101, $values);
            }
        }

        if ($forcestack or $watches["stack"]) {
            $bike=R::findOne('bikes', 'bikenum=?', [$bikenum]);
            $stacktopbike=checktopofstack($bike->currentstand);
            if ($watches["stack"] and $stacktopbike<>$bikenum) {
                $stand=R::load('stands', $bike->currentstand);
                $username=getusername($userid);
                $values->bikenum=$bikenum;
                $values->username=$username;
                $values->stacktopbike=$stacktopbike;
                $values->currentstand=$bike->currentstand;
                notification(10, $values);
            }
            if ($forcestack and $stacktopbike<>$bikenum) {
                $values->bikenum=$bikenum;
                $values->stacktopbike=$stacktopbike;
                status('RENT', 110, $values);
            }
        }
    }

    $bike=R::findOne('bikes', 'bikenum=?', [$bikenum]);
    $bike->currentcode=sprintf("%04d", $bike->currentcode);
    $notes=R::find('notes', 'bikenum=? ORDER BY time DESC', [$bikenum]);
    $note="";
    if (!empty($notes)) {
        foreach ($notes as $noteitem) {
            $note.=$noteitem->note."; ";
        }
    }
    $note=substr($note, 0, strlen($note)-2); // remove last two chars - comma and space

    $newcode=sprintf("%04d", rand(100, 9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

    $values->bikenum=$bikenum;
    $values->currentcode=$bike->currentcode;
    $values->currentuser=$bike->currentuser;
    $values->newcode=$newcode;
    $values->note=$note;
    if ($bike->currentuser) {
        $values->currentusernumber=getphonenumber($bike->currentuser);
    }

    if ($force==false) {
        if ($bike->currentuser==$userid) {
            $values->bikenum=$bikenum;
            $values->currentcode=$bike->currentcode;
            status('RENT', 120, $values);
        } elseif ($bike->currentuser!=0) {
            $values->bikenum=$bikenum;
            status('RENT', 121, $values);
        }
    }

    $bike->currentuser=$userid;
    $bike->currentcode=$newcode;
    $bike->currentstand=null;
    R::store($bike);
    $history=R::dispense('history');
    $history->userid=$userid;
    $history->bikenum=$bikenum;
    if ($force==false) {
        $history->action='RENT';
    } else {
        $history->action='FORCERENT';
    }
    $history->parameter=$newcode;
    R::store($history);
    status('RENT', OK, $values);

}

function returnbike($userid, $bikenum, $stand, $note = "", $force = false)
{

    global $connectors;
    $values=new stdClass;
    $stand=strtoupper($stand);

    $values->bikenum=$bikenum;
    if ($force==false) {
        $countrented=R::count('bikes', 'currentuser=? ORDER BY bikenum', [$userid]);
        $values->countrented=$countrented;
        if ($countrented==0) {
            status('RETURN', 100);
        } elseif ($countrented>1 and !$bikenum) { // QR code only, when multiple bikes rented
            $values->countrented=$countrented;
            status('RETURN', 101, $values);
        }
        $bike=R::findOne('bikes', 'currentuser=:userid AND bikenum=:bikenum', [':userid'=>$userid,':bikenum'=>$bikenum]);
        if (empty($bike)) { // no such bike is rented
            $userbikes=R::find('bikes', 'currentuser=?', [$userid]);
            $values->bikelist="";
            foreach ($userbikes as $userbike) {
                $values->bikelist.=$userbike->bikenum.',';
            }
            if ($countrented>=1) {
                $values->bikelist=substr($values->bikelist, 0, strlen($values->bikelist)-1); // remove last comma
            }          status('RETURN', 102, $values);
        }
    } else {
        $bike=R::findOne('bikes', 'currentuser=:userid AND bikenum=:bikenum', [':userid'=>$userid,':bikenum'=>$bikenum]);
        if (!empty($bike)) { // bike does not exist
            $values->bikenum=$bikenum;
            status('RETURN', 103, $values);
        }
        $values->currentuser=getphonenumber($bike->currentuser);
       // ??????? $existingnote=R::findOne('notes','bikeNum=? AND deleted IS NULL ORDER BY time DESC LIMIT 1',[$bikenum]);
       // ?????? $values->existingnote=$existingnote->note;
    }

    $bike->currentcode=sprintf("%04d", $bike->currentcode);
    $values->currentcode=$bike->currentcode;

    $stand=R::findOne('stands', 'standname=?', [$stand]);

    $bike->currentuser=null;
    $bike->currentstand=$stand->id;
    R::store($bike);

    $creditchange=changecreditendrental($bike->bikenum, $userid);
    if (iscreditenabled() and $creditchange) {
        $values->creditchange=$creditchange;
    }
    if ($note) {
        $notes=R::dispense('notes');
        $notes->bikenum=$bikenum;
        $notes->userid=$userid;
        $notes->note=$note;
        R::store($notes);
        $values->note=$note;
        $values->standname=$stand;
        $values->bikestatus=_('at')." ".$stand;
        $values->username=getusername($userid);
        $values->phone=getphonenumber($userid);
        notification(20, $values);
    }
    $history=R::dispense('history');
    $history->userid=$userid;
    $history->bikenum=$bike->bikenum;
    if ($force==false) {
        $history->action='RETURN';
    } else {
        $history->action='FORCERETURN';
    }
    $history->parameter=$stand->id;
    R::store($history);
    $values->userid=$userid;
    status('RETURN', OK, $values);

}

function where($userid, $bikenum)
{

    $values=new stdClass;
    $bike=R::find('bikes', 'bikenum=?', [$bikenum]);
    $notes=R::find('notes', 'bikenum=? AND deleted IS NULL ORDER BY time DESC', [$bikenum]);
    $note="";
    foreach ($notes as $noteitem) {
        $note.=$noteitem->note."; ";
    }
    $values->bikenum=$bikenum;
    $values->note=substr($note, 0, strlen($note)-2); // remove last two chars - comma and space
    if ($note) {
        $values->note=$note;
    }
    if ($bike->standname) {
        $values->standname=$bike->currentstand;
        status('WHERE', 100, $values);
    } else {
        $values->username=getusername($bike->currentuser);
        $values->phone=getphonenumber($bike->currentuser);
        status('WHERE', 101, $values);
    }

}

function listbikes($stand)
{
    global $forcestack;
    $values=new stdClass;
    $values->stacktopbike=false;
    if ($forcestack) {
        $stand=R::findOne('stand', 'standname=?', [$stand]);
        $values->stacktopbike=checktopofstack($stand->id);
    }
    $rows=R::getAll('SELECT bikes.id,bikenum FROM bikes LEFT JOIN stands ON bikes.currentstand=stands.id WHERE standname=?', [$stand]);
    $bikes=R::convertToBeans('bikes', $rows);
    if (!empty($bikes)) {
        $values->standcount=count($bikes);
        foreach ($bikes as $bike) {
            $notes=R::find('notes', 'bikenum=:bikenum AND deleted=:deleted ORDER BY time DESC', [':bikenum'=>$bike->bikenum,':deleted'=>'0000-00-00 00:00:00']);
            $note="";
            foreach ($notes as $noteitem) {
                $note.=$noteitem->note."; ";
            }
            $note=substr($note, 0, strlen($note)-2); // remove last two chars - comma and space
            if ($note) {
                $values->id[]=$bike->bikenum; // bike with note / issue
                $values->notes[]=$note;
            } else {
                $values->id[]=$bike->bikenum;
                $values->notes[]="";
            }
        }
    } else {
        status('LISTBIKES', 100, $values);
    }
    status('LISTBIKES', OK, $values);

}

function addnote($userid, $bikenum, $message)
{

    $values=new stdClass;
    $user=R::load('users', $userid);
    $stand=R::getRow('SELECT stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userId LEFT JOIN stands on bikes.currentStand=stands.standId WHERE bikeNum=?', [$bikenum]);
    if (!empty($stand)) {
        $bikestatus=_('at')." ".$stand->standname;
    } else {
        $bikestatus=_('used by')." ".$user->username." +".$user->phone;
    }
    $note=R::dispense('notes');
    $note->bikenum=$bikenum;
    $note->userid=$userid;
    $note->note=$message;
    $noteid=R::store($note);
    notifyAdmins(_('Note #').$noteid.": b.".$bikenum." (".$bikestatus.") "._('by')." ".$user->username."/".$user->phone.":".$message);

}

function removenote($userid, $bikenum)
{
    $values=new stdClass;
    $values->bikenum=$bikenum;
    $note=R::findOne('notes', 'bikenum=?', [$bikenum]);
    R::trash($note);
    status('DELNOTE', OK, $values);
}

function saveuser($userid, $username, $email, $phone, $privileges, $userlimit)
{
    $user=R::load('users', $userid);
    if ($user->id) {
        $user->username=$username;
        $user->mail=$email;
        if ($phone) {
            $user->phone=$phone;
        }
        $user->privileges=$privileges;
        R::store($user);
        $limit=findOne('limits', 'userid=?', [$userid]);
        $limit->limit=$userlimit;
        R::store($limit);
        response(_('Details of user')." ".$user->username." "._('updated').".");
    }
}

function addcredit($userid, $creditmultiplier)
{
    $addcreditamount=getrequiredcredit()*$creditmultiplier;
    $credit->findOne('credit', 'userid=?', [$userid]);
    $credit->credit=$credit->credit+$addcreditamount;
    R::store($credit);
    $history=R::dispense('history');
    $history->userid=$userid;
    $history->action='CREDITCHANGE';
    $history->parameter=$addcreditamount.'|add+'.$addcreditamount;
    R::store($history);
    response(_('Added')." ".$addcreditamount.getcreditcurrency()." "._('credit for')." ".getusername($userid).".");
}

function getcouponlist()
{
    if (iscreditenabled()==false) {
        return; // if credit system disabled, exit
    }    $coupons=R::find('coupons', 'status=0 ORDER BY status,value,coupon');
    foreach ($coupons as $coupon) {
        $jsoncontent[]=array("coupon"=>$coupon->coupon,"value"=>$coupon->value);
    }
    echo json_encode($jsoncontent);// TODO change to response function
}

function generatecoupons($multiplier)
{
    if (iscreditenabled()==false) {
        return; // if credit system disabled, exit
    }    $value=getrequiredcredit()*$multiplier;
    $codes=generatecodes(10, 6);
    foreach ($codes as $code) {
        $coupon=findOne('coupons', 'coupon=?', $code);
        if (empty($coupon)) {
            $coupon=R::dispense('coupons');
            $coupon->coupon=$code;
            $coupon->value=$value;
            $coupon->status=0;
            R::store($coupon);
        }
    }
    response(_('Generated 10 new').' '.$value.' '.getcreditcurrency().' '._('coupons').'.', 0, array("coupons"=>$codes));
}

function sellcoupon($couponcode)
{
    if (iscreditenabled()==false) {
        return; // if credit system disabled, exit
    }    $coupon=R::findOne('coupons', 'coupon=?', [$couponcode]);
    $coupon->status=1;
    R::store($coupon);
    response(_('Coupon').' '.$coupon->coupon.' '._('sold').'.');
}

function validatecoupon($userid, $couponcode)
{
    if (iscreditenabled()==false) {
        return; // if credit system disabled, exit
    }    $coupon=R::findOne('coupons', 'coupon=? AND status<2', [$couponcode]);
    if (!empty($coupon)) {
        $credit=R::findOne('credit', 'userid=?', $userid);
        $credit->credit=$credit->credit+$coupon->value;
        R::store($credit);
        $history=R::dispense('history');
        $history->userid=$userid;
        $history->action='CREDITCHANGE';
        $history->parameter=$coupon->value.'|add+'.$coupon->value.'|'.$coupon->coupon;
        R::store($history);
        $coupon->status=2;
        R::store($coupon);
        response('+'.$coupon->value.' '.getcreditcurrency().'. '._('Coupon').' '.$coupon->coupon.' '._('has been redeemed').'.');
    }
    response(_('Invalid coupon, try again.'), 1);
}

function resetpassword($number)
{
    $user=findOne('users', 'number=?', [$number]);
    if (empty($user)) {
        response(_('No such user found.'), 1);
    }

    $subject=_('Password reset');

    mt_srand(crc32(microtime()));
    $user->password=substr(md5(mt_rand().microtime().$user->mail), 0, 8);

    R::store($user);

    $names=preg_split("/[\s,]+/", $user->username);
    $firstname=$names[0];
    $message=_('Hello').' '.$firstname.",\n\n".
    _('Your password has been reset successfully.')."\n\n".
    _('Your new password is:')."\n".$user->password;

    sendEmail($user->mail, $subject, $message);
    response(_('Your password has been reset successfully.').' '._('Check your email.'));
}


function credit($number)
{
    $values=new stdClass;
    $userid=getuserid($number);
    $values->usercredit=getusercredit($userid);
    status('CREDIT', 100, $values);
}


function checkbikenum($bikenum)
{
    $values=new stdClass;
    $bike=R::load('bikes', $bikenum);
    if (!$bike->id) {
        $values->bikenum=$bikenum;
        status('CHECKBIKE', 100, $values);
    }
}

function userbikes($userid)
{
    if (!isloggedin()) {
        response("");
    }
    $bikes=R::find('bikes', 'currentuser=? ORDER BY bikenum', [$userid]);
    if (!empty($bikes)) {
        foreach ($bikes as $bike) {
            $bicycles[]=$bike->bikenum;
            $codes[]=str_pad($bike->code, 4, "0", STR_PAD_LEFT);
            $history=R::findOne('history', 'bikenum=:bikenum AND action=:action ORDER BY time DESC', [':bikenum'=>$bike->bikenum,':action'=>'RENT']);
            if (!empty($history)) {
                $oldcodes[]=str_pad($history->parameter, 4, "0", STR_PAD_LEFT);
            }
        }
    } else {
        $bicycles="";
    }
    if (!isset($codes)) {
        $codes="";
    }
    if (!isset($oldcodes)) {
        $oldcodes="";
    }
    response('', 0, array("userbikes"=>array("bicycles"=>$bicycles,"codes"=>$codes,"oldcodes"=>$oldcodes)), 0);
}

function mapgeolocation($userid, $lat, $long)
{
    $geolocation=R::dispense('geolocation');
    $geolocation->userid=$userid;
    $geolocation->latitude=$lat;
    $geolocation->longitude=$long;
    R::store($geolocation);
    response("");
}


function mapgetmarkers()
{
    $rows = R::getAll(
       'SELECT S.id, count(B.bikeNum) AS bikecount, S.standDescription, S.standName, S.standPhoto, S.longitude AS lon, S.latitude AS lat
        FROM stands AS S
        LEFT JOIN bikes AS B ON B.currentStand = S.id
        WHERE S.serviceTag = 0
        GROUP BY standName
        ORDER BY standName');

    if (!empty($rows)) {
        foreach ($rows as $row) {
            $jsoncontent["markers"][] = $row;
        }
    }

    response('', 0, $jsoncontent, 0);
}

function mapgetlimit($userid)
{
    if (!isloggedin()) {
        response("");
    }
    $rented = R::count('bikes', 'currentuser=?', [$userid]);
    $limit = R::findOne('limits', 'userid=?', [$userid]);

    $currentlimit = $limit->userlimit-$rented;

    $usercredit = 0;
    $usercredit = getusercredit($userid);

    $jsoncontent = array("limit"=>$currentlimit,"rented"=>$rented,"usercredit"=>$usercredit);
    response('', 0, $jsoncontent, 0);
}
