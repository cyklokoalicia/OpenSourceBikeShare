var markers=[]; var nameid=[]; var markerdata=[]; var iconsize=60; var sidebar;

$(document).ready(function(){
   $('#standactions').hide();
   $('.bicycleactions').hide();
   $('.adminactions').hide();
   $(document).ajaxStart(function() { $('#console').html('<img src="img/loading.gif" alt="loading" id="loading" />'); });
   $(document).ajaxComplete(function() { $('#loading').remove(); });
   $("#rent").click(function() { ga('send', 'event', 'buttons', 'click', 'bike-rent'); rent(); });
   $("#return").click(function(e) { ga('send', 'event', 'buttons', 'click', 'bike-return'); returnbike(); });
   $("#note").click(function() { ga('send', 'event', 'buttons', 'click', 'bike-note'); note(); });
   $("#where").click(function() { ga('send', 'event', 'buttons', 'click', 'admin-where'); where(); });
   $("#last").click(function() { ga('send', 'event', 'buttons', 'click', 'admin-last'); last(); });
   $("#revert").click(function() { ga('send', 'event', 'buttons', 'click', 'admin-revert'); revert(); });
   mapinit();
   setInterval(getmarkers, 60000); // refresh map every 60 seconds
});

function mapinit()
{
   var viewport = $.viewportDetect(); // ("xs", "sm", "md", or "lg");
   if (viewport=="xs" || viewport=="sm") iconsize=80;

   $("body").data("mapcenterlat", maplat);
   $("body").data("mapcenterlong", maplon);
   $("body").data("mapzoom", mapzoom);

   map = new L.Map('map');
   Modernizr.load({
   test: Modernizr.geolocation,
   yep : 'js/geo.js'
   });

   // create the tile layer with correct attribution
   var osmUrl='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
   var osmAttrib='Map data (c) <a href="http://openstreetmap.org">OpenStreetMap</a> contributors';
   var osm = new L.TileLayer(osmUrl, {minZoom: 8, maxZoom: 19, attribution: osmAttrib});
   map.setView(new L.LatLng($("body").data("mapcenterlat"), $("body").data("mapcenterlong")), $("body").data("mapzoom"));
   map.addLayer(osm);
   sidebar = L.control.sidebar('sidebar', {
        position: 'left'
        });
   map.addControl(sidebar);
   getmarkers();
   getuserstatus();
   resetconsole();
   sidebar.show();

}

function getmarkers()
{
   markers=[]; markerdata=[];
   $.ajax({
         global: false,
         url: "command.php?action=map:markers"
         }).done(function(jsonresponse) {
            jsonobject=$.parseJSON(jsonresponse);
            for (var i=0, len=jsonobject.length; i < len; i++)
               {

               if (jsonobject[i].bikecount==0)
                  {
                  tempicon=L.divIcon({
                     iconSize: [iconsize, iconsize],
                     iconAnchor: [iconsize/2, 0],
                     html: '<dl class="icondesc none" id="stand-'+jsonobject[i].standName+'"><dt class="bikecount">'+jsonobject[i].bikecount+'</dt><dd class="standname">'+jsonobject[i].standName+'</dd></dl>',
                     standid: jsonobject[i].standId
                  });
                  }
               else
                  {
                  tempicon=L.divIcon({
                     iconSize: [iconsize, iconsize],
                     iconAnchor: [iconsize/2, 0],
                     html: '<dl class="icondesc" id="stand-'+jsonobject[i].standName+'"><dt class="bikecount">'+jsonobject[i].bikecount+'</dt><dd class="standname">'+jsonobject[i].standName+'</dd></dl>',
                     standid: jsonobject[i].standId
                  });
                  }

               markerdata[jsonobject[i].standId]={name:jsonobject[i].standName,desc:jsonobject[i].standDescription,count:jsonobject[i].bikecount};
               markers[jsonobject[i].standId] = L.marker([jsonobject[i].lat, jsonobject[i].lon], { icon: tempicon }).addTo(map).on("click", showstand );
               nameid[jsonobject[i].standName]=jsonobject[i].standId; // creates reverse relation - for matching name to id purposes
               $('body').data('markerdata',markerdata);
               }
         });
}

function getuserstatus()
{
   $.ajax({
         global: false,
         url: "command.php?action=map:status"
         }).done(function(jsonresponse) {
            jsonobject=$.parseJSON(jsonresponse);
            $('body').data('limit',jsonobject.limit);
            $('body').data('rented',jsonobject.rented);
            togglebikeactions();
         });
}

