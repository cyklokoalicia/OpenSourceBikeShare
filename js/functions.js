var markers=[]; var markerdata=[]; var iconsize=60; var sidebar; var firstrun=1;
var watchID, circle;

$(document).ready(function(){
   $('#standactions').hide();
   $('.bicycleactions').hide();
   $('.adminactions').hide();
   $('#notetext').hide();
   $(document).ajaxStart(function() { $('#console').html('<img src="img/loading.gif" alt="loading" id="loading" />'); });
   $(document).ajaxComplete(function() { $('#loading').remove(); });
   $("#rent").click(function() { ga('send', 'event', 'buttons', 'click', 'bike-rent'); rent(); });
   $("#return").click(function(e) { ga('send', 'event', 'buttons', 'click', 'bike-return'); returnbike(); });
   $("#note").click(function() { ga('send', 'event', 'buttons', 'click', 'bike-note'); note(); });
   $("#where").click(function() { ga('send', 'event', 'buttons', 'click', 'admin-where'); where(); });
   $("#last").click(function() { ga('send', 'event', 'buttons', 'click', 'admin-last'); last(); });
   $("#revert").click(function() { ga('send', 'event', 'buttons', 'click', 'admin-revert'); revert(); });
   $('#stands').change(function() { showstand($('#stands').val()); }).keyup(function() { showstand($('#stands').val()); });
   mapinit();
   setInterval(getmarkers, 60000); // refresh map every 60 seconds
   setInterval(getuserstatus, 60000); // refresh map every 60 seconds
   if ("geolocation" in navigator) {
   navigator.geolocation.getCurrentPosition(showlocation);
   watchID=navigator.geolocation.watchPosition(changelocation);
   }
});

