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
    $("#fleetconsole").on('click', '.bike-set-code', function (event) {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-set-code');
        setCode($(this).data('bike-number'));
        event.preventDefault();
    });
    $("#fleetconsole").on('click', '.bike-remove-note', function (event) {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-remove-note');
        removeNote($(this).data('bike-number'));
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
    $("#inactivebikesreport").click(function () {
        if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-inactivebikes');
        inactivebikesreport();
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
                usagestats();
                break;
            case "#credit":
                if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-couponlist');
                couponlist();
                break;
        }
    });

    $('#admin-tabs li:first-child a').tab('show')

    const $setCodeModal = $('#setBikeCodeModal');
    const $setCodeForm = $('#setBikeCodeForm');
    const $setCodeInput = $('#setBikeCodeInput');
    const $setCodeAlert = $('#setBikeCodeAlert');

    $setCodeModal.on('shown.bs.modal', function () {
        $setCodeInput.trigger('focus');
    });

    $setCodeModal.on('hidden.bs.modal', function () {
        $setCodeForm.trigger('reset');
        $setCodeInput.removeClass('is-invalid');
        $setCodeAlert.addClass('d-none').text('');
    });

    $setCodeInput.on('input', function () {
        $(this).removeClass('is-invalid');
    });

    $setCodeForm.on('submit', function (event) {
        event.preventDefault();
        submitSetCode();
    });
});

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
        $card.find(".bike-remove-note").attr("data-bike-number", item.bikeNum);
        $card.find(".bike-set-code").attr("data-bike-number", item.bikeNum);

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
            $card.find(".bike-remove-note").removeClass("d-none");
        } else {
            $noteInfo.addClass("d-none");
            $card.find(".bike-remove-note").addClass("d-none");
        }

        $container.append($card);
    });
}

function bikeInfo(bikeNumber) {
    $.ajax({
        url: bikeNumber ? ("/api/v1/admin/bikes/" + bikeNumber) : "/api/v1/admin/bikes",
        method: "GET",
        dataType: "json",
        success: function(response) {
            response = apiData(response);
            if (!Array.isArray(response)) {
                response = response ? [response] : [];
            }

            generateBikeCards(response);
        },
        error: function(xhr, status, error) {
            handleApiError(xhr, 'Unable to load bike data.', 'fleetconsole');
            console.error("Error fetching bike data:", error);
        }
    });
}

function last(bikeNumber) {
    $.ajax({
        url: "/api/v1/admin/bikes/" + bikeNumber + "/last-usage",
        method: "GET",
        dataType: "json",
        success: function(data) {
            data = apiData(data) || {};
            $container = $("#bikeLastUsage .modal-body");
            $container.empty();

            if (data.notes !== '') {
                const $bikeUsageNotesTemplate = $("#bike-card-last_usage_notes_template");
                const $notes = $bikeUsageNotesTemplate.clone().removeClass("d-none");
                $notes.find("#note").text(data.notes);
                $container.append($notes);
            }

            const dateTimeFormatter = new Intl.DateTimeFormat(navigator.language, {
                day: 'numeric',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            });

            $.each(data.history, function (index, item) {
                const $template = $("#bike-card-last_usage_template");
                const $history = $template.clone().removeClass("d-none");
                const date = new Date(item.time);
                $history.find("#time").text(isNaN(date.getTime()) ? item.time : dateTimeFormatter.format(date));
                $history.find("#standName").text(item.standName);
                $history.find("#userName").text(item.userName);
                $history.find("#parameter").text(item.parameter ?? '');
                $history.find("#action i").addClass("d-none");
                $history.find("#action ." + item.action).removeClass("d-none");

                $container.append($history);
            });

            $('#bikeLastUsage').modal()
        },
        error: function(xhr, status, error) {
            handleApiError(xhr, 'Unable to load bike history.', 'fleetconsole');
            console.error("Error fetching bike data:", error);
        }
    });
}

