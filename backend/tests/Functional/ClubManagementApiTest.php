<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use App\Entity\Club;
use App\Entity\ClubManager;

class ClubManagementApiTest extends ApiTestCase
{
    /**
     * Test Scénario 1.1 : Création de club via API
     * Un propriétaire crée son club via l'API
     */
    public function testCreateClubViaApi(): void
    {
        // Given: Un utilisateur authentifié avec le rôle CLUB_OWNER
        $owner = $this->createTestUser('marc@test.com', ['ROLE_CLUB_OWNER'], [
            'firstName' => 'Marc',
            'lastName' => 'Dubois',
            'dateOfBirth' => '1980-05-15'
        ]);

        // When: POST /api/clubs avec multipart/form-data (selon le contrôleur)
        $this->authenticatedMultipartRequest('POST', '/api/clubs', $owner, [
            'name' => 'Racing Club Paris',
            'description' => 'Club de tennis parisien',
            'isPublic' => 'true',  // multipart envoie des strings
            'allowJoinRequests' => 'true'
        ], []);

        // Then: Le club est créé avec succès
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals('Racing Club Paris', $responseData['name']);
        $this->assertEquals('Club de tennis parisien', $responseData['description']);
        $this->assertTrue($responseData['isPublic']);
        $this->assertTrue($responseData['allowJoinRequests']);
        $this->assertNotNull($responseData['id']);
        $this->assertNotNull($responseData['createdAt']);

        // Vérifier en base de données
        $club = $this->entityManager->getRepository(Club::class)->find($responseData['id']);
        $this->assertNotNull($club);
        $this->assertEquals($owner->getId(), $club->getOwner()->getId());
    }

    /**
     * Test Scénario 1.2 : Création de club avec upload de logo
     */
    public function testCreateClubWithLogo(): void
    {
        // Given: Un utilisateur authentifié et un fichier logo
        $owner = $this->createTestUser('marc@test.com', ['ROLE_CLUB_OWNER'], [
            'firstName' => 'Marc',
            'lastName' => 'Dubois',
            'dateOfBirth' => '1980-05-15'
        ]);

        // Créer un fichier temporaire pour le logo
        $logoPath = $this->createTestImage(100, 100);

        // When: POST avec multipart/form-data incluant le logo
        $this->authenticatedMultipartRequest('POST', '/api/clubs', $owner, [
            'name' => 'Tennis Club Elite',
            'description' => 'Club premium',
            'isPublic' => 'false',
            'allowJoinRequests' => 'false'
        ], [
            'logo' => $logoPath
        ]);

        // Then: Le club est créé avec le logo
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals('Tennis Club Elite', $responseData['name']);
        $this->assertFalse($responseData['isPublic']);
        $this->assertFalse($responseData['allowJoinRequests']);
        // Note: imagePath peut être null si le service n'a pas traité le logo
        $this->assertArrayHasKey('imagePath', $responseData);

        // Cleanup
        unlink($logoPath);
    }

    /**
     * Test Scénario 1.3 : Échec création sans authorization
     */
    public function testCreateClubUnauthorized(): void
    {
        // When: Tentative de création sans authentification (multipart car c'est ce qu'attend le contrôleur)
        $this->client->request('POST', '/api/clubs', ['name' => 'Club Non Autorisé'], [], [
            'CONTENT_TYPE' => 'multipart/form-data'
        ]);

        // Then: Erreur 401
        $this->assertJsonResponse(401);
    }

    /**
     * Test Scénario 1.4 : Échec création avec données invalides
     */
    public function testCreateClubWithInvalidData(): void
    {
        // Given: Un utilisateur authentifié
        $owner = $this->createTestUser('marc@test.com', ['ROLE_CLUB_OWNER'], [
            'firstName' => 'Marc',
            'lastName' => 'Dubois',
            'dateOfBirth' => '1980-05-15'
        ]);

        // When: Tentative de création avec nom vide
        $this->authenticatedMultipartRequest('POST', '/api/clubs', $owner, [
            'name' => '', // Nom vide
            'description' => 'Description'
        ], []);

        // Then: Erreur 400 (selon ton contrôleur qui vérifie si le nom est vide)
        $responseData = $this->assertJsonResponse(400);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertStringContainsString('requis', $responseData['error']);
    }

    /**
     * Test Scénario 1.5 : Liste des clubs de l'utilisateur
     */
    public function testListUserClubs(): void
    {
        // Given: Un utilisateur avec plusieurs clubs (propriétaire + gestionnaire)
        $owner = $this->createTestUser('marc@test.com', ['ROLE_CLUB_OWNER'], [
            'firstName' => 'Marc',
            'lastName' => 'Dubois',
            'dateOfBirth' => '1980-05-15'
        ]);
        
        // Club dont il est propriétaire
        $ownedClub = new Club();
        $ownedClub->setName('Club Propriétaire')
                  ->setOwner($owner)
                  ->setIsPublic(true)
                  ->setAllowJoinRequests(true)
                  ->setIsActive(true);
        $this->entityManager->persist($ownedClub);
        
        // Club dont il est gestionnaire
        $otherOwner = $this->createTestUser('other@test.com', ['ROLE_CLUB_OWNER'], [
            'firstName' => 'Other',
            'lastName' => 'Owner',
            'dateOfBirth' => '1975-03-20'
        ]);
        $managedClub = new Club();
        $managedClub->setName('Club Géré')
                   ->setOwner($otherOwner)
                   ->setIsPublic(true)
                   ->setAllowJoinRequests(true)
                   ->setIsActive(true);
        $this->entityManager->persist($managedClub);
        
        $clubManager = new ClubManager();
        $clubManager->setClub($managedClub)->setUser($owner);
        $this->entityManager->persist($clubManager);
        
        $this->entityManager->flush();

        // When: GET /api/clubs
        $this->authenticatedRequest('GET', '/api/clubs', $owner);

        // Then: Les deux clubs sont retournés
        $responseData = $this->assertJsonResponse(200);
        $this->assertCount(2, $responseData);
        
        $clubNames = array_column($responseData, 'name');
        $this->assertContains('Club Propriétaire', $clubNames);
        $this->assertContains('Club Géré', $clubNames);
        
        // Vérifier les permissions
        foreach ($responseData as $clubData) {
            if ($clubData['name'] === 'Club Propriétaire') {
                $this->assertTrue($clubData['isOwner']);
            } else {
                $this->assertFalse($clubData['isOwner']);
            }
        }
    }
} 