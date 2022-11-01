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

use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\RequiredPackagesInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @internal
 */
class AsyncAwsAdapterFactory extends AbstractFactory implements RequiredPackagesInterface
{
    protected const CLASS_NAME = AsyncAwsS3Adapter::class;

    public function getName(): string
    {
        return 'asyncaws';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredPackages(): array
    {
        return [
            AsyncAwsS3Adapter::class => 'league/flysystem-async-aws-s3',
        ];
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('client');
        $resolver->setAllowedTypes('client', 'string');

        $resolver->setRequired('bucket');
        $resolver->setAllowedTypes('bucket', 'string');

        $resolver->setDefault('prefix', '');
        $resolver->setAllowedTypes('prefix', 'string');
    }

    protected function configureDefinition(Definition $definition, array $options): void
    {
        $definition->args([\class_exists($options['client']) ? new Statement($options['client']) : new Reference($options['client']), $options['bucket'], $options['prefix']]);
    }
}
