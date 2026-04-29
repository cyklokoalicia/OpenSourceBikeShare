function apiData(payload) {
    return payload && payload.data !== undefined ? payload.data : payload;
}

function apiProblemMessage(payload, fallback) {
    if (payload && typeof payload === 'object' && typeof payload.detail === 'string') {
        return payload.detail;
    }

    return fallback;
}

function renderAlert(alertType, message, elementId = 'console') {
    if (!message) {
        return;
    }

    const $alert = $('<div/>', {
        class: 'alert alert-' + alertType,
        role: 'alert'
    }).html(message);

    $('#' + elementId).empty().append($alert).fadeIn();
}

function handleApiError(xhr, fallback, elementId = 'console') {
    renderAlert('danger', apiProblemMessage(xhr && xhr.responseJSON, fallback), elementId);
}

function handleApiResponse(jsonobject, elementId = 'console') {
    jsonobject = apiData(jsonobject) || {};
    if (jsonobject.message) {
        renderAlert('success', jsonobject.message, elementId);
    }
}
