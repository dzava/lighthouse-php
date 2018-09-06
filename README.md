# Lighthouse

This package provide a php interface for [Google Lighthouse](https://github.com/GoogleChrome/lighthouse).

Here's an example that will perform the default Lighthouse audits and store the result in `report.json` (You can use the [Lighthouse Viewer](https://googlechrome.github.io/lighthouse/viewer/) to open the report):

```php
use Dzava\Lighthouse\Lighthouse;

(new Lighthouse())
    ->setOutput('report.json')
    ->accessibility()
    ->bestPractices()
    ->performance()
    ->pwa()
    ->seo()
    ->audit('http://example.com');
```

### Output

The `setOutput` method accepts a second argument that can be used to specify the format (json,html).
If the format argument is missing then the file extension will be used to determine the output format.
If the file extension does not specify an accepted format, then json will be used.

You can output both the json and html reports by passing an array as the second argument. For the example
the following code will create two reports `example.report.html` and `example.report.json`.

```php
use Dzava\Lighthouse\Lighthouse;

(new Lighthouse())
    ->setOutput('example', ['html', 'json'])
    ->performance()
    ->audit('http://example.com');
```

### Using a custom config

You can provide your own configuration file using the `withConfig` method.
```php
use Dzava\Lighthouse\Lighthouse;

(new Lighthouse())
    ->withConfig('./my-config.js')
    ->audit('http://example.com');
```

### Customizing node and Lighthouse paths

If you need to manually set these paths, you can do this by calling the `setNodeBinary` and `setLighthousePath` methods.

```php
use Dzava\Lighthouse\Lighthouse;

(new Lighthouse())
    ->setNodeBinary('/usr/bin/node')
    ->setLighthousePath('./lighthouse.js')
    ->audit('http://example.com');
```

### Passing flags to Chrome
Use the `setChromeFlags` method to pass any flags to the Chrome instance.
```php
use Dzava\Lighthouse\Lighthouse;

(new Lighthouse())
    // these are the default flags used
    ->setChromeFlags(['--headless', '--disable-gpu', '--no-sandbox'])
    ->audit('http://example.com');
```
