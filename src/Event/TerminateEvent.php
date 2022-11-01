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

namespace Flange\Event;

use Psr\Http\Message\ServerRequestInterface as Request;
use Flange\Application;

/**
 * Allows to execute logic after a response was sent.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class TerminateEvent extends KernelEvent
{
    public function __construct(Application $kernel, Request $request)
    {
        parent::__construct($kernel, $request);
    }
}