function showstand(e)
{
   standselected=1;
   sidebar.show();
   toggleadminactions();
   rentedbikes();
   checkonebikeattach();
   if ($.isNumeric(e)) standid=e; // passed via manual call
   else
      {
      ga('send', 'event', 'buttons', 'click', 'stand-select');
      standid=e.target.options.icon.options.standid; // passed via event call
      resetconsole();
      }
   resetbutton("rent");
   markerdata=$('body').data('markerdata');

   if (markerdata[standid].count>0)
      {
      if (markerdata[standid].count==1)
         {
         $('#standname').html(markerdata[standid].name+' <span class="label label-success" id="standcount">'+window.markerdata[standid].count+' bicycle:</span>');
         }
      else
         {
         $('#standname').html(markerdata[standid].name+' <span class="label label-success" id="standcount">'+window.markerdata[standid].count+' bicycles:</span>');
         }
      $.ajax({
         global: false,
         url: "command.php?action=list&stand="+markerdata[standid].name
         }).done(function(jsonresponse) {
            jsonobject=$.parseJSON(jsonresponse);
            handleresponse(jsonobject,0);
            bikelist="";
            if (jsonobject.content!="")
               {
               for (var i=0, len=jsonobject.content.length; i < len; i++)
                  {
                  if (jsonobject.content[i][0]=="*" && $("body").data("limit")>0)
                     {
                     jsonobject.content[i]=jsonobject.content[i].replace("*","");
                     bikelist=bikelist+' <button type="button" class="btn btn-warning bikeid">'+jsonobject.content[i]+'</button>';
                     }
                  else if (jsonobject.content[i][0]=="*" && $("body").data("limit")==0)
                     {
                     jsonobject.content[i]=jsonobject.content[i].replace("*","");
                     bikelist=bikelist+' <button type="button" class="btn btn-default bikeid">'+jsonobject.content[i]+'</button>';
                     }
                  else if ($("body").data("limit")>0) bikelist=bikelist+' <button type="button" class="btn btn-success bikeid b'+jsonobject.content[i]+'">'+jsonobject.content[i]+'</button>';
                  else bikelist=bikelist+' <button type="button" class="btn btn-default bikeid">'+jsonobject.content[i]+'</button>';
                  }
               $('#standbikes').html('<div class="btn-group">'+bikelist+'</div>');
               $('#standbikes .bikeid').click( function() { ga('send', 'event', 'buttons', 'click', 'bike-number'); attachbicycleinfo(this,"rent"); });
               }
            else // no bicyles at stand
               {
               $('#standname').html(markerdata[standid].name+' <span class="label label-danger" id="standcount">No bicycles</span>');
               resetstandbikes();
               }

         });
      }
   else
      {
      $('#standname').html(markerdata[standid].name+' <span class="label label-danger" id="standcount">No bicycles</span>');
      resetstandbikes();
      }
   $('#standinfo').html(markerdata[standid].desc);
   togglestandactions(markerdata[standid].count);
   togglebikeactions();
}

function rentedbikes()
{
   $.ajax({
      global: false,
      url: "command.php?action=userbikes"
      }).done(function(jsonresponse) {
         jsonobject=$.parseJSON(jsonresponse);
         handleresponse(jsonobject,0);
         bikelist="";
         if (jsonobject.content!="")
            {
            for (var i=0, len=jsonobject.content.length; i < len; i++)
               {
               bikelist=bikelist+' <button type="button" class="btn btn-info bikeid b'+jsonobject.content[i]+'">'+jsonobject.content[i]+'</button>';
               }
            $('#rentedbikes').html('<div class="btn-group">'+bikelist+'</div>');
            $('#rentedbikes .bikeid').click( function() { attachbicycleinfo(this,"return"); attachbicycleinfo(this,"note"); });
            checkonebikeattach();
            }
         else
            {
            resetrentedbikes();
            }
      });
}

function togglestandactions(count)
{
   if (loggedin==0)
      {
      $('#standactions').hide();
      return false;
      }
   if (count==0 || $("body").data("limit")==0)
      {
      $('#standactions').hide();
      }
   else
      {
      $('#standactions').show();
      }
}

function togglebikeactions()
{
   if (loggedin==0)
      {
      $('.bicycleactions').hide();
      return false;
      }
   if ($('body').data('rented')==0 || standselected==0)
      {
      $('.bicycleactions').hide();
      }
   else
      {
      $('.bicycleactions').show();
      }
}

function toggleadminactions()
{
   if (priv==0)
      {
      $('.adminactions').hide();
      }
   else
      {
      $('.adminactions').show();
      }
}

