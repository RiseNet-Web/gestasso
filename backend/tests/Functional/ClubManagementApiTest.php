<?php

namespace App\Tests\Functional;

use App\Entity\Club;
use App\Entity\ClubManager;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ClubManagementApiTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        // Clean database
        $this->cleanDatabase();
    }

    /**
     * Test Scénario 1.1 : Création de club via API
     * Un propriétaire crée son club via l'API
     */
    public function testCreateClubViaApi(): void
    {
        // Given: Un utilisateur authentifié avec le rôle CLUB_OWNER
        $owner = $this->createUser('marc@test.com', 'Marc', 'Dubois', ['ROLE_CLUB_OWNER']);
        $token = $this->loginUser($owner);

        // When: POST /api/clubs avec les données du club
        $this->client->request('POST', '/api/clubs', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'name' => 'Racing Club Paris',
            'description' => 'Club de tennis parisien',
            'isPublic' => true,
            'allowJoinRequests' => true
        ]));

        // Then: Le club est créé avec succès
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Racing Club Paris', $responseData['name']);
        $this->assertEquals('Club de tennis parisien', $responseData['description']);
        $this->assertTrue($responseData['isPublic']);
        $this->assertTrue($responseData['allowJoinRequests']);
        $this->assertNotNull($responseData['id']);
        $this->assertNotNull($responseData['createdAt']);

        // Vérifier en base de données
        $club = $this->entityManager->getRepository(Club::class)->find($responseData['id']);
        $this->assertNotNull($club);
        $this->assertEquals($owner, $club->getOwner());
    }

    /**
     * Test Scénario 1.2 : Création de club avec upload de logo
     */
    public function testCreateClubWithLogo(): void
    {
        // Given: Un utilisateur authentifié et un fichier logo
        $owner = $this->createUser('marc@test.com', 'Marc', 'Dubois', ['ROLE_CLUB_OWNER']);
        $token = $this->loginUser($owner);

        // Créer un fichier temporaire pour le logo
        $logoPath = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($logoPath, 'fake image content');

        // When: POST avec multipart/form-data incluant le logo
        $this->client->request('POST', '/api/clubs', [
            'name' => 'Tennis Club Elite',
            'description' => 'Club premium',
            'isPublic' => false,
            'allowJoinRequests' => false
        ], [
            'logo' => new \Symfony\Component\HttpFoundation\File\UploadedFile(
                $logoPath,
                'logo.png',
                'image/png',
                null,
                true
            )
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);

        // Then: Le club est créé avec le logo
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Tennis Club Elite', $responseData['name']);
        $this->assertFalse($responseData['isPublic']);
        $this->assertFalse($responseData['allowJoinRequests']);
        $this->assertNotNull($responseData['imagePath']);

        // Cleanup
        unlink($logoPath);
    }

    /**
     * Test Scénario 1.3 : Échec création sans authorization
     */
    public function testCreateClubUnauthorized(): void
    {
        // When: Tentative de création sans authentification
        $this->client->request('POST', '/api/clubs', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'name' => 'Club Non Autorisé'
        ]));

        // Then: Erreur 401
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test Scénario 1.4 : Échec création avec données invalides
     */
    public function testCreateClubWithInvalidData(): void
    {
        // Given: Un utilisateur authentifié
        $owner = $this->createUser('marc@test.com', 'Marc', 'Dubois', ['ROLE_CLUB_OWNER']);
        $token = $this->loginUser($owner);

        // When: Tentative de création avec nom vide
        $this->client->request('POST', '/api/clubs', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'name' => '', // Nom vide
            'description' => 'Description'
        ]));

        // Then: Erreur 400
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }

    /**
     * Test Scénario 1.5 : Liste des clubs de l'utilisateur
     */
    public function testListUserClubs(): void
    {
        // Given: Un utilisateur avec plusieurs clubs (propriétaire + gestionnaire)
        $owner = $this->createUser('marc@test.com', 'Marc', 'Dubois', ['ROLE_CLUB_OWNER']);
        
        // Club dont il est propriétaire
        $ownedClub = $this->createClub('Club Propriétaire', $owner);
        
        // Club dont il est gestionnaire
        $managedClub = $this->createClub('Club Géré', $this->createUser('other@test.com', 'Other', 'Owner'));
        $this->createClubManager($managedClub, $owner);
        
        $token = $this->loginUser($owner);

        // When: GET /api/clubs
        $this->client->request('GET', '/api/clubs', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);

        // Then: Les deux clubs sont retournés
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
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

    /**
     * Test Scénario 1.6 : Clubs publics avec recherche et pagination
     */
    public function testListPublicClubsWithSearch(): void
    {
        // Given: Plusieurs clubs publics et privés
        $owner1 = $this->createUser('owner1@test.com', 'Owner1', 'Test');
        $owner2 = $this->createUser('owner2@test.com', 'Owner2', 'Test');
        
        $this->createClub('Tennis Club Paris', $owner1, true, true); // Public
        $this->createClub('Football Club Lyon', $owner2, true, true); // Public
        $this->createClub('Tennis Elite Privé', $owner1, false, false); // Privé
        
        // When: GET /api/clubs/public avec recherche "tennis"
        $this->client->request('GET', '/api/clubs/public', [
            'search' => 'tennis',
            'limit' => 10,
            'page' => 1
        ]);

        // Then: Seuls les clubs publics contenant "tennis" sont retournés
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('clubs', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);
        
        $this->assertCount(1, $responseData['clubs']); // Seul le Tennis Club Paris
        $this->assertEquals('Tennis Club Paris', $responseData['clubs'][0]['name']);
        
        $this->assertEquals(1, $responseData['pagination']['page']);
        $this->assertEquals(10, $responseData['pagination']['limit']);
    }

    /**
     * Test Scénario 1.7 : Mise à jour d'un club
     */
    public function testUpdateClub(): void
    {
        // Given: Un club existant et son propriétaire
        $owner = $this->createUser('marc@test.com', 'Marc', 'Dubois', ['ROLE_CLUB_OWNER']);
        $club = $this->createClub('Racing Club Paris', $owner);
        $token = $this->loginUser($owner);

        // When: PUT /api/clubs/{id}
        $this->client->request('PUT', "/api/clubs/{$club->getId()}", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'name' => 'Racing Club Paris International',
            'description' => 'Club de tennis international',
            'isPublic' => false,
            'allowJoinRequests' => false
        ]));

        // Then: Le club est mis à jour
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Racing Club Paris International', $responseData['name']);
        $this->assertEquals('Club de tennis international', $responseData['description']);
        $this->assertFalse($responseData['isPublic']);
        $this->assertFalse($responseData['allowJoinRequests']);
    }

    /**
     * Test Scénario 1.8 : Ajout d'un gestionnaire de club
     */
    public function testAddClubManager(): void
    {
        // Given: Un club, son propriétaire et un utilisateur à ajouter comme gestionnaire
        $owner = $this->createUser('marc@test.com', 'Marc', 'Dubois', ['ROLE_CLUB_OWNER']);
        $futureManager = $this->createUser('julie@test.com', 'Julie', 'Moreau', ['ROLE_USER']);
        $club = $this->createClub('Racing Club Paris', $owner);
        $token = $this->loginUser($owner);

        // When: POST /api/clubs/{id}/managers
        $this->client->request('POST', "/api/clubs/{$club->getId()}/managers", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'userId' => $futureManager->getId()
        ]));

        // Then: Le gestionnaire est ajouté
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($futureManager->getId(), $responseData['user']['id']);
        $this->assertEquals('Julie', $responseData['user']['firstName']);
        $this->assertEquals('Moreau', $responseData['user']['lastName']);
        $this->assertNotNull($responseData['createdAt']);

        // Vérifier en base de données
        $clubManager = $this->entityManager->getRepository(ClubManager::class)
            ->findOneBy(['club' => $club, 'user' => $futureManager]);
        $this->assertNotNull($clubManager);
    }

    /**
     * Test Scénario 1.9 : Suppression d'un club
     */
    public function testDeleteClub(): void
    {
        // Given: Un club et son propriétaire
        $owner = $this->createUser('marc@test.com', 'Marc', 'Dubois', ['ROLE_CLUB_OWNER']);
        $club = $this->createClub('Club à Supprimer', $owner);
        $token = $this->loginUser($owner);

        // When: DELETE /api/clubs/{id}
        $this->client->request('DELETE', "/api/clubs/{$club->getId()}", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);

        // Then: Le club est supprimé (marqué comme inactif)
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $responseData);

        // Vérifier que le club est marqué comme inactif
        $this->entityManager->refresh($club);
        $this->assertFalse($club->isActive());
    }

    // Helper methods

    private function createUser(string $email, string $firstName, string $lastName, array $roles = ['ROLE_USER']): User
    {
        $user = new User();
        $user->setEmail($email)
             ->setFirstName($firstName)
             ->setLastName($lastName)
             ->setRoles($roles);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createClub(string $name, User $owner, bool $isPublic = true, bool $allowJoinRequests = true): Club
    {
        $club = new Club();
        $club->setName($name)
             ->setOwner($owner)
             ->setIsPublic($isPublic)
             ->setAllowJoinRequests($allowJoinRequests)
             ->setIsActive(true);

        $this->entityManager->persist($club);
        $this->entityManager->flush();

        return $club;
    }

    private function createClubManager(Club $club, User $user): ClubManager
    {
        $clubManager = new ClubManager();
        $clubManager->setClub($club)->setUser($user);

        $this->entityManager->persist($clubManager);
        $this->entityManager->flush();

        return $clubManager;
    }

    private function loginUser(User $user): string
    {
        // Simuler la génération d'un token JWT
        // Dans un test réel, vous utiliseriez votre service JWT
        return 'fake_jwt_token_for_' . $user->getId();
    }

    private function cleanDatabase(): void
    {
        // Supprimer les données de test
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE club_manager CASCADE');
        $connection->executeStatement('TRUNCATE TABLE club CASCADE');
        $connection->executeStatement('TRUNCATE TABLE "user" CASCADE');
    }
} 