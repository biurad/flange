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

namespace Flange\Extensions\Security\Provider;

use Rade\DI\Container;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * ProviderFactoryInterface is the interface for all security provider factories.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface ProviderFactoryInterface
{
    public function create(Container $container, string $id, array $config): void;

    public function getKey(): string;

    public function addConfiguration(NodeDefinition $builder): void;
}
