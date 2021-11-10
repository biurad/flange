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

use Rade\Application;

/**
 * This interface serves as a way of extending existing services
 * or extending the application with extra services.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface ControllerProviderInterface
{
    /**
     * Register your http routes and or console commands.
     *
     * @param Application $app An Application instance
     */
    public function __invoke(Application $app): void;
}
