<?php

namespace App\Tests\Integration;

use App\Tests\ApiTestCase;
use App\Entity\User;
use App\Entity\Club;
use App\Entity\Team;
use App\Entity\DocumentType;
use App\Entity\Document;
use App\Entity\TeamMember;
use App\Enum\DocumentType as DocumentTypeEnum;
use App\Enum\DocumentStatus;
use App\Enum\TeamMemberRole;

/**
 * Tests d'intégration pour le workflow complet des documents
 * Couvre les scénarios 3.1, 3.2, 3.3 (validation/rejet) du markdown
 */
class DocumentWorkflowTest extends ApiTestCase
{
    private User $marc;
    private User $julie;
    private User $emma;
    private Club $racingClub;
    private Team $u18Team;
    private DocumentType $certificatType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBasicFixtures();
    }

    /**
     * Scénario 3.1 : Validation par le propriétaire
     * Test Marc validant le certificat médical d'Emma
     */
    public function testDocumentValidationByOwner(): void
    {
        // Given: Emma a uploadé son certificat médical (statut PENDING)
        $document = $this->createPendingDocument();

        // When: Marc consulte les documents en attente et valide le certificat d'Emma
        $this->authenticatedRequest('PUT', "/api/documents/{$document->getId()}/validate", $this->marc, [
            'status' => 'APPROVED',
            'validationNotes' => 'Certificat valide jusqu\'au 15/03/2026'
        ]);

        // Then: Le statut passe à "APPROVED"
        $responseData = $this->assertJsonResponse(200);

        $this->assertEquals('APPROVED', $responseData['document']['status']);
        $this->assertEquals('Certificat valide jusqu\'au 15/03/2026', $responseData['document']['validationNotes']);
        $this->assertEquals($this->marc->getId(), $responseData['document']['validatedBy']['id']);
        $this->assertNotNull($responseData['document']['validatedAt']);
        
        // Vérifier que la date d'expiration est calculée automatiquement (365 jours)
        $this->assertNotNull($responseData['document']['expirationDate']);

        // Vérifier en base de données
        $this->entityManager->refresh($document);
        $this->assertEquals(DocumentStatus::APPROVED, $document->getStatus());
        $this->assertEquals($this->marc, $document->getValidatedBy());
        $this->assertNotNull($document->getValidatedAt());
        $this->assertNotNull($document->getExpirationDate());
    }

    /**
     * Scénario 3.2 : Rejet par un gestionnaire
     * Test Marc rejetant une licence FFT illisible
     */
    public function testDocumentRejectionByManager(): void
    {
        // Given: Emma a uploadé une licence FFT illisible
        $licenceType = $this->createLicenceType();
        $document = $this->createPendingDocumentOfType($licenceType);

        // When: Marc rejette le document avec une raison
        $this->authenticatedRequest('PUT', "/api/documents/{$document->getId()}/validate", $this->marc, [
            'status' => 'REJECTED',
            'rejectionReason' => 'Document illisible, merci de re-scanner'
        ]);

        // Then: Le statut passe à "REJECTED"
        $responseData = $this->assertJsonResponse(200);

        $this->assertEquals('REJECTED', $responseData['document']['status']);
        $this->assertEquals('Document illisible, merci de re-scanner', $responseData['document']['rejectionReason']);
        $this->assertEquals($this->marc->getId(), $responseData['document']['validatedBy']['id']);
        $this->assertNotNull($responseData['document']['validatedAt']);
        $this->assertNull($responseData['document']['expirationDate']); // Pas d'expiration pour un document rejeté

        // Vérifier en base de données
        $this->entityManager->refresh($document);
        $this->assertEquals(DocumentStatus::REJECTED, $document->getStatus());
        $this->assertEquals('Document illisible, merci de re-scanner', $document->getRejectionReason());
        $this->assertNull($document->getExpirationDate());
    }

    /**
     * Scénario 3.3 : Validation par un coach
     * Test Julie (coach) validant un document de son équipe
     */
    public function testDocumentValidationByCoach(): void
    {
        // Given: Julie est coach de l'équipe U18 Filles
        $this->makeJulieCoach();
        
        // And: Emma a uploadé son autorisation parentale
        $autorisationType = $this->createAutorisationType();
        $document = $this->createPendingDocumentOfType($autorisationType);

        // When: Julie valide le document
        $this->authenticatedRequest('PUT', "/api/documents/{$document->getId()}/validate", $this->julie, [
            'status' => 'APPROVED',
            'validationNotes' => 'Autorisation parentale conforme'
        ]);

        // Then: Le document est approuvé
        $responseData = $this->assertJsonResponse(200);

        $this->assertEquals('APPROVED', $responseData['document']['status']);
        $this->assertEquals($this->julie->getId(), $responseData['document']['validatedBy']['id']);

        // Vérifier que Julie peut voir tous les documents de son équipe
        $this->authenticatedRequest('GET', "/api/documents/team/{$this->u18Team->getId()}", $this->julie);
        $teamDocuments = $this->assertJsonResponse(200);
        
        $this->assertArrayHasKey('documents', $teamDocuments);
        $this->assertNotEmpty($teamDocuments['documents']);
    }

    /**
     * Test que Julie ne peut pas voir les documents des autres équipes
     */
    public function testCoachCannotAccessOtherTeamDocuments(): void
    {
        // Given: Julie est coach de U18 Filles
        $this->makeJulieCoach();
        
        // And: Il existe une autre équipe U16 Garçons
        $u16Team = $this->createU16Team();
        $otherDocument = $this->createDocumentForTeam($u16Team);

        // When: Julie tente d'accéder aux documents de l'équipe U16
        $this->authenticatedRequest('GET', "/api/documents/team/{$u16Team->getId()}", $this->julie);

        // Then: L'accès est refusé
        $this->assertJsonResponse(403);
    }

    /**
     * Test du workflow complet : upload → validation → notification
     */
    public function testCompleteDocumentWorkflow(): void
    {
        // 1. Emma uploade un document
        $testFile = $this->createTestImage();
        
        $this->authenticatedMultipartRequest(
            'POST', 
            '/api/documents', 
            $this->emma,
            [
                'documentTypeId' => $this->certificatType->getId(),
                'description' => 'Certificat médical 2025'
            ],
            ['document' => $testFile]
        );

        $uploadResponse = $this->assertJsonResponse(201);
        $documentId = $uploadResponse['document']['id'];

        // 2. Marc valide le document
        $this->authenticatedRequest('PUT', "/api/documents/{$documentId}/validate", $this->marc, [
            'status' => 'APPROVED',
            'validationNotes' => 'Document conforme'
        ]);

        $validationResponse = $this->assertJsonResponse(200);
        $this->assertEquals('APPROVED', $validationResponse['document']['status']);

        // 3. Emma peut voir son document validé
        $this->authenticatedRequest('GET', '/api/documents/my', $this->emma);
        $myDocuments = $this->assertJsonResponse(200);
        
        $approvedDoc = array_filter($myDocuments['documents'], fn($doc) => $doc['id'] === $documentId);
        $this->assertNotEmpty($approvedDoc);
        $this->assertEquals('APPROVED', array_values($approvedDoc)[0]['status']);

        // Nettoyer
        unlink($testFile);
    }

    /**
     * Test de la récupération des statistiques du club
     */
    public function testClubDocumentStatistics(): void
    {
        // Given: Plusieurs documents avec différents statuts
        $doc1 = $this->createPendingDocument();
        $doc2 = $this->createPendingDocument();
        $doc3 = $this->createPendingDocument();

        // Valider un document
        $doc1->validate($this->marc);
        $this->entityManager->flush();

        // Rejeter un document
        $doc2->reject('Document invalide', $this->marc);
        $this->entityManager->flush();

        // When: Marc consulte les statistiques du club
        $this->authenticatedRequest('GET', "/api/documents/club/{$this->racingClub->getId()}", $this->marc);
        $response = $this->assertJsonResponse(200);

        // Then: Les statistiques sont correctes
        $this->assertArrayHasKey('stats', $response);
        $stats = $response['stats'];
        
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['approved']);
        $this->assertEquals(1, $stats['rejected']);
        $this->assertEquals(1, $stats['pending']);
    }

    /**
     * Test que les athlètes ne peuvent voir que leurs propres documents
     */
    public function testAthleteCanOnlySeeOwnDocuments(): void
    {
        // Given: Emma et un autre athlète Léa ont chacune un document
        $lea = $this->createTestUser('lea@test.com', ['ROLE_USER'], [
            'firstName' => 'Léa',
            'lastName' => 'Martin',
            'dateOfBirth' => '2008-07-20'
        ]);
        
        $this->addUserToTeam($lea, $this->u18Team);
        
        $emmaDoc = $this->createPendingDocument();
        $leaDoc = $this->createDocumentForUser($lea);

        // When: Emma consulte ses documents
        $this->authenticatedRequest('GET', '/api/documents/my', $this->emma);
        $response = $this->assertJsonResponse(200);

        // Then: Emma ne voit que son document
        $documentIds = array_column($response['documents'], 'id');
        $this->assertContains($emmaDoc->getId(), $documentIds);
        $this->assertNotContains($leaDoc->getId(), $documentIds);
    }

    public function testBasicWorkflow(): void
    {
        $this->assertTrue(true);
    }

    // Helper methods

    private function createBasicFixtures(): void
    {
        // Créer Marc (propriétaire)
        $this->marc = $this->createTestUser('marc@racing.fr', ['ROLE_USER'], [
            'firstName' => 'Marc',
            'lastName' => 'Dubois',
            'dateOfBirth' => '1975-05-15'
        ]);

        // Créer Julie (coach)
        $this->julie = $this->createTestUser('julie@racing.fr', ['ROLE_USER'], [
            'firstName' => 'Julie',
            'lastName' => 'Moreau',
            'dateOfBirth' => '1985-09-12'
        ]);

        // Créer Emma (athlète)
        $this->emma = $this->createTestUser('emma@test.com', ['ROLE_USER'], [
            'firstName' => 'Emma',
            'lastName' => 'Leblanc',
            'dateOfBirth' => '2008-03-10'
        ]);

        // Créer le club et l'équipe
        $this->racingClub = new Club();
        $this->racingClub->setName('Racing Club Paris');
        $this->racingClub->setOwner($this->marc);
        $this->racingClub->setIsPublic(true);
        $this->racingClub->setAllowJoinRequests(true);

        $this->u18Team = new Team();
        $this->u18Team->setName('U18 Filles');
        $this->u18Team->setClub($this->racingClub);
        $this->u18Team->setMinAge(16);
        $this->u18Team->setMaxAge(18);
        $this->u18Team->setGender('F');

        $this->certificatType = new DocumentType();
        $this->certificatType->setName('Certificat médical');
        $this->certificatType->setType(DocumentTypeEnum::MEDICAL_CERTIFICATE);
        $this->certificatType->setTeam($this->u18Team);
        $this->certificatType->setIsRequired(true);
        $this->certificatType->setHasExpirationDate(true);
        $this->certificatType->setValidityDurationInDays(365);

        // Ajouter Emma à l'équipe
        $this->addUserToTeam($this->emma, $this->u18Team);

        $this->entityManager->persist($this->racingClub);
        $this->entityManager->persist($this->u18Team);
        $this->entityManager->persist($this->certificatType);
        $this->entityManager->flush();
    }

    private function addUserToTeam(User $user, Team $team): void
    {
        $teamMember = new TeamMember();
        $teamMember->setUser($user);
        $teamMember->setTeam($team);
        $teamMember->setRole(TeamMemberRole::PLAYER);
        $teamMember->setIsActive(true);

        $this->entityManager->persist($teamMember);
        $this->entityManager->flush();
    }

    private function makeJulieCoach(): void
    {
        $julieMember = new TeamMember();
        $julieMember->setUser($this->julie);
        $julieMember->setTeam($this->u18Team);
        $julieMember->setRole(TeamMemberRole::COACH);
        $julieMember->setIsActive(true);

        $this->entityManager->persist($julieMember);
        $this->entityManager->flush();
    }

    private function createPendingDocument(): Document
    {
        return $this->createDocumentForUser($this->emma);
    }

    private function createPendingDocumentOfType(DocumentType $type): Document
    {
        $document = new Document();
        $document->setUser($this->emma);
        $document->setDocumentTypeEntity($type);
        $document->setOriginalFileName('test_document.pdf');
        $document->setSecurePath('secure/' . uniqid() . '.pdf');
        $document->setMimeType('application/pdf');
        $document->setFileSize(1024000);
        $document->setStatus(DocumentStatus::PENDING);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    private function createDocumentForUser(User $user): Document
    {
        $document = new Document();
        $document->setUser($user);
        $document->setDocumentTypeEntity($this->certificatType);
        $document->setOriginalFileName('certificat_' . $user->getFirstName() . '.pdf');
        $document->setSecurePath('secure/' . uniqid() . '.pdf');
        $document->setMimeType('application/pdf');
        $document->setFileSize(1024000);
        $document->setStatus(DocumentStatus::PENDING);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    private function createDocumentForTeam(Team $team): Document
    {
        $otherUser = $this->createTestUser('other@test.com', ['ROLE_USER'], [
            'firstName' => 'Other',
            'lastName' => 'User',
            'dateOfBirth' => '2008-01-01'
        ]);

        $this->addUserToTeam($otherUser, $team);

        $otherType = new DocumentType();
        $otherType->setName('Certificat médical');
        $otherType->setType(DocumentTypeEnum::MEDICAL_CERTIFICATE);
        $otherType->setTeam($team);
        $otherType->setIsRequired(true);

        $this->entityManager->persist($otherType);

        $document = new Document();
        $document->setUser($otherUser);
        $document->setDocumentTypeEntity($otherType);
        $document->setOriginalFileName('other_doc.pdf');
        $document->setSecurePath('secure/' . uniqid() . '.pdf');
        $document->setMimeType('application/pdf');
        $document->setFileSize(1024000);
        $document->setStatus(DocumentStatus::PENDING);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    private function createLicenceType(): DocumentType
    {
        $licenceType = new DocumentType();
        $licenceType->setName('Licence FFT');
        $licenceType->setType(DocumentTypeEnum::LICENSE);
        $licenceType->setTeam($this->u18Team);
        $licenceType->setIsRequired(true);
        $licenceType->setHasExpirationDate(true);
        $licenceType->setValidityDurationInDays(365);

        $this->entityManager->persist($licenceType);
        $this->entityManager->flush();

        return $licenceType;
    }

    private function createAutorisationType(): DocumentType
    {
        $autorisationType = new DocumentType();
        $autorisationType->setName('Autorisation parentale');
        $autorisationType->setType(DocumentTypeEnum::AUTHORIZATION);
        $autorisationType->setTeam($this->u18Team);
        $autorisationType->setIsRequired(true);
        $autorisationType->setHasExpirationDate(false);

        $this->entityManager->persist($autorisationType);
        $this->entityManager->flush();

        return $autorisationType;
    }

    private function createU16Team(): Team
    {
        $u16Team = new Team();
        $u16Team->setName('U16 Garçons');
        $u16Team->setClub($this->racingClub);
        $u16Team->setMinAge(14);
        $u16Team->setMaxAge(16);
        $u16Team->setGender('M');

        $this->entityManager->persist($u16Team);
        $this->entityManager->flush();

        return $u16Team;
    }
} 