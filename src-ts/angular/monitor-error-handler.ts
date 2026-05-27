declare global {
	interface Window {
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
		try {
			if (typeof window !== 'undefined' && window._n2nMonitorErrorHandler?.(error)) {
				return;
			}
		} catch (monitorError) {
			console.error(monitorError);
		}

		this.defaultErrorHandler.handleError(error);
	}
}

export function provideN2nMonitorErrorHandler(errorHandler: ErrorHandlerConstructor) {
	return {
		provide: errorHandler,
		useFactory: () => new N2nMonitorErrorHandler(new errorHandler())
	};
}
