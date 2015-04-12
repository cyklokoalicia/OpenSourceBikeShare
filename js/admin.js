$(document).ready(function(){
   $("#edituser").hide();
   $("#where").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-where'); where(); });
   $("#revert").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-revert'); revert(); });
   $("#last").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-last'); last(); });
   $("#stands").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-stands'); stands(); });
   $("#userlist").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-userlist'); userlist(); });
   $("#userstats").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-userstats'); userstats(); });
   $("#trips").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-trips'); trips(); });
   $('.nav-tabs a').each(function () { $(this).click(function () { activetab=$(this).attr('href'); $(activetab).addClass('active'); } ); });
   $("#addcredit").click(function() { addcredit(1); return false; });
   $("#addcredit2").click(function() { addcredit(5); return false; });
   $("#addcredit3").click(function() { addcredit(10); return false; });
   last();
});

function handleresponse(elementid,jsonobject,display)
{
   if (display==undefined)
      {
      if (jsonobject.error==1)
         {
         $('#'+elementid).html('<div class="alert alert-danger" role="alert">'+jsonobject.content+'</div>').fadeIn();
         }
      else
         {
         $('#'+elementid).html('<div class="alert alert-success" role="alert">'+jsonobject.content+'</div>');
         }
      }
}

function where()
{
   if (window.ga) ga('send', 'event', 'bikes', 'where', $('#adminparam').val());
   $.ajax({
   url: "command.php?action=where&bikeno="+$('#adminparam').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse("fleetconsole",jsonobject);
   });
}

function last()
{
   if (window.ga) ga('send', 'event', 'bikes', 'last', $('#adminparam').val());
   $.ajax({
   url: "command.php?action=last&bikeno="+$('#adminparam').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse("fleetconsole",jsonobject);
   });
}

function stands()
{
   $.ajax({
   url: "command.php?action=stands"
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse("standsconsole",jsonobject);
   });
}

function userlist()
{
   var code="";
   $.ajax({
   url: "command.php?action=userlist"
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      if (jsonobject.length>0) code='<table class="table table-striped"><tr><th>User</th><th>Privileges</th><th>Limit</th>';
      if (creditenabled==1) code=code+'<th>Credit</th>';
      code=code+'</tr>';
      for (var i=0, len=jsonobject.length; i < len; i++)
         {
         code=code+'<tr><td><a href="#" class="edituser" data-userid="'+jsonobject[i]["userid"]+'">'+jsonobject[i]["username"]+'</a><br />'+jsonobject[i]["number"]+'<br />'+jsonobject[i]["mail"]+'</td><td>'+jsonobject[i]["privileges"]+'</td><td>'+jsonobject[i]["limit"]+'</td>';
         if (creditenabled==1)
            {
            code=code+'<td>'+jsonobject[i]["credit"]+creditcurrency+'</td></tr>';
            }
         }
      if (jsonobject.length>0) code=code+'</table>';
      $('#userconsole').html(code);
      createeditlinks();
   });
}

function userstats()
{
   var code="";
   $.ajax({
   url: "command.php?action=userstats"
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      if (jsonobject.length>0) code='<table class="table table-striped"><tr><th>User</th><th>Actions</th><th>Rentals</th><th>Returns</th></tr>';
      for (var i=0, len=jsonobject.length; i < len; i++)
         {
         code=code+'<tr><td><a href="#" class="edituser" data-userid="'+jsonobject[i]["userid"]+'">'+jsonobject[i]["username"]+'</a></td><td>'+jsonobject[i]["count"]+'</td><td>'+jsonobject[i]["rentals"]+'</td><td>'+jsonobject[i]["returns"]+'</td></tr>';
         }
      if (jsonobject.length>0) code=code+'</table>';
      $('#reportsconsole').html(code);
      createeditlinks();
   });
}

function createeditlinks()
{
   $('.edituser').each(function () {
      $(this).click(function () { if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-edituser', $(this).attr('data-userid')); edituser($(this).attr('data-userid')); });
   });
}

function edituser(userid)
{
   $.ajax({
   url: "command.php?action=edituser&edituserid="+userid
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      if (jsonobject)
         {
         $('#userid').val(jsonobject["userid"]);
         $('#username').val(jsonobject["username"]);
         $('#email').val(jsonobject["email"]);
         if ($('#phone')) $('#phone').val(jsonobject["phone"]);
         $('#privileges').val(jsonobject["privileges"]);
         $('#limit').val(jsonobject["limit"]);
         $('#edituser').show();
         $('a[href=#users]').trigger('click');
         }
   });
}

function saveuser()
{
   if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-saveuser', $('#userid').val());
   var phone="";
   if ($('#phone')) phone="&phone="+$('#phone').val();
   $.ajax({
   url: "command.php?action=saveuser&edituserid="+$('#userid').val()+"&username="+$('#username').val()+"&email="+$('#email').val()+"&privileges="+$('#privileges').val()+"&limit="+$('#limit').val()+phone
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      $("#edituser").hide();
      handleresponse("userconsole",jsonobject);
   });
}

function addcredit(creditmultiplier)
{
   if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-addcredit', $('#userid').val());
   $.ajax({
   url: "command.php?action=addcredit&edituserid="+$('#userid').val()+"&creditmultiplier="+creditmultiplier
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      $("#edituser").hide();
      handleresponse("userconsole",jsonobject);
   });
}

function trips()
{
   if (window.ga) ga('send', 'event', 'bikes', 'trips', $('#adminparam').val());
   $.ajax({
   url: "command.php?action=trips&bikeno="+$('#adminparam').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      if (jsonobject.error==1)
         {
         handleresponse(elementid,jsonobject);
         }
      else
         {
         if (jsonobject[0]) // concrete bike requested
            {
            if (polyline!=undefined) map.removeLayer(polyline);
            polyline = L.polyline([[jsonobject[0].latitude*1,jsonobject[0].longitude*1],[jsonobject[1].latitude*1,jsonobject[1].longitude*1]], {color: 'red'}).addTo(map);
            for (var i=2, len=jsonobject.length; i < len; i++)
               {
               if (jsonobject[i].longitude*1 && jsonobject[i].latitude*1)
                  {
                  polyline.addLatLng([jsonobject[i].latitude*1,jsonobject[i].longitude*1]);
                  }
               }
            }
         else // all bikes requested
            {
            var polylines=[];
            for (var bikenumber in jsonobject)
               {
               var bikecolor='#'+('00000'+(Math.random()*16777216<<0).toString(16)).substr(-6);
               polylines[bikenumber] = L.polyline([[jsonobject[bikenumber][0].latitude*1,jsonobject[bikenumber][0].longitude*1],[jsonobject[bikenumber][1].latitude*1,jsonobject[bikenumber][1].longitude*1]], {color: bikecolor}).addTo(map);
               for (var i=2, len=jsonobject[bikenumber].length; i < len; i++)
                  {
                  if (jsonobject[bikenumber][i].longitude*1 && jsonobject[bikenumber][i].latitude*1)
                     {
                     polylines[bikenumber].addLatLng([jsonobject[bikenumber][i].latitude*1,jsonobject[bikenumber][i].longitude*1]);
                     }
                  }
               }
            }

         }
   });
}

function revert()
{
   if (window.ga) ga('send', 'event', 'bikes', 'revert', $('#adminparam').val());
   $.ajax({
   url: "command.php?action=revert&bikeno="+$('#adminparam').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse("fleetconsole",jsonobject);
   });
}