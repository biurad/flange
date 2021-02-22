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

namespace Rade\Debug\Tracy;

use DivineNii\Invoker\CallableReflection;
use Nette;
use Rade\DI\Container;
use Tracy;

/**
 * Dependency injection container panel for Debugger Bar.
 */
class ContainerPanel implements Tracy\IBarPanel
{
    use Nette\SmartObject;

    private COntainer $container;

    /** @var null|float */
    private $elapsedTime;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Renders tab.
     */
    public function getTab(): string
    {
        return Nette\Utils\Helpers::capture(function (): void {
            require __DIR__ . '/templates/ContainerPanel.tab.phtml';
        });
    }

    /**
     * Renders panel.
     */
    public function getPanel(): string
    {
        $rc       = new \ReflectionClass($this->container);
        $types    = [];
        $resolver = $this->getContainerProperty('resolver');

        foreach ($this->getContainerProperty('values') as $id => $service) {
            if (\is_callable($service) && null !== $type = CallableReflection::create($service)->getReturnType()) {
                if ($type instanceof \ReflectionNamedType) {
                    $types[$id] = $type->getName();
                }
            } elseif (\is_object($service) && !$service instanceof \stdClass) {
                $types[$id] = \get_class($service);
            } else {
                $types[$id] = 'empty';
            }
        }

        \ksort($types);
        $wiring = $this->getContainerProperty('wiring', 'Rade\DI\Resolvers\AutowireValueResolver', $resolver);

        return Nette\Utils\Helpers::capture(function () use ($types, $wiring, $rc): void {
            $container = $this->container;
            $file = $rc->getFileName();
            $instances = $this->getContainerProperty('values');
            $frozen = $this->getContainerProperty('frozen');
            $configs = $this->getContainerProperty('config', 'Rade\Application');

            require __DIR__ . '/templates/ContainerPanel.panel.phtml';
        });
    }

    private function getContainerProperty(string $name, string $className = 'Rade\DI\COntainer', $instance = null)
    {
        $prop = (new \ReflectionClass($className))->getProperty($name);
        $prop->setAccessible(true);

        return $prop->getValue($instance ?? $this->container);
    }
}
