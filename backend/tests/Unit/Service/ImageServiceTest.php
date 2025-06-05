<?php

namespace App\Tests\Unit\Service;

use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImageServiceTest extends TestCase
{
    private ImageService $imageService;
    private EntityManagerInterface $entityManager;
    private SluggerInterface $slugger;
    private ValidatorInterface $validator;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        
        // Créer un répertoire temporaire pour les tests
        $this->tempDir = sys_get_temp_dir() . '/gestasso_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        $this->imageService = new ImageService(
            $this->entityManager,
            $this->slugger,
            $this->validator,
            $this->tempDir
        );
    }

    protected function tearDown(): void
    {
        // Nettoyer le répertoire temporaire
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTestImage(string $extension = 'jpg', int $width = 100, int $height = 100): string
    {
        $tempFile = tempnam($this->tempDir, 'test_image') . '.' . $extension;
        
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 255, 0, 0); // Rouge
        imagefill($image, 0, 0, $color);
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $tempFile);
                break;
            case 'png':
                imagepng($image, $tempFile);
                break;
            case 'gif':
                imagegif($image, $tempFile);
                break;
            case 'webp':
                imagewebp($image, $tempFile);
                break;
        }
        
        imagedestroy($image);
        return $tempFile;
    }

    public function testUploadClubLogoSuccess(): void
    {
        // Créer une image de test
        $testImagePath = $this->createTestImage('jpg', 200, 150);
        
        $uploadedFile = new UploadedFile(
            $testImagePath,
            'logo.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->slugger->expects($this->once())
            ->method('slug')
            ->with('logo')
            ->willReturn('logo');

        $result = $this->imageService->uploadClubLogo($uploadedFile);

        $this->assertStringStartsWith('/uploads/clubs/', $result);
        $this->assertStringContainsString('logo-', $result);
        $this->assertStringEndsWith('.jpg', $result);
        
        // Vérifier que le fichier a été créé
        $fullPath = $this->tempDir . '/public' . $result;
        $this->assertFileExists($fullPath);
    }

    public function testUploadClubLogoInvalidMimeType(): void
    {
        // Créer un fichier texte au lieu d'une image
        $tempFile = tempnam($this->tempDir, 'test_file') . '.txt';
        file_put_contents($tempFile, 'This is not an image');
        
        $uploadedFile = new UploadedFile(
            $tempFile,
            'file.txt',
            'text/plain',
            null,
            true
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Type de fichier non autorisé');

        $this->imageService->uploadClubLogo($uploadedFile);
    }

    public function testUploadClubLogoFileTooLarge(): void
    {
        $testImagePath = $this->createTestImage('jpg', 200, 150);
        
        // Simuler un fichier trop volumineux en modifiant la taille
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getMimeType')->willReturn('image/jpeg');
        $uploadedFile->method('getSize')->willReturn(10 * 1024 * 1024); // 10MB
        $uploadedFile->method('isValid')->willReturn(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fichier est trop volumineux');

        $this->imageService->uploadClubLogo($uploadedFile);
    }

    public function testUploadClubLogoImageTooSmall(): void
    {
        // Créer une très petite image (moins de 50x50)
        $testImagePath = $this->createTestImage('jpg', 30, 20);
        
        $uploadedFile = new UploadedFile(
            $testImagePath,
            'small.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('image est trop petite');

        $this->imageService->uploadClubLogo($uploadedFile);
    }

    public function testUploadClubLogoImageTooLarge(): void
    {
        // Créer une très grande image (plus de 2000x2000)
        $testImagePath = $this->createTestImage('jpg', 2500, 2100);
        
        $uploadedFile = new UploadedFile(
            $testImagePath,
            'large.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('image est trop grande');

        $this->imageService->uploadClubLogo($uploadedFile);
    }

    public function testDeleteClubImageSuccess(): void
    {
        // Créer un fichier temporaire
        $imagePath = '/uploads/clubs/test-image.jpg';
        $fullPath = $this->tempDir . '/public' . $imagePath;
        
        // Créer les répertoires nécessaires
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($fullPath, 'fake image content');
        $this->assertFileExists($fullPath);

        $result = $this->imageService->deleteClubImage($imagePath);
        
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($fullPath);
    }

    public function testDeleteClubImageNonExistent(): void
    {
        $result = $this->imageService->deleteClubImage('/uploads/clubs/non-existent.jpg');
        
        // Doit retourner true même si le fichier n'existe pas
        $this->assertTrue($result);
    }

    public function testDeleteClubImageEmptyPath(): void
    {
        $result = $this->imageService->deleteClubImage('');
        
        $this->assertTrue($result);
    }

    public function testGetImageUrlWithValidPath(): void
    {
        $imagePath = '/uploads/clubs/logo.jpg';
        $result = $this->imageService->getImageUrl($imagePath);
        
        $this->assertEquals($imagePath, $result);
    }

    public function testGetImageUrlWithoutPrefix(): void
    {
        $imagePath = 'clubs/logo.jpg';
        $result = $this->imageService->getImageUrl($imagePath);
        
        $this->assertEquals('/uploads/clubs/logo.jpg', $result);
    }

    public function testGetImageUrlWithNull(): void
    {
        $result = $this->imageService->getImageUrl(null);
        
        $this->assertNull($result);
    }

    public function testGetImageUrlWithEmptyString(): void
    {
        $result = $this->imageService->getImageUrl('');
        
        $this->assertNull($result);
    }

    public function testResizeImageIfNeeded(): void
    {
        // Créer une grande image
        $testImagePath = $this->createTestImage('jpg', 1200, 900);
        
        // Copier l'image dans le répertoire de destination
        $imagePath = '/uploads/clubs/large-image.jpg';
        $fullPath = $this->tempDir . '/public' . $imagePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($testImagePath, $fullPath);

        // Vérifier les dimensions avant redimensionnement
        [$originalWidth, $originalHeight] = getimagesize($fullPath);
        $this->assertEquals(1200, $originalWidth);
        $this->assertEquals(900, $originalHeight);

        // Redimensionner
        $this->imageService->resizeImageIfNeeded($imagePath, 800, 600);

        // Vérifier les nouvelles dimensions
        [$newWidth, $newHeight] = getimagesize($fullPath);
        $this->assertLessThanOrEqual(800, $newWidth);
        $this->assertLessThanOrEqual(600, $newHeight);
        
        // Vérifier que les proportions sont conservées
        $originalRatio = $originalWidth / $originalHeight;
        $newRatio = $newWidth / $newHeight;
        $this->assertEquals($originalRatio, $newRatio, '', 0.01);
    }

    public function testResizeImageIfNeededSmallImage(): void
    {
        // Créer une petite image qui ne doit pas être redimensionnée
        $testImagePath = $this->createTestImage('jpg', 300, 200);
        
        $imagePath = '/uploads/clubs/small-image.jpg';
        $fullPath = $this->tempDir . '/public' . $imagePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($testImagePath, $fullPath);

        // Vérifier les dimensions avant
        [$originalWidth, $originalHeight] = getimagesize($fullPath);
        $this->assertEquals(300, $originalWidth);
        $this->assertEquals(200, $originalHeight);

        // Redimensionner (ne devrait rien faire)
        $this->imageService->resizeImageIfNeeded($imagePath, 800, 600);

        // Vérifier que les dimensions n'ont pas changé
        [$newWidth, $newHeight] = getimagesize($fullPath);
        $this->assertEquals($originalWidth, $newWidth);
        $this->assertEquals($originalHeight, $newHeight);
    }
} 