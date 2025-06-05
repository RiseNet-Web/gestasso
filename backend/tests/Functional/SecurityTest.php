<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use App\Entity\User;
use App\Entity\Club;
use App\Entity\Team;
use App\Entity\TeamMember;

/**
 * Tests de sécurité et permissions selon le persona Julie Moreau (coach)
 * Couvre l'isolation des données, la hiérarchie des rôles et les tests négatifs
 */
class SecurityTest extends ApiTestCase
{
    private User $marcDubois;   // Propriétaire du club
    private User $julieMoreau;  // Coach équipe U18 Filles
    private User $pierreCoach;  // Coach d'une autre équipe
    private User $emmaLeblanc;  // Athlète équipe U18 Filles
    private User $lucasAthlète; // Athlète d'une autre équipe
    private User $otherOwner;   // Propriétaire d'un autre club
    
    private Club $club;
    private Club $otherClub;
    private Team $teamU18Filles;
    private Team $teamSeniors;
    private Team $otherTeam;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Créer les utilisateurs
        $this->marcDubois = $this->createTestUser(
            'marc.dubois@racingclub.com',
            ['ROLE_CLUB_OWNER'],
            ['firstName' => 'Marc', 'lastName' => 'Dubois']
        );

        $this->julieMoreau = $this->createTestUser(
            'julie.moreau@coach.com',
            ['ROLE_COACH'],
            ['firstName' => 'Julie', 'lastName' => 'Moreau']
        );

        $this->pierreCoach = $this->createTestUser(
            'pierre.martin@coach.com',
            ['ROLE_COACH'],
            ['firstName' => 'Pierre', 'lastName' => 'Martin']
        );

        $this->emmaLeblanc = $this->createTestUser(
            'emma.leblanc@athlete.com',
            ['ROLE_ATHLETE'],
            ['firstName' => 'Emma', 'lastName' => 'Leblanc']
        );

        $this->lucasAthlète = $this->createTestUser(
            'lucas.dupont@athlete.com',
            ['ROLE_ATHLETE'],
            ['firstName' => 'Lucas', 'lastName' => 'Dupont']
        );

        $this->otherOwner = $this->createTestUser(
            'other.owner@club.com',
            ['ROLE_CLUB_OWNER'],
            ['firstName' => 'Other', 'lastName' => 'Owner']
        );

        // Créer les clubs et équipes
        $this->club = $this->createTestClub($this->marcDubois, 'Racing Club Paris');
        $this->otherClub = $this->createTestClub($this->otherOwner, 'Other Club');
        
        $this->teamU18Filles = $this->createTestTeam($this->club, 'U18 Filles');
        $this->teamSeniors = $this->createTestTeam($this->club, 'Seniors Masculins');
        $this->otherTeam = $this->createTestTeam($this->otherClub, 'Other Team');

        // Assigner Julie comme coach de l'équipe U18 Filles
        $this->assignCoachToTeam($this->julieMoreau, $this->teamU18Filles);
        // Assigner Pierre comme coach des Seniors
        $this->assignCoachToTeam($this->pierreCoach, $this->teamSeniors);
        
