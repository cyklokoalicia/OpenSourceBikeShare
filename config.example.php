<?php
error_reporting(0);
define('DEBUG',FALSE); // debug mode, TRUE to turn on
define('ERROR',0);

$systemname="OpenSourceBikeShare";
$systemURL="https://github.com/mmmaly/OpenSourceBikeShare"; // needs trailing slash!
$systemlang="en_EN"; // language code such as en_EN, de_DE etc. - translation must be in languages/ directory, defaults to English if non-existent
$systemlat="48.148154"; // default map center point - latitude
$systemlong="17.117232"; // default map center point - longitude
$systemzoom="15"; // default map zoom
$systemrules="http://example.com/rules.htm"; // system rules / help URL

$limits["registration"]=0; // number of bikes user can rent after he registered: 0 = no bike, 1 = 1 bike etc.
$limits["increase"]=0; // allow more bike rentals in addition to user limit: 0 = not allowed, otherwise: temporary limit increase - number of bikes

/*** SMS related ***/
$countrycode=""; // international dialing code (country code prefix), no plus sign

/*** geoJSON files - uncomment line below to use, any number of geoJSON files can be included ***/
// $geojson[]="http://example.com/poi.json"; // example geojson file with points of interests to be displayed on the map

?>