<?php

declare(strict_types=1);

/*
 * This file is part of DivineNii opensource projects.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 DivineNii (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rade\Provider;

use Biurad\Events\TraceableEventDispatcher;
use Monolog\ErrorHandler;
use Monolog\Handler;
use Monolog\Logger;
use Rade\API\BootableProviderInterface;
use Rade\API\EventListenerProviderInterface;
use Rade\Application;
use Rade\DI\Container;
use Rade\DI\ServiceProviderInterface;
use Rade\EventListener\LogListener;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Monolog Provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MonologServiceProvider implements ConfigurationInterface, ServiceProviderInterface, BootableProviderInterface, EventListenerProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'monolog';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->getName());

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('name')->defaultValue('app')->end()
                ->booleanNode('bubble')->defaultTrue()->end()
                ->enumNode('level')
                    ->values(
                        [
                            Logger::DEBUG,
                            Logger::INFO,
                            Logger::NOTICE,
                            Logger::ALERT,
                            Logger::WARNING,
                            Logger::ERROR,
                            Logger::CRITICAL,
                            Logger::EMERGENCY,
                        ]
                    )
                    ->defaultValue(Logger::DEBUG)
                ->end()
                ->scalarNode('permission')->defaultNull()->end()
                ->scalarNode('exception_logger_filter')->defaultNull()->end()
                ->scalarNode('logfile')->defaultNull()->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $app): void
    {
        $config = $app->parameters['monolog'] ?? [];

        $app['monolog'] = function (Container $app) use ($config): Logger {
            $log     = new Logger($config['name']);
            $handler = new Handler\GroupHandler($app['monolog.handlers']);

            $log->pushHandler($handler);

            return $log;
        };

        $app->alias('logger', 'monolog');

        $app['monolog.formatter'] = new \Monolog\Formatter\LineFormatter();
        $app['monolog.handler']   = $defaultHandler = function (Container $app) use ($config): Handler\StreamHandler {
            $level = MonologServiceProvider::translateLevel($config['level']);

            if (null !== $config['logfile']) {
                $config['logfile'] = $app->parameters['project_dir'] . $config['logfile'];
            }

            $handler = new Handler\StreamHandler($config['logfile'], $level, $config['bubble'], $config['permission']);
            $handler->setFormatter($app['monolog.formatter']);

            return $handler;
        };

        $app['monolog.handlers'] = function (Container $app) use ($config, $defaultHandler) {
            $handlers = [];

            // enables the default handler if a logfile was set or the monolog.handler service was redefined
            if ($config['logfile'] || $defaultHandler !== $app->get('monolog.handler')) {
                $handlers[] = $app['monolog.handler'];
            }

            return $handlers;
        };

        if ($app->parameters['debug']) {
            $app->extend(
                'dispatcher',
                function (EventDispatcherInterface $dispatcher, Container $app): TraceableEventDispatcher {
                    return new TraceableEventDispatcher($dispatcher, $app['logger']);
                }
            );
        }
        $app['monolog.listener'] = $app->call(LogListener::class, [1 => $config['exception_logger_filter']]);

        unset($app->parameters['monolog']);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app): void
    {
        if (!$app->parameters['debug']) {
            ErrorHandler::register($app['monolog']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(Container $app, EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->addSubscriber($app['monolog.listener']);
    }

    public static function translateLevel($name)
    {
        // level is already translated to logger constant, return as-is
        if (\is_int($name)) {
            return $name;
        }

        $psrLevel = Logger::toMonologLevel($name);

        if (\is_int($psrLevel)) {
            return $psrLevel;
        }

        $levels = Logger::getLevels();
        $upper  = \strtoupper($name);

        if (!isset($levels[$upper])) {
            throw new \InvalidArgumentException(
                "Provided logging level '$name' does not exist. Must be a valid monolog logging level."
            );
        }

        return $levels[$upper];
    }
}
