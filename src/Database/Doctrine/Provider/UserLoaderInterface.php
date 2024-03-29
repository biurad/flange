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

namespace Flange\Database\Doctrine\Provider;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Represents a class that loads UserInterface objects from Doctrine source for the authentication system.
 *
 * This interface is meant to facilitate the loading of a User from Doctrine source using a custom method.
 * If you want to implement your own logic of retrieving the user from Doctrine your repository should implement this
 * interface.
 *
 * @see UserInterface
 *
 * @author Michal Trojanowski <michal@kmt-studio.pl>
 */
interface UserLoaderInterface
{
    /**
     * Loads the user for the given user identifier (e.g. username or email).
     *
     * This method must return null if the user is not found.
     */
    public function loadUserByIdentifier(string $identifier): ?UserInterface;
}
