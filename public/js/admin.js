var oTable;

$(document).ready(function () {
    $("#edituser").hide();
    $("#where").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-where');
        bikeInfo($('#bikeNumber').val());
    });
    $("#fleetconsole").on('click', '.bike-revert', function (event) {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-revert');
        revert($(this).data('bike-number'));
        event.preventDefault();
    });
    $('#fleetconsole').on('click', '.bike-last-usage', function (event) {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-last');
        last($(this).data('bike-number'));
        event.preventDefault();
    });
    $("#stands").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-stands');
        stands();
    });
    $("#userlist").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-userlist');
        userlist();
    });
    $("#userstats").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-userstats');
        userstats();
    });
    $("#usagestats").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-usagestats');
        usagestats();
    });
    $('#creditconsole').on('click', '.sellcoupon', function (event) {
        sellcoupon($(this).data('coupon'));
        event.preventDefault();
    });
    $("#generatecoupons1").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-generatecoupons');
        generatecoupons(1);
    });
    $("#generatecoupons2").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-generatecoupons');
        generatecoupons(5);
    });
    $("#generatecoupons3").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-generatecoupons');
        generatecoupons(10);
    });
    $("#trips").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-trips');
        trips();
    });
    $("#saveuser").click(function () {
        saveuser();
        return false;
    });
    $("#addcredit").click(function () {
        addcredit(1);
        return false;
    });
    $("#addcredit2").click(function () {
        addcredit(5);
        return false;
    });
    $("#addcredit3").click(function () {
        addcredit(10);
        return false;
    });

    $('a[data-toggle="tab"]').on('show.bs.tab', function (e) {
        const target = $(e.target).attr("href");
        switch (target) {
            case "#fleet":
                bikeInfo();
                break;
            case "#users":
                userlist();
                break;
            case "#stands":
                stands();
                break;
            case "#reports":
                userstats();
                break;
            case "#credit":
                couponlist();
                break;
        }
    });

    $('#admin-tabs li:first-child a').tab('show')
});

function handleresponse(elementid, jsonobject, display) {
    if (typeof display === 'undefined') {
        const alertType = jsonobject.error === 1 ? 'danger' : 'success';
        $('#' + elementid).html(`<div class="alert alert-${alertType}" role="alert">${jsonobject.content}</div>`).fadeIn();
    }
}

function generateBikeCards(data) {
    const $container = $("#fleetconsole");
    const $template = $("#bike-card-template");
    $container.empty();

    $.each(data, function (index, item) {
        const $card = $template.clone().removeAttr("id").removeClass("d-none");

        const $bikeCard = $card.find(".bike-card");
        if (item.userName !== null) {
            $bikeCard.addClass("bg-success text-white border-success");
        } else if (item.notes !== null) {
            $bikeCard.addClass("bg-warning text-dark border-warning");
        } else {
            $bikeCard.addClass("bg-light text-dark border-light");
        }
        $card.attr("data-bike-number", item.bikeNum);
        $card.find(".bike-last-usage").attr("data-bike-number", item.bikeNum);
        $card.find(".bike-revert").attr("data-bike-number", item.bikeNum);

        $card.find(".bike-number").text(item.bikeNum);

        const $standInfo = $card.find(".stand-info");
        const $rentInfo = $card.find(".rent-info");
        if (item.userName !== null) {
            $standInfo.addClass("d-none");
            $rentInfo.removeClass("d-none");
            $rentInfo.find(".user-name").text(item.userName);
            $rentInfo.find(".rent-time").text(item.rentTime || "Unknown time");
        } else {
            $standInfo.removeClass("d-none").find('.stand-name').text(item.standName);
            $rentInfo.addClass("d-none");
        }

        if (item.isServiceStand == 1) {
            $standInfo.find(".service-stand").removeClass("d-none");
        }

        const $noteInfo = $card.find(".note-info");
        if (item.notes) {
            $noteInfo.removeClass("d-none");
            $noteInfo.find(".note-text").text(item.notes);
        } else {
            $noteInfo.addClass("d-none");
        }

        $container.append($card);
    });
}

function bikeInfo(bikeNumber) {
    if (window.ga) ga('send', 'event', 'bikes', 'where', bikeNumber);

    $.ajax({
        url: "/api/bike" + (bikeNumber ? "/" + bikeNumber : ""),
        method: "GET",
        dataType: "json",
        success: function(response) {
            generateBikeCards(response);
        },
        error: function(xhr, status, error) {
            console.error("Error fetching bike data:", error);
        }
    });
}

