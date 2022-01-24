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

namespace Rade\Commands\CycleORM;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Database;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\Driver\Driver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List of every configured database, it's tables and count of records.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class DatabaseListCommand extends Command
{
    /**
     * No information available placeholder.
     */
    private const SKIP = '<comment>---</comment>';

    protected static $defaultName = 'cycle:database:list';

    public function __construct(private DatabaseConfig $config, private DatabaseProviderInterface $provider)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('db', InputArgument::OPTIONAL, 'Database name'),
            ])
            ->setDescription('Get list of available databases, their tables and records count')
            ->setHelp(
                <<<EOT
The <info>%command.name%</info> command list the default connections databases:

    <info>php %command.full_name%</info>

You can also optionally specify the name of a database name to view it's connection and tables:

    <info>php %command.full_name% migrations</info>
EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $databases = $input->getArgument('db') ?? \array_keys($this->config->getDatabases());

        if (!\is_array($databases)) {
            $databases = [$databases];
        }

        if (empty($databases)) {
            $output->writeln('<fg=red>No databases found.</fg=red>');

            return self::SUCCESS;
        }

        // create symfony command table
        $grid = new Table($output);
        $grid->setHeaders(
            [
                'Name (ID):',
                'Database:',
                'Driver:',
                'Prefix:',
                'Status:',
                'Tables:',
                'Count Records:',
            ]
        );

        foreach ($databases as $database) {
            $database = $this->provider->database($database);

            /** @var Driver $driver */
            $driver = $database->getDriver();

            $header = [
                $database->getName(),
                $driver->getSource(),
                $driver->getType(),
                $database->getPrefix() ?: self::SKIP,
            ];

            try {
                $driver->connect();
            } catch (\Exception $exception) {
                $this->renderException($grid, $header, $exception);

                if ($database->getName() != \end($databases)) {
                    $grid->addRow(new TableSeparator());
                }

                continue;
            }

            $header[] = '<info>connected</info>';
            $this->renderTables($grid, $header, $database);

            if ($database->getName() != \end($databases)) {
                $grid->addRow(new TableSeparator());
            }
        }

        $grid->render();

        return self::SUCCESS;
    }

    private function renderException(Table $grid, array $header, \Throwable $exception): void
    {
        $grid->addRow(
            \array_merge(
                $header,
                [
                    "<fg=red>{$exception->getMessage()}</fg=red>",
                    self::SKIP,
                    self::SKIP,
                ]
            )
        );
    }

    private function renderTables(Table $grid, array $header, Database $database): void
    {
        foreach ($database->getTables() as $table) {
            $grid->addRow(
                \array_merge(
                    $header,
                    [$table->getName(), \number_format($table->count())]
                )
            );
            $header = ['', '', '', '', ''];
        }

        $header[1] && $grid->addRow(\array_merge($header, ['no tables', 'no records']));
    }
}