function mapinit()
{
   var viewport = $.viewportDetect(); // ("xs", "sm", "md", or "lg");
   if (viewport=="xs" || viewport=="sm") iconsize=80;

   $("body").data("mapcenterlat", maplat);
   $("body").data("mapcenterlong", maplon);
   $("body").data("mapzoom", mapzoom);

   map = new L.Map('map');

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
   rentedbikes();
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

               markerdata[jsonobject[i].standId]={name:jsonobject[i].standName,desc:jsonobject[i].standDescription,photo:jsonobject[i].standPhoto,count:jsonobject[i].bikecount};
               markers[jsonobject[i].standId] = L.marker([jsonobject[i].lat, jsonobject[i].lon], { icon: tempicon }).addTo(map).on("click", showstand );
               $('body').data('markerdata',markerdata);
               }
            if (firstrun==1)
               {
               createstandselector();
               firstrun=0;
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

function createstandselector()
{
   var selectdata='<option value="del">-- Select stand --</option>';
   $.each( markerdata, function( key, value ) {
   if (value!=undefined)
      {
      selectdata=selectdata+'<option value="'+key+'">'+value.name+'</option>';
      }
   });
   $('#stands').html(selectdata);
   var options = $('#stands option');
   var arr = options.map(function(_, o) { return { t: $(o).text(), v: o.value }; }).get();
   arr.sort(function(o1, o2) { return o1.t > o2.t ? 1 : o1.t < o2.t ? -1 : 0; });
   options.each(function(i, o) {
   o.value = arr[i].v;
   $(o).text(arr[i].t);
   });
}

function showstand(e,clear)
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
      }
   if (clear!=0)
      {
      resetconsole();
      }
   resetbutton("rent");
   markerdata=$('body').data('markerdata');

   $('#stands').val(standid);
   $('#stands option[value="del"]').remove();
   if (markerdata[standid].count>0)
      {
      $('#standcount').removeClass('label label-danger').addClass('label label-success');
      if (markerdata[standid].count==1)
         {
         $('#standcount').html(markerdata[standid].count+' bicycle:');
         }
      else
         {
         $('#standcount').html(markerdata[standid].count+' bicycles:');
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
                  bikeissue=0;
                  if (jsonobject.content[i][0]=="*")
                     {
                     bikeissue=1;
                     jsonobject.content[i]=jsonobject.content[i].replace("*","");
                     }
                  if (jsonobject.stacktopbike==false) // bike stack is disabled, allow renting any bike
                     {
                     if (bikeissue==1 && $("body").data("limit")>0)
                        {
                        bikelist=bikelist+' <button type="button" class="btn btn-warning bikeid" data-id="'+jsonobject.content[i]+'" data-note="'+jsonobject.notes[i]+'">'+jsonobject.content[i]+'</button>';
                        }
                     else if (bikeissue==1 && $("body").data("limit")==0)
                        {
                        bikelist=bikelist+' <button type="button" class="btn btn-default bikeid" data-id="'+jsonobject.content[i]+'">'+jsonobject.content[i]+'</button>';
                        }
                     else if ($("body").data("limit")>0) bikelist=bikelist+' <button type="button" class="btn btn-success bikeid b'+jsonobject.content[i]+'" data-id="'+jsonobject.content[i]+'">'+jsonobject.content[i]+'</button>';
                     else bikelist=bikelist+' <button type="button" class="btn btn-default bikeid">'+jsonobject.content[i]+'</button>';
                     }
                  else  // bike stack is enabled, allow renting top of the stack bike only
                     {
                     if (jsonobject.stacktopbike==jsonobject.content[i] && bikeissue==1 && $("body").data("limit")>0)
                        {
                        bikelist=bikelist+' <button type="button" class="btn btn-warning bikeid b'+jsonobject.content[i]+'" data-id="'+jsonobject.content[i]+'" data-note="'+jsonobject.notes[i]+'">'+jsonobject.content[i]+'</button>';
                        }
                     else if (jsonobject.stacktopbike==jsonobject.content[i] && bikeissue==1 && $("body").data("limit")==0)
                        {
                        bikelist=bikelist+' <button type="button" class="btn btn-default bikeid b'+jsonobject.content[i]+'" data-id="'+jsonobject.content[i]+'">'+jsonobject.content[i]+'</button>';
                        }
                     else if (jsonobject.stacktopbike==jsonobject.content[i] && $("body").data("limit")>0) bikelist=bikelist+' <button type="button" class="btn btn-success bikeid b'+jsonobject.content[i]+'" data-id="'+jsonobject.content[i]+'">'+jsonobject.content[i]+'</button>';
                     else bikelist=bikelist+' <button type="button" class="btn btn-default bikeid">'+jsonobject.content[i]+'</button>';
                     }
                  }
               $('#standbikes').html('<div class="btn-group">'+bikelist+'</div>');
               if (jsonobject.stacktopbike!=false) // bike stack is enabled, allow renting top of the stack bike only
                  {
                  $('.b'+jsonobject.stacktopbike).click( function() { ga('send', 'event', 'buttons', 'click', 'bike-number'); attachbicycleinfo(this,"rent"); });
                  $('body').data('stacktopbike',jsonobject.stacktopbike);
                  }
               else // bike stack is disabled, allow renting any bike
                  {
                  $('#standbikes .bikeid').click( function() { ga('send', 'event', 'buttons', 'click', 'bike-number'); attachbicycleinfo(this,"rent"); });
                  }
               }
            else // no bicyles at stand
               {
               $('#standcount').html('No bicycles');
               $('#standcount').removeClass('label label-success').addClass('label label-danger');
               resetstandbikes();
               }

         });
      }
   else
      {
      $('#standcount').html('No bicycles');
      $('#standcount').removeClass('label label-success').addClass('label label-danger');
      resetstandbikes();
      }
   if (loggedin==1 && markerdata[standid].photo)
      {
      $('#standinfo').html(markerdata[standid].desc+' (<a href="'+markerdata[standid].photo+'" id="photo'+standid+'">photo</a>)');
      $('#standphoto').hide();
      $('#standphoto').html('<img src="'+markerdata[standid].photo+'" alt="'+markerdata[standid].name+'" width="100%" />');
      $('#photo'+standid).click(function() { $('#standphoto').slideToggle(); return false; });
      }
   else if (loggedin==1)
      {
      $('#standinfo').html(markerdata[standid].desc);
      $('#standphoto').hide();
      }
   else
      {
      $('#standinfo').hide();
      $('#standphoto').hide();
      }
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
               bikelist=bikelist+' <button type="button" class="btn btn-info bikeid b'+jsonobject.content[i]+'" data-id="'+jsonobject.content[i]+'">'+jsonobject.content[i]+'<br /><span class="label label-default">('+jsonobject.codes[i]+')</span></button> ';
               }
            $('#rentedbikes').html('<div class="btn-group">'+bikelist+'</div>');
            $('#rentedbikes .bikeid').click( function() { attachbicycleinfo(this,"return"); });
            checkonebikeattach();
            }
         else
            {
            resetrentedbikes();
            }
      });
}

