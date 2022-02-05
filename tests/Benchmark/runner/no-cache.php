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

require __DIR__ . '/../../../vendor/autoload.php';

function helloWorldFunc(): string
{
    return 'Hello World';
}

// create a no cache rade application
$app = new \Rade\Application(null, null, false);
$app->loadExtensions(require __DIR__ . '/extensions.php', require __DIR__ . '/config.php');

$app->run();
