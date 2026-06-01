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

export declare class N2nMonitorErrorHandler implements ErrorHandlerLike {
	constructor(defaultErrorHandler: ErrorHandlerLike);
	handleError(error: unknown): void;
	private reportToMonitor;
}

export declare function provideN2nMonitorErrorHandler(errorHandler: ErrorHandlerConstructor): {
	provide: ErrorHandlerConstructor;
	useFactory: () => N2nMonitorErrorHandler;
};
