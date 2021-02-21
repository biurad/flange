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

namespace Rade\API;

use Rade\DI\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Interface for event listener providers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface EventListenerProviderInterface
{
    public function subscribe(Container $app, EventDispatcherInterface $dispatcher);
}
