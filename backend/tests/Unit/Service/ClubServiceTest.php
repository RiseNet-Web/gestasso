<?php

namespace App\Tests\Unit\Service;

use App\Entity\Club;
use App\Entity\User;
use App\Entity\Season;
use App\Service\ClubService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ClubServiceTest extends TestCase
{
    private ClubService $clubService;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private ImageService $imageService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->imageService = $this->createMock(ImageService::class);

        $this->clubService = new ClubService(
            $this->entityManager,
            $this->validator,
            $this->imageService
        );
    }

    /**
     * Scénario 1.1 : Création de club basique
     * Marc Dubois crée son club "Racing Club Paris"
     */
    public function testCreateBasicClub(): void
    {
        // Given: Un propriétaire et des données basiques
        $owner = $this->createOwner('marc@test.com', 'Marc', 'Dubois');
        $clubData = [
            'name' => 'Racing Club Paris',
            'description' => 'Club de tennis parisien',
            'isPublic' => true,
            'allowJoinRequests' => true
        ];

        // Mock successful validation
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        
        // Mock entity manager
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // When: Création du club
        $club = $this->clubService->createClub($owner, $clubData);

        // Then: Le club est créé correctement
        $this->assertInstanceOf(Club::class, $club);
        $this->assertEquals('Racing Club Paris', $club->getName());
        $this->assertEquals('Club de tennis parisien', $club->getDescription());
        $this->assertEquals($owner, $club->getOwner());
        $this->assertTrue($club->isPublic());
        $this->assertTrue($club->allowJoinRequests());
        $this->assertTrue($club->isActive());
    }

    /**
     * Scénario 1.2 : Création de club avec logo
     */
    public function testCreateClubWithLogo(): void
    {
        // Given: Un propriétaire, des données et un logo
        $owner = $this->createOwner('marc@test.com', 'Marc', 'Dubois');
        $clubData = [
            'name' => 'Racing Club Paris',
            'description' => 'Club de tennis parisien',
            'isPublic' => true,
            'allowJoinRequests' => true
        ];

        $logoFile = $this->createMockUploadedFile('logo.png', 'image/png', 2048);

        // Mock image service
        $this->imageService->method('uploadClubLogo')->willReturn('clubs/logo_12345.png');
        $this->imageService->expects($this->once())->method('resizeImageIfNeeded');

        // Mock successful validation
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        
        // Mock entity manager
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // When: Création du club avec logo
        $club = $this->clubService->createClub($owner, $clubData, $logoFile);

        // Then: Le club est créé avec le logo
        $this->assertEquals('clubs/logo_12345.png', $club->getImagePath());
    }

    /**
     * Scénario 1.3 : Création de club privé sans demandes d'adhésion
     */
    public function testCreatePrivateClubWithoutJoinRequests(): void
    {
        // Given: Un propriétaire et des données pour un club privé
        $owner = $this->createOwner('marc@test.com', 'Marc', 'Dubois');
        $clubData = [
            'name' => 'Club Elite Tennis',
            'description' => 'Club privé réservé aux membres invités',
            'isPublic' => false,
            'allowJoinRequests' => false
        ];

        // Mock successful validation
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        
        // Mock entity manager
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // When: Création du club
        $club = $this->clubService->createClub($owner, $clubData);

        // Then: Le club est créé avec les bonnes propriétés
        $this->assertEquals('Club Elite Tennis', $club->getName());
        $this->assertFalse($club->isPublic());
        $this->assertFalse($club->allowJoinRequests());
    }

    /**
     * Scénario 1.4 : Échec de création avec données invalides
     */
    public function testCreateClubWithInvalidData(): void
    {
        // Given: Un propriétaire et des données invalides
        $owner = $this->createOwner('marc@test.com', 'Marc', 'Dubois');
        $clubData = [
            'name' => '', // Nom vide
            'description' => str_repeat('x', 1000), // Description trop longue
        ];

        // Mock validation with errors
        $violations = new ConstraintViolationList();
        $violation = $this->createMock(\Symfony\Component\Validator\ConstraintViolation::class);
        $violation->method('getPropertyPath')->willReturn('name');
        $violation->method('getMessage')->willReturn('Le nom du club est obligatoire');
        $violations->add($violation);

        $this->validator->method('validate')->willReturn($violations);

        // When & Then: La création doit échouer
        $this->expectException(\InvalidArgumentException::class);
        $this->clubService->createClub($owner, $clubData);
    }

    /**
     * Scénario 1.5 : Mise à jour du club
     */
    public function testUpdateClub(): void
    {
        // Given: Un club existant
        $club = new Club();
        $club->setName('Racing Club Paris')
             ->setDescription('Club de tennis')
             ->setIsPublic(true)
             ->setAllowJoinRequests(true);

        $updateData = [
            'name' => 'Racing Club Paris International',
            'description' => 'Club de tennis international',
            'isPublic' => false,
            'allowJoinRequests' => false
        ];

        // Mock successful validation
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        // When: Mise à jour du club
        $updatedClub = $this->clubService->updateClub($club, $updateData);

        // Then: Le club est mis à jour
        $this->assertEquals('Racing Club Paris International', $updatedClub->getName());
        $this->assertEquals('Club de tennis international', $updatedClub->getDescription());
        $this->assertFalse($updatedClub->isPublic());
        $this->assertFalse($updatedClub->allowJoinRequests());
    }

    /**
     * Scénario 1.6 : Mise à jour du logo du club
     */
    public function testUpdateClubLogo(): void
    {
        // Given: Un club existant avec un ancien logo
        $club = new Club();
        $club->setName('Racing Club Paris')
             ->setImagePath('clubs/old_logo.png');

        $newLogoFile = $this->createMockUploadedFile('new_logo.jpg', 'image/jpeg', 1024);

        // Mock image service
        $this->imageService->expects($this->once())->method('deleteClubImage')->with('clubs/old_logo.png');
        $this->imageService->method('uploadClubLogo')->willReturn('clubs/new_logo_67890.jpg');
        $this->imageService->expects($this->once())->method('resizeImageIfNeeded');

        // Mock successful validation
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        // When: Mise à jour avec nouveau logo
        $updatedClub = $this->clubService->updateClub($club, [], $newLogoFile);

        // Then: Le logo est mis à jour
        $this->assertEquals('clubs/new_logo_67890.jpg', $updatedClub->getImagePath());
    }

    /**
     * Scénario 1.7 : Suppression du club
     */
    public function testDeleteClub(): void
    {
        // Given: Un club existant avec un logo
        $club = new Club();
        $club->setName('Racing Club Paris')
             ->setImagePath('clubs/logo.png')
             ->setIsActive(true);

        // Mock image service pour supprimer le logo
        $this->imageService->expects($this->once())->method('deleteClubImage')->with('clubs/logo.png');

        // When: Suppression du club
        $this->clubService->deleteClub($club);

        // Then: Le club est marqué comme inactif
        $this->assertFalse($club->isActive());
    }

    /**
     * Scénario 1.8 : Erreur lors de l'upload du logo
     */
    public function testCreateClubWithLogoUploadError(): void
    {
        // Given: Un propriétaire, des données et un logo qui cause une erreur
        $owner = $this->createOwner('marc@test.com', 'Marc', 'Dubois');
        $clubData = [
            'name' => 'Racing Club Paris',
            'description' => 'Club de tennis parisien'
        ];

        $logoFile = $this->createMockUploadedFile('invalid_logo.txt', 'text/plain', 2048);

        // Mock image service error
        $this->imageService->method('uploadClubLogo')
            ->willThrowException(new \InvalidArgumentException('Type de fichier non supporté'));

        // When & Then: La création doit échouer avec une erreur spécifique
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Erreur lors de l\'upload du logo : Type de fichier non supporté');

        $this->clubService->createClub($owner, $clubData, $logoFile);
    }

    // Helper methods

    private function createOwner(string $email, string $firstName, string $lastName): User
    {
        $owner = new User();
        $owner->setEmail($email)
              ->setFirstName($firstName)
              ->setLastName($lastName)
              ->setRoles(['ROLE_CLUB_OWNER']);

        return $owner;
    }

    private function createMockUploadedFile(string $name, string $mimeType, int $size): UploadedFile
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn($name);
        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('getSize')->willReturn($size);
        $file->method('getClientOriginalExtension')->willReturn(pathinfo($name, PATHINFO_EXTENSION));

        return $file;
    }
} 