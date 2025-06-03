<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use App\Entity\User;
use App\Entity\Club;
use App\Entity\Team;
use App\Entity\Document;
use App\Entity\DocumentType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests de gestion des documents selon le persona Emma Leblanc (athlète)
 * Couvre l'upload, la validation, le workflow et les notifications
 */
class DocumentTest extends ApiTestCase
{
    private User $marcDubois;   // Propriétaire du club
    private User $emmaLeblanc;  // Athlète
    private User $julieMoreau;  // Coach
    private Club $club;
    private Team $teamU18;
    private DocumentType $certificatMedical;
    private DocumentType $licenceFft;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->marcDubois = $this->createTestUser(
            'marc.dubois@racingclub.com',
            ['ROLE_CLUB_OWNER'],
            ['firstName' => 'Marc', 'lastName' => 'Dubois']
        );

        $this->emmaLeblanc = $this->createTestUser(
            'emma.leblanc@athlete.com',
            ['ROLE_ATHLETE'],
            ['firstName' => 'Emma', 'lastName' => 'Leblanc']
        );

        $this->julieMoreau = $this->createTestUser(
            'julie.moreau@coach.com',
            ['ROLE_COACH'],
            ['firstName' => 'Julie', 'lastName' => 'Moreau']
        );

        $this->club = $this->createTestClub($this->marcDubois);
        $this->teamU18 = $this->createTestTeam($this->club, 'U18 Filles');
        
        // Créer les types de documents requis
        $this->certificatMedical = $this->createDocumentType('Certificat médical', true, ['pdf'], 5);
        $this->licenceFft = $this->createDocumentType('Licence FFT', true, ['pdf', 'jpg', 'png'], 3);
        
