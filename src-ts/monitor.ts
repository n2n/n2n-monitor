(function() {
	'use strict';
	interface ErrorPayload {
		discriminator: string;
		severity: ErrorSeverity;
		name: string;
		message: string;
		stackTrace: string | undefined;
		url: string;
	}

	type ErrorSeverity = 'low' | 'medium' | 'high';

	class ErrorMonitor {
		private readonly monitorUrl: URL;
		private lastErrorKey: string | null = null;
		private isProcessingError = false;
		private readonly originalConsoleError: typeof console.error;

		constructor(monitorUrl: URL) {
			this.monitorUrl = monitorUrl;
			this.originalConsoleError = console.error;
		}

		private determineSeverity(errorName: string): ErrorSeverity {
			switch (errorName) {
				case 'TypeError':
				case 'ReferenceError':
				case 'SyntaxError':
				case 'SecurityPolicyViolationEvent':
				case 'AngularConsoleError':
				case 'AngularZoneError':
					return 'high';
				case 'NetworkError':
				case 'TimeoutError':
				case 'UnhandledPromiseRejection':
					return 'medium';
				default:
					return 'medium';
			}
		}

		private createErrorKey(error: Error): string {
			const stackPreview = error.stack?.substring(0, 100) || '';
			return `${error.name}:${error.message}:${stackPreview}`;
		}

		private extractLocationFromStack(stackTrace: string): string {
			const locationPattern = /(https?:\/\/[^\s]+):(\d+):(\d+)/;
			const match = locationPattern.exec(stackTrace);
			return match ? `${match[1]}${match[2]}${match[3]}` : '';
		}

		private createDiscriminator(error: Error): string {
			const stackLocation = error.stack ? this.extractLocationFromStack(error.stack) : '';
			return `${error.name}${stackLocation}`.replace(/\s/g, '');
		}

		private buildErrorPayload(error: Error): ErrorPayload {
			return {
				discriminator: this.createDiscriminator(error),
				severity: this.determineSeverity(error.name),
				name: error.name,
				message: error.message,
				stackTrace: error.stack,
				url: window.location.href
			};
		}

		private sendToMonitor(payload: ErrorPayload): void {
			fetch(this.monitorUrl.toString(), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(payload)
			}).catch((fetchError) => {
				const errorMessage = fetchError instanceof Error ? fetchError.message : 'Unknown error';
				this.originalConsoleError.call(console, 'Monitor: Failed to send error report:', errorMessage);
			});
		}

		private shouldSkipDuplicateError(errorKey: string): boolean {
			if (this.lastErrorKey === errorKey) {
				return true;
			}
			this.lastErrorKey = errorKey;
			return false;
		}

		handleError(error: Error, shouldLogToConsole: boolean = true): void {
			if (this.isProcessingError) {
				return;
			}

			this.isProcessingError = true;

			if (shouldLogToConsole) {
				this.originalConsoleError.call(console, error);
			}

			const errorKey = this.createErrorKey(error);

			if (this.shouldSkipDuplicateError(errorKey)) {
				this.isProcessingError = false;
				return;
			}

			if (!this.monitorUrl) {
				this.originalConsoleError.call(console, 'Monitor: monitorUrl is undefined');
				this.isProcessingError = false;
				return;
			}

			const payload = this.buildErrorPayload(error);
			this.sendToMonitor(payload);
			this.isProcessingError = false;
		}
	}

	function createSecurityViolationError(event: SecurityPolicyViolationEvent): Error {
		const message = `Content Security Policy violation: blockedURI=${event.blockedURI}, effectiveDirective=${event.effectiveDirective}, violatedDirective=${event.violatedDirective}`;
		const error = new Error(message);
		error.name = `SecurityPolicyViolationEvent on ${window.location.href}`;
		return error;
	}

	function createPromiseRejectionError(event: PromiseRejectionEvent): Error {
		const message = event.reason?.toString() || 'Unhandled Promise Rejection';
		const error = new Error(message);
		error.name = 'UnhandledPromiseRejection';
		return error;
	}

	function setupAngularErrorHandling(errorMonitor: ErrorMonitor): void {
		const windowAny = window as any;

		if (!windowAny.ng && !windowAny.Zone) {
			return;
		}

		if (windowAny.Zone) {
			const zone = windowAny.Zone;
			const originalErrorHandler = zone.current.onHandleError;

			zone.current.onHandleError = function(
					parentZoneDelegate: any,
					currentZone: any,
					targetZone: any,
					error: Error) {
				error.name = 'AngularZoneError';
				errorMonitor.handleError(error);

				if (originalErrorHandler) {
					return originalErrorHandler.call(this, parentZoneDelegate, currentZone, targetZone, error);
				}
				return false;
			};
		}
	}

	function setupConsoleErrorInterception(errorMonitor: ErrorMonitor): void {
		const originalConsoleError = console.error;

		console.error = function(...args: any[]) {
			originalConsoleError.apply(this, args);

			const errorMessage = args.join(' ');
			const hasErrorKeyword = errorMessage.indexOf('ERROR') !== -1;
			const firstArgIsError = args[0] instanceof Error;

			if (hasErrorKeyword || firstArgIsError) {
				const error = firstArgIsError
						? args[0]
						: (() => {
							const newError = new Error(errorMessage);
							newError.name = 'AngularConsoleError';
							return newError;
						})();

				errorMonitor.handleError(error, false);
			}
		};
	}

	function initializeErrorMonitoring(): void {
		const monitorUrlMeta = document.querySelector('meta[name="monitor-url"]');
		const monitorUrlMetaContent = monitorUrlMeta?.getAttribute('content');

		if (!monitorUrlMetaContent) {
			return;
		}

		try {
			const monitorUrl = new URL(monitorUrlMetaContent);
			const errorMonitor = new ErrorMonitor(monitorUrl);

			(window as any)._n2nMonitorErrorHandler = errorMonitor;

			window.addEventListener('error', (event: ErrorEvent) => {
				if (event.error) {
					errorMonitor.handleError(event.error);
				}
			});

			window.addEventListener('securitypolicyviolation', (event: SecurityPolicyViolationEvent) => {
				const violationError = createSecurityViolationError(event);
				errorMonitor.handleError(violationError);
			});

			window.addEventListener('unhandledrejection', (event: PromiseRejectionEvent) => {
				const rejectionError = createPromiseRejectionError(event);
				errorMonitor.handleError(rejectionError);
			});

			setupAngularErrorHandling(errorMonitor);
			setupConsoleErrorInterception(errorMonitor);

		} catch (urlError) {
			console.error('Monitor: Invalid URL configuration:', urlError);
		}
	}

	initializeErrorMonitoring();

})();