<?php

namespace App\Tests\Unit\Service;

use App\Tests\Unit\Service;

use App\Entity\Club;
use App\Entity\ClubManager;
use App\Entity\Document;
use App\Entity\DocumentType;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType as DocumentTypeEnum;
use App\Enum\TeamMemberRole;
use App\Security\DocumentVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Tests unitaires pour les permissions et la sécurité des documents
 * Couvre les scénarios 5.1, 5.2, 5.3 du markdown (isolation des clubs, contrôle d'accès)
 */
class DocumentSecurityTest extends TestCase
{
    private DocumentVoter $documentVoter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentVoter = new DocumentVoter();
    }

    /**
     * Scénario 5.1 : Isolation des clubs
     * Test qu'un coach externe ne peut pas accéder aux documents d'un autre club
     */
    public function testClubIsolation(): void
    {
        // Given: Paul est coach du Tennis Club Lyon
        $paul = $this->createUser('paul@lyon.fr', 'Paul', 'Martin');
        $tennisClub = $this->createClub('Tennis Club Lyon', $paul);
        $paulTeam = $this->createTeam('Équipe Senior', $tennisClub);

        // And: Emma est membre du Racing Club Paris
        $marc = $this->createUser('marc@racing.fr', 'Marc', 'Dubois');
        $racingClub = $this->createClub('Racing Club Paris', $marc);
        $emmaTeam = $this->createTeam('U18 Filles', $racingClub);
        $emma = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $emmaDocument = $this->createDocument($emma, $emmaTeam);

        // When: Paul tente d'accéder aux documents d'Emma
        $token = $this->createMockToken($paul);
        $result = $this->documentVoter->vote($token, $emmaDocument, [DocumentVoter::VIEW]);

        // Then: L'accès est refusé
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    /**
     * Scénario 5.2 : Contrôle d'accès athlète
     * Test qu'Emma ne peut voir que ses propres documents
     */
    public function testAthleteAccessControl(): void
    {
        // Given: Emma et Léa sont toutes deux dans l'équipe U18 Filles
        $marc = $this->createUser('marc@racing.fr', 'Marc', 'Dubois');
        $racingClub = $this->createClub('Racing Club Paris', $marc);
        $u18Team = $this->createTeam('U18 Filles', $racingClub);
        
        $emma = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $lea = $this->createUser('lea@test.com', 'Léa', 'Martin');
        
        $emmaDocument = $this->createDocument($emma, $u18Team);
        $leaDocument = $this->createDocument($lea, $u18Team);

        // When: Emma tente de voir les documents de Léa
        $emmaToken = $this->createMockToken($emma);
        $result = $this->documentVoter->vote($emmaToken, $leaDocument, [DocumentVoter::VIEW]);

        // Then: L'accès est refusé
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);

        // But: Emma peut voir ses propres documents
        $ownDocResult = $this->documentVoter->vote($emmaToken, $emmaDocument, [DocumentVoter::VIEW]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $ownDocResult);
    }

    /**
     * Test que le propriétaire du club peut voir tous les documents
     */
    public function testClubOwnerCanViewAllDocuments(): void
    {
        // Given: Marc est propriétaire du Racing Club Paris
        $marc = $this->createUser('marc@racing.fr', 'Marc', 'Dubois');
        $racingClub = $this->createClub('Racing Club Paris', $marc);
        $u18Team = $this->createTeam('U18 Filles', $racingClub);
        
        $emma = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $emmaDocument = $this->createDocument($emma, $u18Team);

        // When: Marc tente de voir le document d'Emma
        $marcToken = $this->createMockToken($marc);
        $result = $this->documentVoter->vote($marcToken, $emmaDocument, [DocumentVoter::VIEW]);

        // Then: L'accès est accordé
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    /**
     * Test que les gestionnaires de club peuvent voir les documents
     */
    public function testClubManagerCanViewDocuments(): void
    {
        // Given: Julie est gestionnaire du Racing Club Paris
        $marc = $this->createUser('marc@racing.fr', 'Marc', 'Dubois');
        $julie = $this->createUser('julie@racing.fr', 'Julie', 'Moreau');
        $racingClub = $this->createClub('Racing Club Paris', $marc);
        $u18Team = $this->createTeam('U18 Filles', $racingClub);
        
        // Ajouter Julie comme gestionnaire
        $clubManager = new ClubManager();
        $clubManager->setUser($julie);
        $clubManager->setClub($racingClub);
        $julie->addClubManager($clubManager);

        $emma = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $emmaDocument = $this->createDocument($emma, $u18Team);

        // When: Julie tente de voir le document d'Emma
        $julieToken = $this->createMockToken($julie);
        $result = $this->documentVoter->vote($julieToken, $emmaDocument, [DocumentVoter::VIEW]);

        // Then: L'accès est accordé
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    /**
     * Test des permissions de validation de documents
     */
    public function testDocumentValidationPermissions(): void
    {
        // Given: Configuration standard
        $marc = $this->createUser('marc@racing.fr', 'Marc', 'Dubois');
        $racingClub = $this->createClub('Racing Club Paris', $marc);
        $u18Team = $this->createTeam('U18 Filles', $racingClub);
        
        $emma = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $document = $this->createDocument($emma, $u18Team);

        // Test 1: Le propriétaire peut valider
        $marcToken = $this->createMockToken($marc);
        $validateResult = $this->documentVoter->vote($marcToken, $document, [DocumentVoter::VALIDATE]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $validateResult);

        // Test 2: L'athlète ne peut pas valider ses propres documents
        $emmaToken = $this->createMockToken($emma);
        $emmaValidateResult = $this->documentVoter->vote($emmaToken, $document, [DocumentVoter::VALIDATE]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $emmaValidateResult);
    }

    /**
     * Test des permissions de suppression de documents
     */
    public function testDocumentDeletionPermissions(): void
    {
        // Given: Configuration standard
        $marc = $this->createUser('marc@racing.fr', 'Marc', 'Dubois');
        $racingClub = $this->createClub('Racing Club Paris', $marc);
        $u18Team = $this->createTeam('U18 Filles', $racingClub);
        
        $emma = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $document = $this->createDocument($emma, $u18Team);

        // Test 1: Le propriétaire peut supprimer
        $marcToken = $this->createMockToken($marc);
        $deleteResult = $this->documentVoter->vote($marcToken, $document, [DocumentVoter::DELETE]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $deleteResult);

        // Test 2: L'athlète peut supprimer ses propres documents
        $emmaToken = $this->createMockToken($emma);
        $emmaDeleteResult = $this->documentVoter->vote($emmaToken, $document, [DocumentVoter::DELETE]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $emmaDeleteResult);
    }

    /**
     * Test avec utilisateur non authentifié
     */
    public function testUnauthenticatedUserDenied(): void
    {
        // Given: Un document quelconque
        $marc = $this->createUser('marc@racing.fr', 'Marc', 'Dubois');
        $racingClub = $this->createClub('Racing Club Paris', $marc);
        $u18Team = $this->createTeam('U18 Filles', $racingClub);
        $emma = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $document = $this->createDocument($emma, $u18Team);

        // When: Tentative d'accès sans authentification
        $token = $this->createMockToken(null);
        $result = $this->documentVoter->vote($token, $document, [DocumentVoter::VIEW]);

        // Then: L'accès est refusé
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    /**
     * Test que le voter ne supporte que les entités Document
     */
    public function testVoterSupportsOnlyDocuments(): void
    {
        // Given: Une entité qui n'est pas un Document
        $user = $this->createUser('test@test.com', 'Test', 'User');
        $token = $this->createMockToken($user);

        // When: Test du support pour un objet non-Document
        $result = $this->documentVoter->vote($token, $user, [DocumentVoter::VIEW]);

        // Then: Le voter ne traite pas cet objet
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    /**
     * Test des coach ne peuvent valider que les documents de leur équipe
     */
    public function testCoachCanOnlyValidateOwnTeamDocuments(): void
    {
        // Given: Julie est coach de l'équipe U18 Filles
        $marc = $this->createUser('marc@racing.fr', 'Marc', 'Dubois');
        $julie = $this->createUser('julie@racing.fr', 'Julie', 'Moreau');
        $racingClub = $this->createClub('Racing Club Paris', $marc);
        
        $u18Team = $this->createTeam('U18 Filles', $racingClub);
        $u16Team = $this->createTeam('U16 Garçons', $racingClub);

        // Julie est coach de U18 Filles
        $teamMember = new TeamMember();
        $teamMember->setUser($julie);
        $teamMember->setTeam($u18Team);
        $teamMember->setRole(TeamMemberRole::COACH);
        $julie->addTeamMembership($teamMember);

        $emma = $this->createUser('emma@test.com', 'Emma', 'Leblanc');
        $pierre = $this->createUser('pierre@test.com', 'Pierre', 'Durand');
        
        $emmaDocument = $this->createDocument($emma, $u18Team); // Document de son équipe
        $pierreDocument = $this->createDocument($pierre, $u16Team); // Document d'une autre équipe

        $julieToken = $this->createMockToken($julie);

        // When/Then: Julie peut valider les documents de son équipe
        $ownTeamResult = $this->documentVoter->vote($julieToken, $emmaDocument, [DocumentVoter::VALIDATE]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $ownTeamResult);

        // But: Julie ne peut pas valider les documents d'autres équipes
        $otherTeamResult = $this->documentVoter->vote($julieToken, $pierreDocument, [DocumentVoter::VALIDATE]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $otherTeamResult);
    }

    // Helper methods

    private function createUser(string $email, string $firstName, string $lastName): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setDateOfBirth(new \DateTime('1990-01-01'));
        return $user;
    }

    private function createClub(string $name, User $owner): Club
    {
        $club = new Club();
        $club->setName($name);
        $club->setOwner($owner);
        $club->setIsPublic(true);
        $club->setAllowJoinRequests(true);
        return $club;
    }

    private function createTeam(string $name, Club $club): Team
    {
        $team = new Team();
        $team->setName($name);
        $team->setClub($club);
        return $team;
    }

    private function createDocument(User $user, Team $team): Document
    {
        $documentType = new DocumentType();
        $documentType->setName('Test Document');
        $documentType->setType(DocumentTypeEnum::OTHER);
        $documentType->setTeam($team);
        $documentType->setIsRequired(false);

        $document = new Document();
        $document->setUser($user);
        $document->setDocumentTypeEntity($documentType);
        $document->setOriginalFileName('test.pdf');
        $document->setSecurePath('secure/test.pdf');
        $document->setMimeType('application/pdf');
        $document->setFileSize(1024);
        $document->setStatus(DocumentStatus::PENDING);

        return $document;
    }

    private function createMockToken(?User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }
} 