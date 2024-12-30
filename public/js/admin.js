$(document).ready(function () {
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
    $('#userconsole').on('click', '.edituser', function (event) {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-edituser', $(this).attr('data-userid'));
        edituser($(this).attr('data-userid'));
        event.preventDefault();
    })
    $('#edituser').on('click', '.cancel', function (event) {
        $('#edituser')
            .addClass('d-none')
            .find('input').val('');
        event.preventDefault();
    });
    $(".generatecoupons").click(function (event) {
        generatecoupons($(this).data('multiplier'));
        event.preventDefault();
    });
    $("#trips").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-trips');
        trips();
    });
    $("#saveuser").click(function (event) {
        saveuser($('#userid').val());
        event.preventDefault();
    });
    $(".addcredit").click(function (event) {
        addcredit($('#userid').val(), $(this).data('multiplier'));
        event.preventDefault();
    });

    $('a[data-toggle="tab"]').on('show.bs.tab', function (e) {
        const target = $(e.target).attr("href");
        switch (target) {
            case "#fleet":
                if (window.ga) ga('send', 'event', 'bikes', 'admin-fleet');
                bikeInfo();
                break;
            case "#users":
                if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-userlist');
                userlist();
                break;
            case "#stands":
                if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-stands');
                stands();
                break;
            case "#reports":
                userstats();
                break;
            case "#credit":
                if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-couponlist');
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
    let table = $('#user-table').DataTable({
        destroy: true,
        searching: false,
        ajax: {
            url: '/api/user',
            dataSrc: '',
            cache: true
        },
        dom: 'lrtip',
        columns: [
            {
                data: 'username',
                name: 'username',
                render: function(data, type, row) {
                    return `<a href="#" class="edituser" data-userid="${row.userId}">${data}</a>
                        <br />${row.number}
                        <br />${row.mail}`;
                }
            },
            {
                data: 'privileges',
                name: 'privileges'
            },
            {
                data: 'userLimit',
                name: 'userLimit'
            },
            {
                data: 'credit',
                name: 'credit',
                visible: creditenabled === 1,
                render: function(data, type, row) {
                    return `${data} ${creditcurrency}`;
                }
            }
        ],
        error: function(xhr, error, code) {
            console.error('Error loading data:', error);
        }
    });

    // Variable to track the column index for custom search
    let searchColumnIndex = 0;

    // Update dropdown and search column index
    $('.search-option').click(function () {
        const columnName = $(this).data('column');
        searchColumnIndex = table.column(`${columnName}:name`).index(); // Get column index
        $('#searchTypeDropdown').text($(this).text()); // Update dropdown button text
    });

    // Custom search logic
    $('#customSearchInput').on('keyup', function () {
        const searchTerm = this.value;
        table.columns(searchColumnIndex)
             .search(searchTerm)
             .draw();
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

function edituser(userid) {
    $.ajax({
        url: "/api/user/" + userid,
        method: "GET",
        dataType: "json",
        success: function(data) {
            console.log(data);
            $container = $("#edituser");
            $container.find('input').val('');
            $container.find('#userid').val(data.userId);
            $container.find('#username').val(data.username);
            $container.find('#email').val(data.mail);
            if ($container.find('#phone').length) {
                $container.find('#phone').val(data.number);
            }
            $container.find('#privileges').val(data.privileges);
            $container.find('#limit').val(data.userLimit);
            $container.removeClass('d-none');
            $('html, body').animate({
                scrollTop: $container.offset().top
            }, 500);
        },
        error: function(xhr, status, error) {
            console.error("Error fetching user data:", error);
        }
    });
}

function saveuser(userId) {
    if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-saveuser', userId);

    $.ajax({
        url: "/api/user/" + userId,
        method: "PUT",
        dataType: "json",
        data: {
            'username': $('#username').val(),
            'email': $('#email').val(),
            'number': $('#phone').length ? $('#phone').val() : '',
            'privileges': $('#privileges').val(),
            'userLimit': $('#limit').val()

        },
        success: function(data) {
            $("#edituser").addClass('d-none');
            userlist();
        },
        error: function(xhr, status, error) {
            console.error("Error update user data:", error);
        }
    });
}

function addcredit(userId, multiplier) {
    if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-addcredit', userId, multiplier);

    $.ajax({
        url: "/api/credit",
        method: "PUT",
        dataType: "json",
        data: {
            'userId': userId,
            'multiplier': multiplier
        },
        success: function(data) {
            $("#edituser").addClass('d-none');
            userlist();
        },
        error: function(xhr, status, error) {
            console.error("Error update user data:", error);
        }
    });
}

function couponlist() {
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
    if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-generatecoupons', multiplier);
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
