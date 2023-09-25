interface Window {
	_n2nMonitorErrorHandler?: MonitorErrorHandlerImpl;
}

class MonitorErrorHandlerImpl {
	private monitorUrl: URL | undefined;

	constructor(url: URL) {
		this.monitorUrl = url;
	}

	private getSeverityByErrorType(errorName: string): 'low' | 'medium' | 'high' {
		switch (errorName) {
			// add severity depending on error name
			default:
				return 'medium';
		}
	}

	handleError(error: Error) {
		const severity = this.getSeverityByErrorType(error.name);
		const options = {
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

		fetch(this.monitorUrl, options).catch(error => console.error(error));
		console.error(error);
	}

	private errorToBodyJson(error: Error, severity: 'low' | 'medium' | 'high') {
		let errorStack = error.stack;
		if (!errorStack) {
			errorStack = "";
		}

		const regex = /(https?:\/\/[^\s]+):(\d+):(\d+)/;
		const match = regex.exec(errorStack);

		let fileNameLineAndColumn = null;
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
	}
}

const monitorUrlMeta = document.querySelector('meta[name="monitor-url"]')?.getAttribute('content');
if (monitorUrlMeta) {
	const url = new URL(monitorUrlMeta);
	window._n2nMonitorErrorHandler = new MonitorErrorHandlerImpl(url);

	window.addEventListener('error', (event: ErrorEvent) => {
		window._n2nMonitorErrorHandler?.handleError(event.error);
	});

	window.addEventListener('securitypolicyviolation', (event: SecurityPolicyViolationEvent) => {
		const error = new Error(`Content Security Policy violation: blockedURI=${event.blockedURI}, effectiveDirective=${event.effectiveDirective}, violatedDirective=${event.violatedDirective}`);
		error.name = `SecurityPolicyViolationEvent on ${window.location.href}`;
		window._n2nMonitorErrorHandler?.handleError(error);
	});
}