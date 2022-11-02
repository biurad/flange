<?php declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange\Extensions\CycleORM;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\EntityManager;
use Cycle\ORM\Factory;
use Cycle\ORM\FactoryInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Schema;
use Cycle\Schema\Compiler;
use Cycle\Schema\Generator;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Rade\Commands\CycleORM\DatabaseOrmCommand;
use Rade\DI\Builder\PhpLiteral;
use Rade\DI\Container;
use Rade\DI\ContainerBuilder;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Exceptions\ServiceCreationException;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Spiral\Tokenizer\ClassLocator;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Finder\Finder;

/**
 * Cycle ORM Extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class ORMExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'orm';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('entities_path')->isRequired()->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $container, array $configs = []): void
    {
        if (!\interface_exists(ORMInterface::class)) {
            throw new \LogicException('Cycle ORM support cannot be enabled as the ORM component is not installed. Try running "composer require cycle/orm".');
        }

        if (!$container->typed(DatabaseProviderInterface::class)) {
            throw new \LogicException(\sprintf('Cycle ORM requires the %s to be registered first.', DatabaseExtension::class));
        }

        $container->set('cycle.orm.factory', new Definition(Factory::class))->typed(FactoryInterface::class);
        $container->autowire('cycle.orm', new Definition(ORM::class, [new Reference('cycle.orm.factory'), new Reference('cycle.orm.schema')]));
        $container->autowire('cycle.orm.entity_manager', new Definition(EntityManager::class, [new Reference('cycle.orm')]));
        $container->autowire('cycle.orm.schema', new Definition(Schema::class, [new Statement(
            [Compiler::class, 'compile'],
            [new Statement(Registry::class), $generators = $this->getGenerators($container, $configs['entities_path'])]
        )]));

        if ($container->has('cycle.database.migrator') && $container->has('console')) {
            $container->set('console.command.cycle_orm', new Definition(DatabaseOrmCommand::class, [2 => $generators]))->public(false)->tag('console.command');
        }
    }

    /**
     * @return GeneratorInterface[]
     */
    private function getGenerators(Container $container, string $entityPath): array
    {
        $generators = [new Statement(Generator\ResetTables::class)]; // re-declared table schemas (remove columns)

        if (\class_exists(\Cycle\Annotated\Configurator::class)) {
            if (!$container->has('annotation.reader')) {
                throw new ServiceCreationException('The Spiral Annotation reader is required to use Cycle ORM annotations.');
            }

            if ($container instanceof ContainerBuilder) {
                $finder = new PhpLiteral(\sprintf('%s::create()->in(\'%s\')->name(\'*.php\');', Finder::class, $container->parameter($entityPath)));
            } else {
                $finder = Finder::create()->followLinks()->in($container->parameter($entityPath))->name('*.php');
            }

            $container->set('cycle.orm.schema.class_locator', new Definition(ClassLocator::class, [$finder]))->public(false);
            $generators = \array_merge([
                new Statement(\Cycle\Annotated\Embeddings::class, [$load = new Reference('cycle.orm.schema.class_locator'), $annotation = new Reference('annotation.reader')]), // register embeddable entities
                new Statement(\Cycle\Annotated\Entities::class, [$load, $annotation]), // register annotated entities
                new Statement(\Cycle\Annotated\MergeColumns::class, [$annotation]), // copy column declarations from all related classes (@Table annotation)
            ], $generators);
        }

        $generators = \array_merge($generators, [
            new Statement(Generator\GenerateRelations::class), // generate entity relations
            new Statement(Generator\ValidateEntities::class), // make sure all entity schemas are correct
            new Statement(Generator\RenderTables::class), // declare table schemas
            new Statement(Generator\RenderRelations::class), // declare relation keys and indexes
            isset($annotation) ? new Statement(\Cycle\Annotated\MergeIndexes::class, [$annotation]) : null, // copy index declarations from all related classes (@Table annotation)
            new Statement(Generator\GenerateTypecast::class), // typecast non string columns
        ]);

        return \array_filter($generators);
    }
}
