<?php

namespace App\Controller;

use App\Entity\Cagnotte;
use App\Entity\Team;
use App\Entity\Event;
use App\Repository\CagnotteRepository;
use App\Service\CagnotteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class CagnotteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private CagnotteRepository $cagnotteRepository,
        private CagnotteService $cagnotteService
    ) {}

    #[Route('/teams/{id}/cagnottes', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTeamCagnottes(int $id): JsonResponse
    {
        $team = $this->entityManager->getRepository(Team::class)->find($id);
        
        if (!$team) {
            return $this->json(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_VIEW', $team);

        $cagnottes = $this->cagnotteRepository->findBy(['team' => $team]);

        return $this->json([
            'cagnottes' => array_map(function (Cagnotte $cagnotte) {
                return [
                    'id' => $cagnotte->getId(),
                    'user' => [
                        'id' => $cagnotte->getUser()->getId(),
                        'firstName' => $cagnotte->getUser()->getFirstName(),
                        'lastName' => $cagnotte->getUser()->getLastName(),
                        'email' => $cagnotte->getUser()->getEmail()
                    ],
                    'currentAmount' => $cagnotte->getCurrentAmount(),
                    'totalEarned' => $cagnotte->getTotalEarned(),
                    'createdAt' => $cagnotte->getCreatedAt()->format('c'),
                    'updatedAt' => $cagnotte->getUpdatedAt()->format('c')
                ];
            }, $cagnottes),
            'summary' => [
                'totalAmount' => array_reduce($cagnottes, fn($sum, $c) => $sum + $c->getCurrentAmount(), 0),
                'totalEarned' => array_reduce($cagnottes, fn($sum, $c) => $sum + $c->getTotalEarned(), 0),
                'count' => count($cagnottes)
            ]
        ]);
    }

    #[Route('/cagnottes/{id}', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCagnotte(int $id): JsonResponse
    {
        $cagnotte = $this->cagnotteRepository->find($id);
        
        if (!$cagnotte) {
            return $this->json(['error' => 'Cagnotte non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('CAGNOTTE_VIEW', $cagnotte);

        $transactions = $cagnotte->getCagnotteTransactions()->toArray();
        usort($transactions, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $this->json([
            'id' => $cagnotte->getId(),
            'user' => [
                'id' => $cagnotte->getUser()->getId(),
                'firstName' => $cagnotte->getUser()->getFirstName(),
                'lastName' => $cagnotte->getUser()->getLastName(),
                'email' => $cagnotte->getUser()->getEmail()
            ],
            'team' => [
                'id' => $cagnotte->getTeam()->getId(),
                'name' => $cagnotte->getTeam()->getName()
            ],
            'currentAmount' => $cagnotte->getCurrentAmount(),
            'totalEarned' => $cagnotte->getTotalEarned(),
            'transactions' => array_map(function ($transaction) {
                return [
                    'id' => $transaction->getId(),
                    'amount' => $transaction->getAmount(),
                    'type' => $transaction->getType(),
                    'description' => $transaction->getDescription(),
                    'event' => $transaction->getEvent() ? [
                        'id' => $transaction->getEvent()->getId(),
                        'title' => $transaction->getEvent()->getTitle()
                    ] : null,
                    'createdAt' => $transaction->getCreatedAt()->format('c')
                ];
            }, array_slice($transactions, 0, 20)), // Limiter aux 20 dernières transactions
            'createdAt' => $cagnotte->getCreatedAt()->format('c'),
            'updatedAt' => $cagnotte->getUpdatedAt()->format('c')
        ]);
    }

    #[Route('/teams/{id}/events', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createEvent(int $id, Request $request): JsonResponse
    {
        $team = $this->entityManager->getRepository(Team::class)->find($id);
        
        if (!$team) {
            return $this->json(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $team);

        $data = json_decode($request->getContent(), true);

        $event = new Event();
        $event->setTitle($data['title'] ?? '');
        $event->setDescription($data['description'] ?? null);
        $event->setTotalBudget($data['totalBudget'] ?? 0);
        $event->setClubPercentage($data['clubPercentage'] ?? 0);
        $event->setTeam($team);
        $event->setCreatedBy($this->getUser());
        $event->setEventDate(new \DateTime($data['eventDate'] ?? 'now'));
        $event->setStatus('draft');

        // Valider l'entité
        $errors = $this->validator->validate($event);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $this->json([
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'totalBudget' => $event->getTotalBudget(),
            'clubPercentage' => $event->getClubPercentage(),
            'teamId' => $team->getId(),
            'eventDate' => $event->getEventDate()->format('c'),
            'status' => $event->getStatus(),
            'createdAt' => $event->getCreatedAt()->format('c')
        ], Response::HTTP_CREATED);
    }

    #[Route('/events/{id}/participants', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addEventParticipants(int $id, Request $request): JsonResponse
    {
        $event = $this->entityManager->getRepository(Event::class)->find($id);
        
        if (!$event) {
            return $this->json(['error' => 'Événement non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $event->getTeam());

        if ($event->getStatus() !== 'active') {
            return $this->json(['error' => 'L\'événement doit être actif pour ajouter des participants'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['userIds']) || !is_array($data['userIds'])) {
            return $this->json(['error' => 'userIds doit être un tableau'], Response::HTTP_BAD_REQUEST);
        }

        $addedCount = 0;
        foreach ($data['userIds'] as $userId) {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if ($user) {
                // Vérifier que l'utilisateur est membre de l'équipe
                $isMember = $event->getTeam()->getTeamMembers()->exists(
                    fn($key, $member) => $member->getUser() === $user && $member->isActive()
                );
                
                if ($isMember) {
                    $this->cagnotteService->addEventParticipant($event, $user);
                    $addedCount++;
                }
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => sprintf('%d participant(s) ajouté(s) avec succès', $addedCount),
            'addedCount' => $addedCount,
            'eventId' => $event->getId()
        ]);
    }

    #[Route('/events/{id}/distribute', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function distributeEventGains(int $id): JsonResponse
    {
        $event = $this->entityManager->getRepository(Event::class)->find($id);
        
        if (!$event) {
            return $this->json(['error' => 'Événement non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $event->getTeam());

        if ($event->getStatus() !== 'active') {
            return $this->json(['error' => 'L\'événement doit être actif pour distribuer les gains'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->cagnotteService->distributeEventGains($event);
            
            // Marquer l'événement comme complété
            $event->setStatus('completed');
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Gains distribués avec succès',
                'totalDistributed' => $result['totalDistributed'],
                'participantsCount' => $result['participantsCount'],
                'amountPerParticipant' => $result['amountPerParticipant'],
                'clubCommission' => $result['clubCommission']
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/users/{userId}/cagnotte', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUserCagnotte(int $userId, Request $request): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur peut voir cette cagnotte
        if ($this->getUser() !== $user && !$this->isGranted('ROLE_CLUB_MANAGER')) {
            throw $this->createAccessDeniedException();
        }

        $teamId = $request->query->get('teamId');
        
        $criteria = ['user' => $user];
        if ($teamId) {
            $team = $this->entityManager->getRepository(Team::class)->find($teamId);
            if ($team) {
                $criteria['team'] = $team;
            }
        }

        $cagnottes = $this->cagnotteRepository->findBy($criteria);

        return $this->json([
            'cagnottes' => array_map(function (Cagnotte $cagnotte) {
                return [
                    'id' => $cagnotte->getId(),
                    'team' => [
                        'id' => $cagnotte->getTeam()->getId(),
                        'name' => $cagnotte->getTeam()->getName()
                    ],
                    'currentAmount' => $cagnotte->getCurrentAmount(),
                    'totalEarned' => $cagnotte->getTotalEarned(),
                    'lastTransaction' => $cagnotte->getCagnotteTransactions()->last() ? [
                        'amount' => $cagnotte->getCagnotteTransactions()->last()->getAmount(),
                        'type' => $cagnotte->getCagnotteTransactions()->last()->getType(),
                        'date' => $cagnotte->getCagnotteTransactions()->last()->getCreatedAt()->format('c')
                    ] : null,
                    'createdAt' => $cagnotte->getCreatedAt()->format('c'),
                    'updatedAt' => $cagnotte->getUpdatedAt()->format('c')
                ];
            }, $cagnottes),
            'totalAmount' => array_reduce($cagnottes, fn($sum, $c) => $sum + $c->getCurrentAmount(), 0)
        ]);
    }
} 