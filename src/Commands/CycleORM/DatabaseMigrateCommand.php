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

namespace Flange\Commands\CycleORM;

use Cycle\Migrations\Migrator;
use Cycle\Migrations\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Perform one or all outstanding migrations.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class DatabaseMigrateCommand extends Command
{
    protected static $defaultName = 'cycle:database:migrate';

    public function __construct(private Migrator $migrator)
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
                new InputOption('force', 's', InputOption::VALUE_NONE, 'Skip safe environment check'),
                new InputOption('one', 'o', InputOption::VALUE_NONE, 'Execute only one (first) migration'),
                new InputOption('init', 'i', InputOption::VALUE_NONE, 'Init Database migrations (create migrations table)'),
                new InputOption('status', null, InputOption::VALUE_NONE, 'Get list of all available migrations and their statuses'),
                new InputOption('rollback', null, InputOption::VALUE_NONE, 'Rollback multiple migrations (default) or one setting -o'),
                new InputOption('replay', null, InputOption::VALUE_NONE, 'Replay (down, up) one or multiple migrations'),
            ])
            ->setDescription('Perform one or all outstanding migrations')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->migrator->isConfigured()) {
            $this->migrator->configure();
            $output->writeln('');
            $output->writeln('<info>Migrations table were successfully created</info>');
        }

        if ($input->getOption('init')) {
            goto exitCode;
        }

        if (!$this->verifyConfigured($output) || !$this->verifyEnvironment($input, $io)) {
            //Making sure migration is configured.
            return 1;
        }

        if ($input->getOption('status')) {
            if (empty($this->migrator->getMigrations())) {
                $output->writeln('<comment>No migrations were found.</comment>');

                return 1;
            }

            $table = new Table($output);
            $table = $table->setHeaders(['Migration', 'Created at', 'Executed at']);

            foreach ($this->migrator->getMigrations() as $migration) {
                $state = $migration->getState();

                $table->addRow([
                    $state->getName(),
                    $state->getTimeCreated()->format('Y-m-d H:i:s'),
                    State::STATUS_PENDING === $state->getStatus() ? '<fg=red>not executed yet</fg=red>' : '<info>' . $state->getTimeExecuted()->format('Y-m-d H:i:s') . '</info>',
                ]);
            }
            $table->render();

            goto exitCode;
        }

        $found = false;
        $count = $input->getOption('one') ? 1 : \PHP_INT_MAX;

        if ($input->getOption('replay')) {
            $input->setOption('rollback', $replay = true);
        }

        while ($count > 0 && ($migration = $this->migrator->{$input->getOption('rollback') ? 'rollback' : 'run'}())) {
            $found = true;
            --$count;

            $io->newLine();
            $output->write(\sprintf(
                "<info>Migration <comment>%s</comment> was successfully %s.</info>\n",
                $migration->getState()->getName(),
                $input->getOption('rollback') ? 'rolled back' : 'executed'
            ));
        }

        if (!$found) {
            $io->error('No outstanding migrations were found');

            return 1;
        }

        if (isset($replay)) {
            $output->writeln('');

            if ($io->confirm('Do you want to execute <info>migrations</info> immediately?', false)) {
                $output->writeln('Executing outstanding migration(s)...');

                return $this->getApplication()->find($this->getName())->run(new ArrayInput(['--force' => true]), $output);
            }
        }

        exitCode:
        return self::SUCCESS;
    }

    protected function verifyConfigured(OutputInterface $output): bool
    {
        if (!$this->migrator->isConfigured()) {
            $output->writeln('');
            $output->writeln("<fg=red>Migrations are not configured yet, run '<info>migrations:init</info>' first.</fg=red>");

            return false;
        }

        return true;
    }

    /**
     * Check if current environment is safe to run migration.
     */
    protected function verifyEnvironment(InputInterface $input, SymfonyStyle $io): bool
    {
        if ($input->getOption('force') || $this->migrator->getConfig()->isSafe()) {
            //Safe to run
            return true;
        }

        $io->newLine();
        $io->writeln('<fg=red>Confirmation is required to run migrations!</fg=red>');

        if (!$io->confirm('Would you like to continue?', false)) {
            $io->writeln('<comment>Cancelling operation...</comment>');

            return false;
        }

        return true;
    }
}
