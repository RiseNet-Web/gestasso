<?php

namespace App\Tests\Unit\Service;

use App\Entity\DocumentType;
use App\Entity\Team;
use App\Entity\Club;
use App\Entity\User;
use App\Enum\DocumentType as DocumentTypeEnum;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Tests unitaires pour la logique métier des types de documents
 * Couvre la création, modification et validation des types de documents
 */
class DocumentTypeServiceTest extends TestCase
{
    private DocumentService $documentService;
    private EntityManagerInterface|MockObject $entityManager;
    private ValidatorInterface|MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        
        $this->documentService = new DocumentService(
            $this->entityManager,
            $this->createMock(\Symfony\Component\String\Slugger\SluggerInterface::class),
            $this->validator,
            $this->createMock(\Psr\Log\LoggerInterface::class),
            '/tmp'
        );
    }

    /**
     * Scénario 1.1 : Création de types de documents obligatoires
     * Test Marc Dubois créant un certificat médical obligatoire
     */
    public function testCreateMandatoryDocumentType(): void
    {
        // Given: Marc est propriétaire du Racing Club Paris avec une équipe U18 Filles
        $marc = $this->createUser('marc@racing.fr', 'Marc', 'Dubois');
        $racingClub = $this->createClub('Racing Club Paris', $marc);
        $u18Team = $this->createTeam('U18 Filles', $racingClub);

        // When: Il crée un type de document "Certificat médical"
        $documentTypeData = [
            'name' => 'Certificat médical',
            'description' => 'Certificat d\'aptitude sportive obligatoire',
            'type' => DocumentTypeEnum::MEDICAL_CERTIFICATE,
            'isRequired' => true,
            'isExpirable' => true,
            'validityDurationInDays' => 365
        ];

        // Mock validator success
        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(DocumentType::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // Then: Le type est créé avec les bonnes propriétés
        $documentType = $this->documentService->createDocumentType($u18Team, $documentTypeData);

        $this->assertInstanceOf(DocumentType::class, $documentType);
        $this->assertEquals('Certificat médical', $documentType->getName());
        $this->assertEquals('Certificat d\'aptitude sportive obligatoire', $documentType->getDescription());
        $this->assertEquals(DocumentTypeEnum::MEDICAL_CERTIFICATE, $documentType->getType());
        $this->assertTrue($documentType->isRequired());
        $this->assertTrue($documentType->hasExpirationDate());
        $this->assertEquals(365, $documentType->getValidityDurationInDays());
        $this->assertEquals($u18Team, $documentType->getTeam());
        $this->assertTrue($documentType->isActive());
    }

    /**
     * Scénario 1.2 : Configuration par équipe spécifique
     * Test de configuration de différents types pour l'équipe U18 Filles
     */
    public function testConfigureTeamSpecificDocumentTypes(): void
    {
        // Given: L'équipe U18 Filles existe
        $marc = $this->createUser('marc@racing.fr', 'Marc', 'Dubois');
        $racingClub = $this->createClub('Racing Club Paris', $marc);
        $u18Team = $this->createTeam('U18 Filles', $racingClub);

        // Mock validator success pour tous les types
        $this->validator->expects($this->exactly(4))
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager->expects($this->exactly(4))
            ->method('persist');

        $this->entityManager->expects($this->exactly(4))
            ->method('flush');

        // When: Marc configure les documents requis pour cette équipe
        $documentTypes = [
            [
                'name' => 'Certificat médical',
                'type' => DocumentTypeEnum::MEDICAL_CERTIFICATE,
                'isRequired' => true,
                'isExpirable' => true,
                'validityDurationInDays' => 365
            ],
            [
                'name' => 'Licence FFT',
                'type' => DocumentTypeEnum::LICENSE,
                'isRequired' => true,
                'isExpirable' => true,
                'validityDurationInDays' => 365
            ],
            [
                'name' => 'Autorisation parentale',
                'type' => DocumentTypeEnum::AUTHORIZATION,
                'isRequired' => true,
                'isExpirable' => false,
                'validityDurationInDays' => null
            ],
            [
                'name' => 'Photo d\'identité',
                'type' => DocumentTypeEnum::PHOTO,
                'isRequired' => false,
                'isExpirable' => false,
                'validityDurationInDays' => null
            ]
        ];

        $createdTypes = [];
        foreach ($documentTypes as $typeData) {
            $createdTypes[] = $this->documentService->createDocumentType($u18Team, $typeData);
        }

        // Then: Chaque type a ses propres paramètres de validation
        $this->assertCount(4, $createdTypes);
        
        // Certificat médical
        $this->assertEquals('Certificat médical', $createdTypes[0]->getName());
        $this->assertTrue($createdTypes[0]->isRequired());
        $this->assertTrue($createdTypes[0]->hasExpirationDate());
        $this->assertEquals(365, $createdTypes[0]->getValidityDurationInDays());

        // Photo d'identité (optionnel)
        $this->assertEquals('Photo d\'identité', $createdTypes[3]->getName());
        $this->assertFalse($createdTypes[3]->isRequired());
        $this->assertFalse($createdTypes[3]->hasExpirationDate());
        $this->assertNull($createdTypes[3]->getValidityDurationInDays());
    }

    /**
     * Test de validation des données invalides
     */
    public function testCreateDocumentTypeWithInvalidData(): void
    {
        $team = $this->createTeam('Test Team', $this->createClub('Test Club', $this->createUser()));

        // Mock validator avec erreurs
        $violations = new ConstraintViolationList();
        $violation = $this->createMock(\Symfony\Component\Validator\ConstraintViolation::class);
        $violation->method('getPropertyPath')->willReturn('name');
        $violation->method('getMessage')->willReturn('Le nom est obligatoire');
        $violations->add($violation);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $this->expectException(\InvalidArgumentException::class);

        $this->documentService->createDocumentType($team, [
            'name' => '', // Nom vide pour déclencher l'erreur
            'type' => DocumentTypeEnum::OTHER, // Ajouter un type valide
            'isRequired' => true
        ]);
    }

    /**
     * Test des méthodes helper pour la validité
     */
    public function testDocumentTypeValidityHelpers(): void
    {
        $documentType = new DocumentType();
        $documentType->setHasExpirationDate(true);
        $documentType->setValidityDurationInDays(365);

        // Test isExpirable
        $this->assertTrue($documentType->isExpirable());

        // Test conversion en mois et années
        $this->assertEquals(12.0, $documentType->getValidityDurationInMonths());
        $this->assertEquals(1.0, $documentType->getValidityDurationInYears());

        // Test sans expiration
        $nonExpirableType = new DocumentType();
        $nonExpirableType->setHasExpirationDate(false);
        $this->assertFalse($nonExpirableType->isExpirable());
        $this->assertNull($nonExpirableType->getValidityDurationInMonths());
    }

    /**
     * Test des statistiques de documents
     */
    public function testDocumentTypeStatistics(): void
    {
        $documentType = new DocumentType();
        
        // Test getDocumentCount avec collection vide
        $this->assertEquals(0, $documentType->getDocumentCount());

        // Les autres méthodes de statistiques sont testées dans les tests d'intégration
        // car elles nécessitent des documents réels
    }

    // Helper methods

    private function createUser(string $email = 'test@test.com', string $firstName = 'Test', string $lastName = 'User'): User
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
} 