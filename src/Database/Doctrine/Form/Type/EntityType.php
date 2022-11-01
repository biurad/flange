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

namespace Flange\Database\Doctrine\Form\Type;

use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectManager;
use Flange\Database\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntityType extends DoctrineType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        // Invoke the query builder closure so that we can cache choice lists
        // for equal query builders
        $queryBuilderNormalizer = function (Options $options, $queryBuilder) {
            if (\is_callable($queryBuilder)) {
                $queryBuilder = $queryBuilder($this->registry->getRepository($options['class']));

                if (null !== $queryBuilder && !$queryBuilder instanceof QueryBuilder) {
                    throw new UnexpectedTypeException($queryBuilder, QueryBuilder::class);
                }
            }

            return $queryBuilder;
        };

        $resolver->setNormalizer('query_builder', $queryBuilderNormalizer);
        $resolver->setAllowedTypes('query_builder', ['null', 'callable', QueryBuilder::class]);
    }

    /**
     * Return the default loader object.
     *
     * @param QueryBuilder $queryBuilder
     */
    public function getLoader(ObjectManager $manager, object $queryBuilder, string $class): ORMQueryBuilderLoader
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \TypeError(\sprintf('Expected an instance of "%s", but got "%s".', QueryBuilder::class, \get_debug_type($queryBuilder)));
        }

        return new ORMQueryBuilderLoader($queryBuilder);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'entity';
    }

    /**
     * We consider two query builders with an equal SQL string and
     * equal parameters to be equal.
     *
     * @param QueryBuilder $queryBuilder
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public function getQueryBuilderPartsForCachingHash(object $queryBuilder): ?array
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \TypeError(\sprintf('Expected an instance of "%s", but got "%s".', QueryBuilder::class, \get_debug_type($queryBuilder)));
        }

        return [
            $queryBuilder->getQuery()->getSQL(),
            \array_map([$this, 'parameterToArray'], $queryBuilder->getParameters()->toArray()),
        ];
    }

    /**
     * Converts a query parameter to an array.
     */
    private function parameterToArray(Parameter $parameter): array
    {
        return [$parameter->getName(), $parameter->getType(), $parameter->getValue()];
    }
}
