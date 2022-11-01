<div align="center">

# The PHP Flange Framework

[![PHP Version](https://img.shields.io/packagist/php-v/biurad/flange.svg?style=flat-square&colorB=%238892BF)](http://php.net)
[![Latest Version](https://img.shields.io/packagist/v/biurad/flange.svg?style=flat-square)](https://packagist.org/packages/biurad/flange)
[![Workflow Status](https://img.shields.io/github/workflow/status/biurad/flange/build?style=flat-square)](https://github.com/biurad/flange/actions?query=workflow%3Abuild)
[![Code Maintainability](https://img.shields.io/codeclimate/maintainability/biurad/flange?style=flat-square)](https://codeclimate.com/github/biurad/flange)
[![Coverage Status](https://img.shields.io/codecov/c/github/biurad/flange?style=flat-square)](https://codecov.io/gh/biurad/flange)
[![Quality Score](https://img.shields.io/scrutinizer/g/biurad/flange.svg?style=flat-square)](https://scrutinizer-ci.com/g/biurad/flange)

</div>

---

**Flange** is an incredibly fast, compilable and scalable framework for [PHP][1] 8.0+ based on [PSR-7][2], [PSR-11][3], [PSR-14][4] and [PSR-15][5] with support for annotations/attributes, created by [Divine Niiquaye][@divineniiquaye].

This library is shipped with lots of features that suites developers needs in developing web applications. Flange is truly scalable, has less dependencies and has high performance.

## 📦 Installation & Basic Usage

This project requires [PHP][1] 8.0 or higher. The recommended way to install, is via [Composer][6]. Simply run:

```bash
$ composer require biurad/flange 2.0.*
```

Flange is built based on [Flight Routing][7], [Biurad DI][8], [Symfony components][9] and [Biurad libraries][10]. Flange is a fully PSR complaint [PHP][1] framework, fully customizable and can even be used to develop from small to large projects:

```php
require_once __DIR__ . '/vendor/autoload.php';

// Boot the application.
$app = new Flange\Application();

// Add a route to application
$app->match('/hello/{name:\w+}', to: fn (string $name): string => 'Hello ' . $app->escape()->escapeHtml($name));

$extensions = [
    [Flange\Extensions\CoreExtension::class, [__DIR__]],
    // You can add more extensions here ...
];

//If you want to use extensions, here is an example:
$app->loadExtensions($extensions, ['config' => '%project_dir%/config']);

// You can set custom pages for caught exceptions, using default event dispatcher, or your custom event dispatcher.
$app->getDispatcher()?->addListener(Flange\Events::EXCEPTION, new ErrorListener(), -8);

$app->run();
```

Working on a big project!, it is advisable to use the application's cacheable version. This gives you over 60% - 100% more performance than using the un-cacheable Application class with extensions.

```php
use function Rade\DI\Loader\{param, phpCode, wrap};

$config = [
    'cacheDir' => __DIR__ . '/caches',
    'debug' => $_ENV['APP_DEBUG'] ?? false, // Set the debug mode environment
];

// Setup cache for application.
$app = Flange\AppBuilder::build(static function (Flange\AppBuilder $creator): void {
    // Add resource to re-compile if changes are made to this file.
    $creator->addResource(new FileResource(__FILE__));

    // Adding routes requires the Rade\DI\Extensions\RoutingExtension to be loaded.
    // Routes should always be added before Rade\DI\Extensions\RoutingExtension is booted, else it will not be compiled.
    $creator->match('/hello/{name:\w+}', to: phpCode('fn (string $name): string => \'Hello \' . $this->escape()->escapeHtml($name);'));

    $extensions = [
        [Flange\Extensions\CoreExtension::class, [__DIR__]],
        // You can add more extensions here ...
    ];

    //If you want to use extensions, here is an example as its recommended to use extensions to build your application.
    $creator->loadExtensions($extensions, ['config' => '%project_dir%/config']);

    // You can set custom pages for caught exceptions, using default event dispatcher, or your custom event dispatcher.
    $creator->definition('events.dispatcher')->bind('addListener', [Flange\Events::EXCEPTION, wrap(ErrorListener::class), -8]);
}, $config);

$app->run(); // Boot the application.

```

Here's an example of a custom error you can use for your application.

```php
use Biurad\Http\Response\HtmlResponse;
use Flange\Event\ExceptionEvent;

class ErrorListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        // If extensions were loaded, the %project_dir% will exist, else replace will absolute path
        $errorsPath = $event->getApplication()->parameter('%project_dir%/errors/');

        $code = $event->getThrowable()->getCode();
        $templates = [
            $errorsPath . \substr($code, 0, 2) . 'x.html.php', // 40x.html.php format ...
            $errorsPath . \substr($code, 0, 1) . 'xx.html.php', // 4xx.html.php format ...
            $errorsPath . $code . '.html.php', // 404.html.php format ...
            $errorsPath . 'default.html.php',
        ];

        // Tries to load a template file from a list of error templates.
        foreach ($template as $template) {
            if (\file_exists($template)) {
                $event->setResponse(
                    (static function () use ($template, $code) {
                        \ob_start();
                        include __DIR__ . $template;

                        return new HtmlResponse(\ob_get_clean(), (int) $code);
                    })()
                );
            }
        }
    }
}
```

Important to note that, using [PSR-15][5] middlewares stack uses the [PHP][1] SPL Queue class with the following algorithm, LAST <- FIRST : FIRST -> LAST. Loading extensions and events listeners by default uses the priority stacking algorithm (which means the higher the priority, the earlier an extension or event listener will be triggered in the chain) which defaults to 0.

## 📓 Documentation

In-depth documentation on how to use this library, kindly check out the [documentation][11] for this library. It is also recommended to browse through unit tests in the [tests](./tests/) directory.

## 🙌 Sponsors

If this library made it into your project, or you interested in supporting us, please consider [donating][12] to support future development.

## 👥 Credits & Acknowledgements

- [Divine Niiquaye Ibok][@divineniiquaye] is the author this library.
- [All Contributors][13] who contributed to this project.

## 📄 License

Flange is completely free and released under the [BSD 3 License](LICENSE).

[1]: https://php.net
[2]: http://www.php-fig.org/psr/psr-7/
[3]: http://www.php-fig.org/psr/psr-11/
[4]: http://www.php-fig.org/psr/psr-14/
[5]: http://www.php-fig.org/psr/psr-15/
[6]: https://getcomposer.org
[7]: https://github.com/divineniiquaye/flight-routing
[8]: https://github.com/biurad/php-di
[9]: https://github.com/symfony
[10]: https://github.com/biurad
[11]: https://divinenii.com/courses/flange/
[12]: https://biurad.com/sponser
[13]: https://github.com/biurad/flange/contributors
[@divineniiquaye]: https://github.com/divineniiquaye
