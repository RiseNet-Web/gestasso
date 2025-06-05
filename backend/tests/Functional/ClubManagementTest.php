<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use App\Entity\User;
use App\Entity\Club;
use App\Entity\Team;
use App\Entity\Season;
use App\Entity\ClubManager;

/**
 * Tests de gestion des clubs selon le persona Marc Dubois
 * Couvre la création, équipes, gestionnaires, saisons
 */
class ClubManagementTest extends ApiTestCase
{
    private User $marcDubois;
    private User $sophieMartin;
    private User $julieMoreau;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Créer les personas de test
        $this->marcDubois = $this->createTestUser(
            'marc.dubois@racingclub.com',
            ['ROLE_CLUB_OWNER'],
            ['firstName' => 'Marc', 'lastName' => 'Dubois']
        );

        $this->sophieMartin = $this->createTestUser(
            'sophie.martin@gestionnaire.com',
            ['ROLE_USER'],
            ['firstName' => 'Sophie', 'lastName' => 'Martin']
        );

        $this->julieMoreau = $this->createTestUser(
            'julie.moreau@coach.com',
            ['ROLE_COACH'],
            ['firstName' => 'Julie', 'lastName' => 'Moreau']
        );
    }

    public function testClubCreation(): void
    {
        // Données du club Racing Club Paris
        $clubData = [
            'name' => 'Racing Club Paris',
            'description' => 'Club de tennis parisien fondé en 1987',
            'address' => '123 Avenue des Sports, 75015 Paris',
            'phone' => '0145678900',
            'email' => 'contact@racingclub.com',
            'website' => 'https://racingclub.com'
        ];

        // Marc crée son club
        $this->authenticatedRequest('POST', '/api/clubs', $this->marcDubois, $clubData);
        
        $responseData = $this->assertJsonResponse(201);
        
        // Vérifications de la réponse
        $this->assertArrayHasKey('id', $responseData);
        $this->assertEquals($clubData['name'], $responseData['name']);
        $this->assertEquals($clubData['description'], $responseData['description']);
        $this->assertEquals($this->marcDubois->getId(), $responseData['owner']['id']);
        
        // Vérifier en base de données
        $club = $this->entityManager->getRepository(Club::class)
            ->find($responseData['id']);
        
        $this->assertNotNull($club);
        $this->assertEquals($clubData['name'], $club->getName());
        $this->assertEquals($this->marcDubois->getId(), $club->getOwner()->getId());
        $this->assertTrue($club->isActive());
    }

    public function testClubCreationWithLogo(): void
    {
        // Créer une image de test
        $testImagePath = $this->createTestImage();
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $testImagePath,
            'logo.jpg',
            'image/jpeg',
            null,
            true
        );
        
        // Préparer les données avec upload de fichier
        $formData = [
            'name' => 'Racing Club Paris',
            'description' => 'Club avec logo',
            'isPublic' => true,
            'allowJoinRequests' => true
        ];

        $files = [
            'logo' => $uploadedFile
        ];

        $this->authenticatedMultipartRequest('POST', '/api/clubs', $this->marcDubois, $formData, $files);
        
        $responseData = $this->assertJsonResponse(201);
        $this->assertArrayHasKey('imagePath', $responseData);
        $this->assertNotNull($responseData['imagePath']);
        $this->assertStringContainsString('/uploads/clubs/', $responseData['imagePath']);
        
        // Nettoyer
        if (file_exists($testImagePath)) {
            unlink($testImagePath);
        }
    }

    public function testClubLogoUpload(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        
        // Créer une image de test
        $testImagePath = $this->createTestImage();
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $testImagePath,
            'new-logo.jpg',
            'image/jpeg',
            null,
            true
        );
        
        $files = [
            'logo' => $uploadedFile
        ];

        $this->authenticatedMultipartRequest('POST', '/api/clubs/' . $club->getId() . '/logo', $this->marcDubois, [], $files);
        
        $responseData = $this->assertJsonResponse(200);
        $this->assertArrayHasKey('imagePath', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Logo uploadé avec succès', $responseData['message']);
        
        // Vérifier que le club a bien été mis à jour
        $this->authenticatedRequest('GET', '/api/clubs/' . $club->getId(), $this->marcDubois);
        $clubData = $this->assertJsonResponse(200);
        $this->assertNotNull($clubData['imagePath']);
        $this->assertStringContainsString('/uploads/clubs/', $clubData['imagePath']);
        
        // Nettoyer
        if (file_exists($testImagePath)) {
            unlink($testImagePath);
        }
    }

    public function testClubLogoUploadInvalidFile(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        
        // Créer un fichier texte au lieu d'une image
        $testTextFile = tempnam(sys_get_temp_dir(), 'test_text') . '.txt';
        file_put_contents($testTextFile, 'This is not an image');
        
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $testTextFile,
            'not-an-image.txt',
            'text/plain',
            null,
            true
        );
        
        $files = [
            'logo' => $uploadedFile
        ];

        $this->authenticatedMultipartRequest('POST', '/api/clubs/' . $club->getId() . '/logo', $this->marcDubois, [], $files);
        
        $this->assertErrorResponse(400, 'Type de fichier non autorisé');
        
        // Nettoyer
        if (file_exists($testTextFile)) {
            unlink($testTextFile);
        }
    }

    public function testClubLogoUploadTooLarge(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        
        // Créer une image très grande
        $testImagePath = $this->createTestImage(2500, 2100);
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $testImagePath,
            'huge-logo.jpg',
            'image/jpeg',
            null,
            true
        );
        
        $files = [
            'logo' => $uploadedFile
        ];

        $this->authenticatedMultipartRequest('POST', '/api/clubs/' . $club->getId() . '/logo', $this->marcDubois, [], $files);
        
        $this->assertErrorResponse(400, 'image est trop grande');
        
        // Nettoyer
        if (file_exists($testImagePath)) {
            unlink($testImagePath);
        }
    }

    public function testClubLogoUploadUnauthorized(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        
        // Créer une image de test
        $testImagePath = $this->createTestImage();
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $testImagePath,
            'logo.jpg',
            'image/jpeg',
            null,
            true
        );
        
        $files = [
            'logo' => $uploadedFile
        ];

        // Sophie (co-gestionnaire) ne peut pas uploader de logo sans permission d'édition
        $this->authenticatedMultipartRequest('POST', '/api/clubs/' . $club->getId() . '/logo', $this->sophieMartin, [], $files);
        
        $this->assertErrorResponse(403);
        
        // Nettoyer
        if (file_exists($testImagePath)) {
            unlink($testImagePath);
        }
    }

    public function testSeasonCreation(): void
    {
        // Créer d'abord un club
        $club = $this->createTestClub($this->marcDubois);

        // Créer la saison 2024-2025
        $seasonData = [
            'name' => '2024-2025',
            'startDate' => '2024-09-01',
            'endDate' => '2025-06-30',
            'isActive' => true,
            'club' => '/api/clubs/' . $club->getId()
        ];

        $this->authenticatedRequest('POST', '/api/seasons', $this->marcDubois, $seasonData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($seasonData['name'], $responseData['name']);
        $this->assertTrue($responseData['isActive']);
        
        // Vérifier en base
        $season = $this->entityManager->getRepository(Season::class)
            ->find($responseData['id']);
        
        $this->assertNotNull($season);
        $this->assertEquals($club->getId(), $season->getClub()->getId());
    }

    public function testTeamCreation(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        $season = $this->createTestSeason($club);

        // Créer l'équipe "Seniors Masculins"
        $teamData = [
            'name' => 'Seniors Masculins',
            'description' => 'Équipe principale masculine',
            'category' => 'senior',
            'gender' => 'male',
            'minBirthYear' => null, // Pas de limite haute d'âge  
            'maxBirthYear' => 2006, // Né en 2006 ou avant (18 ans min en 2024)
            'annualPrice' => 450.00,
            'maxMembers' => 25,
            'club' => '/api/clubs/' . $club->getId(),
            'season' => '/api/seasons/' . $season->getId()
        ];

        $this->authenticatedRequest('POST', '/api/teams', $this->marcDubois, $teamData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($teamData['name'], $responseData['name']);
        $this->assertEquals($teamData['annualPrice'], $responseData['annualPrice']);
        $this->assertEquals($teamData['category'], $responseData['category']);
        $this->assertNull($responseData['minBirthYear']);
        $this->assertEquals($teamData['maxBirthYear'], $responseData['maxBirthYear']);
        
        // Créer l'équipe "U18 Filles"
        $teamU18Data = [
            'name' => 'U18 Filles',
            'description' => 'Équipe jeunes filles',
            'category' => 'youth',
            'gender' => 'female',
            'minBirthYear' => 2006, // Né en 2006 ou après (18 ans max en 2024)
            'maxBirthYear' => 2012, // Né en 2012 ou avant (12 ans min en 2024)
            'annualPrice' => 320.00,
            'maxMembers' => 15,
            'club' => '/api/clubs/' . $club->getId(),
            'season' => '/api/seasons/' . $season->getId()
        ];

        $this->authenticatedRequest('POST', '/api/teams', $this->marcDubois, $teamU18Data);
        
        $responseData = $this->assertJsonResponse(201);
        $this->assertEquals($teamU18Data['minBirthYear'], $responseData['minBirthYear']);
        $this->assertEquals($teamU18Data['maxBirthYear'], $responseData['maxBirthYear']);
    }

    public function testTeamCreationWithInvalidAgeRestrictions(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        $season = $this->createTestSeason($club);

        // Tenter de créer une équipe avec des restrictions d'âge incohérentes
        $teamData = [
            'name' => 'Équipe invalide',
            'description' => 'Équipe avec restrictions d\'âge incohérentes',
            'category' => 'youth',
            'minBirthYear' => 2010, // Plus jeune que maxBirthYear
            'maxBirthYear' => 2005, // Plus âgé que minBirthYear (incohérent)
            'annualPrice' => 300.00,
            'club' => '/api/clubs/' . $club->getId(),
            'season' => '/api/seasons/' . $season->getId()
        ];

        $this->authenticatedRequest('POST', '/api/teams', $this->marcDubois, $teamData);
        
        $this->assertErrorResponse(400, 'restrictions d\'âge');
    }



    public function testAddCoManager(): void
    {
        $club = $this->createTestClub($this->marcDubois);

        // Marc invite Sophie comme co-gestionnaire
        $managerData = [
            'user' => '/api/users/' . $this->sophieMartin->getId(),
            'club' => '/api/clubs/' . $club->getId(),
            'role' => 'manager',
            'permissions' => ['manage_teams', 'view_finances', 'manage_documents']
        ];

        $this->authenticatedRequest('POST', '/api/club-managers', $this->marcDubois, $managerData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($this->sophieMartin->getId(), $responseData['user']['id']);
        $this->assertEquals('manager', $responseData['role']);
        $this->assertContains('manage_teams', $responseData['permissions']);
        
        // Vérifier en base
        $clubManager = $this->entityManager->getRepository(ClubManager::class)
            ->findOneBy(['user' => $this->sophieMartin, 'club' => $club]);
        
        $this->assertNotNull($clubManager);
        $this->assertEquals('manager', $clubManager->getRole());
    }

    public function testCoManagerPermissions(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        $this->addCoManager($club, $this->sophieMartin);

        // Sophie doit pouvoir créer des équipes
        $teamData = [
            'name' => 'Équipe créée par Sophie',
            'category' => 'youth',
            'club' => '/api/clubs/' . $club->getId()
        ];

        $this->authenticatedRequest('POST', '/api/teams', $this->sophieMartin, $teamData);
        $this->assertJsonResponse(201);

        // Mais ne peut pas supprimer le club
        $this->authenticatedRequest('DELETE', '/api/clubs/' . $club->getId(), $this->sophieMartin);
        $this->assertErrorResponse(403);
    }

    public function testAssignCoachToTeam(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        $season = $this->createTestSeason($club);
        $team = $this->createTestTeam($club, $season, 'U18 Filles', 320.00);

        // Marc assigne Julie comme coach de l'équipe U18 Filles
        $assignmentData = [
            'user' => '/api/users/' . $this->julieMoreau->getId(),
            'team' => '/api/teams/' . $team->getId(),
            'role' => 'coach',
            'assignedAt' => date('Y-m-d H:i:s')
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $assignmentData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($this->julieMoreau->getId(), $responseData['user']['id']);
        $this->assertEquals('coach', $responseData['role']);
        $this->assertEquals($team->getId(), $responseData['team']['id']);
    }

    public function testAddAthleteWithValidAge(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        $season = $this->createTestSeason($club);
        
        // Créer une équipe U18 avec restrictions d'âge
        $team = $this->createTestTeamWithAge($club, $season, 'U18 Filles', 320.00, 2006, 2012);

        // Créer une athlète de 16 ans (née en 2008)
        $validAthlete = $this->createTestUser(
            'valid.athlete@test.com',
            ['ROLE_ATHLETE'],
            [
                'firstName' => 'Valid',
                'lastName' => 'Athlete',
                'dateOfBirth' => '2008-06-15' // 16 ans
            ]
        );

        // Marc ajoute l'athlète à l'équipe
        $memberData = [
            'user' => '/api/users/' . $validAthlete->getId(),
            'team' => '/api/teams/' . $team->getId(),
            'role' => 'athlete'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $memberData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($validAthlete->getId(), $responseData['user']['id']);
        $this->assertEquals('athlete', $responseData['role']);
    }

    public function testAddAthleteWithInvalidAge(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        $season = $this->createTestSeason($club);
        
        // Créer une équipe U18 avec restrictions d'âge (12-18 ans)
        $team = $this->createTestTeamWithAge($club, $season, 'U18 Filles', 320.00, 2006, 2012);

        // Créer une athlète trop jeune (9 ans, née en 2015)
        $tooYoungAthlete = $this->createTestUser(
            'tooyoung.athlete@test.com',
            ['ROLE_ATHLETE'],
            [
                'firstName' => 'TooYoung',
                'lastName' => 'Athlete',
                'dateOfBirth' => '2015-03-10' // 9 ans, trop jeune
            ]
        );

        // Marc tente d'ajouter l'athlète à l'équipe
        $memberData = [
            'user' => '/api/users/' . $tooYoungAthlete->getId(),
            'team' => '/api/teams/' . $team->getId(),
            'role' => 'athlete'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $memberData);
        
        $this->assertErrorResponse(400, 'trop jeune');
    }

    public function testAddAthleteWithoutDateOfBirth(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        $season = $this->createTestSeason($club);
        
        // Créer une équipe avec restrictions d'âge
        $team = $this->createTestTeamWithAge($club, $season, 'U18 Filles', 320.00, 2006, 2012);

        // Créer un utilisateur sans date de naissance
        $athleteNoAge = $this->createTestUser(
            'noage.athlete@test.com',
            ['ROLE_ATHLETE'],
            [
                'firstName' => 'NoAge',
                'lastName' => 'Athlete'
                // Pas de dateOfBirth
            ]
        );

        // Marc tente d'ajouter l'athlète à l'équipe
        $memberData = [
            'user' => '/api/users/' . $athleteNoAge->getId(),
            'team' => '/api/teams/' . $team->getId(),
            'role' => 'athlete'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $memberData);
        
        $this->assertErrorResponse(400, 'date de naissance obligatoire');
    }

    public function testCoachNotSubjectToAgeRestrictions(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        $season = $this->createTestSeason($club);
        
        // Créer une équipe U18 avec restrictions d'âge
        $team = $this->createTestTeamWithAge($club, $season, 'U18 Filles', 320.00, 2006, 2012);

        // Créer un coach de 40 ans (hors de la tranche d'âge de l'équipe)
        $oldCoach = $this->createTestUser(
            'old.coach@test.com',
            ['ROLE_COACH'],
            [
                'firstName' => 'Old',
                'lastName' => 'Coach',
                'dateOfBirth' => '1984-01-01' // 40 ans
            ]
        );

        // Marc assigne le coach (doit fonctionner malgré l'âge)
        $memberData = [
            'user' => '/api/users/' . $oldCoach->getId(),
            'team' => '/api/teams/' . $team->getId(),
            'role' => 'coach'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $memberData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($oldCoach->getId(), $responseData['user']['id']);
        $this->assertEquals('coach', $responseData['role']);
    }

    public function testClubInformation(): void
    {
        $club = $this->createTestClub($this->marcDubois);

        // Marc consulte les informations de son club
        $this->authenticatedRequest('GET', '/api/clubs/' . $club->getId(), $this->marcDubois);
        
        $responseData = $this->assertJsonResponse(200);
        
        $this->assertEquals($club->getName(), $responseData['name']);
        $this->assertEquals($this->marcDubois->getId(), $responseData['owner']['id']);
        $this->assertArrayHasKey('teams', $responseData);
        $this->assertArrayHasKey('seasons', $responseData);
        $this->assertArrayHasKey('managers', $responseData);
    }

    public function testOnlyOwnerCanDeleteClub(): void
    {
        $club = $this->createTestClub($this->marcDubois);
        $this->addCoManager($club, $this->sophieMartin);

        // Sophie ne peut pas supprimer le club
        $this->authenticatedRequest('DELETE', '/api/clubs/' . $club->getId(), $this->sophieMartin);
        $this->assertErrorResponse(403);

        // Mais Marc peut le supprimer
        $this->authenticatedRequest('DELETE', '/api/clubs/' . $club->getId(), $this->marcDubois);
        $this->assertJsonResponse(204);
    }

    public function testClubDataIsolation(): void
    {
        // Créer deux clubs différents
        $club1 = $this->createTestClub($this->marcDubois);
        
        $otherOwner = $this->createTestUser('other@owner.com', ['ROLE_CLUB_OWNER']);
        $club2 = $this->createTestClub($otherOwner);

        // Marc ne peut pas accéder au club de l'autre propriétaire
        $this->authenticatedRequest('GET', '/api/clubs/' . $club2->getId(), $this->marcDubois);
        $this->assertErrorResponse(403);

        // Marc peut accéder à son propre club
        $this->authenticatedRequest('GET', '/api/clubs/' . $club1->getId(), $this->marcDubois);
        $this->assertJsonResponse(200);
    }

    public function testMultipleActiveSeasons(): void
    {
        $club = $this->createTestClub($this->marcDubois);

        // Créer deux saisons actives simultanément (cas d'erreur)
        $season1Data = [
            'name' => '2024-2025',
            'startDate' => '2024-09-01',
            'endDate' => '2025-06-30',
            'isActive' => true,
            'club' => '/api/clubs/' . $club->getId()
        ];

        $this->authenticatedRequest('POST', '/api/seasons', $this->marcDubois, $season1Data);
        $this->assertJsonResponse(201);

        // Tenter de créer une deuxième saison active
        $season2Data = [
            'name' => '2025-2026',
            'startDate' => '2025-09-01',
            'endDate' => '2026-06-30',
            'isActive' => true,
            'club' => '/api/clubs/' . $club->getId()
        ];

        $this->authenticatedRequest('POST', '/api/seasons', $this->marcDubois, $season2Data);
        $this->assertErrorResponse(400, 'saison active');
    }

    // Méthodes helper pour créer les entités de test

    private function createTestClub(User $owner): Club
    {
        $club = new Club();
        $club->setName('Test Club');
        $club->setDescription('Club de test');
        $club->setOwner($owner);
        $club->setIsActive(true);
        
        $this->entityManager->persist($club);
        $this->entityManager->flush();
        
        return $club;
    }

    private function createTestSeason(Club $club): Season
    {
        $season = new Season();
        $season->setName('2024-2025');
        $season->setStartDate(new \DateTime('2024-09-01'));
        $season->setEndDate(new \DateTime('2025-06-30'));
        $season->setIsActive(true);
        $season->setClub($club);
        
        $this->entityManager->persist($season);
        $this->entityManager->flush();
        
        return $season;
    }

    private function createTestTeam(Club $club, Season $season, string $name, float $price): Team
    {
        $team = new Team();
        $team->setName($name);
        $team->setClub($club);
        $team->setSeason($season);
        $team->setAnnualPrice($price);
        $team->setCategory('senior');
        $team->setGender('mixed');
        $team->setMaxMembers(20);
        
        $this->entityManager->persist($team);
        $this->entityManager->flush();
        
        return $team;
    }

    private function createTestTeamWithAge(Club $club, Season $season, string $name, float $price, ?int $minBirthYear, ?int $maxBirthYear): Team
    {
        $team = new Team();
        $team->setName($name);
        $team->setClub($club);
        $team->setSeason($season);
        $team->setAnnualPrice($price);
        $team->setCategory('youth');
        $team->setGender('female');
        $team->setMaxMembers(15);
        $team->setMinBirthYear($minBirthYear);
        $team->setMaxBirthYear($maxBirthYear);
        
        $this->entityManager->persist($team);
        $this->entityManager->flush();
        
        return $team;
    }

    private function addCoManager(Club $club, User $user): ClubManager
    {
        $manager = new ClubManager();
        $manager->setClub($club);
        $manager->setUser($user);
        $manager->setRole('manager');
        $manager->setPermissions(['manage_teams', 'view_finances']);
        
        $this->entityManager->persist($manager);
        $this->entityManager->flush();
        
        return $manager;
    }
} 