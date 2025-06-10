<?php

namespace App\Tests\Unit\Service;

use App\Entity\Club;
use App\Entity\JoinRequest;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\JoinRequestStatus;
use App\Enum\TeamMemberRole;
use App\Service\JoinRequestService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class JoinRequestServiceTest extends TestCase
{
    private JoinRequestService $joinRequestService;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->joinRequestService = new JoinRequestService(
            $this->entityManager,
            $this->validator
        );
    }

    /**
     * Scénario 3.1 : Demande d'adhésion basique d'un utilisateur
     * Emma veut rejoindre l'équipe U18 Filles
     */
    public function testCreateBasicJoinRequest(): void
    {
        // Given: Un utilisateur, une équipe publique qui accepte les demandes
        $user = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $team = $this->createTeam('U18 Filles', true);
        
        $requestData = [
            'message' => 'Je souhaite rejoindre l\'équipe pour améliorer mon niveau',
            'requestedRole' => TeamMemberRole::ATHLETE
        ];

        // Mock successful validation
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        // Mock repository checks
        $joinRequestRepo = $this->createMock(\App\Repository\JoinRequestRepository::class);
        $joinRequestRepo->method('hasPendingRequestForTeam')->willReturn(false);
        
        $teamMemberRepo = $this->createMock(\App\Repository\TeamMemberRepository::class);
        $teamMemberRepo->method('findOneBy')->willReturn(null);
        
        $this->entityManager->method('getRepository')
            ->willReturnMap([
                [JoinRequest::class, $joinRequestRepo],
                [TeamMember::class, $teamMemberRepo]
            ]);

        // Mock entity manager
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // When: Création de la demande d'adhésion
        $joinRequest = $this->joinRequestService->createJoinRequest($user, $team, $requestData);

        // Then: La demande est créée correctement
        $this->assertInstanceOf(JoinRequest::class, $joinRequest);
        $this->assertEquals($user, $joinRequest->getUser());
        $this->assertEquals($team, $joinRequest->getTeam());
        $this->assertEquals($team->getClub(), $joinRequest->getClub());
        $this->assertEquals('Je souhaite rejoindre l\'équipe pour améliorer mon niveau', $joinRequest->getMessage());
        $this->assertEquals(TeamMemberRole::ATHLETE, $joinRequest->getRequestedRole());
        $this->assertEquals(JoinRequestStatus::PENDING, $joinRequest->getStatus());
    }

    /**
     * Scénario 3.2 : Demande d'adhésion avec rôle de coach
     */
    public function testCreateJoinRequestAsCoach(): void
    {
        // Given: Un utilisateur expérimenté qui veut être coach
        $user = $this->createUser('julie@test.com', 'Julie', 'Moreau');
        $team = $this->createTeam('U18 Filles', true);
        
        $requestData = [
            'message' => 'J\'ai 5 ans d\'expérience en coaching',
            'requestedRole' => TeamMemberRole::COACH
        ];

        // Mock successful validation
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        // Mock repository checks
        $joinRequestRepo = $this->createMock(\App\Repository\JoinRequestRepository::class);
        $joinRequestRepo->method('hasPendingRequestForTeam')->willReturn(false);
        
        $teamMemberRepo = $this->createMock(\App\Repository\TeamMemberRepository::class);
        $teamMemberRepo->method('findOneBy')->willReturn(null);
        
        $this->entityManager->method('getRepository')
            ->willReturnMap([
                [JoinRequest::class, $joinRequestRepo],
                [TeamMember::class, $teamMemberRepo]
            ]);

        // Mock entity manager
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // When: Création de la demande d'adhésion
        $joinRequest = $this->joinRequestService->createJoinRequest($user, $team, $requestData);

        // Then: La demande est créée avec le rôle coach
        $this->assertEquals(TeamMemberRole::COACH, $joinRequest->getRequestedRole());
        $this->assertEquals('J\'ai 5 ans d\'expérience en coaching', $joinRequest->getMessage());
    }

    /**
     * Scénario 3.3 : Échec - Demande à un club qui n'accepte pas les demandes
     */
    public function testCreateJoinRequestClubDoesNotAllowRequests(): void
    {
        // Given: Un utilisateur et une équipe d'un club privé
        $user = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $team = $this->createTeam('U18 Filles', false); // Club n'accepte pas les demandes
        
        $requestData = [
            'message' => 'Je souhaite rejoindre l\'équipe',
            'requestedRole' => TeamMemberRole::ATHLETE
        ];

        // When & Then: La création doit échouer
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce club n\'accepte pas les demandes d\'adhésion');

        $this->joinRequestService->createJoinRequest($user, $team, $requestData);
    }

    /**
     * Scénario 3.4 : Échec - Demande déjà en cours
     */
    public function testCreateJoinRequestAlreadyPending(): void
    {
        // Given: Un utilisateur et une équipe avec une demande déjà en cours
        $user = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $team = $this->createTeam('U18 Filles', true);
        
        $requestData = [
            'message' => 'Je souhaite rejoindre l\'équipe',
            'requestedRole' => TeamMemberRole::ATHLETE
        ];

        // Mock repository check returning existing request
        $joinRequestRepo = $this->createMock(\App\Repository\JoinRequestRepository::class);
        $joinRequestRepo->method('hasPendingRequestForTeam')->willReturn(true);
        
        $this->entityManager->method('getRepository')->willReturn($joinRequestRepo);

        // When & Then: La création doit échouer
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Une demande est déjà en cours pour cette équipe');

        $this->joinRequestService->createJoinRequest($user, $team, $requestData);
    }

    /**
     * Scénario 3.5 : Échec - Utilisateur déjà membre de l'équipe
     */
    public function testCreateJoinRequestUserAlreadyMember(): void
    {
        // Given: Un utilisateur déjà membre de l'équipe
        $user = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $team = $this->createTeam('U18 Filles', true);
        
        $requestData = [
            'message' => 'Je souhaite rejoindre l\'équipe',
            'requestedRole' => TeamMemberRole::ATHLETE
        ];

        // Mock repository checks
        $joinRequestRepo = $this->createMock(\App\Repository\JoinRequestRepository::class);
        $joinRequestRepo->method('hasPendingRequestForTeam')->willReturn(false);
        
        $existingMember = new TeamMember();
        $teamMemberRepo = $this->createMock(\App\Repository\TeamMemberRepository::class);
        $teamMemberRepo->method('findOneBy')->willReturn($existingMember);
        
        $this->entityManager->method('getRepository')
            ->willReturnMap([
                [JoinRequest::class, $joinRequestRepo],
                [TeamMember::class, $teamMemberRepo]
            ]);

        // When & Then: La création doit échouer
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Vous êtes déjà membre de cette équipe');

        $this->joinRequestService->createJoinRequest($user, $team, $requestData);
    }

    /**
     * Scénario 3.6 : Approbation d'une demande
     */
    public function testApproveJoinRequest(): void
    {
        // Given: Une demande d'adhésion en attente
        $user = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $team = $this->createTeam('U18 Filles', true);
        $reviewer = $this->createUser('marc@test.com', 'Marc', 'Dubois');
        
        $joinRequest = new JoinRequest();
        $joinRequest->setUser($user)
                   ->setTeam($team)
                   ->setClub($team->getClub())
                   ->setRequestedRole(TeamMemberRole::ATHLETE)
                   ->setStatus(JoinRequestStatus::PENDING);

        // Mock que l'utilisateur n'est pas déjà membre
        $teamMemberRepo = $this->createMock(\App\Repository\TeamMemberRepository::class);
        $teamMemberRepo->method('findOneBy')->willReturn(null);
        $this->entityManager->method('getRepository')->willReturn($teamMemberRepo);

        // Mock entity manager
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // When: Approbation de la demande
        $result = $this->joinRequestService->approveJoinRequest($joinRequest, $reviewer, TeamMemberRole::ATHLETE, 'Bienvenue dans l\'équipe');

        // Then: La demande est approuvée et un membre d'équipe est créé
        $this->assertEquals(JoinRequestStatus::APPROVED, $joinRequest->getStatus());
        $this->assertEquals($reviewer, $joinRequest->getReviewedBy());
        $this->assertEquals(TeamMemberRole::ATHLETE, $joinRequest->getAssignedRole());
        $this->assertEquals('Bienvenue dans l\'équipe', $joinRequest->getReviewNotes());
        $this->assertNotNull($joinRequest->getReviewedAt());
        $this->assertInstanceOf(TeamMember::class, $result['teamMember']);
    }

    /**
     * Scénario 3.7 : Rejet d'une demande
     */
    public function testRejectJoinRequest(): void
    {
        // Given: Une demande d'adhésion en attente
        $user = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $team = $this->createTeam('U18 Filles', true);
        $reviewer = $this->createUser('marc@test.com', 'Marc', 'Dubois');
        
        $joinRequest = new JoinRequest();
        $joinRequest->setUser($user)
                   ->setTeam($team)
                   ->setClub($team->getClub())
                   ->setRequestedRole(TeamMemberRole::ATHLETE)
                   ->setStatus(JoinRequestStatus::PENDING);

        // Mock entity manager
        $this->entityManager->expects($this->once())->method('flush');

        // When: Rejet de la demande
        $this->joinRequestService->rejectJoinRequest($joinRequest, $reviewer, 'Équipe complète');

        // Then: La demande est rejetée
        $this->assertEquals(JoinRequestStatus::REJECTED, $joinRequest->getStatus());
        $this->assertEquals($reviewer, $joinRequest->getReviewedBy());
        $this->assertEquals('Équipe complète', $joinRequest->getReviewNotes());
        $this->assertNotNull($joinRequest->getReviewedAt());
        $this->assertNull($joinRequest->getAssignedRole());
    }

    /**
     * Scénario 3.8 : Annulation d'une demande par l'utilisateur
     */
    public function testCancelJoinRequest(): void
    {
        // Given: Une demande d'adhésion en attente créée par l'utilisateur
        $user = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $team = $this->createTeam('U18 Filles', true);
        
        $joinRequest = new JoinRequest();
        $joinRequest->setUser($user)
                   ->setTeam($team)
                   ->setClub($team->getClub())
                   ->setRequestedRole(TeamMemberRole::ATHLETE)
                   ->setStatus(JoinRequestStatus::PENDING);

        // Mock entity manager
        $this->entityManager->expects($this->once())->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        // When: Annulation de la demande
        $this->joinRequestService->cancelJoinRequest($joinRequest, $user);

        // Then: La demande est supprimée (pas de changement de statut car remove)
        $this->assertTrue(true); // La demande sera supprimée de la base
    }

    /**
     * Scénario 3.9 : Échec annulation - Utilisateur non autorisé
     */
    public function testCancelJoinRequestUnauthorized(): void
    {
        // Given: Une demande créée par un autre utilisateur
        $user = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $otherUser = $this->createUser('julie@test.com', 'Julie', 'Moreau');
        $team = $this->createTeam('U18 Filles', true);
        
        $joinRequest = new JoinRequest();
        $joinRequest->setUser($user) // Créée par Emma
                   ->setTeam($team)
                   ->setClub($team->getClub())
                   ->setStatus(JoinRequestStatus::PENDING);

        // When & Then: L'annulation par Julie doit échouer
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Vous ne pouvez annuler que vos propres demandes');

        $this->joinRequestService->cancelJoinRequest($joinRequest, $otherUser);
    }

    // Helper methods

    private function createUser(string $email, string $firstName, string $lastName): User
    {
        $user = new User();
        $user->setEmail($email)
             ->setFirstName($firstName)
             ->setLastName($lastName)
             ->setRoles(['ROLE_USER']);

        return $user;
    }

    private function createTeam(string $name, bool $allowJoinRequests): Team
    {
        $owner = $this->createUser('owner@test.com', 'Owner', 'Club');
        
        $club = new Club();
        $club->setName('Racing Club Paris')
             ->setOwner($owner)
             ->setIsPublic(true)
             ->setAllowJoinRequests($allowJoinRequests)
             ->setIsActive(true);

        $team = new Team();
        $team->setName($name)
             ->setClub($club)
             ->setIsActive(true);

        return $team;
    }
} 