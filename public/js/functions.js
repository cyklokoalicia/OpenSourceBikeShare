var markers = [];
var markerdata = [];
var iconsize = 60;
var sidebar;
var firstrun = 1;
var watchID, circle, polyline;
var temp = "";
$(document).ready(function () {
    $('#overlay').hide();
    $('#standactions').hide();
    $('.bicycleactions').hide();
    $('#notetext').hide();
    $('#couponblock').hide();
    $("#rent").hide();
    $(document).ajaxStart(function () {
        $('#overlay').show();
    });
    $(document).ajaxStop(function () {
        $('#overlay').hide();
    });
    $("#rent").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-rent');
        rent();
    });
    $("#return").click(function (e) {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-return');
        returnbike();
    });
    $("#note").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-note');
        note();
    });
    $('#stands').change(function () {
        showstand($('#stands').val());
    }).keyup(function () {
        showstand($('#stands').val());
    });
    if ($('usercredit')) {
        $("#opencredit").click(function () {
            if (window.ga) ga('send', 'event', 'buttons', 'click', 'credit-enter');
            $('#couponblock').toggle();
        });
        $("#validatecoupon").click(function () {
            if (window.ga) ga('send', 'event', 'buttons', 'click', 'credit-add');
            validatecoupon();
        });
    }

    /*$("#usercity").click(function() {
            if (window.ga) ga('send', 'event', 'buttons', 'click', 'credit-enter');
            //$('#citychange').toggle();
        });*/
    $("#citychange").change(function (e) {
        changecity();
        console.log($(this).val());
    });

    mapinit();
    geolocate();
    setInterval(getmarkers, 60000); // refresh map every 60 seconds
    setInterval(getuserstatus, 60000); // refresh map every 60 seconds
    setInterval(geolocate, 300000); // refresh map every 5 min
});