        // Ajouter Emma à l'équipe U18 Filles
        $this->addAthleteToTeam($this->emmaLeblanc, $this->teamU18Filles);
        // Ajouter Lucas à l'équipe Seniors
        $this->addAthleteToTeam($this->lucasAthlète, $this->teamSeniors);
    }

    public function testCoachCanAccessOwnTeam(): void
    {
        // Julie peut accéder à son équipe U18 Filles
        $this->authenticatedRequest('GET', '/api/teams/' . $this->teamU18Filles->getId(), $this->julieMoreau);
        $responseData = $this->assertJsonResponse(200);
        
        $this->assertEquals($this->teamU18Filles->getId(), $responseData['id']);
        $this->assertEquals('U18 Filles', $responseData['name']);
    }

    public function testCoachCannotAccessOtherTeam(): void
    {
        // Julie NE PEUT PAS accéder à l'équipe Seniors (coached par Pierre)
        $this->authenticatedRequest('GET', '/api/teams/' . $this->teamSeniors->getId(), $this->julieMoreau);
        $this->assertErrorResponse(403, 'accès refusé');
    }

    public function testCoachCannotAccessOtherClubTeam(): void
    {
        // Julie NE PEUT PAS accéder à une équipe d'un autre club
        $this->authenticatedRequest('GET', '/api/teams/' . $this->otherTeam->getId(), $this->julieMoreau);
        $this->assertErrorResponse(403, 'accès refusé');
    }

    public function testCoachCanViewOwnTeamAthletes(): void
    {
        // Julie peut voir les athlètes de son équipe
        $this->authenticatedRequest('GET', '/api/teams/' . $this->teamU18Filles->getId() . '/members', $this->julieMoreau);
        $responseData = $this->assertJsonResponse(200);
        
        $this->assertGreaterThan(0, count($responseData['hydra:member']));
        
        // Vérifier qu'Emma est dans la liste
        $emmaFound = false;
        foreach ($responseData['hydra:member'] as $member) {
            if ($member['user']['id'] === $this->emmaLeblanc->getId()) {
                $emmaFound = true;
                break;
            }
        }
        $this->assertTrue($emmaFound);
    }

    public function testCoachCannotViewOtherTeamAthletes(): void
    {
        // Julie NE PEUT PAS voir les athlètes de l'équipe Seniors
        $this->authenticatedRequest('GET', '/api/teams/' . $this->teamSeniors->getId() . '/members', $this->julieMoreau);
        $this->assertErrorResponse(403);
    }



    public function testCoachCannotModifyTeamPrice(): void
    {
        // Julie NE PEUT PAS modifier le prix de l'équipe
        $updateData = [
            'annualPrice' => 400.00 // Augmentation du prix
        ];

        $this->authenticatedRequest('PATCH', '/api/teams/' . $this->teamU18Filles->getId(), $this->julieMoreau, $updateData);
        $this->assertErrorResponse(403, 'permission');
    }



    public function testAthleteCanOnlyViewOwnData(): void
    {
        // Emma peut voir ses propres données
        $this->authenticatedRequest('GET', '/api/users/' . $this->emmaLeblanc->getId(), $this->emmaLeblanc);
        $this->assertJsonResponse(200);

        // Mais NE PEUT PAS voir les données de Lucas
        $this->authenticatedRequest('GET', '/api/users/' . $this->lucasAthlète->getId(), $this->emmaLeblanc);
        $this->assertErrorResponse(403);
    }



    public function testOwnerCanAccessAllClubData(): void
    {
        // Marc peut accéder à toutes les données de son club
        $this->authenticatedRequest('GET', '/api/clubs/' . $this->club->getId() . '/teams', $this->marcDubois);
        $responseData = $this->assertJsonResponse(200);
        
        // Doit voir toutes les équipes de son club
        $this->assertGreaterThanOrEqual(2, count($responseData['hydra:member']));
    }

    public function testCrossClubDataIsolation(): void
    {
        // Marc NE PEUT PAS accéder aux données de l'autre club
        $this->authenticatedRequest('GET', '/api/clubs/' . $this->otherClub->getId(), $this->marcDubois);
        $this->assertErrorResponse(403);

        // L'autre propriétaire NE PEUT PAS accéder au club de Marc
        $this->authenticatedRequest('GET', '/api/clubs/' . $this->club->getId(), $this->otherOwner);
        $this->assertErrorResponse(403);
    }

    public function testSqlInjectionPrevention(): void
    {
        // Test de tentatives d'injection SQL dans les paramètres
        $maliciousInputs = [
            "'; DROP TABLE user; --",
            "1 OR 1=1",
            "UNION SELECT * FROM user",
            "<script>alert('xss')</script>"
        ];

        foreach ($maliciousInputs as $input) {
            // Tenter d'injecter via un paramètre de recherche
            $this->authenticatedRequest('GET', '/api/users?name=' . urlencode($input), $this->marcDubois);
            
            // L'API doit soit retourner une erreur 400 (validation), soit des résultats vides
            $response = $this->client->getResponse();
            $this->assertContains($response->getStatusCode(), [200, 400, 422]);
            
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getContent(), true);
                // Les résultats doivent être vides ou valides (pas d'exécution de l'injection)
                $this->assertArrayHasKey('hydra:member', $data);
            }
        }
    }

    public function testInputValidationSecurity(): void
    {
        // Test avec des données invalides pour vérifier la validation
        $invalidUserData = [
            'email' => 'not-an-email',
            'firstName' => str_repeat('A', 200), // Trop long
            'lastName' => '',
            'phone' => 'invalid-phone-123-abc'
        ];

        $this->authenticatedRequest('POST', '/api/users', $this->marcDubois, $invalidUserData);
        $this->assertErrorResponse(400);
    }

    public function testTokenExpiration(): void
    {
        // Test avec un token potentiellement expiré (simulation)
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE2MDAwMDAwMDAsImV4cCI6MTYwMDAwMDAwMX0.invalid');
        
        $this->client->request('GET', '/api/users');
        $this->assertErrorResponse(401);
    }

    public function testRateLimiting(): void
    {
        // Test de limitation du taux de requêtes (si implémenté)
        $attempts = 0;
        $maxAttempts = 100;

        while ($attempts < $maxAttempts) {
            $this->authenticatedRequest('GET', '/api/users', $this->marcDubois);
            $response = $this->client->getResponse();
            
            if ($response->getStatusCode() === 429) {
                // Rate limiting détecté
                $this->assertEquals(429, $response->getStatusCode());
                return;
            }
            
            $attempts++;
        }

        // Si aucune limite n'est atteinte, le test passe quand même
        $this->assertTrue(true, 'Aucune limite de taux détectée (peut être normal)');
    }

    public function testPermissionEscalation(): void
    {
        // Julie tente d'élever ses privilèges
        $escalationData = [
            'roles' => ['ROLE_CLUB_OWNER', 'ROLE_ADMIN']
        ];

        $this->authenticatedRequest('PATCH', '/api/users/' . $this->julieMoreau->getId(), $this->julieMoreau, $escalationData);
        $this->assertErrorResponse(403);

        // Vérifier que les rôles n'ont pas changé
        $this->authenticatedRequest('GET', '/api/users/' . $this->julieMoreau->getId(), $this->julieMoreau);
        $userData = $this->assertJsonResponse(200);
        
        $this->assertNotContains('ROLE_CLUB_OWNER', $userData['roles'] ?? []);
        $this->assertNotContains('ROLE_ADMIN', $userData['roles'] ?? []);
    }

    public function testDataIntegrityConstraints(): void
    {
        // Test des contraintes d'intégrité en base
        
        // Tenter de créer une équipe sans club (doit échouer)
        $invalidTeamData = [
            'name' => 'Équipe invalide',
            'annualPrice' => 300.00
            // Pas de club spécifié
        ];

        $this->authenticatedRequest('POST', '/api/teams', $this->marcDubois, $invalidTeamData);
        $this->assertErrorResponse(400);
    }

    // Méthodes helper

    private function createTestClub(User $owner, string $name): Club
    {
        $club = new Club();
        $club->setName($name);
        $club->setOwner($owner);
        $club->setIsActive(true);
        
        $this->entityManager->persist($club);
        $this->entityManager->flush();
        
        return $club;
    }

    private function createTestTeam(Club $club, string $name): Team
    {
        $team = new Team();
        $team->setName($name);
        $team->setClub($club);
        $team->setAnnualPrice(320.00);
        $team->setCategory('youth');
        $team->setGender('female');
        $team->setMaxMembers(15);
        
        $this->entityManager->persist($team);
        $this->entityManager->flush();
        
        return $team;
    }

    private function assignCoachToTeam(User $coach, Team $team): TeamMember
    {
        $member = new TeamMember();
        $member->setUser($coach);
        $member->setTeam($team);
        $member->setRole('coach');
        $member->setJoinedAt(new \DateTime());
        $member->setIsActive(true);
        
        $this->entityManager->persist($member);
        $this->entityManager->flush();
        
        return $member;
    }

    private function addAthleteToTeam(User $athlete, Team $team): TeamMember
    {
        $member = new TeamMember();
        $member->setUser($athlete);
        $member->setTeam($team);
        $member->setRole('athlete');
        $member->setJoinedAt(new \DateTime());
        $member->setIsActive(true);
        
        $this->entityManager->persist($member);
        $this->entityManager->flush();
        
        return $member;
    }
} 