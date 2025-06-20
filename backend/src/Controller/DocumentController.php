<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentType;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\Club;
use App\Repository\DocumentRepository;
use App\Security\DocumentVoter;
use App\Security\TeamVoter;
use App\Security\ClubVoter;
use App\Security\UserVoter;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/documents')]
#[IsGranted('ROLE_USER')]
class DocumentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private DocumentRepository $documentRepository,
        private DocumentService $documentService
    ) {}

    #[Route('', name: 'api_documents_upload', methods: ['POST'])]
    #[OA\Post(
        path: '/api/documents',
        summary: 'Upload sécurisé d\'un document',
        description: 'Upload d\'un document avec stockage sécurisé et notifications aux gestionnaires',
        tags: ['Documents'],
        security: [['JWT' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                required: ['document', 'documentTypeId'],
                properties: [
                    new OA\Property(property: 'document', type: 'string', format: 'binary', description: 'Fichier document'),
                    new OA\Property(property: 'documentTypeId', type: 'integer', description: 'ID du type de document'),
                    new OA\Property(property: 'description', type: 'string', description: 'Description optionnelle')
                ]
            )
        )
    )]
    public function uploadDocument(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié');
        }

        $documentFile = $request->files->get('document');
        if (!$documentFile) {
            return new JsonResponse(['error' => 'Aucun fichier fourni'], Response::HTTP_BAD_REQUEST);
        }

        $documentTypeId = $request->request->get('documentTypeId');
        if (!$documentTypeId) {
            return new JsonResponse(['error' => 'Type de document requis'], Response::HTTP_BAD_REQUEST);
        }

        $documentType = $this->entityManager->getRepository(DocumentType::class)->find($documentTypeId);
        if (!$documentType) {
            return new JsonResponse(['error' => 'Type de document non trouvé'], Response::HTTP_BAD_REQUEST);
        }

        $description = $request->request->get('description', '');

        try {
            $document = $this->documentService->uploadSecureDocument(
                $documentFile,
                $user,
                $documentType,
                $description
            );

            return new JsonResponse([
                'document' => $this->formatDocumentResponse($document),
                'message' => 'Document uploadé avec succès et stocké de manière sécurisée'
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_documents_show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/{id}',
        summary: 'Informations d\'un document',
        description: 'Récupère les métadonnées d\'un document avec contrôle d\'accès',
        tags: ['Documents'],
        security: [['JWT' => []]]
    )]
    public function getDocumentInfo(int $id): JsonResponse
    {
        $document = $this->documentRepository->find($id);
        if (!$document) {
            throw new NotFoundHttpException('Document non trouvé');
        }

        // Utiliser le voter pour vérifier les permissions
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        return new JsonResponse([
            'document' => $this->formatDocumentResponse($document)
        ]);
    }

    #[Route('/{id}/download', name: 'api_documents_download', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/{id}/download',
        summary: 'Téléchargement sécurisé d\'un document',
        description: 'Télécharge un document avec vérification des autorisations et audit',
        tags: ['Documents'],
        security: [['JWT' => []]]
    )]
    public function downloadDocument(int $id, Request $request): BinaryFileResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié');
        }

        $document = $this->documentRepository->find($id);
        if (!$document) {
            throw new NotFoundHttpException('Document non trouvé');
        }

        // Utiliser le voter pour vérifier les permissions
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        return $this->documentService->downloadSecureDocument($document, $user);
    }

    #[Route('/{id}/validate', name: 'api_documents_validate', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/documents/{id}/validate',
        summary: 'Validation d\'un document',
        description: 'Valide ou rejette un document (réservé aux gestionnaires)',
        tags: ['Documents'],
        security: [['JWT' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['approved', 'rejected'], description: 'Statut de validation'),
                new OA\Property(property: 'notes', type: 'string', description: 'Notes de validation (optionnel)')
            ]
        )
    )]
    public function validateDocument(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié');
        }

        $document = $this->documentRepository->find($id);
        if (!$document) {
            throw new NotFoundHttpException('Document non trouvé');
        }

        // Utiliser le voter pour vérifier les permissions de validation
        $this->denyAccessUnlessGranted(DocumentVoter::VALIDATE, $document);

        $data = json_decode($request->getContent(), true) ?? [];
        
        if (!isset($data['status']) || !in_array($data['status'], ['approved', 'rejected'])) {
            return new JsonResponse(['error' => 'Statut invalide (approved ou rejected)'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $document = $this->documentService->validateDocument(
                $document,
                $user,
                $data['status'],
                $data['notes'] ?? null
            );

            return new JsonResponse([
                'document' => $this->formatDocumentResponse($document),
                'message' => 'Document ' . ($data['status'] === 'approved' ? 'approuvé' : 'rejeté') . ' avec succès'
            ]);

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_documents_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/documents/{id}',
        summary: 'Suppression sécurisée d\'un document',
        description: 'Supprime définitivement un document avec audit',
        tags: ['Documents'],
        security: [['JWT' => []]]
    )]
    public function deleteDocument(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié');
        }

        $document = $this->documentRepository->find($id);
        if (!$document) {
            throw new NotFoundHttpException('Document non trouvé');
        }

        // Utiliser le voter pour vérifier les permissions de suppression
        $this->denyAccessUnlessGranted(DocumentVoter::DELETE, $document);

        $this->documentService->deleteSecureDocument($document, $user);

        return new JsonResponse([
            'message' => 'Document supprimé de manière sécurisée'
        ]);
    }

    #[Route('/team/{teamId}', name: 'api_documents_list_by_team', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/team/{teamId}',
        summary: 'Liste des documents d\'une équipe',
        description: 'Récupère tous les documents d\'une équipe (gestionnaires uniquement)',
        tags: ['Documents'],
        security: [['JWT' => []]]
    )]
    public function getTeamDocuments(int $teamId, Request $request): JsonResponse
    {
        $team = $this->entityManager->getRepository(Team::class)->find($teamId);
        if (!$team) {
            throw new NotFoundHttpException('Équipe non trouvée');
        }

        // Utiliser le voter pour vérifier les permissions de gestion des documents de l'équipe
        $this->denyAccessUnlessGranted(TeamVoter::MANAGE_DOCUMENTS, $team);

        $status = $request->query->get('status');
        $userId = $request->query->get('userId');

        $documents = $this->documentService->getTeamDocuments($team, $status, $userId);

        return new JsonResponse([
            'documents' => array_map([$this, 'formatDocumentResponse'], $documents),
            'team' => [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'club' => [
                    'id' => $team->getClub()->getId(),
                    'name' => $team->getClub()->getName()
                ]
            ]
        ]);
    }

    #[Route('/club/{clubId}', name: 'api_documents_list_by_club', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/club/{clubId}',
        summary: 'Liste des documents d\'un club',
        description: 'Récupère tous les documents d\'un club (propriétaires et gestionnaires)',
        tags: ['Documents'],
        security: [['JWT' => []]]
    )]
    public function getClubDocuments(int $clubId, Request $request): JsonResponse
    {
        $club = $this->entityManager->getRepository(Club::class)->find($clubId);
        if (!$club) {
            throw new NotFoundHttpException('Club non trouvé');
        }

        // Utiliser le voter pour vérifier les permissions de gestion du club
        $this->denyAccessUnlessGranted(ClubVoter::MANAGE, $club);

        $status = $request->query->get('status');
        $teamId = $request->query->get('teamId');
        $userId = $request->query->get('userId');

        $documents = $this->documentService->getClubDocuments($club, $status, $teamId, $userId);

        return new JsonResponse([
            'documents' => array_map([$this, 'formatDocumentResponse'], $documents),
            'club' => [
                'id' => $club->getId(),
                'name' => $club->getName()
            ],
            'stats' => $this->documentService->getClubDocumentStats($club)
        ]);
    }

    #[Route('/user/{userId}', name: 'api_documents_list_by_user', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/user/{userId}',
        summary: 'Liste des documents d\'un utilisateur',
        description: 'Récupère les documents d\'un utilisateur',
        tags: ['Documents'],
        security: [['JWT' => []]]
    )]
    public function getUserDocuments(int $userId, Request $request): JsonResponse
    {
        $targetUser = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$targetUser) {
            throw new NotFoundHttpException('Utilisateur non trouvé');
        }

        // Utiliser le voter pour vérifier les permissions d'accès aux documents de l'utilisateur
        $this->denyAccessUnlessGranted(UserVoter::VIEW_DOCUMENTS, $targetUser);

        $teamId = $request->query->get('teamId');
        $status = $request->query->get('status');

        $documents = $this->documentService->getUserDocuments($targetUser, $teamId, $status);

        return new JsonResponse([
            'documents' => array_map([$this, 'formatDocumentResponse'], $documents),
            'user' => [
                'id' => $targetUser->getId(),
                'firstName' => $targetUser->getFirstName(),
                'lastName' => $targetUser->getLastName(),
                'email' => $targetUser->getEmail()
            ]
        ]);
    }

    #[Route('/types/team/{teamId}', name: 'api_document_types_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/documents/types/team/{teamId}',
        summary: 'Types de documents d\'une équipe',
        description: 'Récupère les types de documents configurés pour une équipe',
        tags: ['Documents'],
        security: [['JWT' => []]]
    )]
    public function getDocumentTypes(int $teamId): JsonResponse
    {
        $team = $this->entityManager->getRepository(Team::class)->find($teamId);
        if (!$team) {
            throw new NotFoundHttpException('Équipe non trouvée');
        }

        // Utiliser le voter pour vérifier l'accès à l'équipe
        $this->denyAccessUnlessGranted(TeamVoter::VIEW, $team);

        $documentTypes = $this->entityManager->getRepository(DocumentType::class)
            ->findBy(['team' => $team], ['name' => 'ASC']);

        return new JsonResponse([
            'documentTypes' => array_map(function (DocumentType $type) {
                return [
                    'id' => $type->getId(),
                    'name' => $type->getName(),
                    'description' => $type->getDescription(),
                    'isRequired' => $type->isRequired(),
                    'isExpirable' => $type->isExpirable(),
                    'validityDurationInDays' => $type->getValidityDurationInDays(),
                    'deadline' => $type->getDeadline()?->format('Y-m-d'),
                    'createdAt' => $type->getCreatedAt()->format('c')
                ];
            }, $documentTypes)
        ]);
    }

    #[Route('/types', name: 'api_document_types_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/documents/types',
        summary: 'Créer un type de document',
        description: 'Crée un nouveau type de document pour une équipe',
        tags: ['Documents'],
        security: [['JWT' => []]]
    )]
    public function createDocumentType(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié');
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['teamId'])) {
            return new JsonResponse(['error' => 'ID de l\'équipe requis'], Response::HTTP_BAD_REQUEST);
        }

        $team = $this->entityManager->getRepository(Team::class)->find($data['teamId']);
        if (!$team) {
            return new JsonResponse(['error' => 'Équipe non trouvée'], Response::HTTP_BAD_REQUEST);
        }

        // Utiliser le voter pour vérifier les permissions de gestion des documents
        $this->denyAccessUnlessGranted(TeamVoter::MANAGE_DOCUMENTS, $team);

        try {
            $documentType = $this->documentService->createDocumentType($team, $data);

            return new JsonResponse([
                'documentType' => [
                    'id' => $documentType->getId(),
                    'name' => $documentType->getName(),
                    'description' => $documentType->getDescription(),
                    'isRequired' => $documentType->isRequired(),
                    'isExpirable' => $documentType->isExpirable(),
                    'validityDurationInDays' => $documentType->getValidityDurationInDays(),
                    'deadline' => $documentType->getDeadline()?->format('Y-m-d'),
                    'teamId' => $team->getId(),
                    'createdAt' => $documentType->getCreatedAt()->format('c')
                ],
                'message' => 'Type de document créé avec succès'
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true) ?: ['error' => $e->getMessage()];
            return new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Formate la réponse d'un document pour l'API
     */
    private function formatDocumentResponse(Document $document): array
    {
        return [
            'id' => $document->getId(),
            'originalName' => $document->getOriginalFileName(),
            'description' => $document->getDescription(),
            'status' => $document->getStatus()->value,
            'mimeType' => $document->getMimeType(),
            'fileSize' => $document->getFileSize(),
            'isConfidential' => true, // Tous les documents sont confidentiels
            'uploadedAt' => $document->getCreatedAt()->format('c'),
            'updatedAt' => $document->getUpdatedAt()?->format('c'),
            'validatedAt' => $document->getValidatedAt()?->format('c'),
            'expirationDate' => $document->getExpirationDate()?->format('Y-m-d'),
            'user' => [
                'id' => $document->getUser()->getId(),
                'firstName' => $document->getUser()->getFirstName(),
                'lastName' => $document->getUser()->getLastName(),
                'email' => $document->getUser()->getEmail()
            ],
            'documentType' => [
                'id' => $document->getDocumentTypeEntity()->getId(),
                'name' => $document->getDocumentTypeEntity()->getName(),
                'isRequired' => $document->getDocumentTypeEntity()->isRequired()
            ],
            'validatedBy' => $document->getValidatedBy() ? [
                'id' => $document->getValidatedBy()->getId(),
                'firstName' => $document->getValidatedBy()->getFirstName(),
                'lastName' => $document->getValidatedBy()->getLastName()
            ] : null,
            'validationNotes' => $document->getValidationNotes()
        ];
    }
} 