function mapinit() {
    $("body").data("mapcenterlat", maplat);
    $("body").data("mapcenterlong", maplon);
    $("body").data("mapzoom", mapzoom);
    map = new L.Map('map');
    // create the tile layer with correct attribution
    var osmUrl = '//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    var osmAttrib = 'Map data (c) <a href="//openstreetmap.org">OpenStreetMap</a> contributors';
    var osm = new L.TileLayer(osmUrl, {
        minZoom: 8,
        maxZoom: 19,
        attribution: osmAttrib
    });

    map.setView(new L.LatLng($("body").data("mapcenterlat"), $("body").data("mapcenterlong")), $("body").data("mapzoom"));
    map.addLayer(osm);
    sidebar = L.control.sidebar('sidebar', {
        position: 'left'
    });
    map.addControl(sidebar);
    getmarkers();
    $('link[rel="points"]').each(function () {
        geojsonurl = $(this).attr("href");
        $.getJSON(geojsonurl, function (data) {
            var geojson = L.geoJson(data, {
                onEachFeature: function (feature, layer) {
                    layer.bindPopup(feature.properties.name);
                },
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

function getmarkers() {
    $.ajax({
        global: false,
        url: "command.php?action=map:markers"
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        for (var i = 0, len = jsonobject.length; i < len; i++) {
            // ugly hack 2015-11-06 repair stand exception - special icon
            if (jsonobject[i].standName.indexOf('SERVIS') > -1) {
                tempicon = L.divIcon({
                    iconSize: [iconsize, iconsize],
                    iconAnchor: [iconsize / 2, 0],
                    html: '<dl class="icondesc special" id="stand-' + jsonobject[i].standName + '"><dt class="bikecount">' + jsonobject[i].bikecount + '</dt><dd class="standname">' + jsonobject[i].standName + '</dd></dl>',
                    standid: jsonobject[i].standId
                });
            } else if (jsonobject[i].bikecount == 0) {
                tempicon = L.divIcon({
                    iconSize: [iconsize, iconsize],
                    iconAnchor: [iconsize / 2, 0],
                    html: '<dl class="icondesc none" id="stand-' + jsonobject[i].standName + '"><dt class="bikecount">' + jsonobject[i].bikecount + '</dt><dd class="standname">' + jsonobject[i].standName + '</dd></dl>',
                    standid: jsonobject[i].standId
                });
            } else {
                tempicon = L.divIcon({
                    iconSize: [iconsize, iconsize],
                    iconAnchor: [iconsize / 2, 0],
                    html: '<dl class="icondesc" id="stand-' + jsonobject[i].standName + '"><dt class="bikecount">' + jsonobject[i].bikecount + '</dt><dd class="standname">' + jsonobject[i].standName + '</dd></dl>',
                    standid: jsonobject[i].standId
                });
            }
            markerdata[jsonobject[i].standId] = {
                name: jsonobject[i].standName,
                desc: jsonobject[i].standDescription,
                photo: jsonobject[i].standPhoto,
                count: jsonobject[i].bikecount
            };
            markers[jsonobject[i].standId] = L.marker([jsonobject[i].lat, jsonobject[i].lon], {
                icon: tempicon
            }).addTo(map).on("click", showstand);
            $('body').data('markerdata', markerdata);
        }
        if (firstrun == 1) {
            createstandselector();
            firstrun = 0;
        }
    });
}

function getuserstatus() {
    $.ajax({
        global: false,
        url: "command.php?action=map:status"
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        $('body').data('limit', jsonobject.limit);
        $('body').data('rented', jsonobject.rented);
        if ($('usercredit')) $('#usercredit').html(jsonobject.usercredit);
        togglebikeactions();
    });
}

function createstandselector() {
    var selectdata = '<option value="del">-- ' + _select_stand + ' --</option>';
    $.each(markerdata, function (key, value) {
        if (value != undefined) {
            selectdata = selectdata + '<option value="' + key + '">' + value.name + '</option>';
        }
    });
    $('#stands').html(selectdata);
    var options = $('#stands option');
    var arr = options.map(function (_, o) {
        return {
            t: $(o).text(),
            v: o.value
        };
    }).get();
    arr.sort(function (o1, o2) {
        return o1.t > o2.t ? 1 : o1.t < o2.t ? -1 : 0;
    });
    options.each(function (i, o) {
        o.value = arr[i].v;
        $(o).text(arr[i].t);
    });
}

function showstand(e, clear) {
    standselected = 1;
    sidebar.show();
    rentedbikes();
    checkonebikeattach();
    if ($.isNumeric(e)) {
        standid = e; // passed via manual call
        lat = markers[e]._latlng.lat;
        long = markers[e]._latlng.lng;
    } else {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'stand-select');
        standid = e.target.options.icon.options.standid; // passed via event call
        lat = e.latlng.lat;
        long = e.latlng.lng;
    }
    if (clear != 0) {
        resetconsole();
    }
    resetbutton("rent");
    markerdata = $('body').data('markerdata');
    $('#stands').val(standid);
    $('#stands option[value="del"]').remove();
    if (markerdata[standid].count > 0) {
        $('#standcount').removeClass('label label-danger').addClass('label label-success');
        if (markerdata[standid].count == 1) {
            $('#standcount').html(markerdata[standid].count + ' ' + _bicycle + ':');
        } else {
            $('#standcount').html(markerdata[standid].count + ' ' + _bicycles + ':');
        }
        $.ajax({
            global: false,
            url: "/api/stand/" + markerdata[standid].name + "/bike",
            dataType: "json"
        }).done(function (jsonobject) {
            let bikeList = '';
            let bikes = jsonobject.bikesOnStand || [];
            let stackTopBike = jsonobject.stackTopBike;
            if (bikes.length > 0) {
                for (var i = 0, len = bikes.length; i < len; i++) {
                    let bikeNum = bikes[i].bikeNum;
                    let note = bikes[i].notes ? bikes[i].notes : '';
                    let bikeIssue = note !== '';
                    if (stackTopBike === false) // bike stack is disabled, allow renting any bike
                    {
                        if (bikeIssue && $("body").data("limit") > 0) {
                            bikeList += ' <button type="button" class="btn btn-warning bikeid" data-id="' + bikeNum + '" data-note="' + note + '">' + bikeNum + '</button>';
                        } else if (bikeIssue && $("body").data("limit") == 0) {
                            bikeList += ' <button type="button" class="btn btn-default bikeid" data-id="' + bikeNum + '">' + bikeNum + '</button>';
                        } else if ($("body").data("limit") > 0) bikeList = bikeList + ' <button type="button" class="btn btn-success bikeid b' + bikeNum + '" data-id="' + bikeNum + '">' + bikeNum + '</button>';
                        else {
                            bikeList += ' <button type="button" class="btn btn-default bikeid">' + bikeNum + '</button>';
                        }
                    } else // bike stack is enabled, allow renting top of the stack bike only
                    {
                        if (stackTopBike == bikeNum && bikeIssue && $("body").data("limit") > 0) {
                            bikeList += ' <button type="button" class="btn btn-warning bikeid b' + bikeNum + '" data-id="' + bikeNum + '" data-note="' + note + '">' + bikeNum + '</button>';
                        } else if (stackTopBike == bikeNum && bikeIssue && $("body").data("limit") == 0) {
                            bikeList += ' <button type="button" class="btn btn-default bikeid b' + bikeNum + '" data-id="' + bikeNum + '">' + bikeNum + '</button>';
                        } else if (stackTopBike == bikeNum && $("body").data("limit") > 0) bikeList = bikeList + ' <button type="button" class="btn btn-success bikeid b' + bikeNum + '" data-id="' + bikeNum + '">' + bikeNum + '</button>';
                        else bikeList += ' <button type="button" class="btn btn-default bikeid">' + bikeNum + '</button>';
                    }
                }
                $('#standbikes').html('<div class="btn-group">' + bikeList + '</div>');
                if (stackTopBike !== false) // bike stack is enabled, allow renting top of the stack bike only
                {
                    $('.b' + stackTopBike).click(function () {
                        if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-number');
                        attachbicycleinfo(this, "rent");
                    });
                    $('body').data('stackTopBike', stackTopBike);
                } else // bike stack is disabled, allow renting any bike
                {
                    $('#standbikes .bikeid').click(function () {
                        if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-number');
                        attachbicycleinfo(this, "rent");
                    });
                }
            } else // no bicyles at stand
            {
                $('#standcount').html(_no_bicycles);
                $('#standcount').removeClass('label label-success').addClass('label label-danger');
                resetstandbikes();
            }
        });
    } else {
        $('#standcount').html(_no_bicycles);
        $('#standcount').removeClass('label label-success').addClass('label label-danger');
        resetstandbikes();
    }
    walklink = '';
    if ("geolocation" in navigator) // if geolocated, provide link to walking directions
    {
        walklink = '<a href="https://www.google.com/maps?q=' + $("body").data("mapcenterlat") + ',' + $("body").data("mapcenterlong") + '+to:' + lat + ',' + long + '&saddr=' + $("body").data("mapcenterlat") + ',' + $("body").data("mapcenterlong") + '&daddr=' + lat + ',' + long + '&output=classic&dirflg=w&t=m" target="_blank" title="' + _open_map + '">' + _walking_directions + '</a>';
    }
    if (loggedin == 1 && markerdata[standid].photo) {
        //walklink = walklink + ' | ';
        $('#standinfo').html(markerdata[standid].desc + ' (' + walklink + ')');
        //removed + ' <a href="' + markerdata[standid].photo + '" id="photo' + standid + '" title="' + _display_photo + '">' + _photo + '</a>
        $('#standphoto').show();
        $('#standphoto').html('<img src="' + markerdata[standid].photo + '" alt="' + markerdata[standid].name + '" width="100%" />');
        $('#photo' + standid).click(function () {
            $('#standphoto').slideToggle();
            return false;
        });
    } else if (loggedin == 1) {
        $('#standinfo').html(markerdata[standid].desc);
        if (walklink) $('#standinfo').html(markerdata[standid].desc + ' (' + walklink + ')');
        $('#standphoto').hide();
    } else {
        $('#standinfo').hide();
        $('#standphoto').hide();
    }
    togglestandactions(markerdata[standid].count);
    togglebikeactions();
}

function rentedbikes() {
    $.ajax({
        global: false,
        url: "command.php?action=userbikes"
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        handleresponse(jsonobject, 0);
        bikeList = "";
        if (jsonobject.content != "") {
            for (var i = 0, len = jsonobject.content.length; i < len; i++) {
                // time of rent calculation -v
                leftTimeText = '';
                rentedSeconds = jsonobject.rentedseconds[i];
                if (rentedSeconds) {
                    if (rentedSeconds < 0) { // if servertime and rent time are not in sync
                        timeDiff = freeTimeSeconds;
                    } else {
                        timeDiff = Math.abs(freeTimeSeconds - rentedSeconds); // convert to a positive number
                    }
                    units = _secs;
                    if (timeDiff > (60 * 59)) { // convert to hours after 59 minutes
                        timeDiff = Math.round(timeDiff / 60 / 59);
                        units = _hour_s;
                    } else if (timeDiff > 59) { // convert to minutes after 59 seconds
                        timeDiff = Math.round(timeDiff / 60);
                        units = _mins;
                    }
                    if (!isNaN(timeDiff)) {
                        leftTimeText += '<br/><span class=\'label\'>';
                        if (rentedSeconds >= freeTimeSeconds) { // free time over
                            leftTimeText += '<span style=\'text-align: center; display: inline-flex\' class=\'text-danger\'>' + timeDiff + ' ' + units + '<br/>' + _over + '</span>';
                        } else {
                            leftTimeText += '<span style=\'text-align: center; display: inline-flex\'>' + timeDiff + ' ' + units + '<br/>' + _left + '</span>';
                        }
                        leftTimeText += '</span>';
                    }
                }
                // time of rent calculation -^
                bikeList = bikeList + ' <button type="button" class="btn btn-info bikeid b' + jsonobject.content[i] + '" data-id="' + jsonobject.content[i] + '" title="' + _currently_rented + '">' + jsonobject.content[i] + '<br /><span class="label label-primary">(' + jsonobject.codes[i] + ')</span><br /><span class="label"><s>(' + jsonobject.oldcodes[i] + ')</s></span>' + leftTimeText + '</button> ';
            }
            $('#rentedbikes').html('<div class="btn-group">' + bikeList + '</div>');
            $('#rentedbikes .bikeid').click(function () {
                attachbicycleinfo(this, "return");
            });
            checkonebikeattach();
        } else {
            resetrentedbikes();
        }
    });
}

function note() {
    $('#notetext').slideToggle();
    $('#notetext').val('');
}

function togglestandactions(count) {
    if (loggedin == 0) {
        $('#standactions').hide();
        return false;
    }
    if (count == 0 || $("body").data("limit") == 0) {
        $('#standactions').hide();
    } else {
        $('#standactions').show();
    }
}

function togglebikeactions() {
    if (loggedin == 0) {
        $('.bicycleactions').hide();
        return false;
    }
    if ($('body').data('rented') == 0 || standselected == 0) {
        $('.bicycleactions').hide();
    } else {
        $('.bicycleactions').show();
    }
}

function rent() {
    if ($('#rent .bikenumber').html() == "") return false;
    if (window.ga) ga('send', 'event', 'bikes', 'rent', $('#rent .bikenumber').html());
    $.ajax({
        url: "/api/bike/" + $('#rent .bikenumber').html() + "/rent",
        method: "POST",
        dataType: "json"
    }).done(function (jsonobject) {
        handleresponse(jsonobject);
        resetbutton("rent");
        $('body').data("limit", $('body').data("limit") - 1);
        if ($("body").data("limit") < 0) $("body").data("limit", 0);
        standid = $('#stands').val();
        markerdata = $('body').data('markerdata');
        standbiketotal = markerdata[standid].count;
        if (jsonobject.error == 0) {
            $('.b' + $('#rent .bikenumber').html()).remove();
            standbiketotal = (standbiketotal * 1) - 1;
            markerdata[standid].count = standbiketotal;
            $('body').data('markerdata', markerdata);
        }
        if (standbiketotal == 0) {
            $('#standcount').removeClass('label-success').addClass('label-danger');
        } else {
            $('#standcount').removeClass('label-danger').addClass('label-success');
        }
        $('#notetext').val('');
        $('#notetext').hide();
        getmarkers();
        getuserstatus();
        showstand(standid, 0);
    });
}

function returnbike() {
    note = "";
    standname = $('#stands option:selected').text();
    standid = $('#stands').val();
    if (window.ga) ga('send', 'event', 'bikes', 'return', $('#return .bikenumber').html());
    if (window.ga) ga('send', 'event', 'stands', 'return', standname);
    $.ajax({
        url: "/api/bike/" + $('#return .bikenumber').html() + "/return/" + standname,
        method: "POST",
        dataType: "json",
        data: {
            'note': $('#notetext').val()
        }
    }).done(function (jsonobject) {
        handleresponse(jsonobject);
        $('.b' + $('#return .bikenumber').html()).remove();
        resetbutton("return");
        markerdata = $('body').data('markerdata');
        standbiketotal = markerdata[standid].count;
        if (jsonobject.error == 0) {
            standbiketotal = (standbiketotal * 1) + 1;
            markerdata[standid].count = standbiketotal
            $('body').data('markerdata', markerdata);
        }
        if (standbiketotal == 0) {
            $('#standcount').removeClass('label-success');
            $('#standcount').addClass('label-danger');
        }
        $('#notetext').val('');
        $('#notetext').hide();
        getmarkers();
        getuserstatus();
        showstand(standid, 0);
    });
}

function changecity() {
    $.ajax({
        url: "command.php?action=changecity&city=" + $('#citychange').val()
    }).done(function (jsonresponse) {
        var jsonobject = $.parseJSON(jsonresponse);
        console.log(jsonobject);
        location.reload();
    });
}

function validatecoupon() {
    $.ajax({
        url: "command.php?action=validatecoupon&coupon=" + $('#coupon').val()
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        temp = $('#couponblock').html();
        if (jsonobject.error == 1) {
            $('#couponblock').html('<div class="alert alert-danger" role="alert">' + jsonobject.content + '</div>');
            setTimeout(function () {
                $('#couponblock').html(temp);
                $("#validatecoupon").click(function () {
                    if (window.ga) ga('send', 'event', 'buttons', 'click', 'credit-add');
                    validatecoupon();
                });
            }, 2500);
        } else {
            $('#couponblock').html('<div class="alert alert-success" role="alert">' + jsonobject.content + '</div>');
            getuserstatus();
            setTimeout(function () {
                $('#couponblock').html(temp);
                $('#couponblock').toggle();
                $("#validatecoupon").click(function () {
                    if (window.ga) ga('send', 'event', 'buttons', 'click', 'credit-add');
                    validatecoupon();
                });
            }, 2500);
        }
    });
}

function attachbicycleinfo(element, attachto) {
    $('#' + attachto + ' .bikenumber').html($(element).attr('data-id'));
    // show warning, if exists:
    if ($(element).hasClass('btn-warning')) $('#console').html('<div class="alert alert-warning" role="alert">' + _reported_problem + ' ' + $(element).attr('data-note') + '</div>');
    // or hide warning, if bike without issue is clicked
    else if ($(element).hasClass('btn-warning') == false && $('#console div').hasClass('alert-warning')) resetconsole();
    $('#rent').show();
}

function checkonebikeattach() {
    if ($("#rentedbikes .btn-group").length == 1) {
        element = $("#rentedbikes .btn-group .btn");
        attachbicycleinfo(element, "return");
    }
}

function handleresponse(jsonobject, display) {
    if (display == undefined) {
        if (jsonobject.error == 1) {
            $('#console').html('<div class="alert alert-danger" role="alert">' + jsonobject.message + '</div>').fadeIn();
        } else {
            $('#console').html('<div class="alert alert-success" role="alert">' + jsonobject.message + '</div>');
        }
    }
    if (jsonobject.limit) {
        if (jsonobject.limit) $("body").data("limit", jsonobject.limit);
    }
}

function resetconsole() {
    $('#console').html('');
}

function resetbutton(attachto) {
    $('#' + attachto + ' .bikenumber').html('');
}

function resetstandbikes() {
    $('body').data('stackTopBike', false);
    $('#standbikes').html('');
}

function resetrentedbikes() {
    $('#rentedbikes').html('');
}

function savegeolocation() {
    $.ajax({
        url: "command.php?action=map:geolocation&lat=" + $("body").data("mapcenterlat") + "&long=" + $("body").data("mapcenterlong")
    }).done(function (jsonresponse) {
        return;
    });
}

function showlocation(location) {
    $("body").data("mapcenterlat", location.coords.latitude);
    $("body").data("mapcenterlong", location.coords.longitude);
    $("body").data("mapzoom", $("body").data("mapzoom") + 1);
    // 80 m x 5 mins walking distance
    circle = L.circle([$("body").data("mapcenterlat"), $("body").data("mapcenterlong")], 80 * 5, {
        color: 'green',
        fillColor: '#0f0',
        fillOpacity: 0.1
    }).addTo(map);
    map.setView(new L.LatLng($("body").data("mapcenterlat"), $("body").data("mapcenterlong")), $("body").data("mapzoom"));
    if (window.ga) ga('send', 'event', 'geolocation', 'latlong', $("body").data("mapcenterlat") + "," + $("body").data("mapcenterlong"));
    savegeolocation();
}

function changelocation(location) {
    if (location.coords.latitude != $("body").data("mapcenterlat") || location.coords.longitude != $("body").data("mapcenterlong")) {
        $("body").data("mapcenterlat", location.coords.latitude);
        $("body").data("mapcenterlong", location.coords.longitude);
        map.removeLayer(circle);
        circle = L.circle([$("body").data("mapcenterlat"), $("body").data("mapcenterlong")], 80 * 5, {
            color: 'green',
            fillColor: '#0f0',
            fillOpacity: 0.1
        }).addTo(map);
        map.setView(new L.LatLng($("body").data("mapcenterlat"), $("body").data("mapcenterlong")), $("body").data("mapzoom"));
        if (window.ga) ga('send', 'event', 'geolocation', 'latlong', $("body").data("mapcenterlat") + "," + $("body").data("mapcenterlong"));
        savegeolocation();
    }
}

function geolocate() {
    return;
    //if ("geolocation" in navigator) {
    //navigator.geolocation.getCurrentPosition(showlocation);
    /*, function() {
        return;
    }, {
        enableHighAccuracy: true,
        maximumAge: 0,
        timeout: 300000 // refresh interval set to 5 min
    });
    /*
    watchID = navigator.geolocation.watchPosition(changelocation, function() {
        return;
    }, {
        enableHighAccuracy: true,
        maximumAge: 0,
        timeout: 5000
    });
    */
    //}
}