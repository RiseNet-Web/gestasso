<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Service\AuthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
#[OA\Tag(name: 'Authentification', description: 'Gestion de l\'authentification des utilisateurs')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private ValidatorInterface $validator,
        private AuthenticationService $authService,
        private ClientRegistry $clientRegistry
    ) {}

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/login',
        summary: 'Connexion utilisateur',
        description: 'Authentifie un utilisateur avec email/mot de passe et retourne un token JWT. Remember Me automatique pour 1 an.',
        tags: ['Authentification']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'owner1@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Connexion réussie',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'),
                new OA\Property(
                    property: 'user',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'email', type: 'string', example: 'owner1@example.com'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_CLUB_OWNER']),
                        new OA\Property(property: 'onboardingCompleted', type: 'boolean', example: true)
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Données manquantes')]
    #[OA\Response(response: 401, description: 'Identifiants invalides')]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'Email et mot de passe requis'], Response::HTTP_BAD_REQUEST);
        }

        $userAuth = $this->entityManager->getRepository(UserAuthentication::class)
            ->findOneBy([
                'email' => $data['email'],
                'provider' => 'email',
                'isActive' => true
            ]);

        if (!$userAuth || !$userAuth->getUser()->isActive()) {
            return new JsonResponse(['error' => 'Identifiants invalides'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->passwordHasher->isPasswordValid($userAuth->getUser(), $data['password'])) {
            return new JsonResponse(['error' => 'Identifiants invalides'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $userAuth->getUser();
        $token = $this->jwtManager->create($user);
        
        // Mise à jour de la dernière connexion
        $userAuth->setLastLoginAt(new \DateTime());
        $this->entityManager->flush();

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

        // Remember Me activé automatiquement (always_remember_me: true)
        // Le cookie sera créé automatiquement par Symfony pour 1 an

        return $response;
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/register',
        summary: 'Inscription utilisateur',
        description: 'Crée un nouveau compte utilisateur avec authentification email',
        tags: ['Authentification']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password', 'firstName', 'lastName', 'onboardingType'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'nouveau@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'motdepasse123'),
                new OA\Property(property: 'firstName', type: 'string', example: 'Prénom'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Nom'),
                new OA\Property(property: 'onboardingType', type: 'string', enum: ['owner', 'member'], example: 'member'),
                new OA\Property(property: 'phone', type: 'string', example: '+33123456789')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Inscription réussie',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(
                    property: 'user',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'firstName', type: 'string'),
                        new OA\Property(property: 'lastName', type: 'string'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'onboardingCompleted', type: 'boolean'),
                        new OA\Property(property: 'onboardingType', type: 'string')
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Données invalides')]
    #[OA\Response(response: 409, description: 'Email déjà utilisé')]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $requiredFields = ['email', 'password', 'firstName', 'lastName', 'onboardingType'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return new JsonResponse(['error' => "Le champ {$field} est requis"], Response::HTTP_BAD_REQUEST);
            }
        }

        // Vérifier si l'email existe déjà
        $existingAuth = $this->entityManager->getRepository(UserAuthentication::class)
            ->findOneBy(['email' => $data['email']]);
            
        if ($existingAuth) {
            return new JsonResponse(['error' => 'Cet email est déjà utilisé'], Response::HTTP_CONFLICT);
        }

        // Créer l'utilisateur
        $user = new User();
        $user->setEmail($data['email'])
             ->setFirstName($data['firstName'])
             ->setLastName($data['lastName'])
             ->setOnboardingType($data['onboardingType'])
             ->setPhone($data['phone'] ?? null);

        // Assigner le rôle initial selon l'onboarding
        if ($data['onboardingType'] === 'owner') {
            $user->setRoles(['ROLE_CLUB_OWNER']);
        } else {
            $user->setRoles(['ROLE_MEMBER']);
        }

        // Validation
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Créer l'authentification email
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider('email')
                 ->setEmail($data['email'])
                 ->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

        $this->entityManager->persist($user);
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
                'onboardingCompleted' => $user->isOnboardingCompleted(),
                'onboardingType' => $user->getOnboardingType()
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/login/google', name: 'connect_google_start', methods: ['GET'])]
    public function connectGoogleAction(): Response
    {
        return $this->clientRegistry
            ->getClient('google')
            ->redirect(['email', 'profile'], []);
    }

    #[Route('/login/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function connectGoogleCheckAction(Request $request): JsonResponse
    {
        $client = $this->clientRegistry->getClient('google');
        
        try {
            $googleUser = $client->fetchUser();
            
            return $this->authService->handleOAuthLogin(
                $googleUser->getEmail(),
                $googleUser->getId(),
                'google',
                [
                    'firstName' => $googleUser->getFirstName(),
                    'lastName' => $googleUser->getLastName()
                ],
                $request->query->getBoolean('remember_me', false)
            );
            
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la connexion Google'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/profile', name: 'api_profile', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phone' => $user->getPhone(),
            'roles' => $user->getRoles(),
            'onboardingCompleted' => $user->isOnboardingCompleted(),
            'onboardingType' => $user->getOnboardingType(),
            'createdAt' => $user->getCreatedAt()->format('c')
        ]);
    }

    #[Route('/profile', name: 'api_profile_update', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Profil mis à jour avec succès',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'phone' => $user->getPhone()
            ]
        ]);
    }

    #[Route('/refresh-token', name: 'api_refresh_token', methods: ['POST'])]
    #[OA\Post(
        path: '/api/refresh-token',
        summary: 'Renouvellement du token JWT',
        description: 'Génère un nouveau token JWT via le cookie Remember Me (valide 1 an)',
        tags: ['Authentification']
    )]
    #[OA\Response(
        response: 200,
        description: 'Token renouvelé avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'),
                new OA\Property(
                    property: 'user',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'email', type: 'string', example: 'owner1@example.com'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_CLUB_OWNER'])
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Cookie Remember Me invalide ou expiré')]
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Token invalide ou expiré'], Response::HTTP_UNAUTHORIZED);
        }

        // Générer un nouveau token JWT
        $token = $this->jwtManager->create($user);

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles()
            ]
        ]);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        // La déconnexion JWT est gérée côté client
        // Ici on peut nettoyer les remember me tokens si nécessaire
        
        $response = new JsonResponse(['message' => 'Déconnexion réussie']);
        
        // Supprimer le cookie remember me
        $response->headers->clearCookie('REMEMBERME');
        
        return $response;
    }
} 