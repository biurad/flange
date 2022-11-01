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

namespace Flange\GraphQL;

use GraphQL\Type\Definition\ObjectType;

/**
 * Types resolver for GraphQL schema.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Types
{
    /** @var array<string,ObjectType> */
    private array $types;

    public function __construct(array $types = [])
    {
        foreach ($types as $name => $type) {
            $this->set($name, $type);
        }
    }

    public function get($name)
    {
        if (!isset($this->types[$name])) {
            throw new \InvalidArgumentException(\sprintf('GraphQL object type for %s is not found.', $name));
        }

        return $this->types[$name];
    }

    public function set(string $name, ObjectType $type): void
    {
        $this->types[$name] = $type;
    }
}
