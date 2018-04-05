# Lighthouse

This package provide a php interface for [Google Lighthouse](https://github.com/GoogleChrome/lighthouse).

Here's an example that will perform the default Lighthouse audits and store the result in `report.json` (You can use the [Lighthouse Viewer](https://googlechrome.github.io/lighthouse/viewer/) to open the report):

```php
use Dzava\Lighthouse;

(new Lighthouse())
	->setOutput('report.json')
    ->accessibility()
	->bestPractices()
    ->performance()
    ->pwa()
    ->seo()
    ->audit('http://example.com');
```

### Using a custom config

You can provide your own configuration file using the `withConfig` method.
```php
(new Lighthouse())
	->withConfig('./my-config.js')
    ->audit('http://example.com');
```

### Customizing node and Lighthouse paths

If you need to manually set these paths, you can do this by calling the `setNodeBinary` and `setLighthousePath` methods.

```php
(new Lighthouse())
	->setNodeBinary('/usr/bin/node')
    ->setLighthousePath('./lighthouse.js')
    ->audit('http://example.com');
```

### Passing flags to Chrome
Use the `setChromeFlags` method to pass any flags to the Chrome instance.
```php
(new Lighthouse())
	// these are the default flags used
	->setChromeFlags(['--headless', '--disable-gpu', '--no-sandbox'])
    ->audit('http://example.com');
```
