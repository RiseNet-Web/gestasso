<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserAuthentication;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
class AuthenticationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager
    ) {}

    public function handleOAuthLogin(
        string $email,
        string $providerId,
        string $provider,
        array $userData,
        bool $rememberMe = false
    ): JsonResponse {
        // Chercher une authentification existante pour ce provider
        $userAuth = $this->entityManager->getRepository(UserAuthentication::class)
            ->findOneBy([
                'provider' => $provider,
                'providerId' => $providerId,
                'isActive' => true
            ]);

        if ($userAuth) {
            // Utilisateur existant avec ce provider
            $user = $userAuth->getUser();
            
            if (!$user->isActive()) {
                return new JsonResponse(['error' => 'Compte désactivé'], Response::HTTP_UNAUTHORIZED);
            }
        } else {
            // Vérifier si un utilisateur existe avec cet email
            $existingUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $email]);

            if ($existingUser) {
                // Lier ce provider à l'utilisateur existant
                $userAuth = new UserAuthentication();
                $userAuth->setUser($existingUser)
                         ->setProvider($provider)
                         ->setProviderId($providerId)
                         ->setEmail($email);
                
                $this->entityManager->persist($userAuth);
                $user = $existingUser;
            } else {
                // Créer un nouvel utilisateur
                $user = new User();
                $user->setEmail($email)
                     ->setFirstName($userData['firstName'] ?? '')
                     ->setLastName($userData['lastName'] ?? '')
                     ->setRoles(['ROLE_MEMBER']); // Rôle par défaut pour OAuth

                $userAuth = new UserAuthentication();
                $userAuth->setUser($user)
                         ->setProvider($provider)
                         ->setProviderId($providerId)
                         ->setEmail($email);

                $this->entityManager->persist($user);
                $this->entityManager->persist($userAuth);
            }
        }

        // Mise à jour de la dernière connexion
        $userAuth->setLastLoginAt(new \DateTime());
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        $response = new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
                'onboardingCompleted' => $user->isOnboardingCompleted()
            ]
        ]);

        return $response;
    }

    public function linkProvider(User $user, string $provider, string $providerId, string $email): UserAuthentication
    {
        // Vérifier que ce provider n'est pas déjà lié
        $existingAuth = $this->entityManager->getRepository(UserAuthentication::class)
            ->findOneBy([
                'provider' => $provider,
                'providerId' => $providerId
            ]);

        if ($existingAuth) {
            throw new \InvalidArgumentException('Ce compte ' . $provider . ' est déjà lié à un autre utilisateur');
        }

        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider($provider)
                 ->setProviderId($providerId)
                 ->setEmail($email);

        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        return $userAuth;
    }

    public function unlinkProvider(User $user, string $provider): bool
    {
        $userAuth = $this->entityManager->getRepository(UserAuthentication::class)
            ->findOneBy([
                'user' => $user,
                'provider' => $provider,
                'isActive' => true
            ]);

        if (!$userAuth) {
            return false;
        }

        // Vérifier qu'il reste au moins une méthode d'authentification
        $authCount = $this->entityManager->getRepository(UserAuthentication::class)
            ->count([
                'user' => $user,
                'isActive' => true
            ]);

        if ($authCount <= 1) {
            throw new \InvalidArgumentException('Impossible de supprimer la dernière méthode d\'authentification');
        }

        $userAuth->setIsActive(false);
        $this->entityManager->flush();

        return true;
    }

    public function register(array $data): array
    {
        // Logique d'inscription utilisateur (validation, création, hashage, etc.)
        // Retourne ['user' => $user, 'token' => $token]
    }

    public function login(string $email, string $password): array
    {
        // Logique d'authentification utilisateur (vérification, token, etc.)
        // Retourne ['user' => $user, 'token' => $token]
    }

    public function refreshToken(User $user): string
    {
        // Logique de renouvellement du token JWT
    }
} 