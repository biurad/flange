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

namespace Flange\Extensions\Flysystem;

use Rade\DI\Definition;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 *
 * @internal
 */
interface AdapterFactoryInterface
{
    public function getName(): string;

    /**
     * Create the definition for this builder's adapter given an array of options.
     */
    public function createDefinition(array $options): Definition;
}
