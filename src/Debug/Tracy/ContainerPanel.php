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

/**
 * Dependency injection container panel for Debugger Bar.
 */
class ContainerPanel implements Tracy\IBarPanel
{
    use Nette\SmartObject;

    public static ?float $compilationTime = null;

    private Container $container;

    private ?float $elapsedTime;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->elapsedTime = self::$compilationTime ? \microtime(true) - self::$compilationTime : null;
    }

    /**
     * Renders tab.
     */
    public function getTab(): string
    {
        return Nette\Utils\Helpers::capture(function (): void {
            $elapsedTime = $this->elapsedTime;

            require __DIR__ . '/templates/ContainerPanel.tab.phtml';
        });
    }

    /**
     * Renders panel.
     *
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function getPanel(): string
    {
        $rc = new \ReflectionClass(Container::class);
        $types = [];
        $wiring = $this->getContainerProperty('types', $rc);

        foreach ($this->container->keys() as $id) {
            foreach ($wiring as $type => $names) {
                if (\in_array($id, $names, true)) {
                    $types[$id] = $type;

                    continue 2;
                }
            }
        }

        \ksort($types);

        return Nette\Utils\Helpers::capture(function () use ($types, $wiring, $rc): void {
            $file = $rc->getFileName();
            $instances = $this->getContainerProperty('definitions', $rc) + $this->getContainerProperty('methodsMap', $rc);
            $services = $this->getContainerProperty('services', $rc);
            $configs = $this->getContainerProperty('parameters', $rc);

            require __DIR__ . '/templates/ContainerPanel.panel.phtml';
        });
    }

    /**
     * @param class-string $className
     * @param object|null  $instance
     *
     * @throws \ReflectionException
     *
     * @return mixed
     */
    private function getContainerProperty(string $property, \ReflectionClass $appRef)
    {
        if (!$appRef->hasProperty($property)) {
            $appRef = $appRef->getParentClass();
        }

        $prop = $appRef->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($this->container);
    }
}