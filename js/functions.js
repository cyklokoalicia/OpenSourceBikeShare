var markers=[]; var markerdata=[]; var iconsize=60; var sidebar; var firstrun=1;
var watchID, circle, polyline; var temp="";

$(document).ready(function(){
   $('#overlay').hide();
   $('#standactions').hide();
   $('.bicycleactions').hide();
   $('#notetext').hide();
   $('#couponblock').hide();
   $('#passwordresetblock').hide();
   $("#rent").hide();
   $(document).ajaxStart(function() { $('#overlay').show(); });
   $(document).ajaxStop(function() { $('#overlay').hide(); });
   $("#password").focus(function() { $('#passwordresetblock').show(); });
   $("#resetpassword").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'password-reset'); resetpassword(); });
   $("#rent").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-rent'); rent(); });
   $("#return").click(function(e) { if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-return'); returnbike(); });
   $("#note").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-note'); note(); });
   $('#stands').change(function() { showstand($('#stands').val()); }).keyup(function() { showstand($('#stands').val()); });
   if ($('usercredit'))
      {
      $("#opencredit").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'credit-enter'); $('#couponblock').toggle(); });
      $("#validatecoupon").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'credit-add'); validatecoupon(); });
      }
   mapinit();
   setInterval(getmarkers, 60000); // refresh map every 60 seconds
   setInterval(getuserstatus, 60000); // refresh map every 60 seconds
   if ("geolocation" in navigator) {
   navigator.geolocation.getCurrentPosition(showlocation,function(){ return; },{enableHighAccuracy:true,maximumAge:30000});
   watchID=navigator.geolocation.watchPosition(changelocation,function(){ return; },{enableHighAccuracy:true,maximumAge:15000});
   }
});

function mapinit()
{

   $("body").data("mapcenterlat", maplat);
   $("body").data("mapcenterlong", maplon);
   $("body").data("mapzoom", mapzoom);

   map = new L.Map('map');

   // create the tile layer with correct attribution
   var osmUrl='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
   var osmAttrib='Map data (c) <a href="http://openstreetmap.org">OpenStreetMap</a> contributors';
   var osm = new L.TileLayer(osmUrl, {minZoom: 8, maxZoom: 19, attribution: osmAttrib});

   var today = new Date();
   if (today.getMonth()+'.'+today.getDate()=='3.1') // april fools
      {
      var osm = new L.StamenTileLayer("toner");
      }

   map.setView(new L.LatLng($("body").data("mapcenterlat"), $("body").data("mapcenterlong")), $("body").data("mapzoom"));
   map.addLayer(osm);
   sidebar = L.control.sidebar('sidebar', {
        position: 'left'
        });
   map.addControl(sidebar);
   getmarkers();
   $('link[rel="points"]').each(function() {
      geojsonurl=$(this).attr("href");
      $.getJSON(geojsonurl, function(data) {
         var geojson = L.geoJson(data, {
            onEachFeature: function (feature, layer) { layer.bindPopup(feature.properties.name); },
            pointToLayer: function (feature, latlng) {
               return L.circleMarker(latlng, {
                  radius: 8,
                  fillColor: "#ff7800",
                  color: "#000",
                  weight: 1,
                  opacity: 1,
                  fillOpacity: 0.8
               });
            }
         });
      geojson.addTo(map);
      });
   });
   getuserstatus();
   resetconsole();
   rentedbikes();
   sidebar.show();

}

