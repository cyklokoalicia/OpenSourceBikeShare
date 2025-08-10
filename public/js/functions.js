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
        addNote();
    });
    $('#stands').change(function () {
        showstand($('#stands').val());
    }).keyup(function () {
        showstand($('#stands').val());
    });
    if ($('usercredit')) {
        $("#opencredit").click(function () {
            if (window.ga) ga('send', 'event', 'buttons', 'click', 'credit-enter');
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

    userLatitude = $("body").data("mapcenterlat");
    userLongitude = $("body").data("mapcenterlong");

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function (position) {
                userLatitude = position.coords.latitude;
                userLongitude = position.coords.longitude;
            },
            function (error) {
                console.warn('Geolocation failed:', error.message);
            }
        );
    }

    mapinit();
    // geolocate();
    setInterval(getmarkers, 60000); // refresh map every 60 seconds
    setInterval(getuserstatus, 60000); // refresh map every 60 seconds
    // setInterval(geolocate, 300000); // refresh map every 5 min
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

    lc = L.control.locate({
        locateOptions: {
            maxZoom: mapzoom
        }
    }).addTo(map);
    lc.start();

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
        url: "/api/stand/markers",
        method: "GET",
        dataType: "json"
    }).done(function (jsonObject) {
        const body = $('body');
        const iconSizeArr = [iconsize, iconsize];
        const iconAnchorArr = [iconsize / 2, 0];

        for (let i = 0, len = jsonObject.length; i < len; i++) {
            const {
                standId,
                standName,
                standDescription,
                standPhoto,
                bikeCount,
                longitude,
                latitude
            } = jsonObject[i];

            let iconClass = 'icondesc';
            if (standName.includes('SERVIS')) {
                iconClass += ' special';
            } else if (bikeCount === 0) {
                iconClass += ' none';
            }

            const iconHTML = `
                <dl class="${iconClass}" id="stand-${standName}">
                    <dt class="bikecount">${bikeCount}</dt>
                    <dd class="standname">${standName}</dd>
                </dl>`;

            const tempIcon = L.divIcon({
                iconSize: iconSizeArr,
                iconAnchor: iconAnchorArr,
                html: iconHTML,
                standid: standId
            });

            markerdata[standId] = {
                name: standName,
                desc: standDescription,
                photo: standPhoto,
                count: bikeCount
            };

            markers[standId] = L.marker([latitude, longitude], {
                icon: tempIcon
            }).addTo(map).on("click", showstand);
        }

        body.data('markerdata', markerdata);

        if (firstrun === 1) {
            createstandselector();
            firstrun = 0;
        }
    });
}

function getuserstatus() {
    $.ajax({
        global: false,
        url: "/api/user/limit",
        method: "GET",
        dataType: "json"
    }).done(function (jsonObject) {
        $('body').data('limit', jsonObject.limit);
        $('body').data('rented', jsonObject.rented);
        if ($('#userCredit').length) {
            $('#userCredit').html(jsonObject.userCredit);
        }
        togglebikeactions();
    });
}

