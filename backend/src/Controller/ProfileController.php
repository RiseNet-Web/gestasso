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
use App\Service\ProfileService;

#[Route('/api')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $passwordHasher,
        private ProfileService $profileService
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
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        try {
            $user = $this->profileService->updateProfile($user, $data);
        } catch (\InvalidArgumentException $e) {
            $msg = json_decode($e->getMessage(), true) ?: ['error' => $e->getMessage()];
            return $this->json(['errors' => $msg], Response::HTTP_BAD_REQUEST);
        }
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
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
            return $this->json(['error' => 'currentPassword et newPassword sont requis'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $this->profileService->changePassword($user, $data['currentPassword'], $data['newPassword']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
        return $this->json(['message' => 'Mot de passe modifié avec succès']);
    }

    #[Route('/profile/complete-onboarding', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function completeOnboarding(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        if (!isset($data['type'])) {
            return $this->json(['error' => 'Type d\'onboarding requis'], Response::HTTP_BAD_REQUEST);
        }
        $this->denyAccessUnlessGranted('ONBOARDING_COMPLETE');
        try {
            $this->profileService->completeOnboarding($user, $data['type']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
        return $this->json(['message' => 'Onboarding complété avec succès']);
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