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

// create a simple rade application
$app = new \Rade\Application(null, null, false);
$app->match('/hello', \Flight\Routing\Route::DEFAULT_METHODS, fn (): string => 'Hello World');

$app->run();