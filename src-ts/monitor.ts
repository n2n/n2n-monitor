interface Window {
	n2nMonitor?: N2nMonitor;
	_n2nMonitorErrorHandler?: (error: unknown) => boolean;
}

type MonitorSeverity = 'low' | 'medium' | 'high';
type MonitorContext = Record<string, unknown>;

interface N2nMonitor {
	report(error: unknown, context?: MonitorContext): boolean;
}

const DEDUPE_TIME_WINDOW_MS = 1000;

const reportedErrors = new WeakMap<Error, number>();
const reportedFingerprints = new Map<string, number>();

let monitorUrl = readMonitorUrl();

const n2nMonitor = window.n2nMonitor ?? {} as N2nMonitor;
n2nMonitor.report = reportMonitorError;
window.n2nMonitor = n2nMonitor;
window._n2nMonitorErrorHandler = (error: unknown) => reportMonitorError(error);

window.addEventListener('error', (event: Event) => {
	try {
		handleWindowError(event);
	} catch (monitorError) {
		console.error(monitorError);
	}
}, true);

window.addEventListener('unhandledrejection', (event: PromiseRejectionEvent) => {
	try {
		reportMonitorError(event.reason, { source: 'unhandledrejection' });
	} catch (monitorError) {
		console.error(monitorError);
	}
});

window.addEventListener('securitypolicyviolation', (event: SecurityPolicyViolationEvent) => {
	try {
		const error = new Error(`Content Security Policy violation: blockedURI=${event.blockedURI}, effectiveDirective=${event.effectiveDirective}, violatedDirective=${event.violatedDirective}`);
		error.name = `SecurityPolicyViolationEvent on ${window.location.href}`;
		reportMonitorError(error, {
			source: 'securitypolicyviolation',
			blockedURI: event.blockedURI,
			effectiveDirective: event.effectiveDirective,
			violatedDirective: event.violatedDirective
		});
	} catch (monitorError) {
		console.error(monitorError);
	}
});

function reportMonitorError(error: unknown, context?: MonitorContext): boolean {
	try {
		return handleMonitorError(error, context);
	} catch (monitorError) {
		console.error(monitorError);
		return false;
	}
}

function handleMonitorError(error: unknown, context?: MonitorContext): boolean {
	if (!monitorUrl) {
		monitorUrl = readMonitorUrl();
	}

	if (!monitorUrl || typeof fetch !== 'function') {
		return false;
	}

	const normalizedError = normalizeError(error);
	if (isDuplicateReport(normalizedError)) {
		return true;
	}

	fetch(monitorUrl.toString(), {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json'
		},
		body: JSON.stringify(createMonitorPayload(normalizedError, context))
	}).catch((fetchError) => console.error(fetchError));

	console.error(normalizedError);
	return true;
}

function handleWindowError(event: Event): void {
	if (isRuntimeErrorEvent(event)) {
		reportMonitorError(event.error ?? event.message, {
			source: 'error',
			filename: event.filename,
			lineno: event.lineno,
			colno: event.colno
		});
		return;
	}

	const resourceContext = createResourceContext(event.target);
	if (resourceContext === null) {
		return;
	}

	const resource = resourceContext.url ? `${resourceContext.tagName} ${resourceContext.url}` : resourceContext.tagName;
	const error = new Error(`Resource failed to load: ${resource}`);
	error.name = 'ResourceError';
	reportMonitorError(error, resourceContext);
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

function createMonitorPayload(error: Error, context?: MonitorContext): {
	discriminator: string,
	severity: MonitorSeverity,
	name: string,
	message: string,
	stackTrace: string|undefined,
	url: string,
	context?: MonitorContext
} {
	return {
		discriminator: (error.name + extractFileNameLineAndColumn(error.stack)).replace(/\s/g, ''),
		severity: getSeverityByErrorType(error.name),
		name: error.name,
		message: error.message,
		stackTrace: error.stack,
		url: window.location.href,
		context
	};
}

function isRuntimeErrorEvent(event: Event): event is ErrorEvent {
	return typeof ErrorEvent !== 'undefined' && event instanceof ErrorEvent;
}

function createResourceContext(target: EventTarget|null): MonitorContext|null {
	if (typeof Element === 'undefined' || !(target instanceof Element)) {
		return null;
	}

	if (target === document.documentElement || target === document.body) {
		return null;
	}

	const tagName = target.tagName.toLowerCase();
	const url = target.getAttribute('src') ?? target.getAttribute('href') ?? target.getAttribute('data') ?? target.getAttribute('poster');

	return {
		source: 'resource',
		tagName,
		url: normalizeResourceUrl(url)
	};
}

function normalizeResourceUrl(url: string|null): string|undefined {
	if (!url) {
		return undefined;
	}

	try {
		return new URL(url, window.location.href).toString();
	} catch {
		return url;
	}
}

function isDuplicateReport(error: Error): boolean {
	const now = Date.now();
	const objectReportedAt = reportedErrors.get(error);
	const fingerprint = createErrorFingerprint(error);
	const fingerprintReportedAt = reportedFingerprints.get(fingerprint);

	if ((objectReportedAt !== undefined && now - objectReportedAt < DEDUPE_TIME_WINDOW_MS)
			|| (fingerprintReportedAt !== undefined && now - fingerprintReportedAt < DEDUPE_TIME_WINDOW_MS)) {
		return true;
	}

	reportedErrors.set(error, now);
	reportedFingerprints.set(fingerprint, now);
	cleanupReportedFingerprints(now);
	return false;
}

function createErrorFingerprint(error: Error): string {
	return [error.name, error.message, extractFileNameLineAndColumn(error.stack)].join('|');
}

function cleanupReportedFingerprints(now: number): void {
	for (const [fingerprint, reportedAt] of reportedFingerprints) {
		if (now - reportedAt > DEDUPE_TIME_WINDOW_MS) {
			reportedFingerprints.delete(fingerprint);
		}
	}
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
