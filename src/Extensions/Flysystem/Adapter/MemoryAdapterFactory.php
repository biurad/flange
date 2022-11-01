<?php

declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange\Extensions\Flysystem\Adapter;

use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Rade\DI\Definition;
use Rade\DI\Extensions\RequiredPackagesInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 *
 * @internal
 */
class MemoryAdapterFactory extends AbstractFactory implements RequiredPackagesInterface
{
    protected const CLASS_NAME = InMemoryFilesystemAdapter::class;

    public function getName(): string
    {
        return 'memory';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredPackages(): array
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
