var _a;
var MonitorErrorHandlerImpl = /** @class */ (function () {
    function MonitorErrorHandlerImpl(url) {
        this.monitorUrl = url;
    }
    MonitorErrorHandlerImpl.prototype.getSeverityByErrorType = function (errorName) {
        switch (errorName) {
            // add severity depending on error name
            default:
                return 'medium';
        }
    };
    MonitorErrorHandlerImpl.prototype.handleError = function (error) {
        var severity = this.getSeverityByErrorType(error.name);
        var options = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: this.errorToBodyJson(error, severity)
        };
        if (this.monitorUrl === undefined) {
            console.error("monitorUrl is undefined");
            return;
        }
        fetch(this.monitorUrl, options).catch(function (error) { return console.error(error); });
        console.error(error);
    };
    MonitorErrorHandlerImpl.prototype.errorToBodyJson = function (error, severity) {
        var errorStack = error.stack;
        if (!errorStack) {
            errorStack = "";
        }
        var regex = /(https?:\/\/[^\s]+):(\d+):(\d+)/;
        var match = regex.exec(errorStack);
        var fileNameLineAndColumn = null;
        if (match !== null) {
            fileNameLineAndColumn = match[1] + match[2] + match[3];
        }
        return JSON.stringify({
            discriminator: (error.name + fileNameLineAndColumn).replace(/\s/g, ""),
            severity: severity,
            name: error.name,
            message: error.message,
            stackTrace: error.stack,
            url: window.location.href
        });
    };
    return MonitorErrorHandlerImpl;
}());
var monitorUrlMeta = (_a = document.querySelector('meta[name="monitor-url"]')) === null || _a === void 0 ? void 0 : _a.getAttribute('content');
if (monitorUrlMeta) {
    var url = new URL(monitorUrlMeta);
    window._n2nMonitorErrorHandler = new MonitorErrorHandlerImpl(url);
    window.addEventListener('error', function (event) {
        var _a;
        (_a = window._n2nMonitorErrorHandler) === null || _a === void 0 ? void 0 : _a.handleError(event.error);
    });
    window.addEventListener('securitypolicyviolation', function (event) {
        var _a;
        var error = new Error("Content Security Policy violation: blockedURI=".concat(event.blockedURI, ", effectiveDirective=").concat(event.effectiveDirective, ", violatedDirective=").concat(event.violatedDirective));
        error.name = "SecurityPolicyViolationEvent on ".concat(window.location.href);
        (_a = window._n2nMonitorErrorHandler) === null || _a === void 0 ? void 0 : _a.handleError(error);
    });
}
