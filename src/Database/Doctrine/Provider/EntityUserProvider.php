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

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Wrapper around a Doctrine ObjectManager.
 *
 * Provides provisioning for Doctrine entity users.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class EntityUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private ObjectManager $objectManager;
    private string $classOrAlias;
    private ?string $property;
    private ?string $class = null;

    public function __construct(ObjectManager $objectManager, string $classOrAlias, string $property = 'username')
    {
        $this->objectManager = $objectManager;
        $this->classOrAlias = $classOrAlias;
        $this->property = $property;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $repository = $this->getRepository();

        if ($repository instanceof UserLoaderInterface || $repository instanceof UserProviderInterface) {
            $user = $repository->loadUserByIdentifier($identifier);
        } elseif (null === $this->property) {
            throw new \InvalidArgumentException(\sprintf('You must either make the "%s" entity Doctrine Repository ("%s") implement "Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface" or set the "property" option in the corresponding entity provider configuration.', $this->classOrAlias, \get_debug_type($repository)));
        }

        if (null === $user = ($user ?? $repository->findOneBy([$this->property => $identifier]))) {
            $e = new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
            $e->setUserIdentifier($identifier);

            throw $e;
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        $class = $this->getClass();

        if (!$user instanceof $class) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', \get_debug_type($user)));
        }

        $repository = $this->getRepository();

        if ($repository instanceof UserProviderInterface) {
            $refreshedUser = $repository->refreshUser($user);
        } else {
            // The user must be reloaded via the primary key as all other data
            // might have changed without proper persistence in the database.
            // That's the case when the user has been changed by a form with
            // validation errors.
            if (!$id = $this->getClassMetadata()->getIdentifierValues($user)) {
                throw new \InvalidArgumentException('You cannot refresh a user from the EntityUserProvider that does not contain an identifier. The user object has to be serialized with its own identifier mapped by Doctrine.');
            }

            $refreshedUser = $repository->find($id);

            if (null === $refreshedUser) {
                $e = new UserNotFoundException('User with id '.\json_encode($id).' not found.');
                $e->setUserIdentifier(\json_encode($id));

                throw $e;
            }
        }

        return $refreshedUser;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class): bool
    {
        return $class === $this->getClass() || \is_subclass_of($class, $this->getClass());
    }

    /**
     * {@inheritdoc}
     *
     * @final
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $class = $this->getClass();

        if (!$user instanceof $class) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', \get_debug_type($user)));
        }

        $repository = $this->getRepository();

        if ($repository instanceof PasswordUpgraderInterface) {
            $repository->upgradePassword($user, $newHashedPassword);
        }
    }

    private function getRepository(): ObjectRepository
    {
        return $this->objectManager->getRepository($this->classOrAlias);
    }

    private function getClass(): string
    {
        if (!isset($this->class)) {
            $class = $this->classOrAlias;

            if (\str_contains($class, ':')) {
                $class = $this->getClassMetadata()->getName();
            }

            $this->class = $class;
        }

        return $this->class;
    }

    private function getClassMetadata(): ClassMetadata
    {
        return $this->objectManager->getClassMetadata($this->classOrAlias);
    }
}