function getmarkers()
{
   $.ajax({
         global: false,
         url: "command.php?action=map:markers"
         }).done(function(jsonresponse) {
            jsonobject=$.parseJSON(jsonresponse);
            for (var i=0, len=jsonobject.markers.length; i < len; i++)
               {

               if (jsonobject.markers[i].bikecount==0)
                  {
                  tempicon=L.divIcon({
                     iconSize: [iconsize, iconsize],
                     iconAnchor: [iconsize/2, 0],
                     html: '<dl class="icondesc none" id="stand-'+jsonobject.markers[i].standName+'"><dt class="bikecount">'+jsonobject.markers[i].bikecount+'</dt><dd class="standname">'+jsonobject.markers[i].standName+'</dd></dl>',
                     standid: jsonobject.markers[i].standId
                  });
                  }
               else
                  {
                  tempicon=L.divIcon({
                     iconSize: [iconsize, iconsize],
                     iconAnchor: [iconsize/2, 0],
                     html: '<dl class="icondesc" id="stand-'+jsonobject.markers[i].standName+'"><dt class="bikecount">'+jsonobject.markers[i].bikecount+'</dt><dd class="standname">'+jsonobject.markers[i].standName+'</dd></dl>',
                     standid: jsonobject.markers[i].standId
                  });
                  }

               markerdata[jsonobject.markers[i].standId]={name:jsonobject.markers[i].standName,desc:jsonobject.markers[i].standDescription,photo:jsonobject.markers[i].standPhoto,count:jsonobject.markers[i].bikecount};
               markers[jsonobject.markers[i].standId] = L.marker([jsonobject.markers[i].lat, jsonobject.markers[i].lon], { icon: tempicon }).addTo(map).on("click", showstand );
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
            if ($('usercredit')) $('#usercredit').html(jsonobject.usercredit);
            togglebikeactions();
         });
}

function createstandselector()
{
   var selectdata='<option value="del">-- '+_select_stand+' --</option>';
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
   rentedbikes();
   checkonebikeattach();
   if ($.isNumeric(e))
      {
      standid=e; // passed via manual call
      lat=markers[e]._latlng.lat;
      long=markers[e]._latlng.lng;
      }
   else
      {
      if (window.ga) ga('send', 'event', 'buttons', 'click', 'stand-select');
      standid=e.target.options.icon.options.standid; // passed via event call
      lat=e.latlng.lat;
      long=e.latlng.lng;
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
         $('#standcount').html(markerdata[standid].count+' '+_bicycle+':');
         }
      else
         {
         $('#standcount').html(markerdata[standid].count+' '+_bicycles+':');
         }
      $.ajax({
         global: false,
         url: "command.php?action=list&stand="+markerdata[standid].name
         }).done(function(jsonresponse) {
            jsonobject=$.parseJSON(jsonresponse);
            handleresponse(jsonobject,0);
            bikelist="";
            if (jsonobject.bicycles!="")
               {
               for (var i=0, len=jsonobject.bicycles.standcount; i < len; i++)
                  {
                  bikeissue=0;
                  if (jsonobject.bicycles.notes[i]!="")
                     {
                     bikeissue=1;
                     }
                  if (jsonobject.bicycles.stacktopbike==false) // bike stack is disabled, allow renting any bike
                     {
                     if (bikeissue==1 && $("body").data("limit")>0)
                        {
                        bikelist=bikelist+' <button type="button" class="btn btn-warning bikeid" data-id="'+jsonobject.bicycles.id[i]+'" data-note="'+jsonobject.bicycles.notes[i]+'">'+jsonobject.bicycles.id[i]+'</button>';
                        }
                     else if (bikeissue==1 && $("body").data("limit")==0)
                        {
                        bikelist=bikelist+' <button type="button" class="btn btn-default bikeid" data-id="'+jsonobject.bicycles.id[i]+'">'+jsonobject.bicycles.id[i]+'</button>';
                        }
                     else if ($("body").data("limit")>0) bikelist=bikelist+' <button type="button" class="btn btn-success bikeid b'+jsonobject.bicycles.id[i]+'" data-id="'+jsonobject.bicycles.id[i]+'">'+jsonobject.bicycles.id[i]+'</button>';
                     else bikelist=bikelist+' <button type="button" class="btn btn-default bikeid">'+jsonobject.bicycles.id[i]+'</button>';
                     }
                  else  // bike stack is enabled, allow renting top of the stack bike only
                     {
                     if (jsonobject.bicycles.stacktopbike==jsonobject.bicycles.id[i] && bikeissue==1 && $("body").data("limit")>0)
                        {
                        bikelist=bikelist+' <button type="button" class="btn btn-warning bikeid b'+jsonobject.bicycles.id[i]+'" data-id="'+jsonobject.bicycles.id[i]+'" data-note="'+jsonobject.bicycles.notes[i]+'">'+jsonobject.bicycles.id[i]+'</button>';
                        }
                     else if (jsonobject.bicycles.stacktopbike==jsonobject.bicycles.id[i] && bikeissue==1 && $("body").data("limit")==0)
                        {
                        bikelist=bikelist+' <button type="button" class="btn btn-default bikeid b'+jsonobject.bicycles.id[i]+'" data-id="'+jsonobject.bicycles.id[i]+'">'+jsonobject.bicycles.id[i]+'</button>';
                        }
                     else if (jsonobject.bicycles.stacktopbike==jsonobject.bicycles.id[i] && $("body").data("limit")>0) bikelist=bikelist+' <button type="button" class="btn btn-success bikeid b'+jsonobject.bicycles.id[i]+'" data-id="'+jsonobject.bicycles.id[i]+'">'+jsonobject.bicycles.id[i]+'</button>';
                     else bikelist=bikelist+' <button type="button" class="btn btn-default bikeid">'+jsonobject.bicycles.id[i]+'</button>';
                     }
                  }
               $('#standbikes').html('<div class="btn-group">'+bikelist+'</div>');
               if (jsonobject.bicycles.stacktopbike!=false) // bike stack is enabled, allow renting top of the stack bike only
                  {
                  $('.b'+jsonobject.bicycles.stacktopbike).click( function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-number'); attachbicycleinfo(this,"rent"); });
                  $('body').data('stacktopbike',jsonobject.bicycles.stacktopbike);
                  }
               else // bike stack is disabled, allow renting any bike
                  {
                  $('#standbikes .bikeid').click( function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-number'); attachbicycleinfo(this,"rent"); });
                  }
               }
            else // no bicyles at stand
               {
               $('#standcount').html(_no_bicycles);
               $('#standcount').removeClass('label label-success').addClass('label label-danger');
               resetstandbikes();
               }

         });
      }
   else
      {
      $('#standcount').html(_no_bicycles);
      $('#standcount').removeClass('label label-success').addClass('label label-danger');
      resetstandbikes();
      }
   walklink='';
   if ("geolocation" in navigator) // if geolocated, provide link to walking directions
      {
      walklink='<a href="https://www.google.com/maps?q='+$("body").data("mapcenterlat")+','+$("body").data("mapcenterlong")+'+to:'+lat+','+long+'&saddr='+$("body").data("mapcenterlat")+','+$("body").data("mapcenterlong")+'&daddr='+lat+','+long+'&output=classic&dirflg=w&t=m" target="_blank" title="'+_open_map+'">'+_walking_directions+'</a>';
      }
   if (loggedin==1 && markerdata[standid].photo)
      {
      walklink=walklink+' | ';
      $('#standinfo').html(markerdata[standid].desc+' ('+walklink+' <a href="'+markerdata[standid].photo+'" id="photo'+standid+'" title="'+_display_photo+'">'+_photo+'</a>)');
      $('#standphoto').hide();
      $('#standphoto').html('<img src="'+markerdata[standid].photo+'" alt="'+markerdata[standid].name+'" width="100%" />');
      $('#photo'+standid).click(function() { $('#standphoto').slideToggle(); return false; });
      }
   else if (loggedin==1)
      {
      $('#standinfo').html(markerdata[standid].desc);
      if (walklink) $('#standinfo').html(markerdata[standid].desc+' ('+walklink+')');
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
         if (jsonobject.userbikes!="")
            {
            for (var i=0, len=jsonobject.userbikes.bicycles.length; i < len; i++)
               {
               bikelist=bikelist+' <button type="button" class="btn btn-info bikeid b'+jsonobject.userbikes.bicycles[i]+'" data-id="'+jsonobject.userbikes.bicycles[i]+'" title="'+_currently_rented+'">'+jsonobject.userbikes.bicycles[i]+'<br /><span class="label label-primary">('+jsonobject.userbikes.codes[i]+')</span><br /><span class="label"><s>('+jsonobject.userbikes.oldcodes[i]+')</s></span></button> ';
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

function rent()
{
   if ($('#rent .bikenumber').html()=="") return false;
   if (window.ga) ga('send', 'event', 'bikes', 'rent', $('#rent .bikenumber').html());
   $.ajax({
   url: "command.php?action=rent&bikeno="+$('#rent .bikenumber').html()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
      resetbutton("rent");
      $('body').data("limit",$('body').data("limit")-1);
      if ($("body").data("limit")<0) $("body").data("limit",0);
      standid=$('#stands').val();
      markerdata=$('body').data('markerdata');
      standbiketotal=markerdata[standid].count;
      if (jsonobject.error==0)
         {
         $('.b'+$('#rent .bikenumber').html()).remove();
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
   if (window.ga) ga('send', 'event', 'bikes', 'return', $('#return .bikenumber').html());
   if (window.ga) ga('send', 'event', 'stands', 'return', standname);
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

function validatecoupon()
{
   $.ajax({
   url: "command.php?action=validatecoupon&coupon="+$('#coupon').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      temp=$('#couponblock').html();
      if (jsonobject.error==1)
         {
         $('#couponblock').html('<div class="alert alert-danger" role="alert">'+jsonobject.content+'</div>');
         setTimeout(function() { $('#couponblock').html(temp); $("#validatecoupon").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'credit-add'); validatecoupon(); }); },2500);
         }
      else
         {
         $('#couponblock').html('<div class="alert alert-success" role="alert">'+jsonobject.content+'</div>');
         getuserstatus();
         setTimeout(function() { $('#couponblock').html(temp); $('#couponblock').toggle(); $("#validatecoupon").click(function() { if (window.ga) ga('send', 'event', 'buttons', 'click', 'credit-add'); validatecoupon(); }); },2500);
         }
   });
}

function resetpassword()
{
   $('#passwordresetblock').hide();
   if (sms==0 && $('#number').val()>0)
      {
      $.ajax({
      url: "command.php?action=resetpassword&number="+$('#number').val()
      }).done(function(jsonresponse) {
         jsonobject=$.parseJSON(jsonresponse);
         handleresponse(jsonobject);
         });
      }
   else if (sms==1 && $('#number').val()>0)
      {
      window.location="register.php#reset"+$('#number').val();
      }
}

function attachbicycleinfo(element,attachto)
{
   $('#'+attachto+' .bikenumber').html($(element).attr('data-id'));
   // show warning, if exists:
   if ($(element).hasClass('btn-warning')) $('#console').html('<div class="alert alert-warning" role="alert">'+_reported_problem+' '+$(element).attr('data-note')+'</div>');
   // or hide warning, if bike without issue is clicked
   else if ($(element).hasClass('btn-warning')==false && $('#console div').hasClass('alert-warning')) resetconsole();
   $('#rent').show();
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
   if (window.ga) ga('send', 'event', 'geolocation', 'latlong', $("body").data("mapcenterlat")+","+$("body").data("mapcenterlong"));
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
      map.setView(new L.LatLng($("body").data("mapcenterlat"), $("body").data("mapcenterlong")), $("body").data("mapzoom"));
      if (window.ga) ga('send', 'event', 'geolocation', 'latlong', $("body").data("mapcenterlat")+","+$("body").data("mapcenterlong"));
      savegeolocation();
      }
}
