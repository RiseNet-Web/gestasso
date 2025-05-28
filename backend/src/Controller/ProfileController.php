<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getProfile(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phoneNumber' => $user->getPhoneNumber(),
            'dateOfBirth' => $user->getDateOfBirth()?->format('Y-m-d'),
            'address' => $user->getAddress(),
            'city' => $user->getCity(),
            'postalCode' => $user->getPostalCode(),
            'country' => $user->getCountry(),
            'profilePicture' => $user->getProfilePicture(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
            'isEmailVerified' => $user->isEmailVerified(),
            'onboardingCompleted' => $user->isOnboardingCompleted(),
            'onboardingType' => $user->getOnboardingType(),
            'createdAt' => $user->getCreatedAt()->format('c'),
            'teams' => array_map(function ($membership) {
                return [
                    'id' => $membership->getTeam()->getId(),
                    'name' => $membership->getTeam()->getName(),
                    'role' => $membership->getRole(),
                    'joinedAt' => $membership->getJoinedAt()->format('c')
                ];
            }, $user->getTeamMemberships()->filter(fn($m) => $m->isActive())->toArray()),
            'managedClubs' => array_map(function ($manager) {
                return [
                    'id' => $manager->getClub()->getId(),
                    'name' => $manager->getClub()->getName(),
                    'role' => $manager->getRole()
                ];
            }, $user->getClubManagers()->toArray()),
            'ownedClubs' => array_map(function ($club) {
                return [
                    'id' => $club->getId(),
                    'name' => $club->getName()
                ];
            }, $user->getOwnedClubs()->toArray())
        ]);
    }

    #[Route('/profile', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        // Mise à jour des champs autorisés
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

        // Valider l'entité
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'phoneNumber' => $user->getPhoneNumber(),
                'dateOfBirth' => $user->getDateOfBirth()?->format('Y-m-d'),
                'address' => $user->getAddress(),
                'city' => $user->getCity(),
                'postalCode' => $user->getPostalCode(),
                'country' => $user->getCountry()
            ]
        ]);
    }

    #[Route('/profile/change-password', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
            return $this->json(['error' => 'currentPassword et newPassword sont requis'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier le mot de passe actuel
        if (!$this->passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
            return $this->json(['error' => 'Mot de passe actuel incorrect'], Response::HTTP_BAD_REQUEST);
        }

        // Valider le nouveau mot de passe
        if (strlen($data['newPassword']) < 8) {
            return $this->json(['error' => 'Le nouveau mot de passe doit contenir au moins 8 caractères'], Response::HTTP_BAD_REQUEST);
        }

        // Mettre à jour le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['newPassword']);
        $user->setPassword($hashedPassword);

        $this->entityManager->flush();

        return $this->json(['message' => 'Mot de passe modifié avec succès']);
    }

    #[Route('/profile/complete-onboarding', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function completeOnboarding(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isOnboardingCompleted()) {
            return $this->json(['error' => 'L\'onboarding est déjà complété'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['type']) || !in_array($data['type'], ['owner', 'member'])) {
            return $this->json(['error' => 'Type d\'onboarding invalide (owner ou member)'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('ONBOARDING_COMPLETE');

        $user->setOnboardingType($data['type']);
        $user->setOnboardingCompleted(true);

        // Si c'est un owner, lui donner le rôle approprié
        if ($data['type'] === 'owner') {
            $roles = $user->getRoles();
            if (!in_array('ROLE_CLUB_OWNER', $roles)) {
                $roles[] = 'ROLE_CLUB_OWNER';
                $user->setRoles($roles);
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Onboarding complété avec succès',
            'onboardingType' => $user->getOnboardingType(),
            'onboardingCompleted' => $user->isOnboardingCompleted(),
            'roles' => $user->getRoles()
        ]);
    }

    #[Route('/profile/stats', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getProfileStats(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $stats = [
            'teamsCount' => $user->getTeamMemberships()->filter(fn($m) => $m->isActive())->count(),
            'pendingPayments' => 0,
            'totalCagnotte' => 0,
            'pendingDocuments' => 0,
            'unreadNotifications' => 0
        ];

        // Compter les paiements en attente
        foreach ($user->getPayments() as $payment) {
            if (in_array($payment->getStatus(), ['pending', 'overdue'])) {
                $stats['pendingPayments']++;
            }
        }

        // Calculer le total des cagnottes
        foreach ($user->getCagnottes() as $cagnotte) {
            $stats['totalCagnotte'] += $cagnotte->getCurrentAmount();
        }

        // Compter les documents en attente
        foreach ($user->getDocuments() as $document) {
            if ($document->getStatus() === 'pending') {
                $stats['pendingDocuments']++;
            }
        }

        // Compter les notifications non lues
        foreach ($user->getNotifications() as $notification) {
            if (!$notification->isRead()) {
                $stats['unreadNotifications']++;
            }
        }

        return $this->json($stats);
    }
} 