<?php

namespace App\Controller;

use App\Entity\Cagnotte;
use App\Entity\Team;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\CagnotteRepository;
use App\Service\CagnotteService;
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
#[OA\Tag(name: 'Cagnottes', description: 'Gestion des cagnottes et événements avec distribution de gains')]
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
    #[OA\Get(
        path: '/api/teams/{id}/cagnottes',
        summary: 'Cagnottes de l\'équipe',
        description: 'Récupère toutes les cagnottes d\'une équipe avec un résumé des montants totaux. Accessible aux membres de l\'équipe et aux gestionnaires.',
        tags: ['Cagnottes'],
        security: [['JWT' => []]]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de l\'équipe',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des cagnottes de l\'équipe avec résumé',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'cagnottes',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(
                                property: 'user',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 5),
                                    new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                                    new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com')
                                ]
                            ),
                            new OA\Property(property: 'currentAmount', type: 'number', format: 'decimal', example: 250.75),
                            new OA\Property(property: 'totalEarned', type: 'number', format: 'decimal', example: 450.50),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00+00:00'),
                            new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2024-02-20T14:20:00+00:00')
                        ]
                    )
                ),
                new OA\Property(
                    property: 'summary',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'totalAmount', type: 'number', format: 'decimal', example: 2750.25),
                        new OA\Property(property: 'totalEarned', type: 'number', format: 'decimal', example: 4200.75),
                        new OA\Property(property: 'count', type: 'integer', example: 15)
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Permission refusée - Utilisateur non autorisé à voir cette équipe',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Accès refusé')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Équipe non trouvée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Équipe non trouvée')
            ]
        )
    )]
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
    #[OA\Get(
        path: '/api/cagnottes/{id}',
        summary: 'Détails de la cagnotte',
        description: 'Récupère les détails d\'une cagnotte avec l\'historique des 20 dernières transactions. Accessible au propriétaire de la cagnotte et aux gestionnaires.',
        tags: ['Cagnottes'],
        security: [['JWT' => []]]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'ID de la cagnotte',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Détails de la cagnotte avec transactions',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(
                    property: 'user',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 5),
                        new OA\Property(property: 'firstName', type: 'string', example: 'Jean'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com')
                    ]
                ),
                new OA\Property(
                    property: 'team',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Équipe Senior')
                    ]
                ),
                new OA\Property(property: 'currentAmount', type: 'number', format: 'decimal', example: 250.75),
                new OA\Property(property: 'totalEarned', type: 'number', format: 'decimal', example: 450.50),
                new OA\Property(
                    property: 'transactions',
                    type: 'array',
                    description: 'Les 20 dernières transactions (triées par date décroissante)',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 15),
                            new OA\Property(property: 'amount', type: 'number', format: 'decimal', example: 45.50),
                            new OA\Property(property: 'type', type: 'string', enum: ['earning', 'usage', 'adjustment'], example: 'earning'),
                            new OA\Property(property: 'description', type: 'string', example: 'Gain du tournoi de fin d\'année'),
                            new OA\Property(
                                property: 'event',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 3),
                                    new OA\Property(property: 'title', type: 'string', example: 'Tournoi de fin d\'année')
                                ]
                            ),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-02-20T14:20:00+00:00')
                        ]
                    )
                ),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00+00:00'),
                new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2024-02-20T14:20:00+00:00')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Permission refusée - Utilisateur non autorisé à voir cette cagnotte'
    )]
    #[OA\Response(
        response: 404,
        description: 'Cagnotte non trouvée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Cagnotte non trouvée')
            ]
        )
    )]
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

    #[Route('/users/{userId}/cagnotte', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/users/{userId}/cagnotte',
        summary: 'Cagnottes d\'un utilisateur',
        description: 'Récupère les cagnottes d\'un utilisateur. L\'utilisateur peut voir ses propres cagnottes, les gestionnaires peuvent voir toutes les cagnottes. Filtrage possible par équipe.',
        tags: ['Cagnottes'],
        security: [['JWT' => []]]
    )]
    #[OA\Parameter(
        name: 'userId',
        description: 'ID de l\'utilisateur',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer', example: 5)
    )]
    #[OA\Parameter(
        name: 'teamId',
        description: 'Filtrer par équipe (optionnel)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Cagnottes de l\'utilisateur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'cagnottes',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(
                                property: 'team',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Équipe Senior')
                                ]
                            ),
                            new OA\Property(property: 'currentAmount', type: 'number', format: 'decimal', example: 250.75),
                            new OA\Property(property: 'totalEarned', type: 'number', format: 'decimal', example: 450.50),
                            new OA\Property(
                                property: 'lastTransaction',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'amount', type: 'number', format: 'decimal', example: 45.50),
                                    new OA\Property(property: 'type', type: 'string', example: 'earning'),
                                    new OA\Property(property: 'date', type: 'string', format: 'date-time', example: '2024-02-20T14:20:00+00:00')
                                ]
                            ),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00+00:00'),
                            new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', example: '2024-02-20T14:20:00+00:00')
                        ]
                    )
                ),
                new OA\Property(property: 'totalAmount', type: 'number', format: 'decimal', example: 750.25)
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Permission refusée - Utilisateur non autorisé à voir ces cagnottes',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Accès refusé')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Utilisateur non trouvé',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Utilisateur non trouvé')
            ]
        )
    )]
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