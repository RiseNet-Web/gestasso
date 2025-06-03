<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthenticationService;
use App\Service\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
#[OA\Tag(name: 'Authentification', description: 'Gestion de l\'authentification des utilisateurs')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager,
        private ValidatorInterface $validator,
        private AuthenticationService $authService,
        private RefreshTokenService $refreshTokenService,
        private ClientRegistry $clientRegistry
    ) {}

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/login',
        summary: 'Connexion utilisateur',
        description: 'Authentifie un utilisateur avec email/mot de passe et retourne des tokens JWT.',
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
                new OA\Property(property: 'accessToken', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'),
                new OA\Property(property: 'refreshToken', type: 'string', example: 'a1b2c3d4e5f6...'),
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
        
        try {
            $result = $this->authService->login(
                $data['email'] ?? '',
                $data['password'] ?? '',
                $request
            );

            return new JsonResponse([
                'accessToken' => $result['accessToken'],
                'refreshToken' => $result['refreshToken'],
                'user' => $this->authService->formatUserResponse($result['user'])
            ]);

        } catch (\InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'requis')) {
                return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
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
                new OA\Property(property: 'phone', type: 'string', example: '+33123456789'),
                new OA\Property(property: 'dateOfBirth', type: 'string', format: 'date', example: '1990-05-15')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Inscription réussie',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'accessToken', type: 'string'),
                new OA\Property(property: 'refreshToken', type: 'string'),
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
        
        // Validation JSON
        if ($data === null) {
            return new JsonResponse(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $result = $this->authService->register($data);

            return new JsonResponse([
                'accessToken' => $result['accessToken'],
                'refreshToken' => $result['refreshToken'],
                'user' => $this->authService->formatUserResponse($result['user'])
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'requis')) {
                return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            } elseif (str_contains($e->getMessage(), 'déjà utilisé')) {
                return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
            } elseif (str_contains($e->getMessage(), 'validation')) {
                // Extraire les erreurs de validation
                $errorMessage = str_replace('Erreurs de validation: ', '', $e->getMessage());
                $errors = explode(', ', $errorMessage);
                return new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST);
            }
            // Log de débogage pour les autres erreurs
            error_log('Erreur d\'inscription non gérée: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            // Log pour les erreurs inattendues
            error_log('Erreur d\'inscription inattendue: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return new JsonResponse(['error' => 'Erreur interne du serveur'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/refresh-token', name: 'api_refresh_token', methods: ['POST'])]
    #[OA\Post(
        path: '/api/refresh-token',
        summary: 'Renouvellement des tokens JWT',
        description: 'Génère de nouveaux tokens JWT à partir d\'un refresh token valide',
        tags: ['Authentification']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['refreshToken'],
            properties: [
                new OA\Property(property: 'refreshToken', type: 'string', example: 'a1b2c3d4e5f6...')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Tokens renouvelés avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'accessToken', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'),
                new OA\Property(property: 'refreshToken', type: 'string', example: 'a1b2c3d4e5f6...'),
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
    #[OA\Response(response: 400, description: 'Refresh token manquant')]
    #[OA\Response(response: 401, description: 'Refresh token invalide ou expiré')]
    public function refreshToken(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['refreshToken'])) {
            return new JsonResponse(['error' => 'Refresh token requis'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->authService->refreshTokens($data['refreshToken'], $request);

            return new JsonResponse([
                'accessToken' => $result['accessToken'],
                'refreshToken' => $result['refreshToken'],
                'user' => $this->authService->formatUserResponse($result['user'])
            ]);

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    #[OA\Post(
        path: '/api/logout',
        summary: 'Déconnexion utilisateur',
        description: 'Révoque le refresh token pour déconnecter l\'utilisateur',
        tags: ['Authentification']
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'refreshToken', type: 'string', example: 'a1b2c3d4e5f6...'),
                new OA\Property(property: 'allDevices', type: 'boolean', example: false, description: 'Déconnecter de tous les appareils')
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Déconnexion réussie')]
    public function logout(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        try {
            // Si allDevices=true, on a besoin d'un utilisateur authentifié
            if (isset($data['allDevices']) && $data['allDevices'] === true) {
                $user = $this->getUser();
                
                if (!$user instanceof User) {
                    return new JsonResponse(
                        ['error' => 'Authentification requise pour déconnecter tous les appareils'], 
                        Response::HTTP_UNAUTHORIZED
                    );
                }
                
                // Déconnecter de tous les appareils
                $this->authService->logoutAllDevices($user);
                
                return new JsonResponse([
                    'message' => 'Déconnexion réussie de tous les appareils',
                    'type' => 'all_devices'
                ]);
            }
            
            // Déconnexion simple avec refresh token
            if (!empty($data['refreshToken'])) {
                $this->authService->logout($data['refreshToken']);
                
                return new JsonResponse([
                    'message' => 'Déconnexion réussie',
                    'type' => 'single_device'
                ]);
            }
            
            // Aucun token fourni - toujours retourner succès pour la sécurité
            return new JsonResponse([
                'message' => 'Déconnexion réussie',
                'type' => 'no_token'
            ]);

        } catch (\InvalidArgumentException $e) {
            // Token invalide - considéré comme un succès pour la sécurité
            return new JsonResponse([
                'message' => 'Déconnexion réussie',
                'type' => 'invalid_token'
            ]);
        } catch (\Exception $e) {
            // Log l'erreur pour le debug mais retourne succès pour la sécurité
            error_log('Logout error: ' . $e->getMessage());
            
            return new JsonResponse([
                'message' => 'Déconnexion réussie',
                'type' => 'error_handled'
            ]);
        }
    }

    #[Route('/profile', name: 'api_profile', methods: ['GET'])]
    #[OA\Get(
        path: '/api/profile',
        summary: 'Profil utilisateur',
        description: 'Récupère les informations du profil de l\'utilisateur connecté',
        tags: ['Authentification']
    )]
    #[OA\Response(
        response: 200,
        description: 'Profil utilisateur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'email', type: 'string'),
                new OA\Property(property: 'firstName', type: 'string'),
                new OA\Property(property: 'lastName', type: 'string'),
                new OA\Property(property: 'phone', type: 'string'),
                new OA\Property(property: 'dateOfBirth', type: 'string'),
                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'onboardingCompleted', type: 'boolean')
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    #[OA\Response(response: 404, description: 'Utilisateur non trouvé')]
    public function profile(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->authService->formatUserResponse($user));
    }

    #[Route('/profile', name: 'api_update_profile', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/profile',
        summary: 'Mise à jour du profil',
        description: 'Met à jour les informations du profil utilisateur',
        tags: ['Authentification']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'firstName', type: 'string'),
                new OA\Property(property: 'lastName', type: 'string'),
                new OA\Property(property: 'phone', type: 'string'),
                new OA\Property(property: 'dateOfBirth', type: 'string', format: 'date')
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Profil mis à jour')]
    #[OA\Response(response: 400, description: 'Données invalides')]
    #[OA\Response(response: 401, description: 'Non authentifié')]
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
        if (isset($data['dateOfBirth'])) {
            if (is_string($data['dateOfBirth'])) {
                $user->setDateOfBirth(new \DateTime($data['dateOfBirth']));
            }
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
            'user' => $this->authService->formatUserResponse($user)
        ]);
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
                $request
            );
            
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de la connexion Google'], Response::HTTP_BAD_REQUEST);
        }
    }
} 