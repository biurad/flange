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

namespace Flange\Database\Doctrine\Form\ChoiceList;

/**
 * Custom loader for entities in the choice list.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
interface EntityLoaderInterface
{
    /**
     * Returns an array of entities that are valid choices in the corresponding choice list.
     */
    public function getEntities(): array;

    /**
     * Returns an array of entities matching the given identifiers.
     */
    public function getEntitiesByIds(string $identifier, array $values): array;
}
