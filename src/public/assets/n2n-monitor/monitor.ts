import { MonitorErrorHandlerImpl } from './monitor-error-handler-impl.js';

const monitorUrl = document?.querySelector('meta[name="monitor-url"]')?.getAttribute('content');
if (!!monitorUrl) {
	const monitorErrorHandler = new MonitorErrorHandlerImpl(new URL(monitorUrl));
	window.addEventListener('error', (event: ErrorEvent) => {
		monitorErrorHandler.handleError(event.error);
	});
	window.addEventListener('securitypolicyviolation', (event: SecurityPolicyViolationEvent) => {
		const error = new Error(`Content Security Policy violation: blockedURI=${event.blockedURI}, effectiveDirective=${event.effectiveDirective}, violatedDirective=${event.violatedDirective}`);
		monitorErrorHandler.handleError(error);
	});
}