<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Entity\RefreshToken;
use App\Enum\AuthProvider;
use App\Service\RefreshTokenService;

/**
 * Tests du système de refresh tokens
 * Couvre la création, l'utilisation et la révocation des refresh tokens
 */
class RefreshTokenTest extends ApiTestCase
{
    private RefreshTokenService $refreshTokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshTokenService = static::getContainer()->get(RefreshTokenService::class);
    }

    public function testSuccessfulLogin(): void
    {
        // Créer un utilisateur avec authentification
        $user = $this->createTestUser('refresh@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1990-01-01'
        ]);
        
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail('refresh@test.com')
                 ->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        // Test de connexion
        $loginData = [
            'email' => 'refresh@test.com',
            'password' => 'password123'
        ];

        $this->unauthenticatedRequest('POST', '/api/login', $loginData);
        $responseData = $this->assertJsonResponse(200);
        
        // Vérifier la présence des tokens
        $this->assertArrayHasKey('accessToken', $responseData);
        $this->assertArrayHasKey('refreshToken', $responseData);
        $this->assertArrayHasKey('user', $responseData);
        
        // Vérifier que les tokens ne sont pas vides
        $this->assertNotEmpty($responseData['accessToken']);
        $this->assertNotEmpty($responseData['refreshToken']);
        
        // Vérifier que le refresh token existe en base
        $refreshToken = $this->entityManager->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $responseData['refreshToken']]);
        
        $this->assertNotNull($refreshToken);
        $this->assertEquals($user->getId(), $refreshToken->getUser()->getId());
        $this->assertTrue($refreshToken->isValid());
    }

    public function testRefreshTokenRotation(): void
    {
        // Créer un utilisateur et se connecter
        $user = $this->createTestUser('rotation@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1990-01-01'
        ]);
        
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail('rotation@test.com')
                 ->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        // Connexion initiale
        $loginData = [
            'email' => 'rotation@test.com',
            'password' => 'password123'
        ];

        $this->unauthenticatedRequest('POST', '/api/login', $loginData);
        $loginResponse = $this->assertJsonResponse(200);
        
        $originalRefreshToken = $loginResponse['refreshToken'];
        
        // Utiliser le refresh token
        $refreshData = ['refreshToken' => $originalRefreshToken];
        
        $this->unauthenticatedRequest('POST', '/api/refresh-token', $refreshData);
        $refreshResponse = $this->assertJsonResponse(200);
        
        // Vérifier qu'on a de nouveaux tokens
        $this->assertArrayHasKey('accessToken', $refreshResponse);
        $this->assertArrayHasKey('refreshToken', $refreshResponse);
        $this->assertNotEquals($originalRefreshToken, $refreshResponse['refreshToken']);
        
        // Vérifier que l'ancien refresh token est révoqué
        $oldToken = $this->entityManager->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $originalRefreshToken]);
        
        $this->assertTrue($oldToken->isRevoked());
        
        // Vérifier que le nouveau token est valide
        $newToken = $this->entityManager->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $refreshResponse['refreshToken']]);
        
        $this->assertNotNull($newToken);
        $this->assertTrue($newToken->isValid());
    }

    public function testRefreshTokenExpiration(): void
    {
        $user = $this->createTestUser('expired@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1990-01-01'
        ]);

        // Créer un refresh token expiré
        $expiredToken = new RefreshToken();
        $expiredToken->setUser($user)
                    ->setToken('expired_token_123')
                    ->setExpiresAt(new \DateTime('-1 day'))
                    ->setIsRevoked(false);

        $this->entityManager->persist($expiredToken);
        $this->entityManager->flush();

        // Essayer d'utiliser le token expiré
        $refreshData = ['refreshToken' => 'expired_token_123'];
        
        $this->unauthenticatedRequest('POST', '/api/refresh-token', $refreshData);
        $this->assertJsonResponse(401);
    }

    public function testRefreshTokenRevocation(): void
    {
        $user = $this->createTestUser('revoke@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1990-01-01'
        ]);

        // Créer un refresh token révoqué
        $revokedToken = new RefreshToken();
        $revokedToken->setUser($user)
                    ->setToken('revoked_token_123')
                    ->setExpiresAt(new \DateTime('+30 days'))
                    ->setIsRevoked(true);

        $this->entityManager->persist($revokedToken);
        $this->entityManager->flush();

        // Essayer d'utiliser le token révoqué
        $refreshData = ['refreshToken' => 'revoked_token_123'];
        
        $this->unauthenticatedRequest('POST', '/api/refresh-token', $refreshData);
        $this->assertJsonResponse(401);
    }

    public function testInvalidRefreshToken(): void
    {
        // Essayer d'utiliser un token inexistant
        $refreshData = ['refreshToken' => 'invalid_token_that_does_not_exist'];
        
        $this->unauthenticatedRequest('POST', '/api/refresh-token', $refreshData);
        $this->assertJsonResponse(401);
    }

    public function testLogout(): void
    {
        // Créer un utilisateur et se connecter
        $user = $this->createTestUser('logout@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1990-01-01'
        ]);
        
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail('logout@test.com')
                 ->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        // Connexion
        $loginData = [
            'email' => 'logout@test.com',
            'password' => 'password123'
        ];

        $this->unauthenticatedRequest('POST', '/api/login', $loginData);
        $loginResponse = $this->assertJsonResponse(200);
        
        $refreshToken = $loginResponse['refreshToken'];
        
        // Déconnexion
        $logoutData = ['refreshToken' => $refreshToken];
        
        $this->unauthenticatedRequest('POST', '/api/logout', $logoutData);
        $this->assertJsonResponse(200);
        
        // Vérifier que le token est révoqué
        $token = $this->entityManager->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $refreshToken]);
        
        $this->assertTrue($token->isRevoked());
        
        // Essayer d'utiliser le token révoqué
        $refreshData = ['refreshToken' => $refreshToken];
        
        $this->unauthenticatedRequest('POST', '/api/refresh-token', $refreshData);
        $this->assertJsonResponse(401);
    }

    public function testLogoutAllDevices(): void
    {
        $user = $this->createTestUser('logout_all@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1990-01-01'
        ]);
        
        $userAuth = new UserAuthentication();
        $userAuth->setUser($user)
                 ->setProvider(AuthProvider::EMAIL)
                 ->setEmail('logout_all@test.com')
                 ->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        
        $this->entityManager->persist($userAuth);
        $this->entityManager->flush();

        // Créer plusieurs refresh tokens pour simuler plusieurs appareils
        $tokens = [];
        for ($i = 0; $i < 3; $i++) {
            $token = $this->refreshTokenService->createRefreshToken($user);
            $tokens[] = $token->getToken();
        }

        // Se connecter pour obtenir un access token
        $accessToken = $this->authenticateUser($user);
        
        // Déconnexion de tous les appareils
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $accessToken);
        $this->client->request('POST', '/api/logout', [], [], [], json_encode(['allDevices' => true]));
        
        $this->assertJsonResponse(200);
        
        // Vérifier que tous les tokens sont révoqués
        foreach ($tokens as $tokenString) {
            $token = $this->entityManager->getRepository(RefreshToken::class)
                ->findOneBy(['token' => $tokenString]);
            
            $this->assertTrue($token->isRevoked());
        }
    }

    public function testTokenCleanup(): void
    {
        $user = $this->createTestUser('cleanup@test.com', ['ROLE_USER'], [
            'dateOfBirth' => '1990-01-01'
        ]);

        // Créer des tokens expirés et révoqués
        $expiredToken = new RefreshToken();
        $expiredToken->setUser($user)
                    ->setToken('expired_for_cleanup')
                    ->setExpiresAt(new \DateTime('-1 day'))
                    ->setIsRevoked(false);

        $revokedToken = new RefreshToken();
        $revokedToken->setUser($user)
                    ->setToken('revoked_for_cleanup')
                    ->setExpiresAt(new \DateTime('+30 days'))
                    ->setIsRevoked(true);

        $validToken = new RefreshToken();
        $validToken->setUser($user)
                  ->setToken('valid_token')
                  ->setExpiresAt(new \DateTime('+30 days'))
                  ->setIsRevoked(false);

        $this->entityManager->persist($expiredToken);
        $this->entityManager->persist($revokedToken);
        $this->entityManager->persist($validToken);
        $this->entityManager->flush();

        // Nettoyer les tokens
        $deletedCount = $this->refreshTokenService->cleanupExpiredTokens();
        
        // Vérifier que 2 tokens ont été supprimés (expiré + révoqué)
        $this->assertEquals(2, $deletedCount);
        
        // Vérifier que seul le token valide reste
        $remainingTokens = $this->entityManager->getRepository(RefreshToken::class)
            ->findBy(['user' => $user]);
        
        $this->assertCount(1, $remainingTokens);
        $this->assertEquals('valid_token', $remainingTokens[0]->getToken());
    }
} 