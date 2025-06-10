<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use App\Entity\User;
use App\Entity\Club;
use App\Entity\Team;
use App\Entity\DocumentType;
use App\Entity\Document;
use App\Entity\UserAuthentication;
use App\Entity\TeamMember;
use App\Enum\AuthProvider;
use App\Enum\DocumentType as DocumentTypeEnum;
use App\Enum\DocumentStatus;
use App\Enum\TeamMemberRole;

/**
 * Tests fonctionnels pour l'API d'upload de documents
 * Couvre les scénarios 2.1, 2.2, 2.3 du markdown
 */
class DocumentUploadApiTest extends ApiTestCase
{
    /**
     * Scénario 2.1 : Upload réussi par un athlète
     * Test Emma uploadant son certificat médical avec succès
     */
    public function testSuccessfulDocumentUpload(): void
    {
        // Given: Emma est membre de l'équipe U18 Filles avec type de document requis
        $clubData = $this->createClubWithTeamAndDocumentType();
        $emma = $clubData['emma'];
        $certificatType = $clubData['certificatType'];

        // When: Emma uploade son certificat médical
        $testFile = $this->createTestPdfFile('certificat_emma.pdf', 2.3);

        $this->authenticatedMultipartRequest(
            'POST', 
            '/api/documents', 
            $emma,
            [
                'documentTypeId' => $certificatType->getId(),
                'description' => 'Certificat médical 2025'
            ],
            ['document' => $testFile]
        );

        // Then: Le document est uploadé avec statut "PENDING"
        $responseData = $this->assertJsonResponse(201);

        $this->assertArrayHasKey('document', $responseData);
        $documentData = $responseData['document'];

        $this->assertEquals('PENDING', $documentData['status']);
        $this->assertEquals('certificat_emma.pdf', $documentData['originalFileName']);
        $this->assertEquals('Certificat médical 2025', $documentData['description']);
        $this->assertEquals('application/pdf', $documentData['mimeType']);
        $this->assertEquals($emma->getId(), $documentData['user']['id']);
        $this->assertEquals($certificatType->getId(), $documentData['documentType']['id']);

        // Vérifier en base de données
        $document = $this->entityManager->getRepository(Document::class)
            ->findOneBy(['user' => $emma, 'documentTypeEntity' => $certificatType]);

        $this->assertNotNull($document);
        $this->assertEquals(DocumentStatus::PENDING, $document->getStatus());
        $this->assertEquals('certificat_emma.pdf', $document->getOriginalFileName());
        $this->assertNotNull($document->getSecurePath());

        // Nettoyer le fichier de test
        unlink($testFile);
    }

    /**
     * Scénario 2.2 : Échec d'upload - fichier trop volumineux
     * Test Emma tentant d'uploader un fichier de 4.5MB
     */
    public function testUploadFileTooLarge(): void
    {
        // Given: Emma et un type de document
        $clubData = $this->createClubWithTeamAndDocumentType();
        $emma = $clubData['emma'];
        $licenceType = $clubData['licenceType'];

        // When: Emma tente d'uploader un fichier trop volumineux (simulé avec 60MB)
        $largeFile = $this->createTestPdfFile('licence_trop_grosse.pdf', 60);

        $this->authenticatedMultipartRequest(
            'POST', 
            '/api/documents', 
            $emma,
            [
                'documentTypeId' => $licenceType->getId(),
                'description' => 'Licence FFT trop grosse'
            ],
            ['document' => $largeFile]
        );

        // Then: L'upload échoue avec l'erreur appropriée
        $responseData = $this->assertJsonResponse(400);
        $this->assertStringContainsString('trop volumineux', $responseData['error']);

        // Vérifier qu'aucun document n'a été créé
        $document = $this->entityManager->getRepository(Document::class)
            ->findOneBy(['user' => $emma, 'documentTypeEntity' => $licenceType]);

        $this->assertNull($document);

        // Nettoyer le fichier de test
        unlink($largeFile);
    }

