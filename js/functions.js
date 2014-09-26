var markers=[]; var iconsize=60; var sidebar;

$(document).ready(function(){
//TODO refresh user limit, used stand bicycle total + bicycle list, buttons after each action (rent/return/note)
   $('#standactions').hide();
   $(".bicycleactions").hide();
   $(document).ajaxStart(function() { $('#console').html('<img src="img/loading.gif" alt="loading" />'); });
   $("#rent").click(function() { rent(); });
   $(".return").click(function() { returnbike(); });
   $("#where").click(function() { where(); });
   $("#last").click(function() { last(); });
   $("#revert").click(function() { revert(); });
   mapinit();

});

function mapinit()
{
   var viewport = $.viewportDetect(); // ("xs", "sm", "md", or "lg");
   if (viewport=="xs" || viewport=="sm") iconsize=100;

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
   var osm = new L.TileLayer(osmUrl, {minZoom: 8, maxZoom: 18, attribution: osmAttrib});
   map.setView(new L.LatLng($("body").data("mapcenterlat"), $("body").data("mapcenterlong")), $("body").data("mapzoom"));
   map.addLayer(osm);
   sidebar = L.control.sidebar('sidebar', {
        position: 'left'
        });
   map.addControl(sidebar);
   sidebar.show();

   getmarkers();
   getuserstatus();
   resetconsole();
}

function getmarkers()
{
   markers=[];
   window.markerdata=[];
   $.ajax({
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
                     html: '<dl class="icondesc none" id="stand-'+jsonobject[i].placename+'"><dt class="bikecount">'+jsonobject[i].bikecount+'</dt><dd class="standname">'+jsonobject[i].placename+'</dd></dl>',
                     standid: jsonobject[i].standId
                  });
                  }
               else
                  {
                  tempicon=L.divIcon({
                     iconSize: [iconsize, iconsize],
                     iconAnchor: [iconsize/2, 0],
                     html: '<dl class="icondesc" id="stand-'+jsonobject[i].placename+'"><dt class="bikecount">'+jsonobject[i].bikecount+'</dt><dd class="standname">'+jsonobject[i].placename+'</dd></dl>',
                     standid: jsonobject[i].standId
                  });
                  }

               window.markerdata[jsonobject[i].standId]={name:jsonobject[i].placename,desc:jsonobject[i].standDescription,count:jsonobject[i].bikecount};
               markers[jsonobject[i].standId] = L.marker([jsonobject[i].lat, jsonobject[i].lon], { icon: tempicon }).addTo(map).on("click", showstand );
               }
         });
}

function getuserstatus()
{
   $.ajax({
         url: "command.php?action=map:status"
         }).done(function(jsonresponse) {
            jsonobject=$.parseJSON(jsonresponse);
            $('body').data('limit',jsonobject.limit);
            $('body').data('rented',jsonobject.rented);
         });
}

function showstand(e)
{
   sidebar.show();
   standid=e.target.options.icon.options.standid;
   resetrentbutton()
   resetconsole();
   if (window.markerdata[standid].count==0)
      {
      $('#standname').html(window.markerdata[standid].name+' <span class="label label-danger" id="standcount">No bicycles</span>');
      resetstandbikes();
      }
   else if (window.markerdata[standid].count==1)
      {
      $('#standname').html(window.markerdata[standid].name+' <span class="label label-success" id="standcount">'+window.markerdata[standid].count+' bicycle:</span>');
      }
   else
      {
      $('#standname').html(window.markerdata[standid].name+' <span class="label label-success" id="standcount">'+window.markerdata[standid].count+' bicycles:</span>');
      }
   if (window.markerdata[standid].count>0)
      {
      $.ajax({
         url: "command.php?action=list&stand="+window.markerdata[standid].name
         }).done(function(jsonresponse) {
            jsonobject=$.parseJSON(jsonresponse);
            handleresponse(jsonobject,0);
            bikelist="";
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
            $('.bikeid').click( function() { attachbicycleinfo(this); });
         });
      }
   showbuttons(window.markerdata[standid].count);
}

function showbuttons(count)
{
   if (count==0 || $("body").data("limit")==0)
      {
      $('#standactions').hide();
      }
   else
      {
      $('#standactions').show();
      }
   $(".bicycleactions").show();
}

function rent()
{
   if ($('#rent .bikenumber').html()=="") return false;
   $.ajax({
   url: "command.php?action=rent&bikeno="+$('#rent .bikenumber').html()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
      $('.b'+$('#rent .bikenumber').html()).remove();
      resetrentbutton();
      $('body').data("limit",$('body').data("limit")-1);
      if ($("body").data("limit")<0) $("body").data("limit",0);
      standname=$('#standname').clone().children().remove().end().text();
      standbiketotal=$('#stand-'+standname+' .bikecount').html();
      standbiketotal=standbiketotal-1;
      $('#stand-'+standname+' .bikecount').html(standbiketotal);
      $('#standcount').html(standbiketotal+' bicycles:');
      if (standbiketotal==0)
         {
         $('#standcount').removeClass('label-success');
         $('#standcount').addClass('label-danger');
         }
      getmarkers();
      getuserstatus();
   });
}

function returnbike()
{
   $.ajax({
   url: "command.php?action=return&bikeno="+$('.return .bikenumber').html()+"&stand="+$('#standname').clone().children().remove().end().text()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
      getmarkers();
      getuserstatus();
      if ($("body").data("rented")==0) $(".bicycleactions").hide();
   });
}

function where()
{
   $.ajax({
   url: "command.php?action=where&bikeno="+$('#adminparam').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
   });
}

function last()
{
   $.ajax({
   url: "command.php?action=last&bikeno="+$('#adminparam').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
   });
}

function list()
{
   $.ajax({
   url: "command.php?action=list&stand="+$('#adminparam').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
   });
}

function revert()
{
   $.ajax({
   url: "command.php?action=revert&bikeno="+$('#adminparam').val()
   }).done(function(jsonresponse) {
      jsonobject=$.parseJSON(jsonresponse);
      handleresponse(jsonobject);
      getmarkers();
      getuserstatus();
   });
}

function attachbicycleinfo(element)
{
   $('#rent .bikenumber').html($(element).html());
   if ($(element).hasClass('btn-warning')) $('#console').html('<div class="alert alert-warning" role="alert">This bicycle might have some problem!</div>');
   else resetconsole();
}

function handleresponse(jsonobject,display)
{
   resetconsole();
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

function resetrentbutton()
{
   $('#rent .bikenumber').html('');
}

function resetstandbikes()
{
   $('#standbikes').html('');
}