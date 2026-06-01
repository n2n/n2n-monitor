export class N2nMonitorErrorHandler {
	constructor(defaultErrorHandler) {
		this.defaultErrorHandler = defaultErrorHandler;
	}

	handleError(error) {
		if (this.reportToMonitor(error)) {
			return;
		}

		this.defaultErrorHandler.handleError(error);
	}

	reportToMonitor(error) {
		if (typeof window === 'undefined') {
			return false;
		}

		try {
			if (window.n2nMonitor?.report(error, { source: 'angular' })) {
				return true;
			}
		} catch (monitorError) {
			console.error(monitorError);
		}

		try {
			return window._n2nMonitorErrorHandler?.(error) ?? false;
		} catch (monitorError) {
			console.error(monitorError);
			return false;
		}
	}
}

export function provideN2nMonitorErrorHandler(errorHandler) {
	return {
		provide: errorHandler,
		useFactory: () => new N2nMonitorErrorHandler(new errorHandler())
	};
}
