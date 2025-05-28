<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\Club;
use App\Service\CagnotteService;
use App\Service\EventService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
#[OA\Tag(name: 'Événements', description: 'Gestion des événements et distribution de gains')]
class EventController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private CagnotteService $cagnotteService,
        private EventService $eventService
    ) {}

    #[Route('/clubs/{id}/events', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/clubs/{id}/events',
        summary: 'Créer un événement',
        description: 'Crée un nouvel événement pour distribuer des gains aux participants. Seuls les gestionnaires du club peuvent créer des événements.',
        tags: ['Événements'],
        security: [['JWT' => []]]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID du club',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'totalBudget', 'clubPercentage', 'eventDate'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Tournoi de fin d\'année'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Grande compétition annuelle avec prix en espèces'),
                new OA\Property(property: 'totalBudget', type: 'number', format: 'decimal', example: 1000.00),
                new OA\Property(property: 'clubPercentage', type: 'number', format: 'decimal', minimum: 0, maximum: 100, example: 20.0),
                new OA\Property(property: 'eventDate', type: 'string', format: 'date-time', example: '2024-12-15T14:00:00+00:00')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Événement créé avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 3),
                new OA\Property(property: 'title', type: 'string', example: 'Tournoi de fin d\'année'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Grande compétition annuelle'),
                new OA\Property(property: 'totalBudget', type: 'number', format: 'decimal', example: 1000.00),
                new OA\Property(property: 'clubPercentage', type: 'number', format: 'decimal', example: 20.0),
                new OA\Property(property: 'teamId', type: 'integer', example: 1),
                new OA\Property(property: 'eventDate', type: 'string', format: 'date-time', example: '2024-12-15T14:00:00+00:00'),
                new OA\Property(property: 'status', type: 'string', enum: ['draft', 'active', 'completed', 'cancelled'], example: 'draft'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-02-20T14:20:00+00:00')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Données invalides',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    example: ['title' => 'Le titre est obligatoire', 'totalBudget' => 'Le budget doit être supérieur à 0']
                )
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Permission refusée - Utilisateur non autorisé à gérer ce club'
    )]
    #[OA\Response(
        response: 404,
        description: 'Club non trouvé'
    )]
    public function createEvent(int $id, Request $request): JsonResponse
    {
        $club = $this->entityManager->getRepository(Club::class)->find($id);
        if (!$club) {
            return $this->json(['error' => 'Club non trouvé'], Response::HTTP_NOT_FOUND);
        }
        $this->denyAccessUnlessGranted('CLUB_MANAGE', $club);
        $data = json_decode($request->getContent(), true);
        try {
            $event = $this->eventService->createEvent($club, $data, $this->getUser());
        } catch (\InvalidArgumentException $e) {
            return $this->json(['errors' => json_decode($e->getMessage(), true)], Response::HTTP_BAD_REQUEST);
        }
        return $this->json([
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'totalBudget' => $event->getTotalBudget(),
            'clubPercentage' => $event->getClubPercentage(),
            'clubId' => $club->getId(),
            'eventDate' => $event->getEventDate()->format('c'),
            'status' => $event->getStatus(),
            'createdAt' => $event->getCreatedAt()->format('c')
        ], Response::HTTP_CREATED);
    }

    #[Route('/events/{id}/participants', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/events/{id}/participants',
        summary: 'Ajouter des participants',
        description: 'Ajoute des participants à un événement. Seuls les membres actifs de l\'équipe peuvent être ajoutés. L\'événement doit être actif.',
        tags: ['Événements'],
        security: [['JWT' => []]]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de l\'événement',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 3)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userIds'],
            properties: [
                new OA\Property(
                    property: 'userIds',
                    type: 'array',
                    items: new OA\Items(type: 'integer'),
                    example: [5, 6, 7, 8]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Participants ajoutés avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: '4 participant(s) ajouté(s) avec succès'),
                new OA\Property(property: 'addedCount', type: 'integer', example: 4),
                new OA\Property(property: 'eventId', type: 'integer', example: 3)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Données invalides ou événement non actif',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'L\'événement doit être actif pour ajouter des participants')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Permission refusée - Utilisateur non autorisé à gérer ce club'
    )]
    #[OA\Response(
        response: 404,
        description: 'Événement non trouvé'
    )]
    public function addEventParticipants(int $id, Request $request): JsonResponse
    {
        $event = $this->entityManager->getRepository(Event::class)->find($id);
        if (!$event) {
            return $this->json(['error' => 'Événement non trouvé'], Response::HTTP_NOT_FOUND);
        }
        $this->denyAccessUnlessGranted('CLUB_MANAGE', $event->getClub());
        if ($event->getStatus() !== 'active') {
            return $this->json(['error' => 'L\'événement doit être actif pour ajouter des participants'], Response::HTTP_BAD_REQUEST);
        }
        $data = json_decode($request->getContent(), true);
        if (!isset($data['userIds']) || !is_array($data['userIds'])) {
            return $this->json(['error' => 'userIds doit être un tableau'], Response::HTTP_BAD_REQUEST);
        }
        $addedCount = $this->eventService->addParticipants($event, $data['userIds']);
        return $this->json([
            'message' => sprintf('%d participant(s) ajouté(s) avec succès', $addedCount),
            'addedCount' => $addedCount,
            'eventId' => $event->getId()
        ]);
    }

    #[Route('/events/{id}/distribute', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Put(
        path: '/api/events/{id}/distribute',
        summary: 'Distribuer les gains',
        description: 'Distribue les gains d\'un événement aux participants. Calcule automatiquement la commission du club et répartit le reste équitablement. L\'événement doit être actif et avoir des participants.',
        tags: ['Événements'],
        security: [['JWT' => []]]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de l\'événement',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 3)
    )]
    #[OA\Response(
        response: 200,
        description: 'Gains distribués avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Gains distribués avec succès'),
                new OA\Property(property: 'totalDistributed', type: 'number', format: 'decimal', example: 800.00),
                new OA\Property(property: 'participantsCount', type: 'integer', example: 8),
                new OA\Property(property: 'amountPerParticipant', type: 'number', format: 'decimal', example: 100.00),
                new OA\Property(property: 'clubCommission', type: 'number', format: 'decimal', example: 200.00)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Événement non valide pour distribution',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'L\'événement doit être actif pour distribuer les gains')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Permission refusée - Utilisateur non autorisé à gérer ce club'
    )]
    #[OA\Response(
        response: 404,
        description: 'Événement non trouvé'
    )]
    public function distributeEventGains(int $id): JsonResponse
    {
        $event = $this->entityManager->getRepository(Event::class)->find($id);
        if (!$event) {
            return $this->json(['error' => 'Événement non trouvé'], Response::HTTP_NOT_FOUND);
        }
        $this->denyAccessUnlessGranted('CLUB_MANAGE', $event->getClub());
        if ($event->getStatus() !== 'active') {
            return $this->json(['error' => 'L\'événement doit être actif pour distribuer les gains'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $result = $this->eventService->distributeGains($event);
            $event->setStatus('completed');
            $this->entityManager->flush();
            return $this->json([
                'message' => 'Gains distribués avec succès',
                'totalDistributed' => $result['available_amount'] ?? null,
                'participantsCount' => $result['participants_count'] ?? null,
                'amountPerParticipant' => $result['amount_per_participant'] ?? null,
                'clubCommission' => $result['club_commission'] ?? null
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
} 