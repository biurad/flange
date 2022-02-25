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

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 *
 * @internal
 */
class AwsAdapterFactory extends AbstractFactory
{
    protected const CLASS_NAME = AwsS3V3Adapter::class;

    public function getName(): string
    {
        return 'aws';
    }

    protected function getRequiredPackages(): array
    {
        return [
            AwsS3V3Adapter::class => 'league/flysystem-aws-s3-v3',
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

        $resolver->setDefault('options', []);
        $resolver->setAllowedTypes('options', 'array');

        $resolver->setDefault('streamReads', true);
        $resolver->setAllowedTypes('streamReads', 'bool');
    }

    protected function configureDefinition(Definition $definition, array $options): void
    {
        $definition->args([
            \class_exists($options['client']) ? new Statement($options['client']) : new Reference($options['client']),
            $options['bucket'],
            $options['prefix'],
            null,
            null,
            $options['options'],
            $options['streamReads'],
        ]);
    }
}
