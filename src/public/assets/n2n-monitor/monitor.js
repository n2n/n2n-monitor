var _a;
var MonitorErrorHandlerImpl = /** @class */ (function () {
    function MonitorErrorHandlerImpl(url) {
        this.monitorUrl = url;
    }
    MonitorErrorHandlerImpl.prototype.handleError = function (error) {
        var options = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: this.errorToBodyJson(error)
        };
        if (this.monitorUrl === undefined) {
            console.error("monitorUrl is undefined");
            return;
        }
        fetch(this.monitorUrl, options).catch(function (error) { return console.error(error); });
        console.error(error);
    };
    MonitorErrorHandlerImpl.prototype.errorToBodyJson = function (error) {
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
            severity: 'high',
            name: error.name,
            message: error.message,
            stackTrace: error.stack
        });
    };
    return MonitorErrorHandlerImpl;
}());
var monitorUrl = (_a = document === null || document === void 0 ? void 0 : document.querySelector('meta[name="monitor-url"]')) === null || _a === void 0 ? void 0 : _a.getAttribute('content');
if (!!monitorUrl) {
    window._n2nMonitorErrorHandler = new MonitorErrorHandlerImpl(new URL(monitorUrl));
    window.addEventListener('error', function (event) {
        window._n2nMonitorErrorHandler.handleError(event.error);
    });
    window.addEventListener('securitypolicyviolation', function (event) {
        var error = new Error("Content Security Policy violation: blockedURI=".concat(event.blockedURI, ", effectiveDirective=").concat(event.effectiveDirective, ", violatedDirective=").concat(event.violatedDirective));
        window._n2nMonitorErrorHandler.handleError(error);
    });
}
