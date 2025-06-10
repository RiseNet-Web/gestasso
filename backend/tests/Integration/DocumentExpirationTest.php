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
 * Tests d'intégration pour la gestion des expirations de documents
 * Couvre les scénarios 4.1, 4.2 du markdown (expiration automatique, rappels)
 */
class DocumentExpirationTest extends ApiTestCase
{
    private User $marc;
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
     * Scénario 4.1 : Document expiré automatiquement
     * Test que le certificat médical d'Emma expire automatiquement après 365 jours
     */
    public function testDocumentExpiresAutomatically(): void
    {
        // Given: Emma a un certificat médical validé qui a expiré
        $document = $this->createValidatedDocument();
        
        // Forcer l'expiration en définissant une date dans le passé
        $expiredDate = new \DateTime('-10 days');
        $document->setExpirationDate($expiredDate);
        $this->entityManager->flush();

        // When: Le système vérifie les expirations (simulation)
        $this->assertTrue($document->isExpired());

        // Then: Le document est automatiquement marqué comme expiré
        $document->checkAndUpdateExpiredStatus();
        $this->assertEquals(DocumentStatus::EXPIRED, $document->getStatus());
    }

    /**
     * Scénario 4.2 : Rappel avant expiration
     * Test que le système génère des rappels avant expiration
     */
    public function testExpirationReminder(): void
    {
        // Given: Le certificat d'Emma expire dans 30 jours
        $document = $this->createValidatedDocument();
        
        $expirationDate = new \DateTime('+30 days');
        $document->setExpirationDate($expirationDate);
        $this->entityManager->flush();

        // When: Le système vérifie les expirations prochaines
        $expiringSoon = $document->isExpiringSoon(45); // Dans les 45 prochains jours

        // Then: Le document est identifié comme expirant bientôt
        $this->assertTrue($expiringSoon);
        $this->assertFalse($document->isExpired());
        $this->assertEquals(DocumentStatus::APPROVED, $document->getStatus());
    }

    /**
     * Test de récupération des documents expirants par équipe
     */
    public function testGetExpiringDocumentsByTeam(): void
    {
        // Given: Plusieurs documents avec différentes dates d'expiration
        $validDocument = $this->createValidatedDocument();
        $validDocument->setExpirationDate(new \DateTime('+100 days'));

        $expiringSoonDocument = $this->createValidatedDocument();
        $expiringSoonDocument->setExpirationDate(new \DateTime('+20 days'));

        $expiredDocument = $this->createValidatedDocument();
        $expiredDocument->setExpirationDate(new \DateTime('-5 days'));

        $this->entityManager->flush();

        // When: Marc récupère les documents de son équipe
        $this->authenticatedRequest('GET', "/api/documents/team/{$this->u18Team->getId()}?status=expiring", $this->marc);
        
        // Then: Les documents expirants sont identifiés
        $response = $this->assertJsonResponse(200);
        
        // Vérifier que nous avons des documents
        $this->assertArrayHasKey('documents', $response);
        
        // Tester les méthodes d'expiration sur les documents
        $this->assertFalse($validDocument->isExpiringSoon(30));
        $this->assertTrue($expiringSoonDocument->isExpiringSoon(30));
        $this->assertTrue($expiredDocument->isExpired());
    }

    /**
     * Test des statistiques d'expiration pour un club
     */
    public function testClubExpirationStatistics(): void
    {
        // Given: Plusieurs documents avec différents statuts d'expiration
        $validDoc = $this->createValidatedDocument();
        $validDoc->setExpirationDate(new \DateTime('+100 days'));

        $expiringSoonDoc = $this->createValidatedDocument();
        $expiringSoonDoc->setExpirationDate(new \DateTime('+20 days'));

        $expiredDoc = $this->createValidatedDocument();
        $expiredDoc->setExpirationDate(new \DateTime('-5 days'));
        $expiredDoc->setStatus(DocumentStatus::EXPIRED);

        $this->entityManager->flush();

        // When: Marc consulte les statistiques du club
        $this->authenticatedRequest('GET', "/api/documents/club/{$this->racingClub->getId()}", $this->marc);
        
        // Then: Les statistiques incluent les documents expirés
        $response = $this->assertJsonResponse(200);
        
        $this->assertArrayHasKey('stats', $response);
        $stats = $response['stats'];
        
        // Vérifier que nous avons au moins un document expiré dans les stats
        $this->assertArrayHasKey('expired', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['expired']);
    }

    /**
     * Test de validation avec calcul automatique de la date d'expiration
     */
    public function testValidationSetsExpirationDate(): void
    {
        // Given: Emma a uploadé un certificat médical (non validé)
        $document = $this->createPendingDocument();

        // When: Marc valide le document
        $this->authenticatedRequest('PUT', "/api/documents/{$document->getId()}/validate", $this->marc, [
            'status' => 'APPROVED',
            'validationNotes' => 'Document valide'
        ]);

        // Then: La date d'expiration est automatiquement calculée
        $response = $this->assertJsonResponse(200);
        
        $this->assertNotNull($response['document']['expirationDate']);
        $this->assertEquals('APPROVED', $response['document']['status']);

        // Vérifier en base de données
        $this->entityManager->refresh($document);
        $this->assertNotNull($document->getExpirationDate());
        
        // Vérifier que la date d'expiration est dans environ 365 jours
        $expectedExpiration = new \DateTime('+365 days');
        $actualExpiration = $document->getExpirationDate();
        
        $diff = $actualExpiration->diff($expectedExpiration)->days;
        $this->assertLessThan(2, $diff); // Tolérance de 1 jour
    }

