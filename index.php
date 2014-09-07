<!--
    WhiteBikes map with points with bicycle availability
    Copyright (C) 2014 Daniel Duris | dusoft[at]staznosti[dot]sk

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
-->
<html>
<head>
<title>WhiteBikes map with availability</title>
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<link rel="stylesheet" type="text/css" href="leaflet/leaflet.css" />
<script type="text/javascript" src="leaflet/leaflet.js"></script>
<script>
$(document).ready(function(){

        map = new L.Map('map');

        // create the tile layer with correct attribution
        var osmUrl='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        var osmAttrib='Map data © <a href="http://openstreetmap.org">OpenStreetMap</a> contributors';
        var osm = new L.TileLayer(osmUrl, {minZoom: 8, maxZoom: 18, attribution: osmAttrib});

        map.setView(new L.LatLng(48.148154, 17.117232),15);
        map.addLayer(osm);

var bicycleicon = L.icon({
    iconUrl: 'icon.png',
    shadowUrl: '',

    iconSize:     [50, 50], // size of the icon
    shadowSize:   [0, 0], // size of the shadow
    iconAnchor:   [25, 25], // point of the icon which will correspond to marker's location
    shadowAnchor: [0, 0],  // the same for the shadow
    popupAnchor:  [0, -25] // point from which the popup should open relative to the iconAnchor
});


<?php
require("config.php");

$mysqli = new mysqli($dbServer,$dbUser,$dbPassword,$dbName);
if (mysqli_connect_errno())
   {
   printf("Connect failed: %s\n", mysqli_connect_error());
   exit();
   }
$result = $mysqli->query("SELECT count(bikeNum) as bikecount,standDescription,placeName as placename,longitude as lon, latitude as lat from stands left join bikes on bikes.currentStand=stands.standId where stands.serviceTag=0 group by placeName order by placeName") or die($mysqli->error.__LINE__);
// $result = $mysqli->query("SELECT placename,bikecount FROM bikes") // testing
$i=0; srand();
while($row = $result->fetch_assoc())
   {
   if (!$row["lat"])
      {
      $rand=rand(-100,100)*0.0001;
      $row["lat"]=48.150013+$rand;
      $row["lon"]=17.124543+$rand;
      }
   echo 'var marker',$i,' = L.marker([',$row["lat"],', ',$row["lon"],'], {icon: bicycleicon}).addTo(map);',"\n";
   echo 'marker',$i,'.bindPopup("<strong>',$row["placename"],'</strong><br/>',$row["standDescription"],'<br/>Bicycles available: ',$row["bikecount"],'");',"\n";
   echo 'marker',$i,'.on("mouseover", function(e){ marker',$i,'.openPopup(); });';
   $i++;
   }
mysqli_close($mysqli);
?>

});
</script>
</head>
<body>
<div id="map" style="margin: 0; padding: 0; width: 100%; height: 100%;"></div>
</body>
</html>
