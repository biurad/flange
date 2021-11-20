# The PHP Rade Framework

[![Latest Version](https://img.shields.io/packagist/v/divineniiquaye/php-rade.svg?style=flat-square)](https://packagist.org/packages/divineniiquaye/php-rade)
[![Software License](https://img.shields.io/badge/License-BSD--3-brightgreen.svg?style=flat-square)](LICENSE)
[![Workflow Status](https://img.shields.io/github/workflow/status/divineniiquaye/php-rade/Tests?style=flat-square)](https://github.com/divineniiquaye/php-rade/actions?query=workflow%3ATests)
[![Code Maintainability](https://img.shields.io/codeclimate/maintainability/divineniiquaye/php-rade?style=flat-square)](https://codeclimate.com/github/divineniiquaye/php-rade)
[![Coverage Status](https://img.shields.io/codecov/c/github/divineniiquaye/php-rade?style=flat-square)](https://codecov.io/gh/divineniiquaye/php-rade)
[![Quality Score](https://img.shields.io/scrutinizer/g/divineniiquaye/php-rade.svg?style=flat-square)](https://scrutinizer-ci.com/g/divineniiquaye/php-rade)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://biurad.com/sponsor)

**divineniiquaye/php-rade** is a fast, simple and micro framework for [PHP] 7.4+ based on [PSR-7] and [PSR-15] with support for annotations, created by [Divine Niiquaye][@divineniiquaye] and inspired by [Silex]. This library seeks to help developers who are lazy, beginners, or people who want to build things fast with extremely less dependencies.

Its also to note that, Rade has support for [PSR-11], built with [Rade DI][] library gracing the project with an advanced DI.

## 📦 Installation & Basic Usage

This project requires [PHP] 7.4 or higher. The recommended way to install, is via [Composer]. Simply run:

```bash
$ composer require divineniiquaye/php-rade
```

Rade is built based on [Flight Routing][], [Symfony components][] and [Biurad libraries][]. Rade is a fully PSR complaint [PHP] framework, fully customizable and can even be used to develop from small to large projects:

```php
require_once __DIR__ . '/vendor/autoload.php';

// Boot the application.
$app = new Rade\Application();

// Add a route to application
$app->match('/hello/{name:\w+}', fn (string $name): string => 'Hello ' . $app->escape()->escapeHtml($name));

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

Working on a big project!, it is advisable to use the application's cacheable version. This gives you 70% - 100% more performance than using the Application class with extensions,

```php
use function Rade\DI\Loader\{phpCode, wrap};

$config = [
    'compiled_file' => __DIR__ . '/caches/compiled_test.php',
    'debug' => $_ENV['APP_DEBUG'] ?? false, // Set the debug mode environment
    'containerClass' => 'Application' // The class name for the compiled application.
];

// Setup cache for application.
$app = \Rade\AppBuilder::build(static function (\Rade\AppBuilder $creator): void {
    // Add resource to re-compile if changes are made to this file.
    $creator->addResource(new FileResource(__FILE__));

    // Adding routes requires the Rade\DI\Extensions\RoutingExtension to be loaded.
    // Routes should always be added before Rade\DI\Extensions\RoutingExtension is booted, else it will not be compiled.
    $creator->match('/hello/{name:\w+}', to: phpCode('fn (string $name): string => \'Hello \' . $this->escape()->escapeHtml($name));'));

    $extensions = [
        [Rade\DI\Extensions\CoreExtension::class, [__DIR__]],
        // You can add more extensions here ...
    ];

    //If you want to use extensions, here is an example as its recommended to use extensions to build your application.
    $app->loadExtensions($extensions, ['config' => ['debug' => $creator->parameters['debug']]]);

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

## 📓 Documentation

For in-depth documentation before using this library.. Full documentation on advanced usage, configuration, and customization can be found at [docs.divinenii.com][docs].

## ⏫ Upgrading

Information on how to upgrade to newer versions of this library can be found in the [UPGRADE].

## 🏷️ Changelog

[SemVer](http://semver.org/) is followed closely. Minor and patch releases should not introduce breaking changes to the codebase; See [CHANGELOG] for more information on what has changed recently.

Any classes or methods marked `@internal` are not intended for use outside of this library and are subject to breaking changes at any time, so please avoid using them.

## 🛠️ Maintenance & Support

When a new **major** version is released (`1.0`, `2.0`, etc), the previous one (`0.19.x`) will receive bug fixes for _at least_ 3 months and security updates for 6 months after that new release comes out.

(This policy may change in the future and exceptions may be made on a case-by-case basis.)

**Professional support, including notification of new releases and security updates, is available at [Biurad Commits][commit].**

## 👷‍♀️ Contributing

To report a security vulnerability, please use the [Biurad Security](https://security.biurad.com). We will coordinate the fix and eventually commit the solution in this project.

Contributions to this library are **welcome**, especially ones that:

- Improve usability or flexibility without compromising our ability to adhere to [PSR-7] and [PSR-15]
- Optimize performance
- Fix issues with adhering to [PSR-7], [PSR-15] and this library

Please see [CONTRIBUTING] for additional details.

## 🧪 Testing

```bash
$ composer test
```

This will tests biurad/php-cache will run against PHP 7.2 version or higher.

## 👥 Credits & Acknowledgements

- [Divine Niiquaye Ibok][@divineniiquaye]
- [All Contributors][]

## 🙌 Sponsors

Are you interested in sponsoring development of this project? Reach out and support us on [Patreon](https://www.patreon.com/biurad) or see <https://biurad.com/sponsor> for a list of ways to contribute.

## 📄 License

**divineniiquaye/php-rade** is licensed under the BSD-3 license. See the [`LICENSE`](LICENSE) file for more details.

## 🏛️ Governance

This project is primarily maintained by [Divine Niiquaye Ibok][@divineniiquaye]. Members of the [Biurad Lap][] Leadership Team may occasionally assist with some of these duties.

## 🗺️ Who Uses It?

You're free to use this package, but if it makes it to your production environment we highly appreciate you sending us an [email] or [message] mentioning this library. We publish all received request's at <https://patreons.biurad.com>.

Check out the other cool things people are doing with `divineniiquaye/php-rade`: <https://packagist.org/packages/divineniiquaye/php-rade/dependents>

[Composer]: https://getcomposer.org
[PHP]: https://php.net
[PSR-7]: http://www.php-fig.org/psr/psr-6/
[PSR-11]: http://www.php-fig.org/psr/psr-11/
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
[Silex]: http://silex.sensiolabs.org
[Symfony components]: https://github.com/symfony
[Biurad libraries]: https://github.com/biurad
