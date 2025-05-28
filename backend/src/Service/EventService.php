<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\Club;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private CagnotteService $cagnotteService
    ) {}

    /**
     * Crée un événement pour un club
     */
    public function createEvent(Club $club, array $data, User $creator): Event
    {
        $event = new Event();
        $event->setTitle($data['title'] ?? '');
        $event->setDescription($data['description'] ?? null);
        $event->setTotalBudget($data['totalBudget'] ?? 0);
        $event->setClubPercentage($data['clubPercentage'] ?? 0);
        $event->setClub($club);
        $event->setCreatedBy($creator);
        $event->setEventDate(new \DateTime($data['eventDate'] ?? 'now'));
        $event->setStatus('draft');

        $errors = $this->validator->validate($event);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new \InvalidArgumentException(json_encode($errorMessages));
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();
        return $event;
    }

    /**
     * Ajoute des participants à un événement (vérifie qu'ils sont membres du club)
     */
    public function addParticipants(Event $event, array $userIds): int
    {
        $addedCount = 0;
        foreach ($userIds as $userId) {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if ($user && $this->isUserInClub($user, $event->getClub())) {
                $this->cagnotteService->addEventParticipant($event, $user);
                $addedCount++;
            }
        }
        $this->entityManager->flush();
        return $addedCount;
    }

    /**
     * Distribue les gains d'un événement (délègue à CagnotteService)
     */
    public function distributeGains(Event $event): array
    {
        return $this->cagnotteService->distributeEventEarnings($event);
    }

    /**
     * Vérifie si un utilisateur est membre du club (directement ou via une équipe)
     */
    private function isUserInClub(User $user, Club $club): bool
    {
        foreach ($user->getTeams() as $team) {
            if ($team->getClub() === $club && $team->isActive()) {
                return true;
            }
        }
        if (method_exists($user, 'getClubs')) {
            foreach ($user->getClubs() as $userClub) {
                if ($userClub === $club) {
                    return true;
                }
            }
        }
        return false;
    }
} 