function note()
{
   $('#notetext').slideToggle();
   $('#notetext').val('');
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
   if (priv<1)
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
      standid=$('#stands').val();
      markerdata=$('body').data('markerdata');
      standbiketotal=markerdata[standid].count;
      if (jsonobject.error==0)
         {
         standbiketotal=(standbiketotal*1)-1;
         markerdata[standid].count=standbiketotal;
         $('body').data('markerdata',markerdata);
         }
      if (standbiketotal==0)
         {
         $('#standcount').removeClass('label-success').addClass('label-danger');
         }
      else
         {
         $('#standcount').removeClass('label-danger').addClass('label-success');
         }
      $('#notetext').val('');
      $('#notetext').hide();
      getmarkers();
      getuserstatus();
      showstand(standid,0);
   });
}

function returnbike()
{
   note="";
   standname=$('#stands option:selected').text();
   standid=$('#stands').val();
   ga('send', 'event', 'bikes', 'return', $('#return .bikenumber').html());
   ga('send', 'event', 'stands', 'return', standname);
   if ($('#notetext').val()) note="&note="+$('#notetext').val();
   $.ajax({
   url: "command.php?action=return&bikeno="+$('#return .bikenumber').html()+"&stand="+standname+note
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
      $('.b'+$('#return .bikenumber').html()).remove();
      resetbutton("return");
      markerdata=$('body').data('markerdata');
      standbiketotal=markerdata[standid].count;
      if (jsonobject.error==0)
         {
         standbiketotal=(standbiketotal*1)+1;
         markerdata[standid].count=standbiketotal
         $('body').data('markerdata',markerdata);
         }
      if (standbiketotal==0)
         {
         $('#standcount').removeClass('label-success');
         $('#standcount').addClass('label-danger');
         }
      $('#notetext').val('');
      $('#notetext').hide();
      getmarkers();
      getuserstatus();
      showstand(standid,0);
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
   $('#'+attachto+' .bikenumber').html($(element).attr('data-id'));
   if ($(element).hasClass('btn-warning')) $('#console').html('<div class="alert alert-warning" role="alert">Reported problem on this bicycle: '+$(element).attr('data-note')+'</div>');
   //else resetconsole();
}

function checkonebikeattach()
{
   if ($("#rentedbikes .btn-group").length==1)
      {
      element=$("#rentedbikes .btn-group .btn");
      attachbicycleinfo(element,"return");
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
   $('body').data('stacktopbike',false);
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


function showlocation(location)
{
   $("body").data("mapcenterlat", location.coords.latitude);
   $("body").data("mapcenterlong", location.coords.longitude);
   $("body").data("mapzoom", $("body").data("mapzoom")+1);

   // 80 m x 5 mins walking distance
   circle = L.circle([$("body").data("mapcenterlat"), $("body").data("mapcenterlong")],80*5, {
   color: 'green',
   fillColor: '#0f0',
   fillOpacity: 0.1
   }).addTo(map);

   map.setView(new L.LatLng($("body").data("mapcenterlat"), $("body").data("mapcenterlong")), $("body").data("mapzoom"));
   ga('send', 'event', 'geolocation', 'latlong', $("body").data("mapcenterlat")+","+$("body").data("mapcenterlong"));
   savegeolocation();
}

function changelocation(location)
{
   if (location.coords.latitude!=$("body").data("mapcenterlat") || location.coords.longitude!=$("body").data("mapcenterlong"))
      {
      $("body").data("mapcenterlat", location.coords.latitude);
      $("body").data("mapcenterlong", location.coords.longitude);
      map.removeLayer(circle);
      circle = L.circle([$("body").data("mapcenterlat"), $("body").data("mapcenterlong")],80*5, {
      color: 'green',
      fillColor: '#0f0',
      fillOpacity: 0.1
      }).addTo(map);
      }
}