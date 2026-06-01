# n2n-monitor

Monitor module for n2n applications.

- `monitor.js`: framework-agnostic browser reporter for plain JavaScript pages.
- `n2n-monitor/angular`: small Angular `ErrorHandler` bridge that forwards Angular errors into `monitor.js`.

## PHP Setup

```php
use n2n\monitor\ui\MonitorHtmlBuilder;

$monitorHtmlBuilder = new MonitorHtmlBuilder($view);
$monitorHtmlBuilder->meta()->setup();
```

This emits:

- `meta[name="monitor-url"]`
- the `n2n-monitor/monitor.js` script

Load `monitor.js` before other javascript code.

## Plain JavaScript

Plain JavaScript projects do not need extra setup beyond `$monitorHtmlBuilder->meta()->setup()`.

## Angular

Angular catches many runtime errors internally and sends them to Angular's `ErrorHandler`, 
so Angular apps must register the monitor bridge in their root application config.

Install the frontend helper from the Composer-installed module:

```json
{
  "dependencies": {
    "n2n-monitor": "file:../../src-php/vendor/n2n/n2n-monitor"
  }
}
```

Adjust the relative path for the project layout.

Then add the provider:

```ts
import { ErrorHandler } from '@angular/core';
import { provideN2nMonitorErrorHandler } from 'n2n-monitor/angular';

export const appConfig = {
	providers: [
		provideN2nMonitorErrorHandler(ErrorHandler)
	]
};
```

The Angular helper only forwards errors. It does not build payloads, read meta tags, or POST anything itself. That logic belongs to `monitor.js`.

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