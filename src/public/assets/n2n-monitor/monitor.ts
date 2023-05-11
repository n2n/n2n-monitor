class MonitorErrorHandlerImpl {
	private monitorUrl: URL|undefined;

	constructor(url: URL) {
		this.monitorUrl = url;
	}

	handleError(error: Error) {
		const options = {
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

		fetch(this.monitorUrl, options).catch(error => console.error(error));
		console.error(error);
	}

	private errorToBodyJson(error: Error) {
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
			severity: 'high',
			name: error.name,
			message: error.message,
			stackTrace: error.stack
		});
	}
}

const monitorUrl = document?.querySelector('meta[name="monitor-url"]')?.getAttribute('content');
if (!!monitorUrl) {
	(window as any).monitorErrorHandler = new MonitorErrorHandlerImpl(new URL(monitorUrl));

	window.addEventListener('error', (event: ErrorEvent) => {
		(window as any).monitorErrorHandler.handleError(event.error);
	});
	window.addEventListener('securitypolicyviolation', (event: SecurityPolicyViolationEvent) => {
		const error = new Error(`Content Security Policy violation: blockedURI=${event.blockedURI}, effectiveDirective=${event.effectiveDirective}, violatedDirective=${event.violatedDirective}`);
		(window as any).handleError(error);
	});
}