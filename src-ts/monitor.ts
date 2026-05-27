interface Window {
	_n2nMonitorErrorHandler?: (error: unknown) => boolean;
}

type MonitorSeverity = 'low' | 'medium' | 'high';

let monitorUrl = readMonitorUrl();
window._n2nMonitorErrorHandler = handleMonitorError;

window.addEventListener('error', (event: ErrorEvent) => {
	handleMonitorError(event.error ?? event.message);
});

window.addEventListener('unhandledrejection', (event: PromiseRejectionEvent) => {
	handleMonitorError(event.reason);
});

window.addEventListener('securitypolicyviolation', (event: SecurityPolicyViolationEvent) => {
	const error = new Error(`Content Security Policy violation: blockedURI=${event.blockedURI}, effectiveDirective=${event.effectiveDirective}, violatedDirective=${event.violatedDirective}`);
	error.name = `SecurityPolicyViolationEvent on ${window.location.href}`;
	handleMonitorError(error);
});

function handleMonitorError(error: unknown): boolean {
	if (!monitorUrl) {
		monitorUrl = readMonitorUrl();
	}

	if (!monitorUrl) {
		return false;
	}

	const normalizedError = normalizeError(error);

	fetch(monitorUrl.toString(), {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json'
		},
		body: JSON.stringify(createMonitorPayload(normalizedError))
	}).catch((fetchError) => console.error(fetchError));

	console.error(normalizedError);
	return true;
}

function readMonitorUrl(): URL|null {
	const monitorUrlMeta = document.querySelector('meta[name="monitor-url"]')?.getAttribute('content');
	if (!monitorUrlMeta) {
		return null;
	}

	try {
		return new URL(monitorUrlMeta, window.location.href);
	} catch (error) {
		console.error(error);
		return null;
	}
}

function normalizeError(error: unknown): Error {
	if (error instanceof Error) {
		return error;
	}

	const normalizedError = new Error(stringifyError(error));
	normalizedError.name = 'NonErrorThrown';
	return normalizedError;
}

function stringifyError(error: unknown): string {
	if (typeof error === 'string') {
		return error;
	}

	try {
		const json = JSON.stringify(error);
		return json === undefined ? String(error) : json;
	} catch {
		return String(error);
	}
}

function createMonitorPayload(error: Error): {
	discriminator: string,
	severity: MonitorSeverity,
	name: string,
	message: string,
	stackTrace: string|undefined,
	url: string
} {
	return {
		discriminator: (error.name + extractFileNameLineAndColumn(error.stack)).replace(/\s/g, ''),
		severity: getSeverityByErrorType(error.name),
		name: error.name,
		message: error.message,
		stackTrace: error.stack,
		url: window.location.href
	};
}

function getSeverityByErrorType(errorName: string): MonitorSeverity {
	switch (errorName) {
		// add severity depending on error name
		default:
			return 'medium';
	}
}

function extractFileNameLineAndColumn(errorStack: string|undefined): string|null {
	const match = /(https?:\/\/[^\s]+):(\d+):(\d+)/.exec(errorStack ?? '');
	if (match === null) {
		return null;
	}

	return match[1] + match[2] + match[3];
}