function generateStandCards(data) {
    const $container = $("#standsconsole");
    const $template = $("#stand-card-template");
    $container.empty();

    $.each(data, function (index, item) {
        const $card = $template.clone().removeAttr("id").removeClass("d-none");

        $card.find(".stand-name").text(item.standName);

        const $photo = $card.find(".stand-photo");
        if (item.standPhoto) {
            $photo.attr("src", item.standPhoto).removeClass("d-none");
        } else {
            $photo.addClass("d-none");
        }

        $card.find(".stand-description").text(item.standDescription);

        if (parseInt(item.latitude) !== 0 && parseInt(item.longitude) !== 0) {
            const googleMapsUrl = `https://www.google.com/maps?q=${item.latitude},${item.longitude}`;
            $card.find(".stand-location")
                .removeClass("d-none")
                .attr("href", googleMapsUrl);
        }

        if (item.standName.toLowerCase().includes("servis")) {
            $card.find(".service-stand").removeClass("d-none");
        } else if (item.standName.toLowerCase().includes("zruseny")) {
            $card.find(".removed-stand").removeClass("d-none");
        }

        $container.append($card);
    });
}

function stands(standName) {
    const hasStandName = standName !== undefined && standName !== null && standName !== '';
    const url = hasStandName
        ? ("/api/v1/admin/stands/" + encodeURIComponent(String(standName)))
        : "/api/v1/admin/stands";

    $.ajax({
        url: url,
        method: "GET",
        dataType: "json",
        success: function(response) {
            response = apiData(response);
            if (!Array.isArray(response)) {
                response = response ? [response] : [];
            }

            generateStandCards(response);
        },
        error: function(xhr, status, error) {
            handleApiError(xhr, 'Unable to load stand data.', 'standsconsole');
            console.error("Error fetching stand data:", error);
        }
    });
}

