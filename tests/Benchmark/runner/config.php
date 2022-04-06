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

return [
    'routing' => [
        'pipes' => [
            //'web' => [Biurad\Http\Middlewares\ContentTypeOptionsMiddleware::class, Biurad\Http\Middlewares\ContentLengthMiddleware::class],
            //Biurad\Http\Middlewares\ContentTypeOptionsMiddleware::class,
            //Biurad\Http\Middlewares\ContentLengthMiddleware::class,
        ],
        'routes' => [
            ['name' => 'homepage', 'path' => '/hello*<helloWorldFunc>', 'methods' => ['GET']],
        ]
    ],
    'symfony' => [
        'cache' => [
            'directory' => '%project.var_dir%/cache',
        ],
    ],
];
