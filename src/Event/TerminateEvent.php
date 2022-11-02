<?php declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange\Event;

use Flange\Application;
use Psr\Http\Message\ServerRequestInterface as Request;

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
