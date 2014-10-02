navigator.geolocation.getCurrentPosition(GetLocation);
function GetLocation(location) {
   $("body").data("mapcenterlat", location.coords.latitude);
   $("body").data("mapcenterlong", location.coords.longitude);
   $("body").data("mapzoom", $("body").data("mapzoom")+1);

   // 80 m x 5 mins walking distance
   var circle = L.circle([$("body").data("mapcenterlat"), $("body").data("mapcenterlong")],80*5, {
   color: 'green',
   fillColor: '#0f0',
   fillOpacity: 0.1
   }).addTo(map);

   map.setView(new L.LatLng($("body").data("mapcenterlat"), $("body").data("mapcenterlong")), $("body").data("mapzoom"));
   ga('send', 'event', 'geolocation', 'latlong', $("body").data("mapcenterlat")+","+$("body").data("mapcenterlong"));
   savegeolocation();

}