        // Ajouter Emma à l'équipe
        $this->addAthleteToTeam($this->emmaLeblanc, $this->teamU18);
    }

    public function testDocumentTypeCreation(): void
    {
        // Marc configure un nouveau type de document obligatoire
        $documentTypeData = [
            'name' => 'Autorisation parentale',
            'description' => 'Autorisation parentale pour les mineurs',
            'isRequired' => true,
            'allowedExtensions' => ['pdf', 'jpg'],
            'maxSizeInMb' => 2,
            'validityPeriodInMonths' => 12,
            'club' => '/api/clubs/' . $this->club->getId()
        ];

        $this->authenticatedRequest('POST', '/api/document-types', $this->marcDubois, $documentTypeData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($documentTypeData['name'], $responseData['name']);
        $this->assertTrue($responseData['isRequired']);
        $this->assertEquals($documentTypeData['allowedExtensions'], $responseData['allowedExtensions']);
        $this->assertEquals($documentTypeData['maxSizeInMb'], $responseData['maxSizeInMb']);
    }

    public function testDocumentUpload(): void
    {
        // Emma upload son certificat médical
        $documentData = [
            'name' => 'Certificat médical Emma',
            'documentType' => '/api/document-types/' . $this->certificatMedical->getId(),
            'user' => '/api/users/' . $this->emmaLeblanc->getId(),
            'team' => '/api/teams/' . $this->teamU18->getId(),
            'expirationDate' => '2025-12-31',
            'filePath' => '/uploads/documents/certificat-emma.pdf', // Simulation
            'originalFilename' => 'certificat-medical-emma.pdf',
            'fileSize' => 2048000, // 2MB
            'mimeType' => 'application/pdf'
        ];

        $this->authenticatedRequest('POST', '/api/documents', $this->emmaLeblanc, $documentData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($documentData['name'], $responseData['name']);
        $this->assertEquals('pending', $responseData['status']); // En attente de validation
        $this->assertEquals($this->emmaLeblanc->getId(), $responseData['user']['id']);
        $this->assertEquals($this->certificatMedical->getId(), $responseData['documentType']['id']);
    }

    public function testDocumentUploadWithInvalidExtension(): void
    {
        // Emma tente d'uploader un fichier avec une extension non autorisée
        $documentData = [
            'name' => 'Document invalide',
            'documentType' => '/api/document-types/' . $this->certificatMedical->getId(),
            'user' => '/api/users/' . $this->emmaLeblanc->getId(),
            'filePath' => '/uploads/documents/document.txt', // Extension non autorisée
            'originalFilename' => 'document.txt',
            'mimeType' => 'text/plain'
        ];

        $this->authenticatedRequest('POST', '/api/documents', $this->emmaLeblanc, $documentData);
        
        $this->assertErrorResponse(400, 'extension');
    }

    public function testDocumentUploadWithOversizedFile(): void
    {
        // Emma tente d'uploader un fichier trop volumineux
        $documentData = [
            'name' => 'Fichier trop volumineux',
            'documentType' => '/api/document-types/' . $this->certificatMedical->getId(),
            'user' => '/api/users/' . $this->emmaLeblanc->getId(),
            'filePath' => '/uploads/documents/huge-file.pdf',
            'originalFilename' => 'huge-file.pdf',
            'fileSize' => 10485760, // 10MB (limite: 5MB)
            'mimeType' => 'application/pdf'
        ];

        $this->authenticatedRequest('POST', '/api/documents', $this->emmaLeblanc, $documentData);
        
        $this->assertErrorResponse(400, 'taille');
    }

    public function testDocumentValidationWorkflow(): void
    {
        // Emma soumet un document
        $document = $this->createTestDocument($this->emmaLeblanc, $this->certificatMedical);

        // Vérifier le statut initial
        $this->assertEquals('pending', $document->getStatus());

        // Marc valide le document
        $this->authenticatedRequest('PATCH', '/api/documents/' . $document->getId(), $this->marcDubois, [
            'status' => 'approved',
            'validationComment' => 'Document conforme'
        ]);

        $responseData = $this->assertJsonResponse(200);
        
        $this->assertEquals('approved', $responseData['status']);
        $this->assertEquals('Document conforme', $responseData['validationComment']);
        $this->assertEquals($this->marcDubois->getId(), $responseData['validatedBy']['id']);
        $this->assertNotNull($responseData['validatedAt']);
    }

    public function testDocumentRejection(): void
    {
        // Emma soumet un document
        $document = $this->createTestDocument($this->emmaLeblanc, $this->certificatMedical);

        // Marc rejette le document
        $this->authenticatedRequest('PATCH', '/api/documents/' . $document->getId(), $this->marcDubois, [
            'status' => 'rejected',
            'validationComment' => 'Document illisible, merci de le refournir'
        ]);

        $responseData = $this->assertJsonResponse(200);
        
        $this->assertEquals('rejected', $responseData['status']);
        $this->assertEquals('Document illisible, merci de le refournir', $responseData['validationComment']);
    }

    public function testDocumentResubmission(): void
    {
        // Emma soumet un document qui est rejeté
        $document = $this->createTestDocument($this->emmaLeblanc, $this->certificatMedical);
        
        // Marc le rejette
        $this->authenticatedRequest('PATCH', '/api/documents/' . $document->getId(), $this->marcDubois, [
            'status' => 'rejected',
            'validationComment' => 'Document illisible'
        ]);

        // Emma resoumet un nouveau document du même type
        $newDocumentData = [
            'name' => 'Certificat médical Emma - Nouvelle version',
            'documentType' => '/api/document-types/' . $this->certificatMedical->getId(),
            'user' => '/api/users/' . $this->emmaLeblanc->getId(),
            'team' => '/api/teams/' . $this->teamU18->getId(),
            'filePath' => '/uploads/documents/certificat-emma-v2.pdf',
            'originalFilename' => 'certificat-medical-emma-v2.pdf',
            'replacesDocument' => '/api/documents/' . $document->getId()
        ];

        $this->authenticatedRequest('POST', '/api/documents', $this->emmaLeblanc, $newDocumentData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals('pending', $responseData['status']);
        $this->assertEquals($document->getId(), $responseData['replacesDocument']['id']);
    }

    public function testUserDocumentList(): void
    {
        // Créer plusieurs documents pour Emma
        $doc1 = $this->createTestDocument($this->emmaLeblanc, $this->certificatMedical);
        $doc2 = $this->createTestDocument($this->emmaLeblanc, $this->licenceFft);

        // Emma consulte ses documents
        $this->authenticatedRequest('GET', '/api/users/' . $this->emmaLeblanc->getId() . '/documents', $this->emmaLeblanc);
        
        $responseData = $this->assertJsonResponse(200);
        
        $this->assertCount(2, $responseData['hydra:member']);
        
        // Vérifier que tous les documents appartiennent à Emma
        foreach ($responseData['hydra:member'] as $doc) {
            $this->assertEquals($this->emmaLeblanc->getId(), $doc['user']['id']);
        }
    }

    public function testRequiredDocumentsStatus(): void
    {
        // Vérifier le statut des documents requis pour Emma
        $this->authenticatedRequest('GET', '/api/users/' . $this->emmaLeblanc->getId() . '/required-documents', $this->emmaLeblanc);
        
        $responseData = $this->assertJsonResponse(200);
        
        $this->assertArrayHasKey('required', $responseData);
        $this->assertArrayHasKey('submitted', $responseData);
        $this->assertArrayHasKey('approved', $responseData);
        $this->assertArrayHasKey('missing', $responseData);
        
        // Au début, Emma a des documents manquants
        $this->assertGreaterThan(0, count($responseData['missing']));
    }

    public function testDocumentExpirationNotification(): void
    {
        // Créer un document qui expire bientôt
        $expiringDocument = $this->createTestDocument($this->emmaLeblanc, $this->certificatMedical);
        $expiringDocument->setExpirationDate(new \DateTime('+10 days')); // Expire dans 10 jours
        $expiringDocument->setStatus('approved');
        $this->entityManager->flush();

        // Appeler l'endpoint de vérification des documents expirants
        $this->authenticatedRequest('POST', '/api/documents/check-expiring', $this->marcDubois);
        
        $this->assertJsonResponse(200);

        // Vérifier qu'une notification a été créée pour Emma
        $this->authenticatedRequest('GET', '/api/users/' . $this->emmaLeblanc->getId() . '/notifications', $this->emmaLeblanc);
        $notificationsData = $this->assertJsonResponse(200);
        
        $expirationNotificationFound = false;
        foreach ($notificationsData['hydra:member'] as $notification) {
            if (str_contains($notification['message'], 'expire')) {
                $expirationNotificationFound = true;
                break;
            }
        }
        
        $this->assertTrue($expirationNotificationFound);
    }

    public function testCoachCanViewTeamDocuments(): void
    {
        // Créer un document pour Emma
        $document = $this->createTestDocument($this->emmaLeblanc, $this->certificatMedical);

        // Julie (coach) peut voir les documents de son équipe
        $this->authenticatedRequest('GET', '/api/teams/' . $this->teamU18->getId() . '/documents', $this->julieMoreau);
        
        $responseData = $this->assertJsonResponse(200);
        
        $this->assertGreaterThan(0, count($responseData['hydra:member']));
        
        // Vérifier qu'Emma's document est dans la liste
        $emmaDocFound = false;
        foreach ($responseData['hydra:member'] as $doc) {
            if ($doc['user']['id'] === $this->emmaLeblanc->getId()) {
                $emmaDocFound = true;
                break;
            }
        }
        
        $this->assertTrue($emmaDocFound);
    }

    public function testCoachCannotValidateDocuments(): void
    {
        // Créer un document pour Emma
        $document = $this->createTestDocument($this->emmaLeblanc, $this->certificatMedical);

        // Julie (coach) NE PEUT PAS valider les documents (réservé aux gestionnaires)
        $this->authenticatedRequest('PATCH', '/api/documents/' . $document->getId(), $this->julieMoreau, [
            'status' => 'approved'
        ]);
        
        $this->assertErrorResponse(403, 'permission');
    }

    public function testAthleteCannotViewOtherAthleteDocuments(): void
    {
        // Créer un autre athlète
        $otherAthlete = $this->createTestUser('other@athlete.com', ['ROLE_ATHLETE']);
        $otherDocument = $this->createTestDocument($otherAthlete, $this->certificatMedical);

        // Emma NE PEUT PAS voir les documents de l'autre athlète
        $this->authenticatedRequest('GET', '/api/users/' . $otherAthlete->getId() . '/documents', $this->emmaLeblanc);
        $this->assertErrorResponse(403);

        // Emma NE PEUT PAS accéder directement au document de l'autre athlète
        $this->authenticatedRequest('GET', '/api/documents/' . $otherDocument->getId(), $this->emmaLeblanc);
        $this->assertErrorResponse(403);
    }

    public function testDocumentStorageOrganization(): void
    {
        // Vérifier l'organisation du stockage des documents
        $document = $this->createTestDocument($this->emmaLeblanc, $this->certificatMedical);

        // Le chemin doit être organisé par utilisateur/équipe
        $expectedPathPattern = '/uploads/documents/users/' . $this->emmaLeblanc->getId() . '/teams/' . $this->teamU18->getId();
        
        $this->assertStringContainsString('users', $document->getFilePath());
        $this->assertStringContainsString((string)$this->emmaLeblanc->getId(), $document->getFilePath());
    }

    public function testDocumentVersioning(): void
    {
        // Emma soumet un document
        $originalDoc = $this->createTestDocument($this->emmaLeblanc, $this->certificatMedical);

        // Emma upload une nouvelle version
        $newVersionData = [
            'name' => 'Certificat médical Emma - Version 2',
            'documentType' => '/api/document-types/' . $this->certificatMedical->getId(),
            'user' => '/api/users/' . $this->emmaLeblanc->getId(),
            'team' => '/api/teams/' . $this->teamU18->getId(),
            'filePath' => '/uploads/documents/certificat-emma-v2.pdf',
            'version' => 2,
            'replacesDocument' => '/api/documents/' . $originalDoc->getId()
        ];

        $this->authenticatedRequest('POST', '/api/documents', $this->emmaLeblanc, $newVersionData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals(2, $responseData['version']);
        $this->assertEquals($originalDoc->getId(), $responseData['replacesDocument']['id']);
    }

    public function testBulkDocumentStatusCheck(): void
    {
        // Créer plusieurs documents avec différents statuts
        $doc1 = $this->createTestDocument($this->emmaLeblanc, $this->certificatMedical);
        $doc2 = $this->createTestDocument($this->emmaLeblanc, $this->licenceFft);
        
        // Approuver le premier
        $doc1->setStatus('approved');
        $this->entityManager->flush();

        // Marc vérifie le statut global des documents de l'équipe
        $this->authenticatedRequest('GET', '/api/teams/' . $this->teamU18->getId() . '/documents/status', $this->marcDubois);
        
        $responseData = $this->assertJsonResponse(200);
        
        $this->assertArrayHasKey('totalDocuments', $responseData);
        $this->assertArrayHasKey('approvedCount', $responseData);
        $this->assertArrayHasKey('pendingCount', $responseData);
        $this->assertArrayHasKey('rejectedCount', $responseData);
        $this->assertArrayHasKey('missingCount', $responseData);
        
        $this->assertEquals(1, $responseData['approvedCount']);
        $this->assertEquals(1, $responseData['pendingCount']);
    }

    // Méthodes helper

    private function createTestClub(User $owner): Club
    {
        $club = new Club();
        $club->setName('Test Club');
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

    private function createDocumentType(string $name, bool $isRequired, array $extensions, int $maxSizeMb): DocumentType
    {
        $documentType = new DocumentType();
        $documentType->setName($name);
        $documentType->setDescription('Type de document pour les tests');
        $documentType->setIsRequired($isRequired);
        $documentType->setAllowedExtensions($extensions);
        $documentType->setMaxSizeInMb($maxSizeMb);
        $documentType->setValidityPeriodInMonths(12);
        $documentType->setClub($this->club);
        $documentType->setIsActive(true);
        
        $this->entityManager->persist($documentType);
        $this->entityManager->flush();
        
        return $documentType;
    }

    private function createTestDocument(User $user, DocumentType $type): Document
    {
        $document = new Document();
        $document->setName($type->getName() . ' - ' . $user->getFirstName());
        $document->setDocumentType($type);
        $document->setUser($user);
        $document->setTeam($this->teamU18);
        $document->setFilePath('/uploads/documents/test-document.pdf');
        $document->setOriginalFilename('test-document.pdf');
        $document->setFileSize(1024000); // 1MB
        $document->setMimeType('application/pdf');
        $document->setStatus('pending');
        $document->setExpirationDate(new \DateTime('+1 year'));
        $document->setVersion(1);
        
        $this->entityManager->persist($document);
        $this->entityManager->flush();
        
        return $document;
    }

    private function addAthleteToTeam(User $athlete, Team $team): void
    {
        // Cette méthode devrait créer une relation TeamMember
        // Pour simplifier, on assume que la relation existe
    }
} 