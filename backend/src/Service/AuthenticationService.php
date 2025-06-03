<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Enum\AuthProvider;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthenticationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private RefreshTokenService $refreshTokenService
    ) {}

    public function register(array $data): array
    {
        // Validation des champs requis
        $requiredFields = ['email', 'password', 'firstName', 'lastName', 'onboardingType'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Le champ {$field} est requis");
            }
        }

        // Vérifier si l'email existe déjà (dans UserAuthentication ou User)
        $existingAuth = $this->entityManager->getRepository(UserAuthentication::class)
            ->findOneBy(['email' => $data['email']]);
            
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);
            
        if ($existingAuth || $existingUser) {
            throw new \InvalidArgumentException('Cet email est déjà utilisé');
        }

        // Créer l'utilisateur
        $user = new User();
        $user->setEmail($data['email'])
             ->setFirstName($data['firstName'])
             ->setLastName($data['lastName'])
             ->setOnboardingType($data['onboardingType'])
             ->setIsActive(true)
             ->setOnboardingCompleted(false);

        // Ajouter les champs optionnels
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        if (isset($data['dateOfBirth'])) {
            if (is_string($data['dateOfBirth'])) {
                $user->setDateOfBirth(new \DateTime($data['dateOfBirth']));
            }
        }

        // Définir le rôle basé sur le type d'onboarding
        $roles = match($data['onboardingType']) {
            'owner' => ['ROLE_CLUB_OWNER'],
            'member' => ['ROLE_MEMBER'],
            default => ['ROLE_USER']
        };
        $user->setRoles($roles);

        // Valider l'utilisateur
        $userErrors = $this->validator->validate($user);
        if (count($userErrors) > 0) {
            $errorMessages = [];
            foreach ($userErrors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            throw new \InvalidArgumentException('Erreurs de validation: ' . implode(', ', $errorMessages));
        }

        // Créer l'authentification email
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail($data['email'])
                 ->setPassword(password_hash($data['password'], PASSWORD_DEFAULT));

        // Valider l'authentification aussi
        $authErrors = $this->validator->validate($userAuth);
        if (count($authErrors) > 0) {
            $errorMessages = [];
            foreach ($authErrors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            throw new \InvalidArgumentException('Erreurs de validation de l\'authentification: ' . implode(', ', $errorMessages));
        }

        $this->entityManager->persist($user);
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        $accessToken = $this->jwtManager->create($user);
        $refreshToken = $this->refreshTokenService->createRefreshToken($user);

        return [
            'user' => $user,
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken->getToken()
        ];
    }

    public function login(string $email, string $password, Request $request = null): array
    {
        if (empty($email) || empty($password)) {
            throw new \InvalidArgumentException('Email et mot de passe requis');
        }

        $userAuth = $this->entityManager->getRepository(UserAuthentication::class)
            ->findOneBy([
                'email' => $email,
                'provider' => AuthProvider::EMAIL,
                'isActive' => true
            ]);

        if (!$userAuth || !$userAuth->getUser()->isActive()) {
            throw new \InvalidArgumentException('Identifiants invalides');
        }

        // Vérifier le mot de passe via UserAuthentication
        if (!password_verify($password, $userAuth->getPassword())) {
            throw new \InvalidArgumentException('Identifiants invalides');
        }

        $user = $userAuth->getUser();
        
        // Mise à jour de la dernière connexion
        $userAuth->setLastLoginAt(new \DateTime());
        $this->entityManager->flush();

        $accessToken = $this->jwtManager->create($user);
        $refreshToken = $this->refreshTokenService->createRefreshToken($user, $request);

        return [
            'user' => $user,
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken->getToken()
        ];
    }

    public function refreshTokens(string $refreshTokenString, Request $request = null): array
    {
        $refreshToken = $this->refreshTokenService->findValidToken($refreshTokenString);
        
        if (!$refreshToken) {
            throw new \InvalidArgumentException('Token de rafraîchissement invalide ou expiré');
        }

        $user = $refreshToken->getUser();
        
        if (!$user->isActive()) {
            throw new \InvalidArgumentException('Compte utilisateur désactivé');
        }

        // S'assurer qu'au moins une seconde s'est écoulée pour avoir un timestamp différent
        $lastUsed = $refreshToken->getLastUsedAt();
        if ($lastUsed && (time() - $lastUsed->getTimestamp()) < 1) {
            // Attendre le temps nécessaire pour avoir un timestamp différent
            usleep(100000); // 100ms pour garantir une différence
        }

        // Générer un nouveau access token
        $accessToken = $this->jwtManager->create($user);
        
        // Rotation du refresh token pour la sécurité
        $newRefreshToken = $this->refreshTokenService->refreshToken($refreshToken, $request);

        return [
            'user' => $user,
            'accessToken' => $accessToken,
            'refreshToken' => $newRefreshToken->getToken()
        ];
    }

    public function logout(string $refreshTokenString): void
    {
        if (!empty($refreshTokenString)) {
            $this->refreshTokenService->revokeToken($refreshTokenString);
        }
    }

    public function logoutAllDevices(User $user): void
    {
        $this->refreshTokenService->revokeAllUserTokens($user);
    }

    public function handleOAuthLogin(
        string $email,
        string $providerId,
        string $provider,
        array $userData,
        Request $request = null
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
                     ->setRoles(['ROLE_MEMBER']) // Rôle par défaut pour OAuth
                     ->setIsActive(true)
                     ->setOnboardingCompleted(false);

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

        $accessToken = $this->jwtManager->create($user);
        $refreshToken = $this->refreshTokenService->createRefreshToken($user, $request);

        return new JsonResponse([
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken->getToken(),
            'user' => $this->formatUserResponse($user)
        ]);
    }

    public function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'onboardingCompleted' => $user->isOnboardingCompleted(),
            'onboardingType' => $user->getOnboardingType(),
            'phone' => $user->getPhone(),
            'dateOfBirth' => $user->getDateOfBirth()?->format('Y-m-d'),
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s')
        ];
    }
} 