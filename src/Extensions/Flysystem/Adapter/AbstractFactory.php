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

use Flange\Extensions\Flysystem\AdapterFactoryInterface;
use Rade\DI\Definition;
use Rade\DI\Exceptions\MissingPackageException;
use Rade\DI\Extensions\RequiredPackagesInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
abstract class AbstractFactory implements AdapterFactoryInterface
{
    protected const CLASS_NAME = 'League\Flysystem\AdapterInterface';

    final public function createDefinition(array $options): Definition
    {
        if ($this instanceof RequiredPackagesInterface) {
            $missingPackages = [];

            foreach ($this->getRequiredPackages() as $requiredClass => $packageName) {
                if (!\class_exists($requiredClass)) {
                    $missingPackages[] = $packageName;
                }
            }

            if ($missingPackages) {
                throw new MissingPackageException(\sprintf('Missing package%s, to use the "%s" adapter, run: composer require %s', \count($missingPackages) > 1 ? 's' : '', $this->getName(), \implode(' ', $missingPackages)));
            }
        }

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $definition = new Definition(static::CLASS_NAME);
        $this->configureDefinition($definition, $resolver->resolve($options));

        return $definition;
    }

    abstract protected function configureOptions(OptionsResolver $resolver);

    abstract protected function configureDefinition(Definition $definition, array $options): void;
}
