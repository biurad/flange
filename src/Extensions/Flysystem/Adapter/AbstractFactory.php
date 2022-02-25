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

use Rade\DI\Definition;
use Rade\DI\Exceptions\MissingPackageException;
use Rade\DI\Extensions\Flysystem\AdapterFactoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
abstract class AbstractFactory implements AdapterFactoryInterface
{
    protected const CLASS_NAME = 'League\Flysystem\AdapterInterface';

    final public function createDefinition(array $options): Definition
    {
        $missingPackages = [];

        foreach ($this->getRequiredPackages() as $requiredClass => $packageName) {
            if (!\class_exists($requiredClass)) {
                $missingPackages[] = $packageName;
            }
        }

        if ($missingPackages) {
            throw new MissingPackageException(\sprintf('Missing package%s, to use the "%s" adapter, run: composer require %s', \count($missingPackages) > 1 ? 's' : '', $this->getName(), \implode(' ', $missingPackages)));
        }


        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $definition = new Definition(static::CLASS_NAME);
        $this->configureDefinition($definition, $resolver->resolve($options));

        return $definition;
    }

    abstract protected function getRequiredPackages(): array;

    abstract protected function configureOptions(OptionsResolver $resolver);

    abstract protected function configureDefinition(Definition $definition, array $options): void;
}
