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

use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Rade\DI\Definition;
use Rade\DI\Definitions\Statement;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 *
 * @internal
 */
class LocalAdapterFactory extends AbstractFactory
{
    protected const CLASS_NAME = LocalFilesystemAdapter::class;

    public function getName(): string
    {
        return 'local';
    }

    protected function getRequiredPackages(): array
    {
        return [];
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('directory');
        $resolver->setAllowedTypes('directory', 'string');

        $resolver->setDefault('lock', 0);
        $resolver->setAllowedTypes('lock', 'scalar');

        $resolver->setDefault('skip_links', false);
        $resolver->setAllowedTypes('skip_links', 'scalar');

        $resolver->setDefault('permissions', function (OptionsResolver $subResolver): void {
            $subResolver->setDefault('file', function (OptionsResolver $permsResolver): void {
                $permsResolver->setDefault('public', 0644);
                $permsResolver->setAllowedTypes('public', 'scalar');

                $permsResolver->setDefault('private', 0600);
                $permsResolver->setAllowedTypes('private', 'scalar');
            });

            $subResolver->setDefault('dir', function (OptionsResolver $permsResolver): void {
                $permsResolver->setDefault('public', 0755);
                $permsResolver->setAllowedTypes('public', 'scalar');

                $permsResolver->setDefault('private', 0700);
                $permsResolver->setAllowedTypes('private', 'scalar');
            });
        });
    }

    protected function configureDefinition(Definition $definition, array $options): void
    {
        $definition->args([
            $options['directory'],
            new Statement(
                PortableVisibilityConverter::class . '::fromArray',
                [
                    [
                        'file' => [
                            'public' => (int) $options['permissions']['file']['public'],
                            'private' => (int) $options['permissions']['file']['private'],
                        ],
                        'dir' => [
                            'public' => (int) $options['permissions']['dir']['public'],
                            'private' => (int) $options['permissions']['dir']['private'],
                        ],
                    ],
                ]
            ),
            $options['lock'],
            $options['skip_links'] ? LocalFilesystemAdapter::SKIP_LINKS : LocalFilesystemAdapter::DISALLOW_LINKS,
        ]);
    }
}
