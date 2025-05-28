<?php

namespace App\Controller;

use App\Entity\Club;
use App\Entity\User;
use App\Entity\ClubManager;
use App\Entity\Season;
use App\Security\ClubVoter;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/clubs')]
#[OA\Tag(name: 'Clubs', description: 'Gestion des clubs sportifs')]
class ClubController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'api_clubs_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/clubs',
        summary: 'Mes clubs',
        description: 'Récupère la liste des clubs où l\'utilisateur est propriétaire ou gestionnaire',
        tags: ['Clubs'],
        security: [['JWT' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des clubs de l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Club Sportif de Paris'),
                    new OA\Property(property: 'description', type: 'string', example: 'Club multisports parisien'),
                    new OA\Property(property: 'imagePath', type: 'string', nullable: true),
                    new OA\Property(property: 'isPublic', type: 'boolean', example: true),
                    new OA\Property(property: 'allowJoinRequests', type: 'boolean', example: true),
                    new OA\Property(property: 'isOwner', type: 'boolean', example: true),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                ]
            )
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Récupérer les clubs où l'utilisateur est propriétaire ou gestionnaire
        $qb = $this->entityManager->createQueryBuilder();
        
        $clubs = $qb->select('c')
            ->from(Club::class, 'c')
            ->leftJoin(ClubManager::class, 'cm', 'WITH', 'cm.club = c.id')
            ->where('c.owner = :user')
            ->orWhere('cm.user = :user')
            ->andWhere('c.isActive = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $clubsData = [];
        foreach ($clubs as $club) {
            $clubsData[] = [
                'id' => $club->getId(),
                'name' => $club->getName(),
                'description' => $club->getDescription(),
                'imagePath' => $club->getImagePath(),
                'isPublic' => $club->isPublic(),
                'allowJoinRequests' => $club->isAllowJoinRequests(),
                'isOwner' => $club->getOwner() === $user,
                'createdAt' => $club->getCreatedAt()->format('c')
            ];
        }

        return new JsonResponse($clubsData);
    }

    #[Route('/public', name: 'api_clubs_public', methods: ['GET'])]
    #[OA\Get(
        path: '/api/clubs/public',
        summary: 'Liste des clubs publics',
        description: 'Récupère la liste des clubs publics avec pagination et recherche',
        tags: ['Clubs']
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        description: 'Terme de recherche dans le nom ou description',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'football')
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'Numéro de page',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Nombre d\'éléments par page (max 50)',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50, example: 20)
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des clubs publics',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'clubs',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'name', type: 'string', example: 'Club Sportif de Paris'),
                            new OA\Property(property: 'description', type: 'string', example: 'Club multisports parisien'),
                            new OA\Property(property: 'imagePath', type: 'string', nullable: true),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                        ]
                    )
                ),
                new OA\Property(
                    property: 'pagination',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 20),
                        new OA\Property(property: 'hasMore', type: 'boolean', example: false)
                    ]
                )
            ]
        )
    )]
    public function publicClubs(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 20)));
        $offset = ($page - 1) * $limit;

        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('c')
           ->from(Club::class, 'c')
           ->where('c.isPublic = true')
           ->andWhere('c.isActive = true')
           ->andWhere('c.allowJoinRequests = true');

        if (!empty($search)) {
            $qb->andWhere('c.name LIKE :search OR c.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('c.createdAt', 'DESC')
           ->setFirstResult($offset)
           ->setMaxResults($limit);

        $clubs = $qb->getQuery()->getResult();

        $clubsData = [];
        foreach ($clubs as $club) {
            $clubsData[] = [
                'id' => $club->getId(),
                'name' => $club->getName(),
                'description' => $club->getDescription(),
                'imagePath' => $club->getImagePath(),
                'createdAt' => $club->getCreatedAt()->format('c')
            ];
        }

        return new JsonResponse([
            'clubs' => $clubsData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'hasMore' => count($clubs) === $limit
            ]
        ]);
    }

    #[Route('', name: 'api_clubs_create', methods: ['POST'])]
    #[IsGranted('ROLE_CLUB_OWNER')]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['name']) || empty($data['name'])) {
            return new JsonResponse(['error' => 'Le nom du club est requis'], Response::HTTP_BAD_REQUEST);
        }

        $club = new Club();
        $club->setName($data['name'])
             ->setDescription($data['description'] ?? null)
             ->setOwner($user)
             ->setIsPublic($data['isPublic'] ?? true)
             ->setAllowJoinRequests($data['allowJoinRequests'] ?? true);

        $errors = $this->validator->validate($club);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($club);

        // Créer une saison par défaut
        $currentYear = date('Y');
        $season = new Season();
        $season->setName("Saison {$currentYear}-" . ($currentYear + 1))
               ->setStartDate(new \DateTime("{$currentYear}-09-01"))
               ->setEndDate(new \DateTime(($currentYear + 1) . "-08-31"))
               ->setClub($club)
               ->setIsActive(true);

        $this->entityManager->persist($season);
        $this->entityManager->flush();

        // Marquer l'onboarding comme terminé si c'était un owner
        if ($user->getOnboardingType() === 'owner' && !$user->isOnboardingCompleted()) {
            $user->setOnboardingCompleted(true);
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'id' => $club->getId(),
            'name' => $club->getName(),
            'description' => $club->getDescription(),
            'isPublic' => $club->isPublic(),
            'allowJoinRequests' => $club->isAllowJoinRequests(),
            'createdAt' => $club->getCreatedAt()->format('c')
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_clubs_show', methods: ['GET'])]
    public function show(Club $club): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClubVoter::VIEW, $club);

        $user = $this->getUser();
        $isOwner = $club->getOwner() === $user;
        $isManager = false;

        if ($user instanceof User) {
            $manager = $this->entityManager->getRepository(ClubManager::class)
                ->findOneBy(['club' => $club, 'user' => $user]);
            $isManager = $manager !== null;
        }

        return new JsonResponse([
            'id' => $club->getId(),
            'name' => $club->getName(),
            'description' => $club->getDescription(),
            'imagePath' => $club->getImagePath(),
            'isPublic' => $club->isPublic(),
            'allowJoinRequests' => $club->isAllowJoinRequests(),
            'owner' => [
                'id' => $club->getOwner()->getId(),
                'firstName' => $club->getOwner()->getFirstName(),
                'lastName' => $club->getOwner()->getLastName()
            ],
            'permissions' => [
                'isOwner' => $isOwner,
                'isManager' => $isManager,
                'canEdit' => $this->isGranted(ClubVoter::EDIT, $club),
                'canManage' => $this->isGranted(ClubVoter::MANAGE, $club),
                'canDelete' => $this->isGranted(ClubVoter::DELETE, $club)
            ],
            'createdAt' => $club->getCreatedAt()->format('c')
        ]);
    }

    #[Route('/{id}', name: 'api_clubs_update', methods: ['PUT'])]
    public function update(Club $club, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClubVoter::EDIT, $club);

        $data = json_decode($request->getContent(), true);

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

        $errors = $this->validator->validate($club);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $club->getId(),
            'name' => $club->getName(),
            'description' => $club->getDescription(),
            'isPublic' => $club->isPublic(),
            'allowJoinRequests' => $club->isAllowJoinRequests(),
            'updatedAt' => $club->getUpdatedAt()->format('c')
        ]);
    }

    #[Route('/{id}', name: 'api_clubs_delete', methods: ['DELETE'])]
    public function delete(Club $club): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClubVoter::DELETE, $club);

        $club->setIsActive(false);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Club supprimé avec succès']);
    }

    #[Route('/{id}/stats', name: 'api_clubs_stats', methods: ['GET'])]
    public function stats(Club $club): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClubVoter::VIEW, $club);

        // Statistiques du club
        $qb = $this->entityManager->createQueryBuilder();

        // Nombre d'équipes
        $teamsCount = $qb->select('COUNT(t.id)')
            ->from('App\Entity\Team', 't')
            ->where('t.club = :club')
            ->andWhere('t.isActive = true')
            ->setParameter('club', $club)
            ->getQuery()
            ->getSingleScalarResult();

        // Nombre total de membres
        $qb->resetDQLParts();
        $membersCount = $qb->select('COUNT(DISTINCT tm.user)')
            ->from('App\Entity\TeamMember', 'tm')
            ->join('tm.team', 't')
            ->where('t.club = :club')
            ->andWhere('tm.isActive = true')
            ->andWhere('t.isActive = true')
            ->setParameter('club', $club)
            ->getQuery()
            ->getSingleScalarResult();

        // Revenus totaux du club
        $qb->resetDQLParts();
        $totalRevenue = $qb->select('COALESCE(SUM(ct.amount), 0)')
            ->from('App\Entity\ClubTransaction', 'ct')
            ->where('ct.club = :club')
            ->andWhere('ct.type = :type')
            ->setParameter('club', $club)
            ->setParameter('type', 'commission')
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'teamsCount' => (int) $teamsCount,
            'membersCount' => (int) $membersCount,
            'totalRevenue' => (float) $totalRevenue,
            'generatedAt' => (new \DateTime())->format('c')
        ]);
    }

    #[Route('/{id}/managers', name: 'api_clubs_managers', methods: ['GET'])]
    public function managers(Club $club): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClubVoter::MANAGE, $club);

        $managers = $this->entityManager->getRepository(ClubManager::class)
            ->findBy(['club' => $club]);

        $managersData = [];
        foreach ($managers as $manager) {
            $managersData[] = [
                'id' => $manager->getId(),
                'user' => [
                    'id' => $manager->getUser()->getId(),
                    'firstName' => $manager->getUser()->getFirstName(),
                    'lastName' => $manager->getUser()->getLastName(),
                    'email' => $manager->getUser()->getEmail()
                ],
                'createdAt' => $manager->getCreatedAt()->format('c')
            ];
        }

        return new JsonResponse($managersData);
    }

    #[Route('/{id}/managers', name: 'api_clubs_add_manager', methods: ['POST'])]
    public function addManager(Club $club, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ClubVoter::MANAGE, $club);

        $data = json_decode($request->getContent(), true);

        if (!isset($data['userId'])) {
            return new JsonResponse(['error' => 'ID utilisateur requis'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)->find($data['userId']);
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur n'est pas déjà gestionnaire
        $existingManager = $this->entityManager->getRepository(ClubManager::class)
            ->findOneBy(['club' => $club, 'user' => $user]);

        if ($existingManager) {
            return new JsonResponse(['error' => 'Cet utilisateur est déjà gestionnaire'], Response::HTTP_CONFLICT);
        }

        $manager = new ClubManager();
        $manager->setClub($club)->setUser($user);

        $this->entityManager->persist($manager);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $manager->getId(),
            'user' => [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail()
            ],
            'createdAt' => $manager->getCreatedAt()->format('c')
        ], Response::HTTP_CREATED);
    }
} 