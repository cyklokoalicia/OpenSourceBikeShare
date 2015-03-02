var circle;

$(document).ready(function(){
   $('#countrycodeblock').hide();
   map = new L.Map('map');
   // create the tile layer with correct attribution
   var osmUrl='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
   var osmAttrib='Map data (c) <a href="http://openstreetmap.org">OpenStreetMap</a> contributors';
   var osm = new L.TileLayer(osmUrl, {minZoom: 3, maxZoom: 19, attribution: osmAttrib});
   if ("geolocation" in navigator) {
      navigator.geolocation.getCurrentPosition(function(position) {
         $("#systemlat").val(position.coords.latitude);
         $("#systemlong").val(position.coords.longitude);
         map.setView(new L.LatLng(position.coords.latitude,position.coords.longitude),13);
         circle = L.circle([position.coords.latitude,position.coords.longitude], 50, {
            color: 'red',
            fillColor: '#f03',
            fillOpacity: 0.5
         }).addTo(map);
      });
   }
   map.addLayer(osm);
   map.on('click', function(e) {
      $("#systemlat").val(e.latlng.lat);
      $("#systemlong").val(e.latlng.lng);
      map.removeLayer(circle);
      circle = L.circle([e.latlng.lat,e.latlng.lng], 50, {
         color: 'red',
         fillColor: '#f03',
         fillOpacity: 0.5
      }).addTo(map);
   });
   $('#smsconnector').click(function () { if ($('#smsconnector option:selected').val()=="") $('#countrycodeblock').hide(); else $('#countrycodeblock').fadeIn(500); } );
});