    /**
     * Test que les documents non expirables n'ont pas de date d'expiration
     */
    public function testNonExpirableDocumentHasNoExpirationDate(): void
    {
        // Given: Un type de document sans expiration (autorisation parentale)
        $autorisationType = new DocumentType();
        $autorisationType->setName('Autorisation parentale');
        $autorisationType->setType(DocumentTypeEnum::PARENTAL_AUTHORIZATION);
        $autorisationType->setTeam($this->u18Team);
        $autorisationType->setIsRequired(true);
        $autorisationType->setHasExpirationDate(false); // Pas d'expiration
        $autorisationType->setValidityDurationInDays(null);

        $this->entityManager->persist($autorisationType);

        $document = new Document();
        $document->setUser($this->emma);
        $document->setDocumentTypeEntity($autorisationType);
        $document->setOriginalFileName('autorisation.pdf');
        $document->setSecurePath('secure/' . uniqid() . '.pdf');
        $document->setMimeType('application/pdf');
        $document->setFileSize(1024000);
        $document->setStatus(DocumentStatus::PENDING);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        // When: Marc valide le document
        $this->authenticatedRequest('PUT', "/api/documents/{$document->getId()}/validate", $this->marc, [
            'status' => 'APPROVED',
            'validationNotes' => 'Autorisation valide'
        ]);

        // Then: Aucune date d'expiration n'est définie
        $response = $this->assertJsonResponse(200);
        
        $this->assertNull($response['document']['expirationDate']);
        $this->assertEquals('APPROVED', $response['document']['status']);

        // Vérifier en base de données
        $this->entityManager->refresh($document);
        $this->assertNull($document->getExpirationDate());
    }

    /**
     * Test de recherche de documents par statut d'expiration
     */
    public function testSearchDocumentsByExpirationStatus(): void
    {
        // Given: Documents avec différents statuts
        $validDoc = $this->createValidatedDocument();
        $validDoc->setExpirationDate(new \DateTime('+100 days'));

        $expiredDoc = $this->createValidatedDocument();
        $expiredDoc->setExpirationDate(new \DateTime('-5 days'));
        $expiredDoc->setStatus(DocumentStatus::EXPIRED);

        $this->entityManager->flush();

        // When: Recherche des documents expirés
        $this->authenticatedRequest('GET', "/api/documents/team/{$this->u18Team->getId()}?status=EXPIRED", $this->marc);
        
        $expiredResponse = $this->assertJsonResponse(200);
        
        // Then: Seuls les documents expirés sont retournés
        $expiredDocs = $expiredResponse['documents'];
        $this->assertNotEmpty($expiredDocs);
        
        foreach ($expiredDocs as $doc) {
            $this->assertEquals('EXPIRED', $doc['status']);
        }

        // When: Recherche des documents validés (non expirés)
        $this->authenticatedRequest('GET', "/api/documents/team/{$this->u18Team->getId()}?status=APPROVED", $this->marc);
        
        $validResponse = $this->assertJsonResponse(200);
        
        // Then: Seuls les documents validés sont retournés
        $validDocs = $validResponse['documents'];
        $this->assertNotEmpty($validDocs);
        
        foreach ($validDocs as $doc) {
            $this->assertEquals('APPROVED', $doc['status']);
        }
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
        $teamMember = new TeamMember();
        $teamMember->setUser($this->emma);
        $teamMember->setTeam($this->u18Team);
        $teamMember->setRole(TeamMemberRole::PLAYER);
        $teamMember->setIsActive(true);

        $this->entityManager->persist($this->racingClub);
        $this->entityManager->persist($this->u18Team);
        $this->entityManager->persist($this->certificatType);
        $this->entityManager->persist($teamMember);
        $this->entityManager->flush();
    }

    private function createValidatedDocument(): Document
    {
        $document = new Document();
        $document->setUser($this->emma);
        $document->setDocumentTypeEntity($this->certificatType);
        $document->setOriginalFileName('certificat_emma.pdf');
        $document->setSecurePath('secure/' . uniqid() . '.pdf');
        $document->setMimeType('application/pdf');
        $document->setFileSize(2411724);
        $document->setStatus(DocumentStatus::APPROVED);
        $document->setValidatedBy($this->marc);
        $document->setValidatedAt(new \DateTime('-30 days'));
        
        // Date d'expiration par défaut dans 335 jours (365 - 30)
        $document->setExpirationDate(new \DateTime('+335 days'));

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    private function createPendingDocument(): Document
    {
        $document = new Document();
        $document->setUser($this->emma);
        $document->setDocumentTypeEntity($this->certificatType);
        $document->setOriginalFileName('certificat_pending.pdf');
        $document->setSecurePath('secure/' . uniqid() . '.pdf');
        $document->setMimeType('application/pdf');
        $document->setFileSize(2411724);
        $document->setStatus(DocumentStatus::PENDING);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }
} 