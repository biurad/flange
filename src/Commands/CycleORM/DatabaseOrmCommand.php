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

use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\Migrator;
use Cycle\Migrations\State;
use Cycle\Schema\Compiler;
use Cycle\Schema\Generator\Migrations\GenerateMigrations;
use Cycle\Schema\Generator\SyncTables;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Rade\DI\Extensions\CycleORM\Generator\ShowChanges;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cycle ORM synchronizer command.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class DatabaseOrmCommand extends Command
{
    protected static $defaultName = 'cycle:database:orm';
    protected static $defaultDescription = 'Generate ORM schema migrations and run if possible';

    /**
     * @param array<int,GeneratorInterface> $generators
     */
    public function __construct(private Migrator $migrator, private DatabaseProviderInterface $provider, private array $generators)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->migrator->configure();
        $io = new SymfonyStyle($input, $output);

        foreach ($this->migrator->getMigrations() as $migration) {
            if (State::STATUS_EXECUTED !== $migration->getState()->getStatus()) {
                $io->error('Outstanding migrations found, run `cycle:database:migrate` first.');

                return self::FAILURE;
            }
        }

        $this->generators[] = $show = new ShowChanges($output);

        if (!\class_exists(GenerateMigrations::class)) {
            $io->warning('Synchronizing ORM schema into database is a ricky operation, instead "composer require cycle/schema-migrations-generator".');

            if (!$io->confirm('Do you want to proceed now?', false)) {
                return self::SUCCESS;
            }

            $this->generators[] = new SyncTables();
            $autoMigrate = false;
        }

        (new Compiler())->compile($registry = new Registry($this->provider), $this->generators);

        if ($show->hasChanges()) {
            if (!isset($autoMigrate)) {
                (new GenerateMigrations($this->migrator->getRepository(), $this->migrator->getConfig()))->run($registry);

                if ($io->confirm('Do you want to run migrations now?', false)) {
                    return $this->getApplication()->find('cycle:database:migrate')->run($input, $output);
                }
            } else {
                $io->writeln("\n<info>ORM Schema has been synchronized</info>");
            }
        }

        return self::SUCCESS;
    }
}
