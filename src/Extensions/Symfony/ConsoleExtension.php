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
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Services\AliasedInterface;
use Rade\KernelInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

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

        $container->type(AboutCommand::class, Command::class);
        $container->set('console.command.server', new Definition(ServerCommand::class, ['%project_dir%', '%debug%']))->tag('console.command', 'serve');
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

        $serviceIds = $container->findBy(Command::class, fn ($v) => $container->has($v) ? new Reference($v) : new Statement($v));
        $commandServices = $container->findBy('console.command', function (string $value) use ($container): array {
            $commandTagged = $container->tagged('console.command', $value);

            if (!\is_string($commandTagged)) {
                try {
                    $r = new \ReflectionClass($c = $container->definition($value)->getEntity());
                } catch (\ReflectionException $e) {
                    throw new \InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $c, $value));
                }

                if (!$r->isSubclassOf(Command::class)) {
                    throw new \InvalidArgumentException(\sprintf('The service "%s" tagged "%s" must be a subclass of "%s".', $value, 'console.command', Command::class));
                }

                return [$c::getDefaultName() => $value];
            }

            return [$commandTagged => $value];
        });

        if (!empty($commandServices)) {
            $container->definition('console')->bind('setCommandLoader', new Statement(ContainerCommandLoader::class, [1 => \array_merge(...$commandServices)]));
        }

        if (!empty($serviceIds)) {
            $container->definition('console')->bind('addCommands', [$serviceIds]);
        }
    }
}
