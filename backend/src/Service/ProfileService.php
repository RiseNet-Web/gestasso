<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProfileService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function updateProfile(User $user, array $data): User
    {
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }
        if (isset($data['phoneNumber'])) {
            $user->setPhoneNumber($data['phoneNumber']);
        }
        if (isset($data['dateOfBirth'])) {
            $user->setDateOfBirth(new \DateTime($data['dateOfBirth']));
        }
        if (isset($data['address'])) {
            $user->setAddress($data['address']);
        }
        if (isset($data['city'])) {
            $user->setCity($data['city']);
        }
        if (isset($data['postalCode'])) {
            $user->setPostalCode($data['postalCode']);
        }
        if (isset($data['country'])) {
            $user->setCountry($data['country']);
        }
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new \InvalidArgumentException(json_encode($errorMessages));
        }
        $this->entityManager->flush();
        return $user;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new \InvalidArgumentException('Mot de passe actuel incorrect');
        }
        if (strlen($newPassword) < 8) {
            throw new \InvalidArgumentException('Le nouveau mot de passe doit contenir au moins 8 caractères');
        }
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $this->entityManager->flush();
    }

    public function completeOnboarding(User $user, string $type): void
    {
        if ($user->isOnboardingCompleted()) {
            throw new \InvalidArgumentException('L\'onboarding est déjà complété');
        }
        if (!in_array($type, ['owner', 'member'])) {
            throw new \InvalidArgumentException('Type d\'onboarding invalide (owner ou member)');
        }
        $user->setOnboardingType($type);
        $user->setOnboardingCompleted(true);
        if ($type === 'owner') {
            $roles = $user->getRoles();
            if (!in_array('ROLE_CLUB_OWNER', $roles)) {
                $roles[] = 'ROLE_CLUB_OWNER';
                $user->setRoles($roles);
            }
        }
        $this->entityManager->flush();
    }
} 