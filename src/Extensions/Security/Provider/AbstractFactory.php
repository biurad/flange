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

namespace Rade\DI\Extensions\Security\Provider;

use Rade\DI\Container;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * AbstractFactory is the base class for all classes inheriting an authentication provider factory.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
abstract class AbstractFactory implements ProviderFactoryInterface
{
    protected array $options = [];

    final public function addOption(string $name, mixed $default = null): void
    {
        $this->options[$name] = $default;
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $builder = $node->children();

        foreach ($this->options as $name => $default) {
            if (\is_bool($default)) {
                $builder->booleanNode($name)->defaultValue($default);
            } else {
                $builder->scalarNode($name)->defaultValue($default);
            }
        }

        $builder->end();
    }

    abstract public function getKey(): string;

    abstract public function create(Container $container, string $id, array $config): void;
}
