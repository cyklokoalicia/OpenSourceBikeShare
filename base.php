<?php

define('OK',1);

function notification($result,$values==FALSE)
{
if ($result==10)
   {
   notifyAdmins(_('Bike')." ".$values->bikenum." "._('rented out of stack by')." ".$values->username.". ".$values->stacktopbike." "._('was on the top of the stack at')." ".$values->currentstand.".",ERROR);
   }
elseif ($result==20)
   {
   notifyAdmins(_('Note')." b.".$values->bikenum." (".$values->bikestatus.") "._('by')." ".$values->username."/".$values->phone.":".$values->note);
   }
response('Unhandled notification '.$result.'.',ERROR);

}

function rentbike($userid,$bikenum,$force=FALSE)
{

   global $forcestack,$watches;
   $values=new stdClass;
   $stacktopbike=FALSE;
   $requiredcredit=getrequiredcredit();

   if ($force==FALSE)
      {
      $creditcheck=checkrequiredcredit($userid);
      if ($creditcheck===FALSE)
         {
         $credit=R::findOne('credit','userid=?',[$userid]);
         $values->credit=$credit->credit;
         $values->requiredcredit=$requiredcredit;
         status('RENT',130,$values);
         }
      checktoomany(0,$userid);

      $countrented=R::count('bikes','currentuser=?',[$userid]);

      $limit=R::findOne('limits','userid=?',[$userid]);

      if ($countrented>=$limit->userlimit)
         {
         if ($limit->userlimit==0)
            {
            status('RENT',100);
            }
         elseif ($limit->userlimit==1)
            {
            $values->userlimit=$limit->userlimit;
            status('RENT',101,$values);
            }
         else
            {
            $values->userlimit=$limit->userlimit;
            status('RENT',101,$values);
            }
         }

      if ($forcestack OR $watches["stack"])
         {
         $bike=R::findOne('bikes','bikenum=?',[$bikenum]);
         $stacktopbike=checktopofstack($bike->currentstand);
         if ($watches["stack"] AND $stacktopbike<>$bikenum)
            {
            $stand=R::load('stands',$bike->currentstand);
            $username=getusername($userid);
            $values->bikenum=$bikenum;
            $values->username=$username;
            $values->stacktopbike=$stacktopbike;
            $values->currentstand=$bike->currentstand;
            notification(10,$values);
            }
         if ($forcestack AND $stacktopbike<>$bikenum)
            {
            $values->bikenum=$bikenum;
            $values->stacktopbike=$stacktopbike;
            status('RENT',110,$values);
            }
         }
      }

   $bike=R::findOne('bikes','bikenum=?',[$bikenum]);
   $bike->currentcode=sprintf("%04d",$bike->currentcode);
   $notes=R::find('notes','bikenum=? ORDER BY time DESC',[$bikenum]);
   $note="";
   if (!empty($notes))
      {
      foreach($notes as $noteitem)
         {
         $note.=$noteitem->note."; ";
         }
      }
   $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space

   $newcode=sprintf("%04d",rand(100,9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

   $values->bikenum=$bikenum;
   $values->currentcode=$bike->currentcode;
   $values->currentuser=$bike->currentuser;
   $values->newcode=$newcode;
   $values->note=$note;
   if ($bike->currentuser) $values->currentusernumber=getphonenumber($bike->currentuser);

   if ($force==FALSE)
      {
      if ($bike->currentuser==$userid)
         {
         $values->bikenum=$bikenum;
         $values->currentcode=$bike->currentcode;
         status('RENT',120,$values);
         }
      elseif ($bike->currentuser!=0)
         {
         $values->bikenum=$bikenum;
         status('RENT',121,$values);
         }
      }

   $bike->currentuser=$userid;
   $bike->currentcode=$newcode;
   $bike->currentstand=NULL;
   R::store($bike);
   $history=R::dispense('history');
   $history->userid=$userid;
   $history->bikenum=$bikenum;
   if ($force==FALSE)
      {
      $history->action='RENT';
      }
   else
      {
      $history->action='FORCERENT';
      }
   $history->parameter=$newcode;
   R::store($history);
   status('RENT',OK,$values);

}

function returnbike($userid,$bikenum,$stand,$note="",$force=FALSE)
{

   global $connectors;
   $values=new stdClass;
   $stand=strtoupper($stand);

   if ($force==FALSE)
      {
      $countrented=R::count('bikes','currentuser=? ORDER BY bikenum',[$userid]);
      if ($countrented==0)
         {
         status('RETURN',100);
         }
      elseif ($countrented>1 AND !$bikenum) // QR code only, when multiple bikes rented
         {
         $values->countrented=$countrented;
         status('RETURN',101,$values);
         }
      $bike=R::findOne('bikes','currentuser=:userid AND bikenum=:bikenum',[':userid'=>$userid,':bikenum'=>$bikenum]);
      if (empty($bike)) // no such bike is rented
         {
         $userbikes=R::find('bikes','currentuser=?',[$userid]);
         $values->bikelist="";
         foreach($userbikes as $userbike)
            {
            $values->bikelist.=$userbike->bikenum.',';
            }
         if ($countrented>1) $values->bikelist=substr($values->bikelist,0,strlen($values->bikelist)-1); // remove last comma
         $values->bikenum=$bikenum;
         $values->countrented=$countrented;
         status('RETURN',102,$values);
         }
      }
   else
      {
      $bike=R::findOne('bikes','currentuser=:userid AND bikenum=:bikenum',[':userid'=>$userid,':bikenum'=>$bikenum]);
      if (empty($bike)) // no such bike is rented
         {
         $values->bikenum=$bikenum;
         status('RETURN',103,$values);
         }
      $values->currentuser=getphonenumber($bike->currentuser);
      // ??????? $existingnote=R::findOne('notes','bikeNum=? AND deleted IS NULL ORDER BY time DESC LIMIT 1',[$bikenum]);
      // ?????? $values->existingnote=$existingnote->note;
      }

   $bike->currentcode=sprintf("%04d",$bike->currentcode);

   $stand=R::findOne('stands','standname=?',[$stand]);

   $bike->currentuser=NULL;
   $bike->currentstand=$stand->id;
   R::store($bike);

   $creditchange=changecreditendrental($bike->bikenum,$userid);
   if (iscreditenabled() AND $creditchange) $values->creditchange=$creditchange;
   if ($note)
      {
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
      notification(20,$values);
      }
   $history=R::dispense('history');
   $history->userid=$userid;
   $history->bikenum=$bike->bikenum;
   if ($force==FALSE)
      {
      $history->action='RETURN';
      }
   else
      {
      $history->action='FORCERETURN';
      }
   $history->parameter=$stand->id;
   R::store($history);
   $values->userid=$userid;
   status('RETURN',OK,$values);

}

function where($userid,$bikenum)
{

   $values=new stdClass;
   $bike=R::find('bikes','bikenum=?',[$bikenum]);
   $notes=R::find('notes','bikenum=? AND deleted IS NULL ORDER BY time DESC',[$bikenum]);
   $note="";
   foreach ($notes as $noteitem)
      {
      $note.=$noteitem->note."; ";
      }
   $values->bikenum=$bikenum;
   $values->note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
   if ($note)
      {
      $values->note=$note;
      }
   if ($bike->standname)
      {
      $values->standname=$bike->currentstand;
      status('WHERE',100,$values);
      }
   else
      {
      $values->username=getusername($bike->currentuser);
      $values->phone=getphonenumber($bike->currentuser);
      status('WHERE',101,$values);
      }

}

function listbikes($stand)
{
   global $forcestack;
   $values=new stdClass;
   $stacktopbike=FALSE;
   if ($forcestack)
      {
      $stand=R::findOne('stand','standname=?',[$stand]);
      $values->stacktopbike=checktopofstack($stand->id);
      }
   $rows=R::getAll('SELECT bikenum FROM bikes LEFT JOIN stands ON bikes.currentstand=stands.id WHERE standname=?',[$stand])
   $bikes=R::convertToBeans('bikes',$rows);
   if (!empty($bikes))
      {
      $values->standcount=count($bikes);
      foreach ($bikes as $bike)
         {
         $notes=R::find('notes','bikenum=? AND deleted IS NULL ORDER BY time DESC',[$bike->bikenum]);
         $note="";
         foreach ($notes as $noteitem)
            {
            $note.=$noteitem->note."; ";
            }
         $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
         if ($note)
            {
            $values->bicycles[]=$bikenum; // bike with note / issue
            $values->notes[]=$note;
            }
         else
            {
            $values->bicycles[]=$bikenum;
            $values->notes[]="";
            }
         }
      }
   else
      {
      status('LISTBIKES',100,$values);
      }
   status('LISTBIKES',OK,$values);

}


function credit($number)
{
   $values=new stdClass;
   $userid=getuserid($number);
   $values->usercredit=getusercredit($userid);
   status('CREDIT',100,$values);
}


function checkbikenum($bikenum)
{
   $values=new stdClass;
   $bike=R::load('bikes',$bikenum);
   if (!$bike->id)
      {
      $values->bikenum=$bikenum;
      status('CHECKBIKE',100,$values);
      }
}

function checkstandname($standname)
{
   $standname=trim(strtoupper($standname));
   $stand=R::findOne('stands','standname=?',[$standname]);
   if (empty($stand))
      {
      $values->standname=$standname;
      status('CHECKSTAND',100,$values);
      }
}

?>