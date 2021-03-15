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

use Biurad\Cache\AdapterFactory;
use Biurad\Cache\CacheItemPool;
use Biurad\Cache\LoggableStorage;
use Biurad\Cache\SimpleCache;
use Biurad\Cache\TagAwareCache;
use Cache\Adapter\Doctrine\DoctrineCachePool;
use Doctrine\Common\Cache as DoctrineCache;
use Monolog\Logger;
use Rade\API\BootableProviderInterface;
use Rade\Application;
use Rade\DI\Container;
use Rade\DI\ServiceProviderInterface;

/**
 * Biurad Cache Provider
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class CacheServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'cache';
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $app): void
    {
        $app['cache.doctrine'] = static function (Container $app): DoctrineCache\Cache {
            $adapter = $app['cache.doctrine_adapter'] ?? AdapterFactory::createHandler(\extension_loaded('apcu') ? 'apcu' : 'array');

            if ($app->parameters['debug'] && isset($app['logger'])) {
                return new LoggableStorage($adapter);
            }

            return $adapter;
        };

        if (\class_exists(DoctrineCachePool::class)) {
            $app['cache.psr6'] = $app->lazy(TagAwareCache::class);
        } else {
            $app['cache.psr6'] = $app->lazy(CacheItemPool::class);
        }

        $app['cache.psr16'] = $app->lazy(SimpleCache::class);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app): void
    {
        if ($app->parameters['debug'] && isset($app['logger'])) {
            $cache = $app['cache.doctrine'];

            if (!$cache instanceof LoggableStorage) {
                return;
            }

            foreach ($cache->getCalls() as $call) {
                unset($call['data']);

                $app['logger']->log(Logger::DEBUG, 'Cache called "{method}" with a given "{key}"', $call);
            }
        }
    }
}
