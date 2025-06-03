<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Entity\RefreshToken;
use App\Enum\AuthProvider;

/**
 * Tests d'authentification JWT avec Refresh Tokens
 * Couvre l'inscription, la connexion, les tokens et la validation
 */
class AuthenticationTest extends ApiTestCase
{
    public function testUserRegistration(): void
    {
        // Données d'inscription valides avec email unique
        $uniqueEmail = 'nouveau.user.' . time() . '@test.com';
        $userData = [
            'email' => $uniqueEmail,
            'password' => 'password123',
            'firstName' => 'Nouveau',
            'lastName' => 'Utilisateur',
            'onboardingType' => 'member',
            'phone' => '0123456789',
            'dateOfBirth' => '1995-06-15' // 29 ans en 2024
        ];

        // Test inscription
        $this->unauthenticatedRequest('POST', '/api/register', $userData);
        
        // Déboguer la réponse avant l'assertion
        $response = $this->client->getResponse();
        $statusCode = $response->getStatusCode();
        $content = $response->getContent();
        
        if ($statusCode !== 201) {
            echo "Code de réponse: " . $statusCode . "\n";
            echo "Contenu de la réponse: " . $content . "\n";
            echo "Headers: \n";
            foreach ($response->headers as $name => $value) {
                echo "  {$name}: " . (is_array($value) ? implode(', ', $value) : $value) . "\n";
            }
        }
        
        $responseData = $this->assertJsonResponse(201);
        
        // Vérifications de la réponse avec refresh token
        $this->assertArrayHasKey('accessToken', $responseData);
        $this->assertArrayHasKey('refreshToken', $responseData);
        $this->assertArrayHasKey('user', $responseData);
        
        $user = $responseData['user'];
        $this->assertEquals($userData['email'], $user['email']);
        $this->assertEquals($userData['firstName'], $user['firstName']);
        $this->assertEquals($userData['lastName'], $user['lastName']);
        $this->assertEquals($userData['onboardingType'], $user['onboardingType']);
        
        // Vérifier que le mot de passe n'est pas retourné
        $this->assertArrayNotHasKey('password', $user);
        
        // Vérifier les tokens
        $this->assertIsString($responseData['accessToken']);
        $this->assertNotEmpty($responseData['accessToken']);
        $this->assertIsString($responseData['refreshToken']);
        $this->assertNotEmpty($responseData['refreshToken']);
        
        // Vérifier en base de données
        $userInDb = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $userData['email']]);
        
        $this->assertNotNull($userInDb);
        $this->assertEquals($userData['email'], $userInDb->getEmail());
        $this->assertTrue($userInDb->isActive());
        $this->assertFalse($userInDb->isOnboardingCompleted()); // Nouveau utilisateur
        $this->assertNotNull($userInDb->getDateOfBirth());
        $this->assertEquals('1995-06-15', $userInDb->getDateOfBirth()->format('Y-m-d'));
        
        // Vérifier l'authentification créée
        $userAuth = $this->entityManager->getRepository(UserAuthentication::class)
            ->findOneBy(['email' => $userData['email'], 'provider' => AuthProvider::EMAIL]);
        
        $this->assertNotNull($userAuth);
        $this->assertTrue($userAuth->isActive());
        
        // Vérifier que le refresh token existe en base
        $refreshToken = $this->entityManager->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $responseData['refreshToken']]);
        
        $this->assertNotNull($refreshToken);
        $this->assertEquals($userInDb->getId(), $refreshToken->getUser()->getId());
        $this->assertTrue($refreshToken->isValid());
    }

    public function testUserRegistrationWithInvalidEmail(): void
    {
        $userData = [
            'email' => 'email-invalide',
            'password' => 'password123',
            'firstName' => 'Test',
            'lastName' => 'User',
            'onboardingType' => 'member',
            'dateOfBirth' => '1990-01-01'
        ];

        $this->unauthenticatedRequest('POST', '/api/register', $userData);
        
        $responseData = $this->assertJsonResponse(400);
        $this->assertArrayHasKey('errors', $responseData);
    }

    public function testUserRegistrationWithMissingFields(): void
    {
        $userData = [
            'email' => 'test@test.com',
            'password' => 'password123'
            // Manque firstName, lastName, onboardingType
        ];

        $this->unauthenticatedRequest('POST', '/api/register', $userData);
        
        $responseData = $this->assertJsonResponse(400);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertStringContainsString('requis', $responseData['error']);
    }

    public function testUserRegistrationWithDuplicateEmail(): void
    {
        // Créer un utilisateur existant
        $existingUser = $this->createTestUser('existing@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1990-05-15'
        ]);
        
        // Créer aussi l'authentification correspondante
        $userAuth = new UserAuthentication();
        $userAuth->setUser($existingUser)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail('existing@test.com')
                 ->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        // Tenter de créer un autre utilisateur avec le même email
        $userData = [
            'email' => 'existing@test.com',
            'password' => 'password123',
            'firstName' => 'Autre',
            'lastName' => 'User',
            'onboardingType' => 'member',
            'dateOfBirth' => '1985-03-20'
        ];

        $this->unauthenticatedRequest('POST', '/api/register', $userData);
        
        $responseData = $this->assertJsonResponse(409);
        $this->assertStringContainsString('déjà utilisé', $responseData['error']);
    }

    public function testUserLogin(): void
    {
        // Créer un utilisateur de test avec authentification
        $user = $this->createTestUser('login@test.com', ['ROLE_USER'], [
            'firstName' => 'Login',
            'lastName' => 'Test',
            'dateOfBirth' => '1992-08-10'
        ]);
        
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail('login@test.com')
                 ->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        // Données de connexion
        $loginData = [
            'email' => 'login@test.com',
            'password' => 'password123'
        ];

        // Test de connexion
        $this->unauthenticatedRequest('POST', '/api/login', $loginData);
        
        $responseData = $this->assertJsonResponse(200);
        
        // Vérifier la présence des tokens JWT avec refresh token
        $this->assertArrayHasKey('accessToken', $responseData);
        $this->assertArrayHasKey('refreshToken', $responseData);
        $this->assertArrayHasKey('user', $responseData);
        
        // Vérifier les données utilisateur
        $userData = $responseData['user'];
        $this->assertEquals($user->getId(), $userData['id']);
        $this->assertEquals($user->getEmail(), $userData['email']);
        $this->assertEquals($user->getFirstName(), $userData['firstName']);
        
        // Vérifier que les tokens sont valides
        $accessToken = $responseData['accessToken'];
        $refreshToken = $responseData['refreshToken'];
        $this->assertIsString($accessToken);
        $this->assertNotEmpty($accessToken);
        $this->assertIsString($refreshToken);
        $this->assertNotEmpty($refreshToken);
        
        // Vérifier que le refresh token existe en base
        $refreshTokenInDb = $this->entityManager->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $refreshToken]);
        
        $this->assertNotNull($refreshTokenInDb);
        $this->assertTrue($refreshTokenInDb->isValid());
        
        // Tester l'utilisation du token pour une requête authentifiée
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $accessToken);
        $this->client->request('GET', '/api/profile');
        
        $this->assertJsonResponse(200);
    }

    public function testLoginWithInvalidCredentials(): void
    {
        // Créer un utilisateur avec authentification
        $user = $this->createTestUser('valid@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1988-12-25'
        ]);
        
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail('valid@test.com')
                 ->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        // Test avec email invalide
        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => 'inexistant@test.com',
            'password' => 'password123'
        ]);
        
        $responseData = $this->assertJsonResponse(401);
        $this->assertStringContainsString('Identifiants invalides', $responseData['error']);

        // Test avec mot de passe invalide
        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => 'valid@test.com',
            'password' => 'motdepasseincorrect'
        ]);
        
        $responseData = $this->assertJsonResponse(401);
        $this->assertStringContainsString('Identifiants invalides', $responseData['error']);
    }

    public function testLoginWithMissingFields(): void
    {
        // Test sans email
        $this->unauthenticatedRequest('POST', '/api/login', [
            'password' => 'password123'
        ]);
        
        $responseData = $this->assertJsonResponse(400);
        $this->assertStringContainsString('requis', $responseData['error']);

        // Test sans mot de passe
        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => 'test@test.com'
        ]);
        
        $responseData = $this->assertJsonResponse(400);
        $this->assertStringContainsString('requis', $responseData['error']);
    }

    public function testLoginWithInactiveUser(): void
    {
        // Créer un utilisateur inactif
        $user = $this->createTestUser('inactive@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1987-04-18'
        ]);
        $user->setIsActive(false);
        
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail('inactive@test.com')
                 ->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => 'inactive@test.com',
            'password' => 'password123'
        ]);
        
        $responseData = $this->assertJsonResponse(401);
        $this->assertStringContainsString('Identifiants invalides', $responseData['error']);
    }

    public function testRefreshToken(): void
    {
        // Créer un utilisateur et se connecter pour obtenir les tokens
        $user = $this->createTestUser('refresh@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1993-11-22'
        ]);
        
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail('refresh@test.com')
                 ->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        // Login pour obtenir les tokens initiaux
        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => 'refresh@test.com',
            'password' => 'password123'
        ]);
        
        $loginResponse = $this->assertJsonResponse(200);
        $originalAccessToken = $loginResponse['accessToken'];
        $originalRefreshToken = $loginResponse['refreshToken'];

        // Attendre 1 seconde pour s'assurer que les timestamps JWT sont différents
        sleep(1);

        // Test du refresh token avec le nouveau format
        $this->unauthenticatedRequest('POST', '/api/refresh-token', [
            'refreshToken' => $originalRefreshToken
        ]);
        
        $responseData = $this->assertJsonResponse(200);
        
        // Vérifier qu'on obtient de nouveaux tokens
        $this->assertArrayHasKey('accessToken', $responseData);
        $this->assertArrayHasKey('refreshToken', $responseData);
        $this->assertArrayHasKey('user', $responseData);
        
        $newAccessToken = $responseData['accessToken'];
        $newRefreshToken = $responseData['refreshToken'];
        
        $this->assertIsString($newAccessToken);
        $this->assertNotEmpty($newAccessToken);
        $this->assertIsString($newRefreshToken);
        $this->assertNotEmpty($newRefreshToken);
        
        // Vérifier que les tokens sont différents (rotation)
        $this->assertNotEquals($originalAccessToken, $newAccessToken, 'L\'access token doit être différent après refresh');
        $this->assertNotEquals($originalRefreshToken, $newRefreshToken, 'Le refresh token doit être différent (rotation)');
        
        // Décoder et comparer les timestamps des JWT pour s'assurer qu'ils sont différents
        $originalPayload = json_decode(base64_decode(explode('.', $originalAccessToken)[1]), true);
        $newPayload = json_decode(base64_decode(explode('.', $newAccessToken)[1]), true);
        
        $this->assertGreaterThan($originalPayload['iat'], $newPayload['iat'], 'Le nouveau token doit avoir un timestamp plus récent');
        
        // Vérifier que l'ancien refresh token est révoqué
        $oldToken = $this->entityManager->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $originalRefreshToken]);
        
        $this->assertNotNull($oldToken);
        $this->assertTrue($oldToken->isRevoked());
        
        // Vérifier que le nouveau refresh token est valide
        $newToken = $this->entityManager->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $newRefreshToken]);
        
        $this->assertNotNull($newToken);
        $this->assertTrue($newToken->isValid());
        
        // Vérifier que le nouveau access token fonctionne
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $newAccessToken);
        $this->client->request('GET', '/api/profile');
        
        $this->assertJsonResponse(200);
    }

    public function testRefreshTokenWithoutValidToken(): void
    {
        // Test refresh sans token
        $this->unauthenticatedRequest('POST', '/api/refresh-token', []);
        $responseData = $this->assertJsonResponse(400);
        $this->assertStringContainsString('requis', $responseData['error']);

        // Test refresh avec token invalide
        $this->unauthenticatedRequest('POST', '/api/refresh-token', [
            'refreshToken' => 'invalid-token-that-does-not-exist'
        ]);
        $responseData = $this->assertJsonResponse(401);
        $this->assertStringContainsString('invalide', $responseData['error']);
    }

    public function testRefreshTokenExpired(): void
    {
        $user = $this->createTestUser('expired@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1990-01-01'
        ]);

        // Créer un refresh token expiré
        $expiredToken = new RefreshToken();
        $expiredToken->setUser($user)
                    ->setToken('expired_token_123456')
                    ->setExpiresAt(new \DateTime('-1 day'))
                    ->setIsRevoked(false);

        $this->entityManager->persist($expiredToken);
        $this->entityManager->flush();

        // Essayer d'utiliser le token expiré
        $this->unauthenticatedRequest('POST', '/api/refresh-token', [
            'refreshToken' => 'expired_token_123456'
        ]);
        
        $responseData = $this->assertJsonResponse(401);
        $this->assertStringContainsString('invalide', $responseData['error']);
    }

    public function testProfile(): void
    {
        // Créer un utilisateur
        $user = $this->createTestUser('profile@test.com', ['ROLE_USER'], [
            'firstName' => 'Profile',
            'lastName' => 'Test',
            'phone' => '0123456789',
            'dateOfBirth' => '1991-07-30'
        ]);
        
        // Test de récupération du profil
        $this->authenticatedRequest('GET', '/api/profile', $user);
        
        $responseData = $this->assertJsonResponse(200);
        
        // Vérifications des données du profil
        $this->assertEquals($user->getId(), $responseData['id']);
        $this->assertEquals($user->getEmail(), $responseData['email']);
        $this->assertEquals($user->getFirstName(), $responseData['firstName']);
        $this->assertEquals($user->getLastName(), $responseData['lastName']);
        $this->assertEquals($user->getPhone(), $responseData['phone']);
        $this->assertEquals($user->getRoles(), $responseData['roles']);
        $this->assertArrayHasKey('createdAt', $responseData);
    }

    public function testUpdateProfile(): void
    {
        // Créer un utilisateur
        $user = $this->createTestUser('update@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1989-02-14'
        ]);
        
        // Données de mise à jour
        $updateData = [
            'firstName' => 'Nouveau Prénom',
            'lastName' => 'Nouveau Nom',
            'phone' => '0987654321'
        ];
        
        // Test de mise à jour du profil
        $this->authenticatedRequest('PUT', '/api/profile', $user, $updateData);
        
        $responseData = $this->assertJsonResponse(200);
        
        // Vérifications
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('user', $responseData);
        
        $userData = $responseData['user'];
        $this->assertEquals($updateData['firstName'], $userData['firstName']);
        $this->assertEquals($updateData['lastName'], $userData['lastName']);
        $this->assertEquals($updateData['phone'], $userData['phone']);
    }

    public function testLogout(): void
    {
        // Créer un utilisateur et se connecter pour obtenir un refresh token
        $user = $this->createTestUser('logout@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1986-09-03'
        ]);
        
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail('logout@test.com')
                 ->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        // Login pour obtenir les tokens
        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => 'logout@test.com',
            'password' => 'password123'
        ]);
        
        $loginResponse = $this->assertJsonResponse(200);
        $refreshToken = $loginResponse['refreshToken'];
        
        // Test de déconnexion avec refresh token
        $this->unauthenticatedRequest('POST', '/api/logout', [
            'refreshToken' => $refreshToken
        ]);
        
        $responseData = $this->assertJsonResponse(200);
        $this->assertStringContainsString('Déconnexion réussie', $responseData['message']);
        
        // Vérifier que le refresh token est révoqué
        $refreshTokenInDb = $this->entityManager->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $refreshToken]);
        
        $this->assertNotNull($refreshTokenInDb);
        $this->assertTrue($refreshTokenInDb->isRevoked());
        
        // Vérifier qu'on ne peut plus utiliser le refresh token
        $this->unauthenticatedRequest('POST', '/api/refresh-token', [
            'refreshToken' => $refreshToken
        ]);
        
        $this->assertJsonResponse(401);
    }

    public function testPasswordSecurity(): void
    {
        // Créer un utilisateur avec authentification
        $user = $this->createTestUser('security@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1994-01-12'
        ]);
        
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail('security@test.com')
                 ->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();
        
        // Vérifier que le mot de passe est hashé en base
        $userAuthFromDb = $this->entityManager->getRepository(UserAuthentication::class)
            ->findOneBy(['email' => 'security@test.com', 'provider' => AuthProvider::EMAIL]);
        
        $this->assertNotEquals('password123', $userAuthFromDb->getPassword());
        $this->assertTrue(password_verify('password123', $userAuthFromDb->getPassword()));
    }

    public function testJwtTokenStructure(): void
    {
        $user = $this->createTestUser('jwt@test.com', ['ROLE_USER', 'ROLE_MEMBER'], [
            'dateOfBirth' => '1996-03-28'
        ]);
        $token = $this->authenticateUser($user);
        
        // Vérifier la structure du JWT (3 parties séparées par des points)
        $tokenParts = explode('.', $token);
        $this->assertCount(3, $tokenParts);
        
        // Décoder le payload (sans vérification de signature pour le test)
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        
        $this->assertArrayHasKey('username', $payload);
        $this->assertArrayHasKey('roles', $payload);
        $this->assertArrayHasKey('exp', $payload);
        
        $this->assertEquals($user->getEmail(), $payload['username']);
        $this->assertContains('ROLE_USER', $payload['roles']);
        $this->assertContains('ROLE_MEMBER', $payload['roles']);
    }

    public function testAccessWithInvalidToken(): void
    {
        // Test avec un token malformé
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer invalid.token.here');
        $this->client->request('GET', '/api/profile');
        
        $this->assertJsonResponse(401);
    }

    public function testRoleBasedAccess(): void
    {
        // Test avec différents rôles
        $basicUser = $this->createTestUser('basic@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1990-06-10'
        ]);
        $clubOwner = $this->createTestUser('owner@test.com', ['ROLE_CLUB_OWNER'], [
            'dateOfBirth' => '1985-12-05'
        ]);
        $member = $this->createTestUser('member@test.com', ['ROLE_MEMBER'], [
            'dateOfBirth' => '1998-04-22'
        ]);

        // Tous les utilisateurs peuvent accéder à leur propre profil
        $this->assertUserCanAccess($basicUser, 'GET', '/api/profile');
        $this->assertUserCanAccess($clubOwner, 'GET', '/api/profile');
        $this->assertUserCanAccess($member, 'GET', '/api/profile');
    }

    public function testConcurrentLogins(): void
    {
        $user = $this->createTestUser('concurrent@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1992-10-17'
        ]);
        
        // Simuler plusieurs connexions simultanées
        $token1 = $this->authenticateUser($user);
        $token2 = $this->authenticateUser($user);
        
        // Les deux tokens doivent être valides
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token1);
        $this->client->request('GET', '/api/profile');
        $this->assertJsonResponse(200);
        
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token2);
        $this->client->request('GET', '/api/profile');
        $this->assertJsonResponse(200);
    }

    public function testRegistrationWithOwnerType(): void
    {
        // Test d'inscription avec le type "owner"
        $userData = [
            'email' => 'owner@test.com',
            'password' => 'password123',
            'firstName' => 'Club',
            'lastName' => 'Owner',
            'onboardingType' => 'owner',
            'dateOfBirth' => '1980-01-15'
        ];

        $this->unauthenticatedRequest('POST', '/api/register', $userData);
        
        $responseData = $this->assertJsonResponse(201);
        
        // Vérifier que l'utilisateur a le bon rôle
        $this->assertContains('ROLE_CLUB_OWNER', $responseData['user']['roles']);
        $this->assertEquals('owner', $responseData['user']['onboardingType']);
        
        // Vérifier la présence des tokens
        $this->assertArrayHasKey('accessToken', $responseData);
        $this->assertArrayHasKey('refreshToken', $responseData);
    }

    public function testRegistrationWithMemberType(): void
    {
        // Test d'inscription avec le type "member"
        $userData = [
            'email' => 'member@test.com',
            'password' => 'password123',
            'firstName' => 'Simple',
            'lastName' => 'Member',
            'onboardingType' => 'member',
            'dateOfBirth' => '2000-08-08'
        ];

        $this->unauthenticatedRequest('POST', '/api/register', $userData);
        
        $responseData = $this->assertJsonResponse(201);
        
        // Vérifier que l'utilisateur a le bon rôle
        $this->assertContains('ROLE_MEMBER', $responseData['user']['roles']);
        $this->assertEquals('member', $responseData['user']['onboardingType']);
        
        // Vérifier la présence des tokens
        $this->assertArrayHasKey('accessToken', $responseData);
        $this->assertArrayHasKey('refreshToken', $responseData);
    }

    public function testRegistrationWithValidAges(): void
    {
        // Test avec un utilisateur mineur
        $minorData = [
            'email' => 'minor@test.com',
            'password' => 'password123',
            'firstName' => 'Jeune',
            'lastName' => 'Athlete',
            'onboardingType' => 'member',
            'dateOfBirth' => '2010-05-20' // 14 ans en 2024
        ];

        $this->unauthenticatedRequest('POST', '/api/register', $minorData);
        $responseData = $this->assertJsonResponse(201);
        $this->assertArrayHasKey('accessToken', $responseData);
        $this->assertArrayHasKey('refreshToken', $responseData);

        // Test avec un utilisateur majeur
        $adultData = [
            'email' => 'adult@test.com',
            'password' => 'password123',
            'firstName' => 'Adulte',
            'lastName' => 'Athlete',
            'onboardingType' => 'member',
            'dateOfBirth' => '1985-11-30' // 39 ans en 2024
        ];

        $this->unauthenticatedRequest('POST', '/api/register', $adultData);
        $responseData = $this->assertJsonResponse(201);
        $this->assertArrayHasKey('accessToken', $responseData);
        $this->assertArrayHasKey('refreshToken', $responseData);
    }
} 