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

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\Driver\DriverInterface;
use Cycle\Database\Exception\DBALException;
use Cycle\Database\Injection\FragmentInterface;
use Cycle\Database\Query\QueryParameters;
use Cycle\Database\Schema\AbstractColumn;
use Cycle\Database\Schema\AbstractForeignKey;
use Cycle\Database\Schema\AbstractIndex;
use Cycle\Database\Schema\AbstractTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Describes table schema for a database.
 */
class DatabaseTableCommand extends Command
{
    protected static $defaultName = 'cycle:database:table';

    private SymfonyStyle $io;

    private Table $table;

    public function __construct(private DatabaseProviderInterface $provider)
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
                new InputArgument('table', InputArgument::REQUIRED, 'Table name'),
                new InputOption('database', 'd', InputOption::VALUE_OPTIONAL, 'Source database', 'default'),
            ])
            ->setDescription('Describe table schema of specific database')
        ;
    }

    /**
     * This optional method is the first one executed for a command after configure()
     * and is useful to initialize properties based on the input arguments and options.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // SymfonyStyle is an optional feature that Symfony provides so you can
        // apply a consistent look to the commands of your application.
        // See https://symfony.com/doc/current/console/style.html
        $this->io = new SymfonyStyle($input, $output);
        $this->table = new Table($output);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $database = $this->provider->database($input->getOption('database'));
        $schema = $database->table($input->getArgument('table'))->getSchema();

        if (!$schema->exists()) {
            throw new DBALException("Table {$database->getName()}.{$input->getArgument('table')} does not exists.");
        }

        $output->writeln(
            \sprintf(
                "\n<fg=cyan>Columns of </fg=cyan><comment>%s.%s</comment>:\n",
                $database->getName(),
                $input->getArgument('table')
            )
        );

        $this->describeColumns($schema);

        if (!empty($indexes = $schema->getIndexes())) {
            $this->describeIndexes($database, $indexes, $input);
        }

        if (!empty($foreignKeys = $schema->getForeignKeys())) {
            $this->describeForeignKeys($database, $foreignKeys, $input);
        }

        $output->write("\n");

        return self::SUCCESS;
    }

    protected function describeColumns(AbstractTable $schema): void
    {
        $columnsTable = $this->table->setHeaders(
            [
                'Column:',
                'Database Type:',
                'Abstract Type:',
                'PHP Type:',
                'Default Value:',
            ]
        );

        foreach ($schema->getColumns() as $column) {
            $name = $column->getName();

            if (\in_array($column->getName(), $schema->getPrimaryKeys(), true)) {
                $name = "<fg=magenta>{$name}</fg=magenta>";
            }

            $defaultValue = $this->describeDefaultValue($column, $schema->getDriver());

            $columnsTable->addRow(
                [
                    $name,
                    $this->describeType($column),
                    $this->describeAbstractType($column),
                    $column->getType(),
                    $defaultValue ?? '<comment>---</comment>',
                ]
            );
        }

        $columnsTable->render();
    }

    /**
     * @param array<int,AbstractIndex> $indexes
     */
    protected function describeIndexes(DatabaseInterface $database, array $indexes, InputInterface $input): void
    {
        $this->sprintf(
            "\n<fg=cyan>Indexes of </fg=cyan><comment>%s.%s</comment>:\n",
            $database->getName(),
            $input->getArgument('table')
        );

        $indexesTable = $this->table->setHeaders(['Name:', 'Type:', 'Columns:']);

        foreach ($indexes as $index) {
            $indexesTable->addRow(
                [
                    $index->getName(),
                    $index->isUnique() ? 'UNIQUE INDEX' : 'INDEX',
                    \implode(', ', $index->getColumns()),
                ]
            );
        }

        $indexesTable->render();
    }

    /**
     * @param array<int,AbstractForeignKey> $foreignKeys
     */
    protected function describeForeignKeys(DatabaseInterface $database, array $foreignKeys, InputInterface $input): void
    {
        $this->sprintf(
            "\n<fg=cyan>Foreign Keys of </fg=cyan><comment>%s.%s</comment>:\n",
            $database->getName(),
            $input->getArgument('table')
        );
        $foreignTable = $this->table->setHeaders(
            [
                'Name:',
                'Column:',
                'Foreign Table:',
                'Foreign Column:',
                'On Delete:',
                'On Update:',
            ]
        );

        foreach ($foreignKeys as $reference) {
            $foreignTable->addRow(
                [
                    $reference->getName(),
                    \implode(', ', $reference->getColumns()),
                    $reference->getForeignTable(),
                    \implode(', ', $reference->getForeignKeys()),
                    $reference->getDeleteRule(),
                    $reference->getUpdateRule(),
                ]
            );
        }

        $foreignTable->render();
    }

    /**
     * @return mixed
     */
    protected function describeDefaultValue(AbstractColumn $column, DriverInterface $driver)
    {
        $defaultValue = $column->getDefaultValue();

        if ($defaultValue instanceof FragmentInterface) {
            $value = $driver->getQueryCompiler()->compile(new QueryParameters(), '', $defaultValue);

            return "<info>{$value}</info>";
        }

        if ($defaultValue instanceof \DateTimeInterface) {
            $defaultValue = $defaultValue->format('c');
        }

        return $defaultValue;
    }

    /**
     * Identical to write function but provides ability to format message. Does not add new line.
     *
     * @param array ...$args
     */
    protected function sprintf(string $format, ...$args)
    {
        return $this->io->write(\sprintf($format, ...$args), false);
    }

    private function describeType(AbstractColumn $column): string
    {
        $type = $column->getType();

        $abstractType = $column->getAbstractType();

        if ($column->getSize()) {
            $type .= " ({$column->getSize()})";
        }

        if ('decimal' === $abstractType) {
            $type .= " ({$column->getPrecision()}, {$column->getScale()})";
        }

        return $type;
    }

    private function describeAbstractType(AbstractColumn $column): string
    {
        $abstractType = $column->getAbstractType();

        if (\in_array($abstractType, ['primary', 'bigPrimary'])) {
            $abstractType = "<fg=magenta>{$abstractType}</fg=magenta>";
        }

        return $abstractType;
    }
}