    /**
     * Scénario 2.3 : Upload avec extension non autorisée
     * Test Emma tentant d'uploader un fichier .docx
     */
    public function testUploadInvalidFileExtension(): void
    {
        // Given: Emma et un type de document
        $clubData = $this->createClubWithTeamAndDocumentType();
        $emma = $clubData['emma'];
        $certificatType = $clubData['certificatType'];

        // When: Emma tente d'uploader un fichier .docx (simulé avec extension différente)
        $docxFile = $this->createTestDocxFile('certificat.docx');

        $this->authenticatedMultipartRequest(
            'POST', 
            '/api/documents', 
            $emma,
            [
                'documentTypeId' => $certificatType->getId(),
                'description' => 'Certificat au mauvais format'
            ],
            ['document' => $docxFile]
        );

        // Then: L'upload échoue avec l'erreur appropriée
        $responseData = $this->assertJsonResponse(400);
        $this->assertStringContainsString('non autorisé', $responseData['error']);

        // Vérifier qu'aucun document n'a été créé
        $document = $this->entityManager->getRepository(Document::class)
            ->findOneBy(['user' => $emma, 'documentTypeEntity' => $certificatType]);

        $this->assertNull($document);

        // Nettoyer le fichier de test
        unlink($docxFile);
    }

    /**
     * Test d'upload de document sans authentification
     */
    public function testUploadDocumentUnauthenticated(): void
    {
        // When: Tentative d'upload sans authentification
        $testFile = $this->createTestPdfFile('test.pdf', 1);

        $this->client->request(
            'POST',
            '/api/documents',
            ['documentTypeId' => 1],
            ['document' => $testFile],
            ['CONTENT_TYPE' => 'multipart/form-data']
        );

        // Then: Doit retourner 401
        $this->assertJsonResponse(401);

        unlink($testFile);
    }

    /**
     * Test d'upload sans fichier
     */
    public function testUploadWithoutFile(): void
    {
        // Given: Emma authentifiée
        $clubData = $this->createClubWithTeamAndDocumentType();
        $emma = $clubData['emma'];
        $certificatType = $clubData['certificatType'];

        // When: Emma tente d'uploader sans fichier
        $this->authenticatedMultipartRequest(
            'POST', 
            '/api/documents', 
            $emma,
            [
                'documentTypeId' => $certificatType->getId(),
                'description' => 'Test sans fichier'
            ],
            [] // Pas de fichier
        );

        // Then: Doit retourner erreur 400
        $responseData = $this->assertJsonResponse(400);
        $this->assertStringContainsString('Aucun fichier fourni', $responseData['error']);
    }

    /**
     * Test d'upload avec type de document inexistant
     */
    public function testUploadWithInvalidDocumentType(): void
    {
        // Given: Emma authentifiée
        $clubData = $this->createClubWithTeamAndDocumentType();
        $emma = $clubData['emma'];

        // When: Emma tente d'uploader avec un type de document inexistant
        $testFile = $this->createTestPdfFile('test.pdf', 1);

        $this->authenticatedMultipartRequest(
            'POST', 
            '/api/documents', 
            $emma,
            [
                'documentTypeId' => 99999, // ID inexistant
                'description' => 'Test type inexistant'
            ],
            ['document' => $testFile]
        );

        // Then: Doit retourner erreur 400
        $responseData = $this->assertJsonResponse(400);
        $this->assertStringContainsString('Type de document non trouvé', $responseData['error']);

        unlink($testFile);
    }

    /**
     * Test d'upload d'image JPEG valide
     */
    public function testUploadValidJpegImage(): void
    {
        // Given: Emma et un type de document pour photo
        $clubData = $this->createClubWithTeamAndDocumentType();
        $emma = $clubData['emma'];
        
        // Créer un type de document pour photo
        $photoType = new DocumentType();
        $photoType->setName('Photo d\'identité');
        $photoType->setType(DocumentTypeEnum::IDENTITY_PHOTO);
        $photoType->setTeam($clubData['team']);
        $photoType->setIsRequired(false);
        $photoType->setHasExpirationDate(false);

        $this->entityManager->persist($photoType);
        $this->entityManager->flush();

        // When: Emma uploade une photo JPEG
        $jpegFile = $this->createTestJpegFile('photo_emma.jpg');

        $this->authenticatedMultipartRequest(
            'POST', 
            '/api/documents', 
            $emma,
            [
                'documentTypeId' => $photoType->getId(),
                'description' => 'Photo d\'identité Emma'
            ],
            ['document' => $jpegFile]
        );

        // Then: L'upload doit réussir
        $responseData = $this->assertJsonResponse(201);
        $this->assertEquals('image/jpeg', $responseData['document']['mimeType']);

        unlink($jpegFile);
    }

