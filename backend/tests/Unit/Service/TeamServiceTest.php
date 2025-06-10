<?php

namespace App\Tests\Unit\Service;

use App\Entity\Club;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\User;
use App\Service\TeamService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TeamServiceTest extends TestCase
{
    private TeamService $teamService;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->teamService = new TeamService(
            $this->entityManager,
            $this->validator
        );
    }

    /**
     * Scénario 2.1 : Création d'équipe basique
     * Marc crée l'équipe "U18 Filles" dans son club
     */
    public function testCreateBasicTeam(): void
    {
        // Given: Un club et des données d'équipe
        $club = $this->createClub('Racing Club Paris');
        $season = $this->createSeason($club, '2023-2024');
        
        $teamData = [
            'name' => 'U18 Filles',
            'description' => 'Équipe des moins de 18 ans filles',
            'seasonId' => $season->getId(),
            'annualPrice' => 450.0
        ];

        // Mock season repository
        $seasonRepo = $this->createMock(\App\Repository\SeasonRepository::class);
        $seasonRepo->method('find')->with($season->getId())->willReturn($season);
        $this->entityManager->method('getRepository')->willReturn($seasonRepo);

        // Mock successful validation
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        
        // Mock entity manager
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // When: Création de l'équipe
        $team = $this->teamService->createTeam($club, $teamData);

        // Then: L'équipe est créée correctement
        $this->assertInstanceOf(Team::class, $team);
        $this->assertEquals('U18 Filles', $team->getName());
        $this->assertEquals('Équipe des moins de 18 ans filles', $team->getDescription());
        $this->assertEquals($club, $team->getClub());
        $this->assertEquals($season, $team->getSeason());
        $this->assertEquals('450', $team->getAnnualPrice());
    }

    /**
     * Scénario 2.2 : Création d'équipe avec données complètes
     */
    public function testCreateTeamWithFullData(): void
    {
        // Given: Un club et des données complètes d'équipe
        $club = $this->createClub('Racing Club Paris');
        $season = $this->createSeason($club, '2023-2024');
        
        $teamData = [
            'name' => 'U16 Garçons Compétition',
            'description' => 'Équipe compétition garçons moins de 16 ans',
            'seasonId' => $season->getId(),
            'annualPrice' => 650.0,
            'gender' => 'male',
            'ageRange' => 'U16',
            'minBirthYear' => 2008,
            'maxBirthYear' => 2010
        ];

        // Mock season repository
        $seasonRepo = $this->createMock(\App\Repository\SeasonRepository::class);
        $seasonRepo->method('find')->with($season->getId())->willReturn($season);
        $this->entityManager->method('getRepository')->willReturn($seasonRepo);

        // Mock successful validation
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        
        // Mock entity manager
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // When: Création de l'équipe
        $team = $this->teamService->createTeam($club, $teamData);

        // Then: L'équipe est créée avec toutes les propriétés
        $this->assertEquals('U16 Garçons Compétition', $team->getName());
        $this->assertEquals('male', $team->getGender());
        $this->assertEquals(2008, $team->getMinBirthYear());
        $this->assertEquals(2010, $team->getMaxBirthYear());
        $this->assertEquals('650', $team->getAnnualPrice()); // String car stocké en DECIMAL
    }

    /**
     * Scénario 2.3 : Création d'équipe sans saison (utilise la saison active par défaut)
     */
    public function testCreateTeamWithoutSeasonId(): void
    {
        // Given: Un club sans seasonId spécifiée
        $club = $this->createClub('Racing Club Paris');
        
        $teamData = [
            'name' => 'École de Tennis',
            'description' => 'Groupe débutants',
            'annualPrice' => 300.0
        ];

        // Mock successful validation
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        
        // Mock entity manager
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // When: Création de l'équipe
        $team = $this->teamService->createTeam($club, $teamData);

        // Then: L'équipe est créée sans saison
        $this->assertEquals('École de Tennis', $team->getName());
        $this->assertEquals($club, $team->getClub());
        $this->assertNull($team->getSeason());
        $this->assertEquals('300', $team->getAnnualPrice());
    }

    /**
     * Scénario 2.4 : Échec de création avec saison invalide
     */
    public function testCreateTeamWithInvalidSeason(): void
    {
        // Given: Un club et une saison qui n'appartient pas au club
        $club = $this->createClub('Racing Club Paris');
        $otherClub = $this->createClub('Autre Club');
        $wrongSeason = $this->createSeason($otherClub, '2023-2024');
        
        $teamData = [
            'name' => 'U18 Filles',
            'seasonId' => $wrongSeason->getId()
        ];

        // Mock season repository returning wrong season
        $seasonRepo = $this->createMock(\App\Repository\SeasonRepository::class);
        $seasonRepo->method('find')->with($wrongSeason->getId())->willReturn($wrongSeason);
        $this->entityManager->method('getRepository')->willReturn($seasonRepo);

        // When & Then: La création doit échouer
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Saison invalide');

        $this->teamService->createTeam($club, $teamData);
    }

    /**
     * Scénario 2.5 : Échec de création avec données invalides
     */
    public function testCreateTeamWithInvalidData(): void
    {
        // Given: Un club et des données invalides
        $club = $this->createClub('Racing Club Paris');
        
        $teamData = [
            'name' => '', // Nom vide
            'annualPrice' => -50 // Prix négatif
        ];

        // Mock validation with errors
        $violations = new ConstraintViolationList();
        $violation = $this->createMock(\Symfony\Component\Validator\ConstraintViolation::class);
        $violation->method('getPropertyPath')->willReturn('name');
        $violation->method('getMessage')->willReturn('Le nom de l\'équipe est obligatoire');
        $violations->add($violation);

        $this->validator->method('validate')->willReturn($violations);

        // When & Then: La création doit échouer
        $this->expectException(\InvalidArgumentException::class);

        $this->teamService->createTeam($club, $teamData);
    }

    /**
     * Scénario 2.6 : Mise à jour d'une équipe
     */
    public function testUpdateTeam(): void
    {
        // Given: Une équipe existante
        $club = $this->createClub('Racing Club Paris');
        $team = new Team();
        $team->setName('U18 Filles')
             ->setDescription('Équipe des moins de 18 ans filles')
             ->setClub($club)
             ->setAnnualPrice(450.0);

        $updateData = [
            'name' => 'U18 Filles Elite',
            'description' => 'Équipe élite des moins de 18 ans filles',
            'annualPrice' => 550.0
        ];

        // Mock successful validation
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        // When: Mise à jour de l'équipe
        $updatedTeam = $this->teamService->updateTeam($team, $updateData);

        // Then: L'équipe est mise à jour
        $this->assertEquals('U18 Filles Elite', $updatedTeam->getName());
        $this->assertEquals('Équipe élite des moins de 18 ans filles', $updatedTeam->getDescription());
        $this->assertEquals('550', $updatedTeam->getAnnualPrice());
    }

    /**
     * Scénario 2.7 : Suppression d'une équipe
     */
    public function testDeleteTeam(): void
    {
        // Given: Une équipe existante active
        $club = $this->createClub('Racing Club Paris');
        $team = new Team();
        $team->setName('U18 Filles')
             ->setClub($club)
             ->setIsActive(true);

        // When: Suppression de l'équipe
        $this->teamService->deleteTeam($team);

        // Then: L'équipe est marquée comme inactive
        $this->assertFalse($team->isActive());
    }

    /**
     * Scénario 2.8 : Activation/Désactivation d'une équipe
     */
    public function testToggleTeamStatus(): void
    {
        // Given: Une équipe active
        $club = $this->createClub('Racing Club Paris');
        $team = new Team();
        $team->setName('U18 Filles')
             ->setClub($club)
             ->setIsActive(true);

        // When: Désactivation puis réactivation
        $this->teamService->setTeamStatus($team, false);
        $this->assertFalse($team->isActive());

        $this->teamService->setTeamStatus($team, true);
        $this->assertTrue($team->isActive());
    }

    // Helper methods

    private function createClub(string $name): Club
    {
        $owner = new User();
        $owner->setEmail('owner@test.com')
              ->setFirstName('Test')
              ->setLastName('Owner');

        $club = new Club();
        $club->setName($name)
             ->setOwner($owner)
             ->setIsPublic(true)
             ->setAllowJoinRequests(true)
             ->setIsActive(true);

        return $club;
    }

    private function createSeason(Club $club, string $name): Season
    {
        $season = new Season();
        $season->setName("Saison $name")
               ->setStartDate(new \DateTime('2023-09-01'))
               ->setEndDate(new \DateTime('2024-08-31'))
               ->setClub($club)
               ->setIsActive(true);

        // Mock the ID for the season
        $reflection = new \ReflectionClass($season);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($season, 1);

        return $season;
    }
} 