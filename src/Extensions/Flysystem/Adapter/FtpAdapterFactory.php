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

namespace Flange\Extensions\Flysystem\Adapter;

use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use Rade\DI\Definition;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\RequiredPackagesInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 *
 * @internal
 */
class FtpAdapterFactory extends AbstractFactory implements RequiredPackagesInterface
{
    protected const CLASS_NAME = FtpAdapter::class;

    public function getName(): string
    {
        return 'ftp';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredPackages(): array
    {
        return [
            FtpAdapter::class => 'league/flysystem-ftp',
        ];
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('host');
        $resolver->setAllowedTypes('host', 'string');

        $resolver->setRequired('username');
        $resolver->setAllowedTypes('username', 'string');

        $resolver->setRequired('password');
        $resolver->setAllowedTypes('password', 'string');

        $resolver->setDefault('port', 21);
        $resolver->setAllowedTypes('port', 'scalar');

        $resolver->setDefault('root', '');
        $resolver->setAllowedTypes('root', 'string');

        $resolver->setDefault('passive', true);
        $resolver->setAllowedTypes('passive', 'scalar');

        $resolver->setDefault('ssl', false);
        $resolver->setAllowedTypes('ssl', 'scalar');

        $resolver->setDefault('timeout', 90);
        $resolver->setAllowedTypes('timeout', 'scalar');

        $resolver->setDefault('ignore_passive_address', null);
        $resolver->setAllowedTypes('ignore_passive_address', ['null', 'bool', 'scalar']);

        $resolver->setDefault('utf8', false);
        $resolver->setAllowedTypes('utf8', 'scalar');
    }

    protected function configureDefinition(Definition $definition, array $options): void
    {
        $options['ignorePassiveAddress'] = $options['ignore_passive_address'];
        unset($options['ignore_passive_address']);

        $definition->arg(0, new Statement(FtpConnectionOptions::class . '::fromArray', [$options]));
    }
}