function createstandselector() {
    var selectdata = '<option value="del">-- ' + window.translations['Select stand'] + ' --</option>';
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
        $('#standcount').removeClass('badge badge-danger').addClass('badge badge-success');
        if (markerdata[standid].count == 1) {
            $('#standcount').html(markerdata[standid].count + ' ' + window.translations['bicycle']);
        } else {
            $('#standcount').html(markerdata[standid].count + ' ' + window.translations['bicycles']);
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
                let bikeButtons = [];

                for (let i = 0; i < bikes.length; i++) {
                    let bike = bikes[i];
                    let bikeNum = bike.bikeNum;
                    let note = bike.notes || '';
                    let hasIssue = note !== '';
                    let limit = $("body").data("limit");
                    let isStacked = stackTopBike && stackTopBike != bikeNum;

                    let btn = {
                        num: bikeNum,
                        note: note,
                        class: 'btn btn-secondary bikeid mr-1 mb-2',
                        dataNote: ''
                    };

                    if (!isStacked) {
                        if (hasIssue && limit > 0) {
                            btn.class = 'btn btn-warning bikeid mr-1 mb-2';
                            btn.dataNote = note;
                        } else if (hasIssue && limit == 0) {
                            btn.class = 'btn btn-secondary bikeid mr-1 mb-2';
                        } else if (limit > 0) {
                            btn.class = 'btn btn-success bikeid b' + bikeNum + ' mr-1 mb-2';
                        }
                    }

                    btn.dataId = bikeNum;
                    bikeButtons.push(btn);
                }

                let bikeList = '<div class="d-flex flex-wrap justify-content-center">';
                bikeButtons.forEach(btn => {
                    let noteAttr = btn.dataNote ? ` data-note="${btn.dataNote}"` : '';
                    bikeList += `<button type="button" class="${btn.class}" data-id="${btn.dataId}"${noteAttr}>${btn.num}</button>`;
                });
                bikeList += '</div>';
                $('#standbikes').html(bikeList);
                if (stackTopBike !== false) {
                    // bike stack is enabled, allow renting top of the stack bike only
                    $('.b' + stackTopBike).click(function () {
                        if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-number');
                        attachbicycleinfo(this, "rent");
                    });
                    $('body').data('stackTopBike', stackTopBike);
                } else {
                    // bike stack is disabled, allow renting any bike
                    $('#standbikes .bikeid').click(function () {
                        if (window.ga) ga('send', 'event', 'buttons', 'click', 'bike-number');
                        attachbicycleinfo(this, "rent");
                    });
                }
            } else {
                // no bicyles at stand
                $('#standcount').html(window.translations['No bicycles']);
                $('#standcount').removeClass('badge badge-success').addClass('badge badge-danger');
                resetstandbikes();
            }
        });
    } else {
        $('#standcount').html(window.translations['No bicycles']);
        $('#standcount').removeClass('badge badge-success').addClass('badge badge-danger');
        resetstandbikes();
    }

    walklink = '<a href="https://www.google.com/maps?q=' + userLatitude + ',' + userLongitude + '+to:' + lat + ',' + long + '&saddr=' + userLatitude + ',' + userLongitude + '&daddr=' + lat + ',' + long + '&output=classic&dirflg=w&t=m" target="_blank" title="' + window.translations['Open a map with directions to the selected stand from your current location.'] + '">' + window.translations['walking directions'] + '</a>';

    if (loggedin == 1 && markerdata[standid].photo) {
        //walklink = walklink + ' | ';
        $('#standinfo').html(markerdata[standid].desc + ' (' + walklink + ')');
        //removed + ' <a href="' + markerdata[standid].photo + '" id="photo' + standid + '" title="' + window.translations['Display photo of the stand.'] + '">' + window.translations['photo'] + '</a>
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
        url: "/api/user/bike",
        dataType: "json"
    }).done(function (jsonArray) {
        handleresponse(jsonArray, 0);
        var bikeList = "";
        if (jsonArray.length > 0) {
            for (var i = 0, len = jsonArray.length; i < len; i++) {
                var bike = jsonArray[i];
                var leftTimeText = '';
                var rentedSeconds = bike.rentedSeconds;

                if (rentedSeconds) {
                    var timeDiff = 0;
                    if (rentedSeconds < 0) {
                        timeDiff = freeTimeSeconds;
                    } else {
                        timeDiff = Math.abs(freeTimeSeconds - rentedSeconds);
                    }

                    var units = window.translations['sec.'];
                    if (timeDiff > (60 * 59)) {
                        timeDiff = Math.round(timeDiff / 60 / 59);
                        units = window.translations['hour/s'];
                    } else if (timeDiff > 59) {
                        timeDiff = Math.round(timeDiff / 60);
                        units = window.translations['min.'];
                    }

                    if (!isNaN(timeDiff)) {
                        leftTimeText += '<br/><span class=\'label\'>';
                        if (rentedSeconds >= freeTimeSeconds) {
                            leftTimeText += '<span style=\'text-align: center; display: inline-flex\' class=\'text-danger\'>' + timeDiff + ' ' + units + '<br/>' + window.translations['over'] + '</span>';
                        } else {
                            leftTimeText += '<span style=\'text-align: center; display: inline-flex\'>' + timeDiff + ' ' + units + '<br/>' + window.translations['left'] + '</span>';
                        }
                        leftTimeText += '</span>';
                    }
                }

                bikeList += ' <button type="button" class="btn btn-info bikeid b' + bike.bikeNum + '" data-id="' + bike.bikeNum + '" title="' + window.translations['You have this bicycle currently rented. The current lock code is displayed below the bike number.'] + '">' + bike.bikeNum + '<br /><span class="badge badge-primary">(' + bike.currentCode + ')</span><br /><span class="label"><s>(' + bike.oldCode + ')</s></span>' + leftTimeText + '</button> ';
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

function addNote() {
    $('#notetext').slideToggle();
    $('#notetext').val('');
}

function togglestandactions(count) {
    if (loggedin == 0) {
        $('#standactions').hide();
        return false;
    }
    if (count == 0 || $("body").data("limit") == 0 || !$('#rent .bikenumber').html()) {
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
        method: "PUT",
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
        method: "PUT",
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
        url: "/api/user/changeCity",
        method: "PUT",
        dataType: "json",
        data: {
            city: $('#citychange').val(),
        }
    }).done(function (jsonObject) {
        console.log(jsonObject);
        location.reload();
    });
}

function validatecoupon() {
    var $input = $('#coupon');
    var $message = $('#coupon-message');
    var code = $input.val();

    $.ajax({
        url: "/api/coupon/use",
        method: "POST",
        dataType: "json",
        data: { coupon: code }
    }).done(function (response) {
        var alertClass = response.error == 1 ? 'alert-danger' : 'alert-success';
        $message.html('<div class="alert ' + alertClass + '" role="alert">' + response.message + '</div>');

        if (response.error !== 1) {
            getuserstatus();
            setTimeout(function () {
                $('#creditModal').modal('hide');
                $input.val('');
                $message.empty();
            }, 2500);
        }
    }).fail(function () {
        $message.html('<div class="alert alert-danger" role="alert">An error occurred. Please try again.</div>');
    });
}

function attachbicycleinfo(element, attachto) {
    $('#' + attachto + ' .bikenumber').html($(element).attr('data-id'));
    // show warning, if exists:
    if ($(element).hasClass('btn-warning')) $('#console').html('<div class="alert alert-warning" role="alert">' + window.translations['Reported problem on this bicycle:'] + ' ' + $(element).attr('data-note') + '</div>');
    // or hide warning, if bike without issue is clicked
    else if ($(element).hasClass('btn-warning') == false && $('#console div').hasClass('alert-warning')) resetconsole();
    $('#rent').show();
    togglestandactions();
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

// function savegeolocation() {
//     $.ajax({
//         url: "command.php?action=map:geolocation&lat=" + $("body").data("mapcenterlat") + "&long=" + $("body").data("mapcenterlong")
//     }).done(function (jsonresponse) {
//         return;
//     });
// }
//
// function showlocation(location) {
//     $("body").data("mapcenterlat", location.coords.latitude);
//     $("body").data("mapcenterlong", location.coords.longitude);
//     $("body").data("mapzoom", $("body").data("mapzoom") + 1);
//     // 80 m x 5 mins walking distance
//     circle = L.circle([$("body").data("mapcenterlat"), $("body").data("mapcenterlong")], 80 * 5, {
//         color: 'green',
//         fillColor: '#0f0',
//         fillOpacity: 0.1
//     }).addTo(map);
//     map.setView(new L.LatLng($("body").data("mapcenterlat"), $("body").data("mapcenterlong")), $("body").data("mapzoom"));
//     if (window.ga) ga('send', 'event', 'geolocation', 'latlong', $("body").data("mapcenterlat") + "," + $("body").data("mapcenterlong"));
//     savegeolocation();
// }
//
// function changelocation(location) {
//     if (location.coords.latitude != $("body").data("mapcenterlat") || location.coords.longitude != $("body").data("mapcenterlong")) {
//         $("body").data("mapcenterlat", location.coords.latitude);
//         $("body").data("mapcenterlong", location.coords.longitude);
//         map.removeLayer(circle);
//         circle = L.circle([$("body").data("mapcenterlat"), $("body").data("mapcenterlong")], 80 * 5, {
//             color: 'green',
//             fillColor: '#0f0',
//             fillOpacity: 0.1
//         }).addTo(map);
//         map.setView(new L.LatLng($("body").data("mapcenterlat"), $("body").data("mapcenterlong")), $("body").data("mapzoom"));
//         if (window.ga) ga('send', 'event', 'geolocation', 'latlong', $("body").data("mapcenterlat") + "," + $("body").data("mapcenterlong"));
//         savegeolocation();
//     }
// }
//
// function geolocate() {
//     return;
//     //if ("geolocation" in navigator) {
//     //navigator.geolocation.getCurrentPosition(showlocation);
//     /*, function() {
//         return;
//     }, {
//         enableHighAccuracy: true,
//         maximumAge: 0,
//         timeout: 300000 // refresh interval set to 5 min
//     });
//     /*
//     watchID = navigator.geolocation.watchPosition(changelocation, function() {
//         return;
//     }, {
//         enableHighAccuracy: true,
//         maximumAge: 0,
//         timeout: 5000
//     });
//     */
//     //}
// }
