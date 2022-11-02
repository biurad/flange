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

namespace Flange\Database\Doctrine\Form;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Form\FormTypeGuesserInterface;

class DoctrineOrmExtension extends AbstractExtension
{
    public function __construct(protected ObjectManager $registry)
    {
    }

    protected function loadTypes(): array
    {
        return [
            new Type\EntityType($this->registry),
        ];
    }

    protected function loadTypeGuesser(): ?FormTypeGuesserInterface
    {
        return new DoctrineOrmTypeGuesser($this->registry);
    }
}
