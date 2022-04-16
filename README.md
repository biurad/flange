<div align="center">

# The PHP Rade Framework

[![PHP Version](https://img.shields.io/packagist/php-v/divineniiquaye/php-rade.svg?style=flat-square&colorB=%238892BF)](http://php.net)
[![Latest Version](https://img.shields.io/packagist/v/divineniiquaye/php-rade.svg?style=flat-square)](https://packagist.org/packages/divineniiquaye/php-rade)
[![Workflow Status](https://img.shields.io/github/workflow/status/divineniiquaye/php-rade/build?style=flat-square)](https://github.com/divineniiquaye/php-rade/actions?query=workflow%3Abuild)
[![Code Maintainability](https://img.shields.io/codeclimate/maintainability/divineniiquaye/php-rade?style=flat-square)](https://codeclimate.com/github/divineniiquaye/php-rade)
[![Coverage Status](https://img.shields.io/codecov/c/github/divineniiquaye/php-rade?style=flat-square)](https://codecov.io/gh/divineniiquaye/php-rade)
[![Quality Score](https://img.shields.io/scrutinizer/g/divineniiquaye/php-rade.svg?style=flat-square)](https://scrutinizer-ci.com/g/divineniiquaye/php-rade)

</div>

---

**divineniiquaye/php-rade** is an incredibly fast, micro, compilable and scalable framework for [PHP] 7.4+ based on [PSR-7], [PSR-11], [PSR-14] and [PSR-15] with support for annotations/attributes, created by [Divine Niiquaye][@divineniiquaye].

This library is shipped with lots of features that suites developers needs in developing web applications. Rade is truly scalable, has less dependencies and has high performance.

## 📦 Installation & Basic Usage

This project requires [PHP] 7.4 or higher. The recommended way to install, is via [Composer]. Simply run:

```bash
$ composer require divineniiquaye/php-rade 2.0.*
```

Rade is built based on [Flight Routing][], [Rade DI][], [Symfony components][] and [Biurad libraries][]. Rade is a fully PSR complaint [PHP] framework, fully customizable and can even be used to develop from small to large projects:

```php
require_once __DIR__ . '/vendor/autoload.php';

// Boot the application.
$app = new Rade\Application();

// Add a route to application
$app->match('/hello/{name:\w+}', to: fn (string $name): string => 'Hello ' . $app->escape()->escapeHtml($name));

$extensions = [
    [Rade\DI\Extensions\CoreExtension::class, [__DIR__]],
    // You can add more extensions here ...
];

//If you want to use extensions, here is an example:
$app->loadExtensions($extensions, ['config' => ['debug' => $_ENV['APP_DEBUG'] ?? false]]);

// You can set custom pages for caught exceptions, using default event dispatcher, or your custom event dispatcher.
$app->getDispatcher()->addListener(Rade\Events::EXCEPTION, new ErrorListener(), -8);

$app->run();
```

Working on a big project!, it is advisable to use the application's cacheable version. This gives you over 100% more performance than using the un-cacheable Application class with extensions,

```php
use function Rade\DI\Loader\{phpCode, wrap};

$config = [
    'cacheDir' => __DIR__ . '/caches',
    'debug' => $_ENV['APP_DEBUG'] ?? false, // Set the debug mode environment
];

// Setup cache for application.
$app = \Rade\AppBuilder::build(static function (\Rade\AppBuilder $creator): void {
    // Add resource to re-compile if changes are made to this file.
    $creator->addResource(new FileResource(__FILE__));

    // Adding routes requires the Rade\DI\Extensions\RoutingExtension to be loaded.
    // Routes should always be added before Rade\DI\Extensions\RoutingExtension is booted, else it will not be compiled.
    $creator->match('/hello/{name:\w+}', to: phpCode('fn (string $name): string => \'Hello \' . $this->escape()->escapeHtml($name);'));

    $extensions = [
        [Rade\DI\Extensions\CoreExtension::class, [__DIR__]],
        // You can add more extensions here ...
    ];

    //If you want to use extensions, here is an example as its recommended to use extensions to build your application.
    $creator->loadExtensions($extensions, ['config' => ['debug' => $creator->isDebug()]]);

    // You can set custom pages for caught exceptions, using default event dispatcher, or your custom event dispatcher.
    $creator->definition('events.dispatcher')->bind('addListener', [Rade\Events::EXCEPTION, wrap(ErrorListener::class), -8]);
}, $config);

$app->run(); // Boot the application.

```

Here's an example of a custom error you can use for your application.

```php
use Biurad\Http\Response\HtmlResponse;
use Rade\Event\ExceptionEvent;

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

Important to note that, using [PSR-15] middlewares stack uses the [PHP] SPL Queue class with the following algorithm, LAST <- FIRST : FIRST -> LAST. Also in regards to loading extensions and adding event listeners to the default event dispatcher by priority, The higher the number, the earlier an extension or event listener will be triggered in the chain (defaults to 0).

## 📓 Documentation

For in-depth documentation before using this library.. Full documentation on advanced usage, configuration, and customization can be found at [docs.divinenii.com][docs].

## ⏫ Upgrading

Information on how to upgrade to newer versions of this library can be found in the [UPGRADE].

## 🏷️ Changelog

[SemVer](http://semver.org/) is followed closely. Minor and patch releases should not introduce breaking changes to the codebase; See [CHANGELOG] for more information on what has changed recently.

Any classes or methods marked `@internal` are not intended for use outside of this library and are subject to breaking changes at any time, so please avoid using them.

## 🛠️ Maintenance & Support

(This policy may change in the future and exceptions may be made on a case-by-case basis.)

- A new **patch version released** (e.g. `1.0.10`, `1.1.6`) comes out roughly every month. It only contains bug fixes, so you can safely upgrade your applications.
- A new **minor version released** (e.g. `1.1`, `1.2`) comes out every six months: one in June and one in December. It contains bug fixes and new features, but it doesn’t include any breaking change, so you can safely upgrade your applications;
- A new **major version released** (e.g. `1.0`, `2.0`, `3.0`) comes out every two years. It can contain breaking changes, so you may need to do some changes in your applications before upgrading.

When a **major** version is released, the number of minor versions is limited to five per branch (X.0, X.1, X.2, X.3 and X.4). The last minor version of a branch (e.g. 1.4, 2.4) is considered a **long-term support (LTS) version** with lasts for more that 2 years and the other ones cam last up to 8 months:

**Get a professional support from [Biurad Lap][] after the active maintenance of a released version has ended**.

## 🧪 Testing

```bash
$ ./vendor/bin/phpunit
```

This will tests divineniiquaye/php-rade will run against PHP 7.4 version or higher.

## 🏛️ Governance

This project is primarily maintained by [Divine Niiquaye Ibok][@divineniiquaye]. Contributions are welcome 👷‍♀️! To contribute, please familiarize yourself with our [CONTRIBUTING] guidelines.

To report a security vulnerability, please use the [Biurad Security](https://security.biurad.com). We will coordinate the fix and eventually commit the solution in this project.

## 🙌 Sponsors

Are you interested in sponsoring development of this project? Reach out and support us on [Patreon](https://www.patreon.com/biurad) or see <https://biurad.com/sponsor> for a list of ways to contribute.

## 👥 Credits & Acknowledgements

- [Divine Niiquaye Ibok][@divineniiquaye]
- [All Contributors][]

## 🗺️ Who Uses It?

You're free to use this package, but if it makes it to your production environment we highly appreciate you sending us an [email] or [message] mentioning this library. We publish all received request's at <https://patreons.biurad.com>.

Check out the other cool things people are doing with `divineniiquaye/php-rade`: <https://packagist.org/packages/divineniiquaye/php-rade/dependents>

## 📄 License

The **divineniiquaye/php-rade** library is copyright © [Divine Niiquaye Ibok](https://divinenii.com) and licensed for use under the [![Software License](https://img.shields.io/badge/License-BSD--3-brightgreen.svg?style=flat-square)](LICENSE).

[Composer]: https://getcomposer.org
[PHP]: https://php.net
[PSR-7]: http://www.php-fig.org/psr/psr-6/
[PSR-11]: http://www.php-fig.org/psr/psr-11/
[PSR-14]: http://www.php-fig.org/psr/psr-14/
[PSR-15]: http://www.php-fig.org/psr/psr-15/
[@divineniiquaye]: https://github.com/divineniiquaye
[docs]: https://docs.divinenii.com/php-rade
[commit]: https://commits.biurad.com/flight-routing.git
[UPGRADE]: UPGRADE.md
[CHANGELOG]: CHANGELOG.md
[CONTRIBUTING]: ./.github/CONTRIBUTING.md
[All Contributors]: https://github.com/divineniiquaye/php-rade/contributors
[Biurad Lap]: https://team.biurad.com
[email]: support@biurad.com
[message]: https://projects.biurad.com/message
[Flight Routing]: https://github.com/divineniiquaye/flight-routing
[Rade DI]: https://github.com/divineniiquaye/rade-di
[Symfony components]: https://github.com/symfony
[Biurad libraries]: https://github.com/biurad
