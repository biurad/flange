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

namespace Flange\Extensions\Flysystem\Adapter;

use League\Flysystem\PhpseclibV2\SftpAdapter;
use League\Flysystem\PhpseclibV2\SftpConnectionProvider;
use Rade\DI\Definition;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\RequiredPackagesInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 *
 * @internal
 */
class SftpAdapterFactory extends AbstractFactory implements RequiredPackagesInterface
{
    protected const CLASS_NAME = SftpAdapter::class;

    public function getName(): string
    {
        return 'sftp';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredPackages(): array
    {
        return [
            SftpAdapter::class => 'league/flysystem-sftp',
        ];
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('host');
        $resolver->setAllowedTypes('host', 'string');

        $resolver->setRequired('username');
        $resolver->setAllowedTypes('username', 'string');

        $resolver->setDefault('password', null);
        $resolver->setAllowedTypes('password', ['string', 'null']);

        $resolver->setDefault('port', 22);
        $resolver->setAllowedTypes('port', 'scalar');

        $resolver->setDefault('root', '');
        $resolver->setAllowedTypes('root', 'string');

        $resolver->setDefault('privateKey', null);
        $resolver->setAllowedTypes('privateKey', ['string', 'null']);

        $resolver->setDefault('timeout', 90);
        $resolver->setAllowedTypes('timeout', 'scalar');

        $resolver->setDefault('directoryPerm', 0744);
        $resolver->setAllowedTypes('directoryPerm', 'scalar');

        $resolver->setDefault('permPrivate', 0700);
        $resolver->setAllowedTypes('permPrivate', 'scalar');

        $resolver->setDefault('permPublic', 0744);
        $resolver->setAllowedTypes('permPublic', 'scalar');
    }

    protected function configureDefinition(Definition $definition, array $options): void
    {
        $definition->args([new Statement(SftpConnectionProvider::class.'::fromArray', [$options]), $options['root']]);
    }
}
