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

namespace Rade\Tests\Benchmark;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

/**
 * @AfterClassMethods({"tearDown"})
 * @Iterations(5)
 * @Revs(500)
 */
class ApplicationBench
{
    protected const CACHE_DIR = __DIR__ . '/var';

    protected static ?Process $simple = null, $noCache = null, $withCache = null;

    public static function tearDown(): void
    {
        if (\file_exists(self::CACHE_DIR)) {
            $fs = new Filesystem();
            $fs->remove(self::CACHE_DIR);
        }

        if (self::$simple) {
            self::$simple->stop();
        }

        if (self::$noCache) {
            self::$noCache->stop();
        }

        if (self::$withCache) {
            self::$withCache->stop();
        }
    }

    public function createSimpleRequest(): void
    {
        static::$simple = Process::fromShellCommandline('php -S 127.0.0.1:8010 simple.php', __DIR__ . '/runner');

        if (!static::$simple->isStarted()) {
            static::$simple->start();
        }
    }

    public function createNoCacheRequest(): void
    {
        static::$noCache = Process::fromShellCommandline('php -S 127.0.0.1:8020 no-cache.php', __DIR__ . '/runner');

        if (!static::$noCache->isStarted()) {
            static::$noCache->start();
        }
    }

    public function createRealRequest(): void
    {
        static::$withCache = Process::fromShellCommandline('php -S 127.0.0.1:8030 real-app.php', __DIR__ . '/runner');

        if (!static::$withCache->isStarted()) {
            static::$withCache->start();
        }
    }

    /**
     * @BeforeMethods({"createSimpleRequest"})
     */
    public function benchSimpleApplication(): void
    {
        if (null === self::$simple || !self::$simple->isRunning()) {
            $this->createSimpleRequest();
        }

        HttpClient::create()->request('GET', 'http://127.0.0.1:8010/hello');
    }

    /**
     * @BeforeMethods({"createNoCacheRequest"})
     */
    public function benchNoCacheApplication(): void
    {
        if (null === self::$noCache || !self::$noCache->isRunning()) {
            $this->createNoCacheRequest();
        }

        HttpClient::create()->request('GET', 'http://127.0.0.1:8020/hello');
    }

    /**
     * @BeforeMethods({"createRealRequest"})
     */
    public function benchRealApplication(): void
    {
        if (null === self::$withCache || !self::$withCache->isRunning()) {
            $this->createRealRequest();
        }

        HttpClient::create()->request('GET', 'http://127.0.0.1:8030/hello');
    }
}
