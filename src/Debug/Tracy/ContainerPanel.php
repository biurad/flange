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

use Nette;
use Rade\DI\Container;
use Tracy;
use Rade\DI\Resolvers\AutowireValueResolver;

/**
 * Dependency injection container panel for Debugger Bar.
 */
class ContainerPanel implements Tracy\IBarPanel
{
    use Nette\SmartObject;

    private COntainer $container;

    /** @var null|float */
    private ?float $elapsedTime;

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
     *
     * @throws \ReflectionException
     * @throws \Throwable
     *
     * @return string
     */
    public function getPanel(): string
    {
        $rc       = new \ReflectionClass($this->container);
        $types    = [];
        $resolver = $this->getContainerProperty('resolver');
        $wiring   = $this->getContainerProperty('types') + $this->getContainerProperty('wiring', AutowireValueResolver::class, $resolver);

        foreach ($this->container->keys() as $id) {
            foreach ($wiring as $type => $names) {
                if (\in_array($id, $names, true) && \class_exists($type)) {
                    $types[$id] = $type;

                    continue;
                }
            }

            if (!isset($types[$id])) {
                $types[$id] = null;
            }
        }
        \ksort($types);

        return Nette\Utils\Helpers::capture(function () use ($types, $wiring, $rc): void {
            $file = $rc->getFileName();
            $instances = $this->getContainerProperty('values') + $this->getContainerProperty('factories') + $this->getContainerProperty('raw');
            $services = $this->getContainerProperty('services');
            $frozen = $this->getContainerProperty('frozen');
            $configs = $this->getContainerProperty('config', 'Rade\Application');

            require __DIR__ . '/templates/ContainerPanel.panel.phtml';
        });
    }

    /**
     * @param class-string $className
     * @param object|null $instance
     *
     * @throws \ReflectionException
     *
     * @return mixed
     */
    private function getContainerProperty(string $name, string $className = Container::class, $instance = null)
    {
        $prop = (new \ReflectionClass($className))->getProperty($name);
        $prop->setAccessible(true);

        return $prop->getValue($instance ?? $this->container);
    }
}
