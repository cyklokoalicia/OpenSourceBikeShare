<?php require("config.php"); ?>
<!--
    smsBikeShare map with points with bicycle availability
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
<title><? echo $systemName; ?> map with availability</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="js/viewportDetect.js"></script>
<script type="text/javascript" src="js/leaflet.js"></script>
<!--script type="text/javascript" src="js/bootstrap.min.js" --></script>
<script type="text/javascript" src="js/modernizr.custom.js"></script>
<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
<link rel="stylesheet" type="text/css" href="css/leaflet.css" />
<link rel="stylesheet" type="text/css" href="css/map.css" />
<script>
$(document).ready(function(){

        var viewport = $.viewportDetect(); // ("xs", "sm", "md", or "lg");
        var iconsize=60;
        if (viewport=="xs" || viewport=="sm") iconsize=100;

        $("body").data("mapcenterlat", <?php echo $systemLat; ?> );
        $("body").data("mapcenterlong", <?php echo $systemLong; ?> );
        $("body").data("mapzoom", <?php echo $systemZoom; ?> );

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

<?php
$mysqli = new mysqli($dbServer,$dbUser,$dbPassword,$dbName);
if (mysqli_connect_errno())
   {
   printf("Connect failed: %s\n", mysqli_connect_error());
   exit();
   }
$result = $mysqli->query("SELECT count(bikeNum) as bikecount,standDescription,placeName as placename,longitude as lon, latitude as lat from stands left join bikes on bikes.currentStand=stands.standId where stands.serviceTag=0 group by placeName order by placeName") or die($mysqli->error.__LINE__);
$i=0; srand();
while($row = $result->fetch_assoc())
   {
   if (!$row["lat"])
      {
      $rand=rand(-100,100)*0.0001;
      $row["lat"]=$systemLat+$rand;
      $row["lon"]=$systemLong+$rand;
      }
   if ($row["bikecount"]) // some available
      {
      echo 'var bicycleicon',$i,' = L.divIcon({
      iconSize:     [iconsize, iconsize], // size of the icon
      iconAnchor:   [iconsize/2, 0], // point of the icon which will correspond to marker location
      html: \'<dl class="icondesc"><dt class="bikecount">',$row["bikecount"],'</dt><dd class="standname">',$row["placename"],'</dd></dl>\'
      });
      ';
      }
   else // none available
      {
      echo 'var bicycleicon',$i,' = L.divIcon({
      iconSize:     [iconsize, iconsize], // size of the icon
      iconAnchor:   [iconsize/2, 0], // point of the icon which will correspond to marker location
      html: \'<dl class="icondesc none"><dt class="bikecount">',$row["bikecount"],'</dt><dd class="standname">',$row["placename"],'</dd></dl>\'
      });
      ';
      }
   echo 'var marker',$i,' = L.marker([',$row["lat"],', ',$row["lon"],'], {icon: bicycleicon',$i,'}).addTo(map);',"\n";
   echo 'marker',$i,'.bindPopup("<strong>',$row["placename"],'</strong><br/>',$row["standDescription"],'<br/>Bicycles available: ',$row["bikecount"],'");',"\n";
   //echo 'marker',$i,'.on("mouseover", function(e){ marker',$i,'.openPopup(); });';
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