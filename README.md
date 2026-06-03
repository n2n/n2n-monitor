# n2n-monitor

Monitor module for n2n applications.

`monitor.js` is a framework-agnostic browser reporter for n2n pages.

## PHP Setup

```php
use n2n\monitor\ui\MonitorHtmlBuilder;

$monitorHtmlBuilder = new MonitorHtmlBuilder($view);
$monitorHtmlBuilder->meta()->setup();
```

This emits:

- `meta[name="monitor-url"]`
- the `n2n-monitor/monitor.js` script

Load `monitor.js` before other JavaScript code.

## JavaScript

Plain JavaScript projects do not need extra setup beyond `$monitorHtmlBuilder->meta()->setup()`.

The script installs:

- `window.n2nMonitor.report(error, context?)`
- `window._n2nMonitorErrorHandler(error)`

Angular projects should forward caught Angular errors to `window.n2nMonitor.report(error, { source: 'angular' })`.

## Payload

Reports are POSTed as JSON to the URL from `meta[name="monitor-url"]`.

Payload fields:

- `discriminator`
- `severity`
- `name`
- `message`
- `stackTrace`
- `url`
- `context` when provided