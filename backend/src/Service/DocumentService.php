<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\DocumentType;
use App\Entity\User;
use App\Entity\Team;
use App\Entity\Club;
use App\Enum\DocumentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DocumentService
{
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain'
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
        private string $projectDir
    ) {}

    /**
     * Upload sécurisé d'un document
     */
    public function uploadSecureDocument(
        UploadedFile $file,
        User $user,
        DocumentType $documentType,
        string $description = ''
    ): Document {
        // Valider le fichier
        $this->validateUploadedFile($file);

        // Créer l'entité Document
        $document = new Document();
        $document->setUser($user);
        $document->setDocumentTypeEntity($documentType);
        $document->setOriginalFileName($file->getClientOriginalName());
        $document->setDescription($description);
        $document->setMimeType($file->getMimeType() ?: 'application/octet-stream');
        $document->setFileSize($file->getSize());
        $document->setStatus(DocumentStatus::PENDING);
        $document->setCreatedAt(new \DateTime());
        $document->setUpdatedAt(new \DateTime());

        // Générer path sécurisé
        $secureFileName = $this->generateSecureFileName($file);
        $document->setSecurePath($secureFileName);

        // Créer le répertoire sécurisé
        $secureDir = $this->getSecureStorageDirectory();
        if (!is_dir($secureDir)) {
            mkdir($secureDir, 0750, true);
        }

        // Déplacer le fichier vers le stockage sécurisé
        $finalPath = $secureDir . '/' . $secureFileName;
        $file->move($secureDir, $secureFileName);

        // Vérifier que le fichier a été déplacé correctement
        if (!file_exists($finalPath)) {
            throw new \RuntimeException('Erreur lors du stockage sécurisé du fichier');
        }

        // Définir la date d'expiration si le type le prévoit
        if ($documentType->isExpirable() && $documentType->getValidityDurationInDays()) {
            $expirationDate = new \DateTime();
            $expirationDate->add(new \DateInterval('P' . $documentType->getValidityDurationInDays() . 'D'));
            $document->setExpirationDate($expirationDate);
        }

        // Sauvegarder en base
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        // Log de sécurité
        $this->logSecurityEvent('document_uploaded', [
            'document_id' => $document->getId(),
            'user_id' => $user->getId(),
            'document_type_id' => $documentType->getId(),
            'file_size' => $file->getSize(),
            'mime_type' => $document->getMimeType(),
            'team_id' => $documentType->getTeam()->getId(),
            'club_id' => $documentType->getTeam()->getClub()->getId()
        ]);

        // Notifier les gestionnaires
        $this->notifyDocumentUploaded($document);

        return $document;
    }

    /**
     * Téléchargement sécurisé d'un document
     */
    public function downloadSecureDocument(Document $document, User $user): BinaryFileResponse
    {
        $filePath = $this->getSecureStorageDirectory() . '/' . $document->getSecurePath();

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('Fichier non trouvé sur le serveur');
        }

        // Log de l'accès
        $this->logSecurityEvent('document_accessed', [
            'document_id' => $document->getId(),
            'accessed_by_user_id' => $user->getId(),
            'file_path' => $document->getSecurePath(),
            'team_id' => $document->getDocumentTypeEntity()->getTeam()->getId(),
            'club_id' => $document->getDocumentTypeEntity()->getTeam()->getClub()->getId()
        ]);

        // Incrémenter le compteur d'accès
        $document->incrementAccessCount();
        $this->entityManager->flush();

        // Créer la réponse avec headers sécurisés
        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $document->getOriginalFileName()
        );

        // Headers de sécurité
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * Validation d'un document par un gestionnaire
     */
    public function validateDocument(
        Document $document,
        User $validator,
        string $status,
        ?string $notes = null
    ): Document {
        if (!in_array($status, ['approved', 'rejected'])) {
            throw new \InvalidArgumentException('Statut de validation invalide');
        }

        $previousStatus = $document->getStatus();
        
        $document->setStatus(DocumentStatus::from($status));
        $document->setValidatedBy($validator);
        $document->setValidatedAt(new \DateTime());
        $document->setValidationNotes($notes);
        $document->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        // Log de sécurité
        $this->logSecurityEvent('document_validated', [
            'document_id' => $document->getId(),
            'validator_user_id' => $validator->getId(),
            'previous_status' => $previousStatus->value,
            'new_status' => $status,
            'validation_notes' => $notes,
            'team_id' => $document->getDocumentTypeEntity()->getTeam()->getId(),
            'club_id' => $document->getDocumentTypeEntity()->getTeam()->getClub()->getId()
        ]);

        // Notifier l'utilisateur
        $this->notifyDocumentValidated($document, $status);

        return $document;
    }

    /**
     * Suppression sécurisée d'un document
     */
    public function deleteSecureDocument(Document $document, User $user): void
    {
        $filePath = $this->getSecureStorageDirectory() . '/' . $document->getSecurePath();

        // Log avant suppression
        $this->logSecurityEvent('document_deleted', [
            'document_id' => $document->getId(),
            'deleted_by_user_id' => $user->getId(),
            'original_filename' => $document->getOriginalFileName(),
            'document_owner_id' => $document->getUser()->getId(),
            'team_id' => $document->getDocumentTypeEntity()->getTeam()->getId(),
            'club_id' => $document->getDocumentTypeEntity()->getTeam()->getClub()->getId()
        ]);

        // Suppression sécurisée du fichier
        if (file_exists($filePath)) {
            $this->secureFileDelete($filePath);
        }

        // Suppression de l'entité
        $this->entityManager->remove($document);
        $this->entityManager->flush();
    }

    /**
     * Récupère les documents d'une équipe
     */
    public function getTeamDocuments(Team $team, ?string $status = null, ?int $userId = null): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(Document::class, 'd')
            ->join('d.documentTypeEntity', 'dt')
            ->where('dt.team = :team')
            ->setParameter('team', $team)
            ->orderBy('d.createdAt', 'DESC');

        if ($status) {
            $queryBuilder->andWhere('d.status = :status')
                ->setParameter('status', DocumentStatus::from($status));
        }

        if ($userId) {
            $queryBuilder->andWhere('d.user = :userId')
                ->setParameter('userId', $userId);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Récupère les documents d'un club
     */
    public function getClubDocuments(Club $club, ?string $status = null, ?int $teamId = null, ?int $userId = null): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(Document::class, 'd')
            ->join('d.documentTypeEntity', 'dt')
            ->join('dt.team', 't')
            ->where('t.club = :club')
            ->setParameter('club', $club)
            ->orderBy('d.createdAt', 'DESC');

        if ($status) {
            $queryBuilder->andWhere('d.status = :status')
                ->setParameter('status', DocumentStatus::from($status));
        }

        if ($teamId) {
            $queryBuilder->andWhere('t.id = :teamId')
                ->setParameter('teamId', $teamId);
        }

        if ($userId) {
            $queryBuilder->andWhere('d.user = :userId')
                ->setParameter('userId', $userId);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Récupère les documents d'un utilisateur
     */
    public function getUserDocuments(User $user, ?int $teamId = null, ?string $status = null): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(Document::class, 'd')
            ->where('d.user = :user')
            ->setParameter('user', $user)
            ->orderBy('d.createdAt', 'DESC');

        if ($teamId) {
            $queryBuilder->join('d.documentTypeEntity', 'dt')
                ->andWhere('dt.team = :teamId')
                ->setParameter('teamId', $teamId);
        }

        if ($status) {
            $queryBuilder->andWhere('d.status = :status')
                ->setParameter('status', DocumentStatus::from($status));
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Statistiques des documents d'un club
     */
    public function getClubDocumentStats(Club $club): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $totalQuery = $qb->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->join('d.documentTypeEntity', 'dt')
            ->join('dt.team', 't')
            ->where('t.club = :club')
            ->setParameter('club', $club);
        
        $total = $totalQuery->getQuery()->getSingleScalarResult();

        $stats = [
            'total' => $total,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'expired' => 0
        ];

        foreach (DocumentStatus::cases() as $status) {
            $count = $this->entityManager->createQueryBuilder()
                ->select('COUNT(d.id)')
                ->from(Document::class, 'd')
                ->join('d.documentTypeEntity', 'dt')
                ->join('dt.team', 't')
                ->where('t.club = :club')
                ->andWhere('d.status = :status')
                ->setParameter('club', $club)
                ->setParameter('status', $status)
                ->getQuery()
                ->getSingleScalarResult();
            
            $stats[strtolower($status->value)] = $count;
        }

        // Documents expirés
        $expiredCount = $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->join('d.documentTypeEntity', 'dt')
            ->join('dt.team', 't')
            ->where('t.club = :club')
            ->andWhere('d.expirationDate < :now')
            ->setParameter('club', $club)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();

        $stats['expired'] = $expiredCount;

        return $stats;
    }

    /**
     * Crée un type de document
     */
    public function createDocumentType(Team $team, array $data): DocumentType
    {
        $documentType = new DocumentType();
        $documentType->setTeam($team);
        $documentType->setName($data['name'] ?? '');
        $documentType->setDescription($data['description'] ?? '');
        $documentType->setIsRequired($data['isRequired'] ?? false);
        $documentType->setIsExpirable($data['isExpirable'] ?? false);
        $documentType->setValidityDurationInDays($data['validityDurationInDays'] ?? null);
        $documentType->setCreatedAt(new \DateTime());

        if (isset($data['deadline'])) {
            $documentType->setDeadline(new \DateTime($data['deadline']));
        }

        // Validation
        $errors = $this->validator->validate($documentType);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new \InvalidArgumentException(json_encode($errorMessages));
        }

        $this->entityManager->persist($documentType);
        $this->entityManager->flush();

        return $documentType;
    }

    /**
     * Valide un fichier uploadé
     */
    private function validateUploadedFile(UploadedFile $file): void
    {
        // Vérifier la taille
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                sprintf('Le fichier est trop volumineux (max %d MB)', self::MAX_FILE_SIZE / 1024 / 1024)
            );
        }

        // Vérifier le type MIME
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new \InvalidArgumentException('Type de fichier non autorisé: ' . $mimeType);
        }

        // Vérifier l'extension
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'];
        if (!in_array($extension, $allowedExtensions)) {
            throw new \InvalidArgumentException('Extension de fichier non autorisée: ' . $extension);
        }

        // Vérifier que le fichier n'est pas corrompu
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Fichier corrompu ou erreur d\'upload');
        }
    }

    /**
     * Génère un nom de fichier sécurisé
     */
    private function generateSecureFileName(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $hash = hash('sha256', 
            $file->getClientOriginalName() . 
            uniqid() . 
            random_bytes(16) . 
            microtime(true)
        );
        
        return $hash . ($extension ? '.' . $extension : '');
    }

    /**
     * Retourne le répertoire de stockage sécurisé
     */
    private function getSecureStorageDirectory(): string
    {
        return $this->projectDir . '/var/secure_documents';
    }

    /**
     * Suppression sécurisée d'un fichier
     */
    private function secureFileDelete(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        try {
            // Écraser le fichier avec des données aléatoires (3 passes)
            $fileSize = filesize($filePath);
            $handle = fopen($filePath, 'r+b');
            
            if ($handle) {
                for ($pass = 0; $pass < 3; $pass++) {
                    fseek($handle, 0);
                    $randomData = random_bytes(min($fileSize, 8192));
                    $written = 0;
                    
                    while ($written < $fileSize) {
                        $toWrite = min(strlen($randomData), $fileSize - $written);
                        fwrite($handle, substr($randomData, 0, $toWrite));
                        $written += $toWrite;
                    }
                    
                    fflush($handle);
                }
                
                fclose($handle);
            }
            
            // Supprimer le fichier
            unlink($filePath);
            
        } catch (\Exception $e) {
            // En cas d'erreur, au moins supprimer le fichier normalement
            @unlink($filePath);
            
            $this->logger->error('Erreur lors de la suppression sécurisée du fichier', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log des événements de sécurité
     */
    private function logSecurityEvent(string $event, array $context = []): void
    {
        $this->logger->info('Document Security Event: ' . $event, array_merge($context, [
            'timestamp' => (new \DateTime())->format('c'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]));
    }

    /**
     * Notifie l'upload d'un nouveau document
     */
    private function notifyDocumentUploaded(Document $document): void
    {
        $team = $document->getDocumentTypeEntity()->getTeam();
        $club = $team->getClub();

        // Notifier le propriétaire du club
        $this->notificationService->createNotification(
            $club->getOwner(),
            'document_uploaded',
            'Nouveau document à valider',
            sprintf(
                '%s a déposé un document "%s" pour l\'équipe %s',
                $document->getUser()->getFullName(),
                $document->getDocumentTypeEntity()->getName(),
                $team->getName()
            ),
            [
                'documentId' => $document->getId(),
                'userId' => $document->getUser()->getId(),
                'documentTypeId' => $document->getDocumentTypeEntity()->getId(),
                'teamId' => $team->getId(),
                'clubId' => $club->getId()
            ]
        );

        // Notifier les gestionnaires du club
        foreach ($club->getClubManagers() as $manager) {
            if ($manager->getUser()->getId() !== $club->getOwner()->getId()) {
                $this->notificationService->createNotification(
                    $manager->getUser(),
                    'document_uploaded',
                    'Nouveau document à valider',
                    sprintf(
                        '%s a déposé un document "%s" pour l\'équipe %s',
                        $document->getUser()->getFullName(),
                        $document->getDocumentTypeEntity()->getName(),
                        $team->getName()
                    ),
                    [
                        'documentId' => $document->getId(),
                        'userId' => $document->getUser()->getId(),
                        'documentTypeId' => $document->getDocumentTypeEntity()->getId(),
                        'teamId' => $team->getId(),
                        'clubId' => $club->getId()
                    ]
                );
            }
        }
    }

    /**
     * Notifie la validation d'un document
     */
    private function notifyDocumentValidated(Document $document, string $status): void
    {
        $message = $status === 'approved' 
            ? sprintf('Votre document "%s" a été approuvé', $document->getDocumentTypeEntity()->getName())
            : sprintf('Votre document "%s" a été rejeté', $document->getDocumentTypeEntity()->getName());

        $this->notificationService->createNotification(
            $document->getUser(),
            'document_validation',
            'Validation de document',
            $message,
            [
                'documentId' => $document->getId(),
                'documentTypeId' => $document->getDocumentTypeEntity()->getId(),
                'status' => $status,
                'validationNotes' => $document->getValidationNotes()
            ]
        );
    }

    /**
     * Vérifie les documents manquants pour un utilisateur dans une équipe
     */
    public function checkMissingDocuments(User $user, Team $team): array
    {
        $requiredDocumentTypes = $this->entityManager->getRepository(DocumentType::class)
            ->findBy(['team' => $team, 'isRequired' => true]);

        $missingDocuments = [];

        foreach ($requiredDocumentTypes as $documentType) {
            $existingDocument = $this->entityManager->getRepository(Document::class)
                ->findOneBy([
                    'user' => $user,
                    'documentTypeEntity' => $documentType,
                    'status' => DocumentStatus::APPROVED
                ]);

            if (!$existingDocument) {
                $missingDocuments[] = [
                    'documentType' => $documentType,
                    'deadline' => $documentType->getDeadline()
                ];
            }
        }

        return $missingDocuments;
    }

    /**
     * Récupère les documents expirés
     */
    public function getExpiredDocuments(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(Document::class, 'd')
            ->where('d.expirationDate < :now')
            ->andWhere('d.status = :approved')
            ->setParameter('now', new \DateTime())
            ->setParameter('approved', DocumentStatus::APPROVED)
            ->getQuery()
            ->getResult();
    }

    /**
     * Nettoyage automatique des documents expirés
     */
    public function cleanupExpiredDocuments(): int
    {
        $expiredDocuments = $this->getExpiredDocuments();
        $count = 0;

        foreach ($expiredDocuments as $document) {
            try {
                // Marquer comme expiré plutôt que supprimer
                $document->setStatus(DocumentStatus::EXPIRED);
                $document->setUpdatedAt(new \DateTime());
                
                $this->logSecurityEvent('document_expired', [
                    'document_id' => $document->getId(),
                    'user_id' => $document->getUser()->getId(),
                    'expiration_date' => $document->getExpirationDate()->format('Y-m-d')
                ]);
                
                $count++;
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors du marquage d\'un document comme expiré', [
                    'document_id' => $document->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }
} 