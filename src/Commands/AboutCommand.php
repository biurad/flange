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

namespace Flange\Commands;

use Flange\Application;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A console command to display information about the current project.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
#[AsCommand('about', 'Display information about the current project.')]
final class AboutCommand extends Command
{
    protected static $defaultName = 'about';
    protected static $defaultDescription = 'Display information about the current project';

    public function __construct(private ContainerInterface $container)
    {
        parent::__construct(self::$defaultName);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setHelp(
            <<<'EOT'
The <info>%command.name%</info> command displays information about the current PHP Rade project.

The <info>PHP</info> section displays important configuration that could affect your application. The values might
be different between web and CLI.
EOT
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $container = $this->container;

        $rows = [
            ['<info>PHP Rade Framework</>'],
            new TableSeparator(),
            ['Version', Application::VERSION],
            ['Long-Term Support', 4 === Application::VERSION[2] ? 'Yes' : 'No'],
            new TableSeparator(),
            ['<info>Kernel</>'],
            new TableSeparator(),
            ['Container', $container::class],
            ['Debug', $container instanceof Application ? ($container->isDebug() ? 'true' : 'false') : 'n/a'],
            new TableSeparator(),
            ['<info>PHP</>'],
            new TableSeparator(),
            ['Version', \PHP_VERSION],
            ['Architecture', (\PHP_INT_SIZE * 8).' bits'],
            ['Intl locale', \class_exists(\Locale::class, false) && \Locale::getDefault() ? \Locale::getDefault() : 'n/a'],
            ['Timezone', \date_default_timezone_get().' (<comment>'.(new \DateTime())->format(\DateTime::W3C).'</>)'],
            ['OPcache', \extension_loaded('Zend OPcache') && \filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'],
            ['APCu', \extension_loaded('apcu') && \filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'],
            ['Xdebug', \extension_loaded('xdebug') ? 'true' : 'false'],
        ];

        $io->table([], $rows);

        return self::SUCCESS;
    }
}