function rent()
{
   if ($('#rent .bikenumber').html()=="") return false;
   ga('send', 'event', 'bikes', 'rent', $('#rent .bikenumber').html());
   $.ajax({
   url: "command.php?action=rent&bikeno="+$('#rent .bikenumber').html()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
      $('.b'+$('#rent .bikenumber').html()).remove();
      resetbutton("rent");
      $('body').data("limit",$('body').data("limit")-1);
      if ($("body").data("limit")<0) $("body").data("limit",0);
      standname=$('#standname').clone().children().remove().end().text().trim();
      markerdata=$('body').data('markerdata');
      standbiketotal=markerdata[nameid[standname]].count;
      if (jsonobject.error==0)
         {
         standbiketotal=(standbiketotal*1)-1;
         markerdata[nameid[standname]].count=standbiketotal
         $('body').data('markerdata',markerdata);
         }
      if (standbiketotal==0)
         {
         $('#standcount').removeClass('label-success');
         $('#standcount').addClass('label-danger');
         }
      getmarkers();
      getuserstatus();
      showstand(nameid[standname]);
   });
}

function returnbike()
{
   standname=$('#standname').clone().children().remove().end().text().trim();
   ga('send', 'event', 'bikes', 'return', $('#return .bikenumber').html());
   ga('send', 'event', 'stands', 'return', standname);
   $.ajax({
   url: "command.php?action=return&bikeno="+$('#return .bikenumber').html()+"&stand="+standname
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
      $('.b'+$('#return .bikenumber').html()).remove();
      $('.b'+$('#note .bikenumber').html()).remove();
      resetbutton("return"); resetbutton("note");
      markerdata=$('body').data('markerdata');
      standbiketotal=markerdata[nameid[standname]].count;
      if (jsonobject.error==0)
         {
         standbiketotal=(standbiketotal*1)+1;
         markerdata[nameid[standname]].count=standbiketotal
         $('body').data('markerdata',markerdata);
         }
      if (standbiketotal==0)
         {
         $('#standcount').removeClass('label-success');
         $('#standcount').addClass('label-danger');
         }
      getmarkers();
      getuserstatus();
      showstand(nameid[standname]);
   });
}

function where()
{
   ga('send', 'event', 'bikes', 'where', $('#adminparam').val());
   $.ajax({
   url: "command.php?action=where&bikeno="+$('#adminparam').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
   });
}

function last()
{
   ga('send', 'event', 'bikes', 'last', $('#adminparam').val());
   $.ajax({
   url: "command.php?action=last&bikeno="+$('#adminparam').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
   });
}

function revert()
{
   ga('send', 'event', 'bikes', 'revert', $('#adminparam').val());
   $.ajax({
   url: "command.php?action=revert&bikeno="+$('#adminparam').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
      getmarkers();
      getuserstatus();
   });
}

function attachbicycleinfo(element,attachto)
{
   $('#'+attachto+' .bikenumber').html($(element).html());
   if ($(element).hasClass('btn-warning')) $('#console').html('<div class="alert alert-warning" role="alert">This bicycle might have some problem!</div>');
   else if ($('#console div').hasClass('alert-warning')) resetconsole();
}

function checkonebikeattach()
{
   if ($("#rentedbikes .btn-group").length==1)
      {
      element=$("#rentedbikes .btn-group .btn");
      attachbicycleinfo(element,"return");
      attachbicycleinfo(element,"note");
      }
}

function handleresponse(jsonobject,display)
{
   if (display==undefined)
      {
      if (jsonobject.error==1)
         {
         $('#console').html('<div class="alert alert-danger" role="alert">'+jsonobject.content+'</div>').fadeIn();
         }
      else
         {
         $('#console').html('<div class="alert alert-success" role="alert">'+jsonobject.content+'</div>');
         }
      }
   if (jsonobject.limit)
      {
      if (jsonobject.limit) $("body").data("limit",jsonobject.limit);
      }
}

function resetconsole()
{
   $('#console').html('');
}

function resetbutton(attachto)
{
   $('#'+attachto+' .bikenumber').html('');
}

function resetstandbikes()
{
   $('#standbikes').html('');
}

function resetrentedbikes()
{
   $('#rentedbikes').html('');
}

function savegeolocation()
{
   $.ajax({
   url: "command.php?action=map:geolocation&lat="+$("body").data("mapcenterlat")+"&long="+$("body").data("mapcenterlong")
   }).done(function(jsonresponse) {
      console.log(jsonresponse);
      return;
   });
}