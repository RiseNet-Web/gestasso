<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Entity\RefreshToken;
use App\Enum\AuthProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Classe de base pour tous les tests API
 * Fournit les méthodes communes pour l'authentification JWT et les assertions
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;
    protected JWTTokenManagerInterface $jwtManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Créer le client seulement s'il n'existe pas
        if (!isset($this->client)) {
            $this->client = static::createClient();
        }
        
        // Obtenir les services du container
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->jwtManager = $container->get(JWTTokenManagerInterface::class);
        
        // Vérifier que l'EntityManager est ouvert
        if (!$this->entityManager->isOpen()) {
            // Réinitialiser l'EntityManager s'il est fermé
            $this->entityManager = $container->get('doctrine')->resetManager();
        }
        
        // Configuration pour les tests
        $this->client->setServerParameter('CONTENT_TYPE', 'application/json');
        $this->client->setServerParameter('HTTP_ACCEPT', 'application/json');
        
        // Nettoyer les données existantes
        $this->clearTestData();
    }

    protected function tearDown(): void
    {
        // Nettoyer les données de test créées
        $this->clearTestData();
        
        // Nettoyer l'EntityManager
        if (isset($this->entityManager) && $this->entityManager->isOpen()) {
            $this->entityManager->clear();
        }
        
        parent::tearDown();
    }

    /**
     * Authentifie un utilisateur et retourne le token JWT
     * S'assure qu'une UserAuthentication existe pour l'utilisateur
     */
    protected function authenticateUser(User $user): string
    {
        // Vérifier si l'EntityManager est ouvert
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = static::getContainer()->get('doctrine')->resetManager();
        }

        // Vérifier si une UserAuthentication existe déjà
        $userAuth = $this->entityManager->getRepository(UserAuthentication::class)
            ->findOneBy(['user' => $user, 'provider' => AuthProvider::EMAIL]);
        
        // Si pas d'authentification, en créer une
        if (!$userAuth) {
            $userAuth = new UserAuthentication();
            $userAuth->setUser($user)
                     ->setProvider(AuthProvider::EMAIL)
                     ->setEmail($user->getEmail())
                     ->setPassword(password_hash('password123', PASSWORD_DEFAULT))
                     ->setIsActive(true);
            
            $this->entityManager->persist($userAuth);
            $this->entityManager->flush();
        }
        
        return $this->jwtManager->create($user);
    }

    /**
     * Effectue une requête authentifiée avec JWT
     */
    protected function authenticatedRequest(string $method, string $uri, User $user, array $data = []): void
    {
        $token = $this->authenticateUser($user);
        
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $token];
        
        if (!empty($data)) {
            $this->client->request(
                $method,
                $uri,
                [],
                [],
                $headers,
                json_encode($data)
            );
        } else {
            $this->client->request($method, $uri, [], [], $headers);
        }
    }

    /**
     * Effectue une requête non authentifiée
     */
    protected function unauthenticatedRequest(string $method, string $uri, array $data = []): void
    {
        if (!empty($data)) {
            $this->client->request(
                $method,
                $uri,
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($data)
            );
        } else {
            $this->client->request($method, $uri);
        }
    }

    /**
     * Effectue une requête multipart authentifiée (pour upload de fichiers)
     */
    protected function authenticatedMultipartRequest(string $method, string $uri, User $user, array $formData = [], array $files = []): void
    {
        $token = $this->authenticateUser($user);
        
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer ' . $token];
        
        // Convertir les chemins de fichiers en objets UploadedFile
        $uploadedFiles = [];
        foreach ($files as $fieldName => $filePath) {
            if (is_string($filePath) && file_exists($filePath)) {
                $uploadedFiles[$fieldName] = $this->createUploadedFile($filePath);
            } elseif ($filePath instanceof UploadedFile) {
                $uploadedFiles[$fieldName] = $filePath;
            }
        }
        
        $this->client->request(
            $method,
            $uri,
            $formData,
            $uploadedFiles,
            $headers
        );
    }

    /**
     * Crée un objet UploadedFile à partir d'un chemin de fichier
     */
    protected function createUploadedFile(string $filePath, ?string $originalName = null, ?string $mimeType = null): UploadedFile
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Le fichier {$filePath} n'existe pas");
        }
        
        $originalName = $originalName ?: basename($filePath);
        $mimeType = $mimeType ?: mime_content_type($filePath) ?: 'application/octet-stream';
        
        return new UploadedFile(
            $filePath,
            $originalName,
            $mimeType,
            null,
            true // test mode
        );
    }

    /**
     * Crée une image de test pour les uploads
     */
    protected function createTestImage(int $width = 200, int $height = 150): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_image') . '.jpg';
        
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 255, 0, 0); // Rouge
        imagefill($image, 0, 0, $color);
        imagejpeg($image, $tempFile);
        imagedestroy($image);
        
        return $tempFile;
    }

    /**
     * Assertions communes pour les réponses JSON
     */
    protected function assertJsonResponse(int $expectedStatusCode, ?string $expectedContentType = 'application/json'): array
    {
        $response = $this->client->getResponse();
        
        $this->assertEquals($expectedStatusCode, $response->getStatusCode());
        
        if ($expectedContentType) {
            $this->assertStringContainsString($expectedContentType, $response->headers->get('Content-Type'));
        }
        
        $content = $response->getContent();
        $this->assertJson($content);
        
        return json_decode($content, true);
    }

    /**
     * Assertion pour vérifier la structure d'une réponse d'erreur
     */
    protected function assertErrorResponse(int $expectedStatusCode, ?string $expectedMessage = null): array
    {
        $data = $this->assertJsonResponse($expectedStatusCode);
        
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('title', $data);
        
        if ($expectedMessage) {
            $this->assertStringContainsString($expectedMessage, $data['detail'] ?? $data['title']);
        }
        
        return $data;
    }

    /**
     * Crée un utilisateur de test avec les rôles spécifiés et son authentification
     */
    protected function createTestUser(string $email, array $roles = ['ROLE_USER'], array $extraData = []): User
    {
        // Vérifier si l'EntityManager est ouvert
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = static::getContainer()->get('doctrine')->resetManager();
        }

        // Ajouter un suffixe unique basé sur le timestamp pour éviter les doublons
        $uniqueEmail = $this->makeEmailUnique($email);

        $user = new User();
        $user->setEmail($uniqueEmail);
        $user->setFirstName($extraData['firstName'] ?? 'Test');
        $user->setLastName($extraData['lastName'] ?? 'User');
        $user->setRoles($roles);
        $user->setIsActive(true);
        $user->setOnboardingType($extraData['onboardingType'] ?? 'member');
        $user->setOnboardingCompleted($extraData['onboardingCompleted'] ?? true);
        
        if (isset($extraData['phone'])) {
            $user->setPhone($extraData['phone']);
        }
        
        // Ajouter la date de naissance si fournie
        if (isset($extraData['dateOfBirth'])) {
            if (is_string($extraData['dateOfBirth'])) {
                $user->setDateOfBirth(new \DateTime($extraData['dateOfBirth']));
            } elseif ($extraData['dateOfBirth'] instanceof \DateTime) {
                $user->setDateOfBirth($extraData['dateOfBirth']);
            }
        }
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        // Créer automatiquement l'authentification email pour les tests
        if (!isset($extraData['skipAuthentication'])) {
            $userAuth = new UserAuthentication();
            $userAuth->setUser($user)
                     ->setProvider(AuthProvider::EMAIL)
                     ->setEmail($uniqueEmail)
                     ->setPassword(password_hash($extraData['password'] ?? 'password123', PASSWORD_DEFAULT))
                     ->setIsActive(true);
            
            $this->entityManager->persist($userAuth);
            $this->entityManager->flush();
        }
        
        return $user;
    }

    /**
     * Rend un email unique en ajoutant un timestamp
     */
    private function makeEmailUnique(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) === 2) {
            return $parts[0] . '.' . time() . '.' . mt_rand(1000, 9999) . '@' . $parts[1];
        }
        return $email . '.' . time() . '.' . mt_rand(1000, 9999);
    }

    /**
     * Vide les données de test de la base
     */
    protected function clearTestData(): void
    {
        // Vérifier si l'EntityManager est ouvert
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = static::getContainer()->get('doctrine')->resetManager();
        }

        try {
            // Supprimer tous les utilisateurs de test (avec patterns de test)
            $qb = $this->entityManager->createQueryBuilder();
            $testUsers = $qb->select('u')
                ->from(User::class, 'u')
                ->where('u.email LIKE :testPattern1')
                ->orWhere('u.email LIKE :testPattern2') 
                ->orWhere('u.email LIKE :testPattern3')
                ->orWhere('u.email LIKE :testPattern4')
                ->setParameter('testPattern1', '%.test.com%')
                ->setParameter('testPattern2', '%@test.com%')
                ->setParameter('testPattern3', '%@example.com%')
                ->setParameter('testPattern4', '%debug.%')
                ->getQuery()
                ->getResult();

            foreach ($testUsers as $user) {
                // Supprimer les refresh tokens associés
                $refreshTokens = $this->entityManager->getRepository(RefreshToken::class)
                    ->findBy(['user' => $user]);
                foreach ($refreshTokens as $token) {
                    $this->entityManager->remove($token);
                }

                // Supprimer les authentifications associées
                $auths = $this->entityManager->getRepository(UserAuthentication::class)
                    ->findBy(['user' => $user]);
                foreach ($auths as $auth) {
                    $this->entityManager->remove($auth);
                }

                // Supprimer l'utilisateur
                $this->entityManager->remove($user);
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            
        } catch (\Exception $e) {
            // En cas d'erreur, réinitialiser l'EntityManager
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = static::getContainer()->get('doctrine')->resetManager();
            }
            $this->entityManager->clear();
        }
    }

    /**
     * Assertion pour vérifier qu'un utilisateur a accès à une ressource
     */
    protected function assertUserCanAccess(User $user, string $method, string $uri): void
    {
        $this->authenticatedRequest($method, $uri, $user);
        $this->assertNotEquals(403, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Assertion pour vérifier qu'un utilisateur n'a PAS accès à une ressource
     */
    protected function assertUserCannotAccess(User $user, string $method, string $uri): void
    {
        $this->authenticatedRequest($method, $uri, $user);
        $this->assertEquals(403, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Créer des fixtures de test de base
     */
    protected function loadBasicFixtures(): array
    {
        // Utilisateurs de base pour les tests
        $clubOwner = $this->createTestUser(
            'marc.dubois@racingclub.com',
            ['ROLE_CLUB_OWNER'],
            ['firstName' => 'Marc', 'lastName' => 'Dubois', 'dateOfBirth' => '1980-01-01']
        );

        $coach = $this->createTestUser(
            'julie.moreau@coach.com',
            ['ROLE_COACH'],
            ['firstName' => 'Julie', 'lastName' => 'Moreau', 'dateOfBirth' => '1985-01-01']
        );

        $athlete = $this->createTestUser(
            'emma.leblanc@athlete.com',
            ['ROLE_ATHLETE'],
            ['firstName' => 'Emma', 'lastName' => 'Leblanc', 'dateOfBirth' => '1995-01-01']
        );

        return [
            'clubOwner' => $clubOwner,
            'coach' => $coach,
            'athlete' => $athlete
        ];
    }
} 