function last(bikeNumber) {
    if (window.ga) ga('send', 'event', 'bikes', 'last', bikeNumber);

    $.ajax({
        url: "/api/bikeLastUsage/" + bikeNumber,
        method: "GET",
        dataType: "json",
        success: function(data) {
            $container = $("#bikeLastUsage .modal-body");
            $container.empty();

            if (data.notes !== '') {
                const $bikeUsageNotesTemplate = $("#bike-card-last_usage_notes_template");
                const $notes = $bikeUsageNotesTemplate.clone().removeClass("d-none");
                $notes.find("#note").text(data.notes);
                $container.append($notes);
            }

            $.each(data.history, function (index, item) {
                const $template = $("#bike-card-last_usage_template");
                const $history = $template.clone().removeClass("d-none");
                $history.find("#time").text(item.time);
                $history.find("#standName").text(item.standName);
                $history.find("#userName").text(item.userName);
                $history.find("#parameter").text(item.parameter);
                $history.find("#action i").addClass("d-none");
                $history.find("#action ." + item.action).removeClass("d-none");

                $container.append($history);
            });

            $('#bikeLastUsage').modal()
        },
        error: function(xhr, status, error) {
            console.error("Error fetching bike data:", error);
        }
    });

}

function stands() {
    $.ajax({
        url: "command.php?action=stands"
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        handleresponse("standsconsole", jsonobject);
    });
}

function userlist() {
    var code = "";
    $.ajax({
        url: "command.php?action=userlist"
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        if (jsonobject.length > 0) code = '<table class="table table-striped" id="usertable"><thead><tr><th>' + _user + '</th><th>' + _privileges + '</th><th>' + _limit + '</th>';
        if (creditenabled == 1) code = code + '<th>' + _credit + '</th>';
        code = code + '</tr></thead>';
        for (var i = 0, len = jsonobject.length; i < len; i++) {
            code = code + '<tr><td><a href="#" class="edituser" data-userid="' + jsonobject[i]["userid"] + '">' + jsonobject[i]["username"] + '</a><br />' + jsonobject[i]["number"] + '<br />' + jsonobject[i]["mail"] + '</td><td>' + jsonobject[i]["privileges"] + '</td><td>' + jsonobject[i]["limit"] + '</td>';
            if (creditenabled == 1) {
                code = code + '<td>' + jsonobject[i]["credit"] + creditcurrency + '</td></tr>';
            }
        }
        if (jsonobject.length > 0) code = code + '</table>';
        $('#userconsole').html(code);
        createeditlinks();
        oTable = $('#usertable').dataTable({
            "dom": 'f<"filtertoolbar">prti',
            "paging": false,
            "ordering": false,
            "info": false
        });
        $('div.filtertoolbar').html('<select id="columnfilter"><option></option></select>');
        $('#usertable th').each(function () {
            $('#columnfilter').append($("<option></option>").attr('value', $(this).text()).text($(this).text()));
        });
        $('#usertable_filter input').keyup(function () {
            x = $('#columnfilter').prop("selectedIndex") - 1;
            if (x == -1) fnResetAllFilters(); else oTable.fnFilter($(this).val(), x);
        });
        $('#columnfilter').change(function () {
            x = $('#columnfilter').prop("selectedIndex") - 1;
            if (x == -1) fnResetAllFilters(); else oTable.fnFilter($('#usertable_filter input').val(), x);
        });
    });
}

function userstats() {
    var code = "";
    $.ajax({
        url: "command.php?action=userstats"
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        if (jsonobject.length > 0) code = '<table class="table table-striped" id="userstatstable"><thead><tr><th>User</th><th>Actions</th><th>Rentals</th><th>Returns</th></tr></thead>';
        for (var i = 0, len = jsonobject.length; i < len; i++) {
            code = code + '<tr><td><a href="#" class="edituser" data-userid="' + jsonobject[i]["userid"] + '">' + jsonobject[i]["username"] + '</a></td><td>' + jsonobject[i]["count"] + '</td><td>' + jsonobject[i]["rentals"] + '</td><td>' + jsonobject[i]["returns"] + '</td></tr>';
        }
        if (jsonobject.length > 0) code = code + '</table>';
        $('#reportsconsole').html(code);
        createeditlinks();
        $('#userstatstable').dataTable({
            "paging": false,
            "ordering": false,
            "info": false
        });
    });
}

function usagestats() {
    var code = "";
    $.ajax({
        url: "command.php?action=usagestats"
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        if (jsonobject.length > 0) code = '<table class="table table-striped" id="usagestatstable"><thead><tr><th>Day</th><th>Action</th><th>Count</th></tr></thead>';
        for (var i = 0, len = jsonobject.length; i < len; i++) {
            code = code + '<tr><td>' + jsonobject[i]["day"] + '</td><td>' + jsonobject[i]["action"] + '</td><td>' + jsonobject[i]["count"] + '</td></tr>';
        }
        if (jsonobject.length > 0) code = code + '</table>';
        $('#reportsconsole').html(code);
    });
}

function createeditlinks() {
    $('.edituser').each(function () {
        $(this).click(function () {
            if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-edituser', $(this).attr('data-userid'));
            edituser($(this).attr('data-userid'));
        });
    });
}

function edituser(userid) {
    $.ajax({
        url: "command.php?action=edituser&edituserid=" + userid
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        if (jsonobject) {
            $('#userid').val(jsonobject["userid"]);
            $('#username').val(jsonobject["username"]);
            $('#email').val(jsonobject["email"]);
            if ($('#phone')) $('#phone').val(jsonobject["phone"]);
            $('#privileges').val(jsonobject["privileges"]);
            $('#limit').val(jsonobject["limit"]);
            $('#edituser').show();
            $('a[href="#users"]').trigger('click');
        }
    });
}

