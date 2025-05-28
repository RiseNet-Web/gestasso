<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentType;
use App\Entity\Team;
use App\Repository\DocumentRepository;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class DocumentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private DocumentRepository $documentRepository,
        private DocumentService $documentService,
        private string $uploadDirectory
    ) {}

    #[Route('/teams/{id}/document-types', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getDocumentTypes(int $id): JsonResponse
    {
        $team = $this->entityManager->getRepository(Team::class)->find($id);
        
        if (!$team) {
            return $this->json(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_VIEW', $team);

        $documentTypes = $this->entityManager->getRepository(DocumentType::class)->findBy(['team' => $team]);

        return $this->json([
            'documentTypes' => array_map(function (DocumentType $type) {
                return [
                    'id' => $type->getId(),
                    'name' => $type->getName(),
                    'description' => $type->getDescription(),
                    'isRequired' => $type->isRequired(),
                    'deadline' => $type->getDeadline()?->format('Y-m-d'),
                    'createdAt' => $type->getCreatedAt()->format('c')
                ];
            }, $documentTypes)
        ]);
    }

    #[Route('/teams/{id}/document-types', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createDocumentType(int $id, Request $request): JsonResponse
    {
        $team = $this->entityManager->getRepository(Team::class)->find($id);
        
        if (!$team) {
            return $this->json(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $team);

        $data = json_decode($request->getContent(), true);

        $documentType = new DocumentType();
        $documentType->setTeam($team);
        $documentType->setName($data['name'] ?? '');
        $documentType->setDescription($data['description'] ?? null);
        $documentType->setIsRequired($data['isRequired'] ?? true);
        
        if (isset($data['deadline'])) {
            $documentType->setDeadline(new \DateTime($data['deadline']));
        }

        // Valider l'entité
        $errors = $this->validator->validate($documentType);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($documentType);
        $this->entityManager->flush();

        return $this->json([
            'id' => $documentType->getId(),
            'name' => $documentType->getName(),
            'description' => $documentType->getDescription(),
            'isRequired' => $documentType->isRequired(),
            'deadline' => $documentType->getDeadline()?->format('Y-m-d'),
            'teamId' => $team->getId(),
            'createdAt' => $documentType->getCreatedAt()->format('c')
        ], Response::HTTP_CREATED);
    }

    #[Route('/documents', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadDocument(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        $documentTypeId = $request->request->get('documentTypeId');
        if (!$documentTypeId) {
            return $this->json(['error' => 'documentTypeId est requis'], Response::HTTP_BAD_REQUEST);
        }

        $documentType = $this->entityManager->getRepository(DocumentType::class)->find($documentTypeId);
        if (!$documentType) {
            return $this->json(['error' => 'Type de document non trouvé'], Response::HTTP_NOT_FOUND);
        }

        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Aucun fichier fourni'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $document = $this->documentService->uploadDocument($user, $documentType, $file);
            
            return $this->json([
                'id' => $document->getId(),
                'originalName' => $document->getOriginalName(),
                'status' => $document->getStatus(),
                'documentType' => [
                    'id' => $documentType->getId(),
                    'name' => $documentType->getName()
                ],
                'createdAt' => $document->getCreatedAt()->format('c')
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/documents/{id}', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function downloadDocument(int $id): Response
    {
        $document = $this->documentRepository->find($id);
        
        if (!$document) {
            return $this->json(['error' => 'Document non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('DOCUMENT_VIEW', $document);

        $filePath = $this->uploadDirectory . '/' . $document->getFilePath();
        
        if (!file_exists($filePath)) {
            return $this->json(['error' => 'Fichier non trouvé'], Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($filePath);
    }

    #[Route('/documents/{id}/validate', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function validateDocument(int $id, Request $request): JsonResponse
    {
        $document = $this->documentRepository->find($id);
        
        if (!$document) {
            return $this->json(['error' => 'Document non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('DOCUMENT_VALIDATE', $document);

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['status']) || !in_array($data['status'], ['approved', 'rejected'])) {
            return $this->json(['error' => 'Status invalide (approved ou rejected)'], Response::HTTP_BAD_REQUEST);
        }

        $document = $this->documentService->validateDocument(
            $document,
            $this->getUser(),
            $data['status'],
            $data['notes'] ?? null
        );

        return $this->json([
            'id' => $document->getId(),
            'status' => $document->getStatus(),
            'validatedBy' => [
                'id' => $document->getValidatedBy()->getId(),
                'firstName' => $document->getValidatedBy()->getFirstName(),
                'lastName' => $document->getValidatedBy()->getLastName()
            ],
            'validatedAt' => $document->getValidatedAt()->format('c'),
            'validationNotes' => $document->getValidationNotes()
        ]);
    }

    #[Route('/documents/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteDocument(int $id): JsonResponse
    {
        $document = $this->documentRepository->find($id);
        
        if (!$document) {
            return $this->json(['error' => 'Document non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('DOCUMENT_DELETE', $document);

        $this->documentService->deleteDocument($document);

        return $this->json(['message' => 'Document supprimé avec succès'], Response::HTTP_NO_CONTENT);
    }

    #[Route('/users/{userId}/documents', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUserDocuments(int $userId, Request $request): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur peut voir ces documents
        if ($this->getUser() !== $user && !$this->isGranted('ROLE_CLUB_MANAGER')) {
            throw $this->createAccessDeniedException();
        }

        $teamId = $request->query->get('teamId');
        $status = $request->query->get('status');

        $criteria = ['user' => $user];
        if ($status) {
            $criteria['status'] = $status;
        }

        $documents = $this->documentRepository->findBy($criteria, ['createdAt' => 'DESC']);

        // Filtrer par équipe si nécessaire
        if ($teamId) {
            $documents = array_filter($documents, function($doc) use ($teamId) {
                return $doc->getDocumentType()->getTeam()->getId() == $teamId;
            });
        }

        return $this->json([
            'documents' => array_map(function (Document $document) {
                return [
                    'id' => $document->getId(),
                    'originalName' => $document->getOriginalName(),
                    'status' => $document->getStatus(),
                    'documentType' => [
                        'id' => $document->getDocumentType()->getId(),
                        'name' => $document->getDocumentType()->getName(),
                        'team' => [
                            'id' => $document->getDocumentType()->getTeam()->getId(),
                            'name' => $document->getDocumentType()->getTeam()->getName()
                        ]
                    ],
                    'validatedBy' => $document->getValidatedBy() ? [
                        'id' => $document->getValidatedBy()->getId(),
                        'firstName' => $document->getValidatedBy()->getFirstName(),
                        'lastName' => $document->getValidatedBy()->getLastName()
                    ] : null,
                    'validatedAt' => $document->getValidatedAt()?->format('c'),
                    'validationNotes' => $document->getValidationNotes(),
                    'createdAt' => $document->getCreatedAt()->format('c'),
                    'updatedAt' => $document->getUpdatedAt()->format('c')
                ];
            }, array_values($documents))
        ]);
    }
} 