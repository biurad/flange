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

namespace Rade\DI\Extensions\Flysystem\Adapter;

use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Rade\DI\Definition;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 *
 * @internal
 */
class MemoryAdapterFactory extends AbstractFactory
{
    protected const CLASS_NAME = InMemoryFilesystemAdapter::class;

    public function getName(): string
    {
        return 'memory';
    }

    protected function getRequiredPackages(): array
    {
        return [
            InMemoryFilesystemAdapter::class => 'league/flysystem-memory',
        ];
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
    }

    protected function configureDefinition(Definition $definition, array $options): void
    {
    }
}
