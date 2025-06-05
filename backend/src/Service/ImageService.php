<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImageService
{
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    public const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    private string $uploadDirectory;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private ValidatorInterface $validator,
        string $projectDir
    ) {
        $this->uploadDirectory = $projectDir . '/public/uploads';
    }

    /**
     * Upload une image de logo pour un club
     */
    public function uploadClubLogo(UploadedFile $file): string
    {
        $this->validateImageFile($file);

        // Générer un nom de fichier sécurisé
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        // Créer le répertoire si nécessaire
        $uploadPath = $this->uploadDirectory . '/clubs';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Déplacer le fichier
        $file->move($uploadPath, $newFilename);

        // Retourner le chemin relatif
        return '/uploads/clubs/' . $newFilename;
    }

    /**
     * Supprime une image de club
     */
    public function deleteClubImage(string $imagePath): bool
    {
        if (empty($imagePath)) {
            return true;
        }

        // Construire le chemin complet du fichier
        $fullPath = $this->uploadDirectory . $imagePath;
        
        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }

        return true; // Fichier déjà supprimé ou inexistant
    }

    /**
     * Valide un fichier image uploadé
     */
    private function validateImageFile(UploadedFile $file): void
    {
        // Vérifier le type MIME
        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            throw new \InvalidArgumentException(sprintf(
                'Type de fichier non autorisé. Types acceptés : %s',
                implode(', ', self::ALLOWED_MIME_TYPES)
            ));
        }

        // Vérifier la taille du fichier
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(sprintf(
                'Le fichier est trop volumineux (max %s MB)',
                self::MAX_FILE_SIZE / (1024 * 1024)
            ));
        }

        // Vérifier que c'est bien un fichier uploadé
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Le fichier uploadé n\'est pas valide');
        }

        // Vérifications supplémentaires de sécurité
        $this->validateImageContent($file);
    }

    /**
     * Valide le contenu de l'image pour la sécurité
     */
    private function validateImageContent(UploadedFile $file): void
    {
        // Vérifier que le fichier est bien une image en lisant ses métadonnées
        $imageInfo = @getimagesize($file->getPathname());
        
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('Le fichier n\'est pas une image valide');
        }

        // Vérifier les dimensions minimales et maximales
        [$width, $height] = $imageInfo;
        
        if ($width < 50 || $height < 50) {
            throw new \InvalidArgumentException('L\'image est trop petite (minimum 50x50 pixels)');
        }

        if ($width > 2000 || $height > 2000) {
            throw new \InvalidArgumentException('L\'image est trop grande (maximum 2000x2000 pixels)');
        }
    }

    /**
     * Retourne l'URL publique d'une image
     */
    public function getImageUrl(?string $imagePath): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        // Si le chemin commence déjà par /uploads, on le retourne tel quel
        if (str_starts_with($imagePath, '/uploads/')) {
            return $imagePath;
        }

        // Sinon on ajoute le préfixe
        return '/uploads/' . ltrim($imagePath, '/');
    }

    /**
     * Redimensionne une image si nécessaire
     */
    public function resizeImageIfNeeded(string $imagePath, int $maxWidth = 800, int $maxHeight = 600): void
    {
        $fullPath = $this->uploadDirectory . $imagePath;
        
        if (!file_exists($fullPath)) {
            return;
        }

        $imageInfo = getimagesize($fullPath);
        if ($imageInfo === false) {
            return;
        }

        [$currentWidth, $currentHeight, $imageType] = $imageInfo;

        // Si l'image est déjà dans les bonnes dimensions, on ne fait rien
        if ($currentWidth <= $maxWidth && $currentHeight <= $maxHeight) {
            return;
        }

        // Calculer les nouvelles dimensions en gardant les proportions
        $ratio = min($maxWidth / $currentWidth, $maxHeight / $currentHeight);
        $newWidth = (int) ($currentWidth * $ratio);
        $newHeight = (int) ($currentHeight * $ratio);

        // Créer une nouvelle image redimensionnée
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Préserver la transparence pour PNG et GIF
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Charger l'image source
        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($fullPath),
            IMAGETYPE_PNG => imagecreatefrompng($fullPath),
            IMAGETYPE_GIF => imagecreatefromgif($fullPath),
            IMAGETYPE_WEBP => imagecreatefromwebp($fullPath),
            default => null
        };

        if ($sourceImage === null) {
            return;
        }

        // Redimensionner
        imagecopyresampled(
            $newImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $currentWidth, $currentHeight
        );

        // Sauvegarder l'image redimensionnée
        match ($imageType) {
            IMAGETYPE_JPEG => imagejpeg($newImage, $fullPath, 85),
            IMAGETYPE_PNG => imagepng($newImage, $fullPath, 6),
            IMAGETYPE_GIF => imagegif($newImage, $fullPath),
            IMAGETYPE_WEBP => imagewebp($newImage, $fullPath, 85),
            default => null
        };

        // Libérer la mémoire
        imagedestroy($sourceImage);
        imagedestroy($newImage);
    }
} 