function userlist() {
    $('#customSearchInput').val('');
    let table = $('#user-table').DataTable({
        destroy: true,
        ajax: {
            url: '/api/v1/admin/users',
            dataSrc: function (response) {
                const payload = apiData(response);
                return Array.isArray(payload) ? payload : [];
            },
            cache: true
        },
        layout: {
            topEnd: null //disable default searchField
        },
        columns: [
            {
                data: 'username',
                name: 'username',
                render: function(data, type, row) {
                    return `<a href="#" class="edituser" data-userid="${row.userId}">${data}</a>` +
                        (isSmsSystemEnabled ? `<br />${row.number}` : '') +
                        `<br />${row.mail}`;
                }
            },
            {
                data: 'privileges',
                name: 'privileges',
                type: 'num'
            },
            {
                data: 'userLimit',
                name: 'userLimit',
                type: 'num'
            },
            {
                data: 'credit',
                name: 'credit',
                type: 'num-fmt',
                visible: creditenabled === 1,
                render: function(data, type, row) {
                    return `${data} ${creditcurrency}`;
                }
            }
        ],
        error: function(xhr, error, code) {
            handleApiError(xhr, 'Unable to load users.', 'userconsole');
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
    $('#report-daily-table').addClass('d-none').closest('#report-daily-table_wrapper').addClass('d-none');
    $('#report-inactive-table').addClass('d-none').closest('#report-inactive-table_wrapper').addClass('d-none');
    $('#report-user-year').removeClass('d-none');
    let table = $('#report-user-table').removeClass('d-none').DataTable({
        destroy: true,
        paging: false,
        info: false,
        searching: false,
        ajax: {
            url: '/api/v1/admin/reports/users',
            dataSrc: function (response) {
                const payload = apiData(response);
                return Array.isArray(payload) ? payload : [];
            },
            cache: true,
        },
        order: [[3, 'desc']],
        columns: [
            {
                data: 'username',
                name: 'username',
            },
            {
                data: 'rentCount',
            },
            {
                data: 'returnCount',
            },
            {
                data: 'totalActionCount',
            }
        ],
        error: function(xhr, error, code) {
            handleApiError(xhr, 'Unable to load user report.', 'reportsconsole');
            console.error('Error loading daily report data:', error);
        }
    });

    $('#year').off('change.report-user').on('change.report-user', function() {
        table.ajax.url('/api/v1/admin/reports/users/' + $('#year').val());
        table.ajax.reload();
    });
}

function usagestats() {
    $('#report-user-table').addClass('d-none').closest('#report-user-table_wrapper').addClass('d-none');
    $('#report-user-year').addClass('d-none');
    $('#report-inactive-table').addClass('d-none').closest('#report-inactive-table_wrapper').addClass('d-none');
    $('#report-daily-table').removeClass('d-none').DataTable({
        destroy: true,
        paging: false,
        info: false,
        searching: false,
        ajax: {
            url: '/api/v1/admin/reports/daily',
            dataSrc: function (response) {
                const payload = apiData(response);
                return Array.isArray(payload) ? payload : [];
            },
            cache: true,
        },
        order: [[0, 'desc']],
        columns: [
            {
                data: 'day',
            },
            {
                data: 'rentCount',
            },
            {
                data: 'returnCount',
            }
        ],
        error: function(xhr, error, code) {
            handleApiError(xhr, 'Unable to load usage report.', 'reportsconsole');
            console.error('Error loading user report data:', error);
        }
    });
}

function inactivebikesreport() {
    $('#report-daily-table').addClass('d-none').closest('#report-daily-table_wrapper').addClass('d-none');
    $('#report-user-table').addClass('d-none').closest('#report-user-table_wrapper').addClass('d-none');
    $('#report-user-year').addClass('d-none');
    $('#report-inactive-table').removeClass('d-none').DataTable({
        destroy: true,
        paging: false,
        info: false,
        searching: false,
        ajax: {
            url: '/api/v1/admin/reports/inactive-bikes',
            dataSrc: function (response) {
                const payload = apiData(response);
                return Array.isArray(payload) ? payload : [];
            },
            cache: true,
        },
        order: [[3, 'desc']],
        columns: [
            {
                data: 'bikeNum',
                render: function(data) {
                    return `${data}`;
                }
            },
            { data: 'standName' },
            { data: 'lastMoveTime' },
            { data: 'inactiveDays' }
        ],
        error: function(xhr, error, code) {
            handleApiError(xhr, 'Unable to load inactive bikes report.', 'reportsconsole');
            console.error('Error loading inactive bikes report data:', error);
        }
    });
}

function edituser(userid) {
    $.ajax({
        url: "/api/v1/admin/users/" + userid,
        method: "GET",
        dataType: "json",
        success: function(data) {
            data = apiData(data) || {};
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
            $container.find('#registrationDate').val(data.registrationDate);
            $container.removeClass('d-none');
            $('html, body').animate({
                scrollTop: $container.offset().top
            }, 500);
        },
        error: function(xhr, status, error) {
            handleApiError(xhr, 'Unable to load user details.', 'userconsole');
            console.error("Error fetching user data:", error);
        }
    });
}

function saveuser(userId) {
    if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-saveuser', userId);

    $.ajax({
        url: "/api/v1/admin/users/" + userId,
        method: "PATCH",
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
            handleApiError(xhr, 'Unable to update user.', 'userconsole');
            console.error("Error update user data:", error);
        }
    });
}

function addcredit(userId, multiplier) {
    if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-addcredit', userId, multiplier);

    $.ajax({
        url: "/api/v1/admin/users/" + userId + "/credit",
        method: "PUT",
        dataType: "json",
        data: {
            'multiplier': multiplier
        },
        success: function(data) {
            $("#edituser").addClass('d-none');
            userlist();
        },
        error: function(xhr, status, error) {
            handleApiError(xhr, 'Unable to add credit.', 'userconsole');
            console.error("Error update user data:", error);
        }
    });
}

function couponlist() {
    $.ajax({
        url: "/api/v1/admin/coupons",
        method: "GET",
        dataType: "json",
        success: function(data) {
            data = apiData(data) || [];
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
            handleApiError(xhr, 'Unable to load coupons.', 'creditconsole');
            console.error("Error fetching coupon data:", error);
        }
    });
}

function generatecoupons(multiplier) {
    if (window.ga) ga('send', 'event', 'buttons', 'click', 'admin-generatecoupons', multiplier);
    $.ajax({
        url: "/api/v1/admin/coupons/generate",
        method: "POST",
        dataType: "json",
        data: {multiplier: multiplier},
        success: function() {
            couponlist();
        },
        error: function(xhr, status, error) {
            handleApiError(xhr, 'Unable to generate coupons.', 'creditconsole');
            console.error("Error sell coupon:", error);
        }
    });
}

function sellcoupon(coupon) {
    $.ajax({
        url: "/api/v1/admin/coupons/" + coupon + "/sell",
        method: "POST",
        dataType: "json",
        success: function() {
            couponlist();
        },
        error: function(xhr, status, error) {
            handleApiError(xhr, 'Unable to sell coupon.', 'creditconsole');
            console.error("Error sell coupon:", error);
        }
    });
}

function trips() {
    if (window.ga) ga('send', 'event', 'bikes', 'trips', $('#bikeNumber').val());
    $.ajax({
        url: "/api/v1/admin/bikes/" + $('#bikeNumber').val() + "/trip",
        method: "GET",
        dataType: "json"
    }).done(function (jsonObject) {
        jsonObject = apiData(jsonObject) || {};
        var polylines = [];
        for (const bikeNumber in jsonObject) {
            const points = jsonObject[bikeNumber];
            const path = [];

            for (let i = 0, len = points.length; i < len; i++) {
                const lat = Number(points[i].latitude);
                const lng = Number(points[i].longitude);
                if (lat && lng) {
                    path.push([lat, lng]);
                }
            }

            if (path.length > 1) {
                const bikeColor = '#' + Math.floor(Math.random() * 16777215).toString(16).padStart(6, '0');
                polylines[bikeNumber] = L.polyline(path, { color: bikeColor }).addTo(map);
            }
        }
    }).fail(function (xhr) {
        handleApiError(xhr, 'Unable to load bike trips.', 'fleetconsole');
    });
}

function revert(bikeNumber) {
    if (window.ga) ga('send', 'event', 'bikes', 'revert', bikeNumber);
    $.ajax({
        url: "/api/v1/admin/reverts",
        method: "POST",
        dataType: "json",
        data: {bikeNumber: bikeNumber},
    }).done(function (jsonobject) {
        handleApiResponse(jsonobject, 'fleetconsole');
    }).fail(function (xhr) {
        handleApiError(xhr, 'Unable to revert bike.', 'fleetconsole');
    });
}

function setCode(bikeNumber) {
    if (window.ga) ga('send', 'event', 'bikes', 'set-code', bikeNumber);

    var $modal = $('#setBikeCodeModal');
    $modal.data('bike-number', bikeNumber);
    $modal.find('.bike-number').text(bikeNumber);
    $('#setBikeCodeInput').val('').removeClass('is-invalid');
    $('#setBikeCodeAlert').addClass('d-none').text('');
    $modal.modal('show');
}

function submitSetCode() {
    var $modal = $('#setBikeCodeModal');
    var bikeNumber = $modal.data('bike-number');
    var $form = $('#setBikeCodeForm');
    var $input = $('#setBikeCodeInput');
    var $alert = $('#setBikeCodeAlert');
    var errorMessage = $modal.data('error-message') || window.translations['Invalid code format. Use four digits.'];
    var code = $.trim($input.val());

    if (!/^\d{4}$/.test(code)) {
        $input.addClass('is-invalid');
        return;
    }

    $input.removeClass('is-invalid');
    $alert.addClass('d-none').text('');

    var $submitButton = $form.find('button[type="submit"]');
    $submitButton.prop('disabled', true);

    $.ajax({
        url: "/api/v1/admin/bikes/" + bikeNumber + "/lock-code",
        method: "PATCH",
        dataType: "json",
        data: {code: code},
    }).done(function (jsonobject) {
        $('#setBikeCodeModal').modal('hide');
        handleApiResponse(jsonobject, 'fleetconsole');
        bikeInfo();
    }).fail(function (xhr) {
        var message = apiProblemMessage(xhr.responseJSON, errorMessage);
        $alert.removeClass('d-none').text(message);
    }).always(function () {
        $submitButton.prop('disabled', false);
    });
}

function removeNote(bikeNumber) {
    if (window.ga) ga('send', 'event', 'bikes', 'remove-note', bikeNumber);
    $.ajax({
        url: "/api/v1/admin/bikes/" + bikeNumber + "/notes",
        method: "DELETE",
        dataType: "json",
    }).done(function (jsonobject) {
        handleApiResponse(jsonobject, 'fleetconsole');
        bikeInfo();
    }).fail(function (xhr) {
        handleApiError(xhr, 'Unable to remove note.', 'fleetconsole');
    });
}
