<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\User;
use App\Entity\ClubManager;
use App\Entity\Season;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ClubService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private ImageService $imageService
    ) {}

    public function createClub(User $owner, array $data, ?UploadedFile $logoFile = null): Club
    {
        $club = new Club();
        $club->setOwner($owner);
        $club->setName($data['name'] ?? '');
        $club->setDescription($data['description'] ?? null);
        $club->setIsPublic($data['isPublic'] ?? true);
        $club->setAllowJoinRequests($data['allowJoinRequests'] ?? true);
        $club->setIsActive(true);
        
        // Gérer l'upload de logo
        if ($logoFile !== null) {
            try {
                $logoPath = $this->imageService->uploadClubLogo($logoFile);
                $club->setImagePath($logoPath);
                
                // Redimensionner l'image si nécessaire
                $this->imageService->resizeImageIfNeeded($logoPath, 400, 400);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('Erreur lors de l\'upload du logo : ' . $e->getMessage());
            }
        } elseif (isset($data['imagePath'])) {
            $club->setImagePath($data['imagePath']);
        }
        
        $errors = $this->validator->validate($club);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new \InvalidArgumentException(json_encode($errorMessages));
        }
        $this->entityManager->persist($club);
        $this->entityManager->flush();
        return $club;
    }

    public function updateClub(Club $club, array $data, ?UploadedFile $logoFile = null): Club
    {
        if (isset($data['name'])) {
            $club->setName($data['name']);
        }
        if (isset($data['description'])) {
            $club->setDescription($data['description']);
        }
        if (isset($data['isPublic'])) {
            $club->setIsPublic($data['isPublic']);
        }
        if (isset($data['allowJoinRequests'])) {
            $club->setAllowJoinRequests($data['allowJoinRequests']);
        }
        
        // Gérer l'upload de nouveau logo
        if ($logoFile !== null) {
            try {
                // Supprimer l'ancien logo s'il existe
                if ($club->getImagePath()) {
                    $this->imageService->deleteClubImage($club->getImagePath());
                }
                
                // Uploader le nouveau logo
                $logoPath = $this->imageService->uploadClubLogo($logoFile);
                $club->setImagePath($logoPath);
                
                // Redimensionner l'image si nécessaire
                $this->imageService->resizeImageIfNeeded($logoPath, 400, 400);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('Erreur lors de l\'upload du logo : ' . $e->getMessage());
            }
        } elseif (isset($data['imagePath'])) {
            $club->setImagePath($data['imagePath']);
        }
        
        $errors = $this->validator->validate($club);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new \InvalidArgumentException(json_encode($errorMessages));
        }
        $this->entityManager->flush();
        return $club;
    }

    public function deleteClub(Club $club): void
    {
        // Supprimer le logo du club s'il existe
        if ($club->getImagePath()) {
            $this->imageService->deleteClubImage($club->getImagePath());
        }
        
        $club->setIsActive(false);
        $this->entityManager->flush();
    }

    public function addManager(Club $club, User $user, string $role): ClubManager
    {
        $manager = new ClubManager();
        $manager->setClub($club);
        $manager->setUser($user);
        $manager->setRole($role);
        $this->entityManager->persist($manager);
        $this->entityManager->flush();
        return $manager;
    }

    public function removeManager(Club $club, User $user): void
    {
        foreach ($club->getClubManagers() as $manager) {
            if ($manager->getUser() === $user) {
                $this->entityManager->remove($manager);
            }
        }
        $this->entityManager->flush();
    }
} 