    // Helper methods

    private function createClubWithTeamAndDocumentType(): array
    {
        // Créer Marc (propriétaire)
        $marc = $this->createTestUser('marc@racing.fr', ['ROLE_USER'], [
            'firstName' => 'Marc',
            'lastName' => 'Dubois',
            'dateOfBirth' => '1975-05-15'
        ]);

        // Créer Emma (athlète)
        $emma = $this->createTestUser('emma@test.com', ['ROLE_USER'], [
            'firstName' => 'Emma',
            'lastName' => 'Leblanc',
            'dateOfBirth' => '2008-03-10' // 16 ans
        ]);

        // Créer le club Racing Club Paris
        $racingClub = new Club();
        $racingClub->setName('Racing Club Paris');
        $racingClub->setOwner($marc);
        $racingClub->setIsPublic(true);
        $racingClub->setAllowJoinRequests(true);

        $this->entityManager->persist($racingClub);

        // Créer l'équipe U18 Filles
        $u18Team = new Team();
        $u18Team->setName('U18 Filles');
        $u18Team->setClub($racingClub);
        $u18Team->setMinAge(16);
        $u18Team->setMaxAge(18);
        $u18Team->setGender('F');

        $this->entityManager->persist($u18Team);

        // Ajouter Emma à l'équipe
        $teamMember = new TeamMember();
        $teamMember->setUser($emma);
        $teamMember->setTeam($u18Team);
        $teamMember->setRole(TeamMemberRole::PLAYER);
        $teamMember->setIsActive(true);

        $this->entityManager->persist($teamMember);

        // Créer les types de documents
        $certificatType = new DocumentType();
        $certificatType->setName('Certificat médical');
        $certificatType->setDescription('Certificat d\'aptitude sportive obligatoire');
        $certificatType->setType(DocumentTypeEnum::MEDICAL_CERTIFICATE);
        $certificatType->setTeam($u18Team);
        $certificatType->setIsRequired(true);
        $certificatType->setHasExpirationDate(true);
        $certificatType->setValidityDurationInDays(365);

        $this->entityManager->persist($certificatType);

        $licenceType = new DocumentType();
        $licenceType->setName('Licence FFT');
        $licenceType->setType(DocumentTypeEnum::LICENSE);
        $licenceType->setTeam($u18Team);
        $licenceType->setIsRequired(true);
        $licenceType->setHasExpirationDate(true);
        $licenceType->setValidityDurationInDays(365);

        $this->entityManager->persist($licenceType);

        $this->entityManager->flush();

        return [
            'marc' => $marc,
            'emma' => $emma,
            'club' => $racingClub,
            'team' => $u18Team,
            'certificatType' => $certificatType,
            'licenceType' => $licenceType
        ];
    }

    private function createTestPdfFile(string $filename, float $sizeMB): string
    {
        $tempFile = sys_get_temp_dir() . '/' . $filename;
        $sizeBytes = (int)($sizeMB * 1024 * 1024);
        
        // Créer un fichier PDF minimal mais valide
        $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n>>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000010 00000 n \n0000000079 00000 n \n0000000136 00000 n \ntrailer\n<<\n/Size 4\n/Root 1 0 R\n>>\nstartxref\n224\n%%EOF";
        
        // Remplir jusqu'à la taille souhaitée
        $padding = str_repeat(' ', max(0, $sizeBytes - strlen($pdfContent)));
        file_put_contents($tempFile, $pdfContent . $padding);
        
        return $tempFile;
    }

    private function createTestJpegFile(string $filename): string
    {
        $tempFile = sys_get_temp_dir() . '/' . $filename;
        
        // Créer une image JPEG de test (50x50 pixels rouge)
        $image = imagecreatetruecolor(50, 50);
        $red = imagecolorallocate($image, 255, 0, 0);
        imagefill($image, 0, 0, $red);
        imagejpeg($image, $tempFile, 90);
        imagedestroy($image);
        
        return $tempFile;
    }

    private function createTestDocxFile(string $filename): string
    {
        $tempFile = sys_get_temp_dir() . '/' . $filename;
        
        // Créer un fichier avec une signature DOCX (mais pas vraiment valide)
        $docxHeader = "PK\x03\x04"; // Signature ZIP
        file_put_contents($tempFile, $docxHeader . str_repeat('x', 1000));
        
        return $tempFile;
    }
} 