var circle, maps=[];

$(document).ready(function(){
   $('.map').each( function() {
      mapno=$(this).attr("id").replace("map","");
      maps[mapno] = new L.Map('map'+mapno);
      // create the tile layer with correct attribution
      var osmUrl='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
      var osmAttrib='Map data (c) <a href="http://openstreetmap.org">OpenStreetMap</a> contributors';
      var osm = new L.TileLayer(osmUrl, {minZoom: 3, maxZoom: 19, attribution: osmAttrib});
      maps[mapno].setView(new L.LatLng(maplat,maplon),13);
      maps[mapno].addLayer(osm);
      maps[mapno].on('click', function(e) {
         mapno=e.target._container.id.replace("map","");
         if (circle!=undefined) maps[mapno].removeLayer(circle);
         $('#standlat-'+mapno).val(e.latlng.lat);
         $('#standlong-'+mapno).val(e.latlng.lng);
         circle = L.circle([e.latlng.lat,e.latlng.lng], 5, {
            color: 'red',
            fillColor: '#f03',
            fillOpacity: 0.5
         }).addTo(maps[mapno]);
      });
   });
});