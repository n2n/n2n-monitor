declare global {
	interface Window {
		n2nMonitor?: {
			report(error: unknown, context?: Record<string, unknown>): boolean;
		};
		_n2nMonitorErrorHandler?: (error: unknown) => boolean;
	}
}

interface ErrorHandlerLike {
	handleError(error: unknown): void;
}

type ErrorHandlerConstructor = new () => ErrorHandlerLike;

export class N2nMonitorErrorHandler implements ErrorHandlerLike {
	constructor(private readonly defaultErrorHandler: ErrorHandlerLike) {
	}

	handleError(error: unknown): void {
		if (this.reportToMonitor(error)) {
			return;
		}

		this.defaultErrorHandler.handleError(error);
	}

	private reportToMonitor(error: unknown): boolean {
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

export function provideN2nMonitorErrorHandler(errorHandler: ErrorHandlerConstructor) {
	return {
		provide: errorHandler,
		useFactory: () => new N2nMonitorErrorHandler(new errorHandler())
	};
}