function saveuser() {
    if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-saveuser', $('#userid').val());
    var phone = "";
    if ($('#phone')) phone = "&phone=" + $('#phone').val();
    $.ajax({
        url: "command.php?action=saveuser&edituserid=" + $('#userid').val() + "&username=" + $('#username').val() + "&email=" + $('#email').val() + "&privileges=" + $('#privileges').val() + "&limit=" + $('#limit').val() + phone
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        $("#edituser").hide();
        handleresponse("userconsole", jsonobject);
        setTimeout(userlist, 2000);
    });
}

function addcredit(creditmultiplier) {
    if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-addcredit', $('#userid').val());
    $.ajax({
        url: "command.php?action=addcredit&edituserid=" + $('#userid').val() + "&creditmultiplier=" + creditmultiplier
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        $("#edituser").hide();
        handleresponse("userconsole", jsonobject);
        setTimeout(userlist, 2000);
    });
}

function couponlist() {
    if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-couponlist');
    $.ajax({
        url: "/api/coupon",
        method: "GET",
        dataType: "json",
        success: function(data) {
            const $container = $("#creditconsole");
            const $table = $('#coupon-table-template').clone();
            const $tableBody = $table.find('tbody');
            const $rowTemplate = $('#coupon-row-template');
            $container.empty();

            $.each(data, function (index, item) {
                const $newRow = $rowTemplate.clone();
                $newRow.removeClass('d-none').removeAttr('id');
                $newRow.find('.coupon-placeholder').text(item["coupon"]);
                $newRow.find('.value-placeholder').text(`${item["value"]} ${creditcurrency}`);
                $newRow.find('.sellcoupon').attr('data-coupon', item["coupon"]);
                $tableBody.append($newRow);
            });

            $table.removeClass('d-none').removeAttr('id');
            $container.append($table);
        },
        error: function(xhr, status, error) {
            console.error("Error fetching coupon data:", error);
        }
    });
}

function generatecoupons(multiplier) {
    $.ajax({
        url: "/api/coupon/generate",
        method: "POST",
        data: {multiplier: multiplier},
        success: function() {
            couponlist();
        },
        error: function(xhr, status, error) {
            console.error("Error sell coupon:", error);
        }
    });
}

function sellcoupon(coupon) {
    $.ajax({
        url: "/api/coupon/sell",
        method: "POST",
        data: {coupon: coupon},
        success: function() {
            couponlist();
        },
        error: function(xhr, status, error) {
            console.error("Error sell coupon:", error);
        }
    });
}

function trips() {
    if (window.ga) ga('send', 'event', 'bikes', 'trips', $('#bikeNumber').val());
    $.ajax({
        url: "command.php?action=trips&bikeno=" + $('#bikeNumber').val()
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        if (jsonobject.error == 1) {
            handleresponse(elementid, jsonobject);
        } else {
            if (jsonobject[0]) // concrete bike requested
            {
                if (polyline != undefined) map.removeLayer(polyline);
                polyline = L.polyline([[jsonobject[0].latitude * 1, jsonobject[0].longitude * 1], [jsonobject[1].latitude * 1, jsonobject[1].longitude * 1]], {color: 'red'}).addTo(map);
                for (var i = 2, len = jsonobject.length; i < len; i++) {
                    if (jsonobject[i].longitude * 1 && jsonobject[i].latitude * 1) {
                        polyline.addLatLng([jsonobject[i].latitude * 1, jsonobject[i].longitude * 1]);
                    }
                }
            } else // all bikes requested
            {
                var polylines = [];
                for (var bikenumber in jsonobject) {
                    var bikecolor = '#' + ('00000' + (Math.random() * 16777216 << 0).toString(16)).substr(-6);
                    polylines[bikenumber] = L.polyline([[jsonobject[bikenumber][0].latitude * 1, jsonobject[bikenumber][0].longitude * 1], [jsonobject[bikenumber][1].latitude * 1, jsonobject[bikenumber][1].longitude * 1]], {color: bikecolor}).addTo(map);
                    for (var i = 2, len = jsonobject[bikenumber].length; i < len; i++) {
                        if (jsonobject[bikenumber][i].longitude * 1 && jsonobject[bikenumber][i].latitude * 1) {
                            polylines[bikenumber].addLatLng([jsonobject[bikenumber][i].latitude * 1, jsonobject[bikenumber][i].longitude * 1]);
                        }
                    }
                }
            }

        }
    });
}

function revert(bikeNumber) {
    if (window.ga) ga('send', 'event', 'bikes', 'revert', bikeNumber);
    $.ajax({
        url: "command.php?action=revert&bikeno=" + bikeNumber
    }).done(function (jsonresponse) {
        jsonobject = $.parseJSON(jsonresponse);
        handleresponse("fleetconsole", jsonobject);
    });
}

function fnResetAllFilters() {
    var oSettings = oTable.fnSettings();
    for (iCol = 0; iCol < oSettings.aoPreSearchCols.length; iCol++) {
        oSettings.aoPreSearchCols[iCol].sSearch = '';
    }
    oTable.fnDraw();
}