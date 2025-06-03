<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use App\Entity\User;
use App\Entity\Club;
use App\Entity\Team;
use App\Entity\Season;

/**
 * Tests de validation d'âge pour l'adhésion aux équipes
 * Couvre la date de naissance des utilisateurs et les conditions d'âge des équipes
 */
class AgeValidationTest extends ApiTestCase
{
    private User $marcDubois;   // Gestionnaire de club
    private Club $club;
    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->marcDubois = $this->createTestUser(
            'marc.dubois@racingclub.com',
            ['ROLE_CLUB_OWNER'],
            ['firstName' => 'Marc', 'lastName' => 'Dubois']
        );

        $this->club = $this->createTestClub($this->marcDubois);
        $this->season = $this->createTestSeason($this->club);
    }

    public function testUserCreationWithDateOfBirth(): void
    {
        // Créer un utilisateur avec une date de naissance
        $userData = [
            'email' => 'emma.leblanc@test.com',
            'password' => 'password123',
            'firstName' => 'Emma',
            'lastName' => 'Leblanc',
            'dateOfBirth' => '2006-03-15' // 18 ans en 2024
        ];

        $this->unauthenticatedRequest('POST', '/api/users', $userData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($userData['email'], $responseData['email']);
        $this->assertEquals($userData['dateOfBirth'], $responseData['dateOfBirth']);
        
        // Vérifier en base de données
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $userData['email']]);
        
        $this->assertNotNull($user);
        $this->assertNotNull($user->getDateOfBirth());
        $this->assertEquals('2006-03-15', $user->getDateOfBirth()->format('Y-m-d'));
    }

    public function testUserCreationWithInvalidDateOfBirth(): void
    {
        // Test avec une date de naissance invalide
        $userData = [
            'email' => 'invalid@test.com',
            'password' => 'password123',
            'firstName' => 'Test',
            'lastName' => 'User',
            'dateOfBirth' => 'date-invalide'
        ];

        $this->unauthenticatedRequest('POST', '/api/users', $userData);
        
        $this->assertErrorResponse(400, 'date de naissance');
    }

    public function testUserCreationWithFutureDateOfBirth(): void
    {
        // Test avec une date de naissance dans le futur
        $userData = [
            'email' => 'future@test.com',
            'password' => 'password123',
            'firstName' => 'Future',
            'lastName' => 'User',
            'dateOfBirth' => '2030-01-01'
        ];

        $this->unauthenticatedRequest('POST', '/api/users', $userData);
        
        $this->assertErrorResponse(400, 'futur');
    }

    public function testTeamCreationWithAgeRestrictions(): void
    {
        // Créer une équipe U18 avec restrictions d'âge
        $teamData = [
            'name' => 'U18 Filles',
            'description' => 'Équipe pour les filles de 12 à 18 ans',
            'category' => 'youth',
            'gender' => 'female',
            'minBirthYear' => 2006, // Né en 2006 ou après (18 ans max en 2024)
            'maxBirthYear' => 2012, // Né en 2012 ou avant (12 ans min en 2024)
            'annualPrice' => 320.00,
            'maxMembers' => 15,
            'club' => '/api/clubs/' . $this->club->getId(),
            'season' => '/api/seasons/' . $this->season->getId()
        ];

        $this->authenticatedRequest('POST', '/api/teams', $this->marcDubois, $teamData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($teamData['name'], $responseData['name']);
        $this->assertEquals($teamData['minBirthYear'], $responseData['minBirthYear']);
        $this->assertEquals($teamData['maxBirthYear'], $responseData['maxBirthYear']);
    }

    public function testTeamCreationWithSeniorsRestrictions(): void
    {
        // Créer une équipe Seniors avec restriction d'âge minimum
        $teamData = [
            'name' => 'Seniors Masculins',
            'description' => 'Équipe pour les hommes de 18 ans et plus',
            'category' => 'senior',
            'gender' => 'male',
            'minBirthYear' => null, // Pas de limite haute d'âge
            'maxBirthYear' => 2006, // Né en 2006 ou avant (18 ans min en 2024)
            'annualPrice' => 450.00,
            'maxMembers' => 25,
            'club' => '/api/clubs/' . $this->club->getId(),
            'season' => '/api/seasons/' . $this->season->getId()
        ];

        $this->authenticatedRequest('POST', '/api/teams', $this->marcDubois, $teamData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($teamData['name'], $responseData['name']);
        $this->assertNull($responseData['minBirthYear']);
        $this->assertEquals($teamData['maxBirthYear'], $responseData['maxBirthYear']);
    }

    public function testValidAgeAthleteJoinsTeam(): void
    {
        // Créer une équipe U18
        $team = $this->createTestTeamWithAgeRestrictions(
            'U18 Filles',
            2006, // Min 18 ans
            2012  // Max 12 ans
        );

        // Créer une athlète de 16 ans (née en 2008)
        $emma = $this->createTestUser(
            'emma.leblanc@athlete.com',
            ['ROLE_ATHLETE'],
            [
                'firstName' => 'Emma',
                'lastName' => 'Leblanc',
                'dateOfBirth' => '2008-05-20' // 16 ans, dans la tranche d'âge
            ]
        );

        // Emma demande à rejoindre l'équipe
        $joinRequestData = [
            'user' => '/api/users/' . $emma->getId(),
            'team' => '/api/teams/' . $team->getId(),
            'role' => 'athlete',
            'message' => 'Je souhaite rejoindre l\'équipe U18'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $joinRequestData);
        
        $this->assertJsonResponse(201);
    }

    public function testTooYoungAthleteCannotJoinTeam(): void
    {
        // Créer une équipe U18 (12-18 ans)
        $team = $this->createTestTeamWithAgeRestrictions(
            'U18 Filles',
            2006, // Min 18 ans
            2012  // Max 12 ans
        );

        // Créer une athlète trop jeune (10 ans, née en 2014)
        $youngAthlete = $this->createTestUser(
            'young@athlete.com',
            ['ROLE_ATHLETE'],
            [
                'firstName' => 'Young',
                'lastName' => 'Athlete',
                'dateOfBirth' => '2014-08-10' // 10 ans, trop jeune
            ]
        );

        // Tentative d'ajout à l'équipe
        $joinRequestData = [
            'user' => '/api/users/' . $youngAthlete->getId(),
            'team' => '/api/teams/' . $team->getId(),
            'role' => 'athlete'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $joinRequestData);
        
        $this->assertErrorResponse(400, 'trop jeune');
    }

    public function testTooOldAthleteCannotJoinTeam(): void
    {
        // Créer une équipe U18 (12-18 ans)
        $team = $this->createTestTeamWithAgeRestrictions(
            'U18 Filles',
            2006, // Min 18 ans
            2012  // Max 12 ans
        );

        // Créer une athlète trop âgée (25 ans, née en 1999)
        $oldAthlete = $this->createTestUser(
            'old@athlete.com',
            ['ROLE_ATHLETE'],
            [
                'firstName' => 'Old',
                'lastName' => 'Athlete',
                'dateOfBirth' => '1999-12-01' // 25 ans, trop âgée
            ]
        );

        // Tentative d'ajout à l'équipe
        $joinRequestData = [
            'user' => '/api/users/' . $oldAthlete->getId(),
            'team' => '/api/teams/' . $team->getId(),
            'role' => 'athlete'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $joinRequestData);
        
        $this->assertErrorResponse(400, 'trop âgé');
    }

    public function testAthleteWithoutDateOfBirthCannotJoinRestrictedTeam(): void
    {
        // Créer une équipe avec restrictions d'âge
        $team = $this->createTestTeamWithAgeRestrictions(
            'U18 Filles',
            2006,
            2012
        );

        // Créer un utilisateur sans date de naissance
        $userWithoutAge = $this->createTestUser(
            'noage@athlete.com',
            ['ROLE_ATHLETE'],
            [
                'firstName' => 'NoAge',
                'lastName' => 'Athlete'
                // Pas de dateOfBirth
            ]
        );

        // Tentative d'ajout à l'équipe
        $joinRequestData = [
            'user' => '/api/users/' . $userWithoutAge->getId(),
            'team' => '/api/teams/' . $team->getId(),
            'role' => 'athlete'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $joinRequestData);
        
        $this->assertErrorResponse(400, 'date de naissance obligatoire');
    }

    public function testCoachesNotSubjectToAgeRestrictions(): void
    {
        // Créer une équipe U18
        $team = $this->createTestTeamWithAgeRestrictions(
            'U18 Filles',
            2006,
            2012
        );

        // Créer un coach de 35 ans (hors des restrictions d'âge de l'équipe)
        $coach = $this->createTestUser(
            'old.coach@test.com',
            ['ROLE_COACH'],
            [
                'firstName' => 'Old',
                'lastName' => 'Coach',
                'dateOfBirth' => '1989-07-15' // 35 ans
            ]
        );

        // Le coach peut être assigné malgré son âge
        $assignmentData = [
            'user' => '/api/users/' . $coach->getId(),
            'team' => '/api/teams/' . $team->getId(),
            'role' => 'coach'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $assignmentData);
        
        $this->assertJsonResponse(201);
    }

    public function testTeamWithoutAgeRestrictionsAcceptsAllAges(): void
    {
        // Créer une équipe sans restrictions d'âge
        $team = $this->createTestTeamWithAgeRestrictions(
            'Open Team',
            null, // Pas de restriction min
            null  // Pas de restriction max
        );

        // Créer des athlètes de différents âges
        $youngAthlete = $this->createTestUser(
            'very.young@test.com',
            ['ROLE_ATHLETE'],
            [
                'firstName' => 'Very',
                'lastName' => 'Young',
                'dateOfBirth' => '2015-01-01' // 9 ans
            ]
        );

        $oldAthlete = $this->createTestUser(
            'very.old@test.com',
            ['ROLE_ATHLETE'],
            [
                'firstName' => 'Very',
                'lastName' => 'Old',
                'dateOfBirth' => '1980-01-01' // 44 ans
            ]
        );

        // Les deux peuvent rejoindre l'équipe
        foreach ([$youngAthlete, $oldAthlete] as $athlete) {
            $joinRequestData = [
                'user' => '/api/users/' . $athlete->getId(),
                'team' => '/api/teams/' . $team->getId(),
                'role' => 'athlete'
            ];

            $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $joinRequestData);
            $this->assertJsonResponse(201);
        }
    }

    public function testAgeValidationOnBirthdayTransition(): void
    {
        // Test avec un athlète qui aura bientôt un anniversaire
        $team = $this->createTestTeamWithAgeRestrictions(
            'U18 Strict',
            2006, // Max 18 ans
            2012  // Min 12 ans
        );

        // Athlète qui fête ses 19 ans dans 1 mois (né en 2005)
        $almostTooOld = $this->createTestUser(
            'almost.old@test.com',
            ['ROLE_ATHLETE'],
            [
                'firstName' => 'Almost',
                'lastName' => 'TooOld',
                'dateOfBirth' => '2005-' . date('m-d', strtotime('+1 month')) // 18 ans actuellement, 19 dans 1 mois
            ]
        );

        // Devrait être refusé car déjà trop âgé selon l'année de naissance
        $joinRequestData = [
            'user' => '/api/users/' . $almostTooOld->getId(),
            'team' => '/api/teams/' . $team->getId(),
            'role' => 'athlete'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $this->marcDubois, $joinRequestData);
        
        $this->assertErrorResponse(400, 'trop âgé');
    }

    public function testTeamAgeInfoDisplay(): void
    {
        // Créer une équipe avec restrictions d'âge
        $team = $this->createTestTeamWithAgeRestrictions(
            'U16 Mixed',
            2008, // Max 16 ans
            2012  // Min 12 ans
        );

        // Consulter les informations de l'équipe
        $this->authenticatedRequest('GET', '/api/teams/' . $team->getId(), $this->marcDubois);
        
        $responseData = $this->assertJsonResponse(200);
        
        $this->assertEquals('U16 Mixed', $responseData['name']);
        $this->assertEquals(2008, $responseData['minBirthYear']);
        $this->assertEquals(2012, $responseData['maxBirthYear']);
        $this->assertArrayHasKey('ageRange', $responseData);
        $this->assertEquals('12-16 ans', $responseData['ageRange']);
    }

    // Méthodes helper

    private function createTestClub(User $owner): Club
    {
        $club = new Club();
        $club->setName('Test Club Age');
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

    private function createTestTeamWithAgeRestrictions(
        string $name, 
        ?int $minBirthYear, 
        ?int $maxBirthYear
    ): Team {
        $team = new Team();
        $team->setName($name);
        $team->setClub($this->club);
        $team->setSeason($this->season);
        $team->setAnnualPrice(300.00);
        $team->setCategory('youth');
        $team->setGender('mixed');
        $team->setMaxMembers(20);
        $team->setMinBirthYear($minBirthYear);
        $team->setMaxBirthYear($maxBirthYear);
        
        $this->entityManager->persist($team);
        $this->entityManager->flush();
        
        return $team;
    }
} 