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

namespace Rade\DI\Extensions\Symfony;

use Rade\Application as RadeApplication;
use Rade\Commands\AboutCommand;
use Rade\Commands\ServerCommand;
use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\KernelInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;

use function Rade\DI\Loader\param;

/**
 * Registers symfony console extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class ConsoleExtension implements AliasedInterface, BootExtensionInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'console';
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs): void
    {
        if ($container instanceof KernelInterface && !$container->isRunningInConsole()) {
            return;
        }

        $container->set('console.command.about', new Definition(AboutCommand::class))->public(false)->tag('console.command');
        $container->set('console.command.server', new Definition(ServerCommand::class, [param('project_dir'), param('debug')]))->public(false)->tag('console.command');
        $container->set('console', new Definition(Application::class))
            ->args([
                $container->parameters['console.name'] ?? 'PHP Rade Framework',
                $container->parameters['console.version'] ?? RadeApplication::VERSION,
            ])
            ->autowire([Application::class]);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        if (!$container->typed(Application::class)) {
            return;
        }

        $commandServices = $lazyCommandMap = [];

        foreach ($container->tagged('console.command') as $commandId => $commandTagged) {
            $definition = $container->definition($commandId);
            $class = $definition->getEntity(); // Assumes the command is a class set right

            if (!\is_array($commandTagged)) {
                $commandTagged = \is_string($commandTagged) ? ['command' => $commandTagged] : [];
            }
            $description = $commandTagged['description'] ?? null;

            if (isset($commandTagged['command'])) {
                $aliases = $commandTagged['command'];
            } else {
                try {
                    $r = new \ReflectionClass($class);
                } catch (\ReflectionException $e) {
                    throw new \InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $commandId));
                }

                if (!$r->isSubclassOf(Command::class)) {
                    throw new \InvalidArgumentException(\sprintf('The service "%s" tagged "%s" must be a subclass of "%s".', $commandId, 'console.command', Command::class));
                }

                $aliases = $class::getDefaultName();

                if (!$description) {
                    $description = $class::getDefaultDescription();
                }
            }

            if ($description) {
                $definition->bind('setDescription', $description);
            }

            if (isset($commandTagged['hidden'])) {
                $definition->bind('setHidden', $commandTagged['hidden']);
            }

            if (empty($aliases)) {
                $commandServices[] = new Reference($commandId);
            } else {
                if (\count($aliases = \explode('|', $aliases)) > 1) {
                    $definition->bind('setAliases', [$aliases]);
                }

                foreach ($aliases as $alias) {
                    $lazyCommandMap[$alias] = new Statement(new Reference($commandId), [], true);
                }
            }
        }

        if (!empty($lazyCommandMap)) {
            $container->definition('console')->bind('setCommandLoader', new Statement(FactoryCommandLoader::class, [$lazyCommandMap]));
        }

        if (!empty($commandServices)) {
            $container->definition('console')->bind('addCommands', [$commandServices]);
        }
    }
}
