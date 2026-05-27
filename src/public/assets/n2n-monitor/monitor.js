var monitorUrl = readMonitorUrl();
window._n2nMonitorErrorHandler = handleMonitorError;
window.addEventListener('error', function (event) {
    var _a;
    handleMonitorError((_a = event.error) !== null && _a !== void 0 ? _a : event.message);
});
window.addEventListener('unhandledrejection', function (event) {
    handleMonitorError(event.reason);
});
window.addEventListener('securitypolicyviolation', function (event) {
    var error = new Error("Content Security Policy violation: blockedURI=".concat(event.blockedURI, ", effectiveDirective=").concat(event.effectiveDirective, ", violatedDirective=").concat(event.violatedDirective));
    error.name = "SecurityPolicyViolationEvent on ".concat(window.location.href);
    handleMonitorError(error);
});
function handleMonitorError(error) {
    if (!monitorUrl) {
        monitorUrl = readMonitorUrl();
    }
    if (!monitorUrl) {
        return false;
    }
    var normalizedError = normalizeError(error);
    fetch(monitorUrl.toString(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(createMonitorPayload(normalizedError))
    }).catch(function (fetchError) { return console.error(fetchError); });
    console.error(normalizedError);
    return true;
}
function readMonitorUrl() {
    var _a;
    var monitorUrlMeta = (_a = document.querySelector('meta[name="monitor-url"]')) === null || _a === void 0 ? void 0 : _a.getAttribute('content');
    if (!monitorUrlMeta) {
        return null;
    }
    try {
        return new URL(monitorUrlMeta, window.location.href);
    }
    catch (error) {
        console.error(error);
        return null;
    }
}
function normalizeError(error) {
    if (error instanceof Error) {
        return error;
    }
    var normalizedError = new Error(stringifyError(error));
    normalizedError.name = 'NonErrorThrown';
    return normalizedError;
}
function stringifyError(error) {
    if (typeof error === 'string') {
        return error;
    }
    try {
        var json = JSON.stringify(error);
        return json === undefined ? String(error) : json;
    }
    catch (_a) {
        return String(error);
    }
}
function createMonitorPayload(error) {
    return {
        discriminator: (error.name + extractFileNameLineAndColumn(error.stack)).replace(/\s/g, ''),
        severity: getSeverityByErrorType(error.name),
        name: error.name,
        message: error.message,
        stackTrace: error.stack,
        url: window.location.href
    };
}
function getSeverityByErrorType(errorName) {
    switch (errorName) {
        // add severity depending on error name
        default:
            return 'medium';
    }
}
function extractFileNameLineAndColumn(errorStack) {
    var match = /(https?:\/\/[^\s]+):(\d+):(\d+)/.exec(errorStack !== null && errorStack !== void 0 ? errorStack : '');
    if (match === null) {
        return null;
    }
    return match[1] + match[2] + match[3];
}
