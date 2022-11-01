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

// create a real rade application
$app = Flange\AppBuilder::build(static function (Flange\AppBuilder $creator): void {
    // Add resource to re-compile if changes are made to this file.
    $creator->addResource(new \Symfony\Component\Config\Resource\FileResource(__FILE__));

    $creator->match('/hello*<helloWorldFunc>')->bind('hello');
    $creator->loadExtensions(require __DIR__ . '/extensions.php');
}, ['cacheDir' => __DIR__ . '/var/app']);

$app->run();
