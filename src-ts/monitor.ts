interface Window {
	n2nMonitor?: MonitorErrorHandlerImpl;
}

type MonitorSeverity = 'low'|'medium'|'high';

const DEDUPE_TIME_WINDOW_MS = 1000;

class MonitorErrorHandlerImpl {
	private monitorUrl: URL|undefined;
	private reportedErrors = new WeakMap<Error, number>();
	private reportedFingerprints = new Map<string, number>();

	constructor(url: URL) {
		this.monitorUrl = url;
	}

	private getSeverityByErrorType(errorName: string): MonitorSeverity {
		switch (errorName) {
			// add severity depending on error name
			default:
				return 'medium';
		}
	}

	report(error: unknown): boolean {
		try {
			this.handleError(this.normalizeError(error));
			return true;
		} catch (monitorError) {
			console.error(monitorError);
			return false;
		}
	}

	handleError(error: Error) {
		if (this.isDuplicateReport(error)) {
			return;
		}

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

	private normalizeError(error: unknown): Error {
		if (error instanceof Error) {
			return error;
		}

		const normalizedError = new Error(this.stringifyError(error));
		normalizedError.name = 'ReportedNonError';
		return normalizedError;
	}

	private stringifyError(error: unknown): string {
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

	private errorToBodyJson(error: Error, severity: MonitorSeverity) {
		const fileNameLineAndColumn = this.extractFileNameLineAndColumn(error.stack);

		return JSON.stringify({
			discriminator: (error.name + fileNameLineAndColumn).replace(/\s/g, ""),
			severity: severity,
			name: error.name,
			message: error.message,
			stackTrace: error.stack,
			url: window.location.href
		});
	}

	private isDuplicateReport(error: Error): boolean {
		const now = Date.now();
		const objectReportedAt = this.reportedErrors.get(error);
		const fingerprint = this.createErrorFingerprint(error);
		const fingerprintReportedAt = this.reportedFingerprints.get(fingerprint);

		if ((objectReportedAt !== undefined && now - objectReportedAt < DEDUPE_TIME_WINDOW_MS)
				|| (fingerprintReportedAt !== undefined && now - fingerprintReportedAt < DEDUPE_TIME_WINDOW_MS)) {
			return true;
		}

		this.reportedErrors.set(error, now);
		this.reportedFingerprints.set(fingerprint, now);
		this.cleanupReportedFingerprints(now);
		return false;
	}

	private createErrorFingerprint(error: Error): string {
		return [error.name, error.message, this.extractFileNameLineAndColumn(error.stack)].join('|');
	}

	private cleanupReportedFingerprints(now: number): void {
		for (const [fingerprint, reportedAt] of this.reportedFingerprints) {
			if (now - reportedAt > DEDUPE_TIME_WINDOW_MS) {
				this.reportedFingerprints.delete(fingerprint);
			}
		}
	}

	private extractFileNameLineAndColumn(errorStack: string|undefined): string|null {
		if (!errorStack) {
			errorStack = "";
		}

		const regex = /(https?:\/\/[^\s]+):(\d+):(\d+)/;
		const match = regex.exec(errorStack);
		if (match === null) {
			return null;
		}

		return match[1] + match[2] + match[3];
	}
}

const monitorUrlMeta = document.querySelector('meta[name="monitor-url"]')?.getAttribute('content');
if (monitorUrlMeta) {
	const url = new URL(monitorUrlMeta, window.location.href);
	window.n2nMonitor = new MonitorErrorHandlerImpl(url);

	window.addEventListener('error', (event: ErrorEvent) => {
		window.n2nMonitor?.report(event.error ?? event.message);
	});

	window.addEventListener('unhandledrejection', (event: PromiseRejectionEvent) => {
		window.n2nMonitor?.report(event.reason);
	});

	window.addEventListener('securitypolicyviolation', (event: SecurityPolicyViolationEvent) => {
		const error = new Error(`Content Security Policy violation: blockedURI=${event.blockedURI}, effectiveDirective=${event.effectiveDirective}, violatedDirective=${event.violatedDirective}`);
		error.name = `SecurityPolicyViolationEvent on ${window.location.href}`;
		window.n2nMonitor?.report(error);
	});
}
