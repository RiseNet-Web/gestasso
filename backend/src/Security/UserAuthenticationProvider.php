<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Enum\AuthProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserAuthenticationProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Charger l'utilisateur via UserAuthentication
        $userAuth = $this->entityManager->getRepository(UserAuthentication::class)
            ->findOneBy([
                'email' => $identifier,
                'provider' => AuthProvider::EMAIL,
                'isActive' => true
            ]);

        if (!$userAuth || !$userAuth->getUser()->isActive()) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return $userAuth->getUser();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }
} 