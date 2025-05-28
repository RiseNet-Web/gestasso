<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\DocumentType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Vich\UploaderBundle\Handler\UploadHandler;

class DocumentService
{
    private string $uploadDirectory;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private UploadHandler $uploadHandler,
        private NotificationService $notificationService,
        string $uploadDirectory
    ) {
        $this->uploadDirectory = $uploadDirectory;
    }

    /**
     * Upload un document pour un utilisateur
     */
    public function uploadDocument(
        User $user,
        DocumentType $documentType,
        UploadedFile $file
    ): Document {
        // Valider le fichier
        $this->validateFile($file);

        // Créer le document
        $document = new Document();
        $document->setUser($user);
        $document->setDocumentType($documentType);
        $document->setOriginalName($file->getClientOriginalName());
        $document->setStatus('pending');
        $document->setCreatedAt(new \DateTime());
        $document->setUpdatedAt(new \DateTime());

        // Générer un nom de fichier sécurisé
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        // Créer le répertoire si nécessaire
        $uploadPath = $this->uploadDirectory . '/documents/' . $user->getId();
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        // Déplacer le fichier
        $file->move($uploadPath, $newFilename);
        $document->setFilePath('documents/' . $user->getId() . '/' . $newFilename);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        // Notifier les gestionnaires
        $this->notifyNewDocument($document);

        return $document;
    }

    /**
     * Valide un fichier uploadé
     */
    private function validateFile(UploadedFile $file): void
    {
        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/jpg',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new \InvalidArgumentException('Type de fichier non autorisé');
        }

        // Taille maximale : 10 MB
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('Le fichier est trop volumineux (max 10 MB)');
        }
    }

    /**
     * Valide ou rejette un document
     */
    public function validateDocument(
        Document $document,
        User $validator,
        string $status,
        ?string $notes = null
    ): Document {
        if (!in_array($status, ['approved', 'rejected'])) {
            throw new \InvalidArgumentException('Statut invalide');
        }

        $document->setStatus($status);
        $document->setValidatedBy($validator);
        $document->setValidatedAt(new \DateTime());
        $document->setValidationNotes($notes);
        $document->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        // Notifier l'utilisateur
        $this->notificationService->createNotification(
            $document->getUser(),
            'document_validation',
            $status === 'approved' ? 'Document approuvé' : 'Document rejeté',
            sprintf(
                'Votre document "%s" a été %s.',
                $document->getDocumentType()->getName(),
                $status === 'approved' ? 'approuvé' : 'rejeté'
            ),
            [
                'documentId' => $document->getId(),
                'documentTypeId' => $document->getDocumentType()->getId(),
                'status' => $status,
                'notes' => $notes
            ]
        );

        return $document;
    }

    /**
     * Notifie les gestionnaires d'un nouveau document
     */
    private function notifyNewDocument(Document $document): void
    {
        $team = $document->getDocumentType()->getTeam();
        $club = $team->getClub();

        // Notifier le propriétaire du club
        $this->notificationService->createNotification(
            $club->getOwner(),
            'document_uploaded',
            'Nouveau document à valider',
            sprintf(
                '%s a déposé un document "%s"',
                $document->getUser()->getFullName(),
                $document->getDocumentType()->getName()
            ),
            [
                'documentId' => $document->getId(),
                'userId' => $document->getUser()->getId(),
                'documentTypeId' => $document->getDocumentType()->getId()
            ]
        );

        // Notifier les gestionnaires
        foreach ($club->getClubManagers() as $manager) {
            if ($manager->getUser() !== $club->getOwner()) {
                $this->notificationService->createNotification(
                    $manager->getUser(),
                    'document_uploaded',
                    'Nouveau document à valider',
                    sprintf(
                        '%s a déposé un document "%s"',
                        $document->getUser()->getFullName(),
                        $document->getDocumentType()->getName()
                    ),
                    [
                        'documentId' => $document->getId(),
                        'userId' => $document->getUser()->getId(),
                        'documentTypeId' => $document->getDocumentType()->getId()
                    ]
                );
            }
        }
    }

    /**
     * Vérifie les documents manquants pour une équipe
     */
    public function checkMissingDocuments(User $user, $team): array
    {
        $missingDocuments = [];
        
        // Récupérer tous les types de documents requis pour l'équipe
        $requiredDocumentTypes = $this->entityManager->getRepository(DocumentType::class)->findBy([
            'team' => $team,
            'isRequired' => true
        ]);

        foreach ($requiredDocumentTypes as $documentType) {
            // Vérifier si l'utilisateur a un document approuvé pour ce type
            $approvedDocument = $this->entityManager->getRepository(Document::class)->findOneBy([
                'user' => $user,
                'documentType' => $documentType,
                'status' => 'approved'
            ]);

            if (!$approvedDocument) {
                $missingDocuments[] = [
                    'documentType' => $documentType,
                    'deadline' => $documentType->getDeadline()
                ];
            }
        }

        return $missingDocuments;
    }

    /**
     * Envoie des rappels pour les documents manquants
     */
    public function sendDocumentReminders(): int
    {
        $count = 0;
        $today = new \DateTime();

        // Récupérer tous les types de documents avec une deadline proche
        $documentTypes = $this->entityManager->getRepository(DocumentType::class)
            ->createQueryBuilder('dt')
            ->where('dt.isRequired = :required')
            ->andWhere('dt.deadline IS NOT NULL')
            ->andWhere('dt.deadline > :today')
            ->andWhere('dt.deadline <= :deadline')
            ->setParameter('required', true)
            ->setParameter('today', $today)
            ->setParameter('deadline', (clone $today)->modify('+7 days'))
            ->getQuery()
            ->getResult();

        foreach ($documentTypes as $documentType) {
            $team = $documentType->getTeam();
            
            // Pour chaque membre de l'équipe
            foreach ($team->getTeamMembers() as $teamMember) {
                if (!$teamMember->isActive()) {
                    continue;
                }

                $user = $teamMember->getUser();
                
                // Vérifier si le document est manquant
                $document = $this->entityManager->getRepository(Document::class)->findOneBy([
                    'user' => $user,
                    'documentType' => $documentType,
                    'status' => 'approved'
                ]);

                if (!$document) {
                    $this->notificationService->createNotification(
                        $user,
                        'document_reminder',
                        'Document requis',
                        sprintf(
                            'Vous devez fournir le document "%s" avant le %s',
                            $documentType->getName(),
                            $documentType->getDeadline()->format('d/m/Y')
                        ),
                        [
                            'documentTypeId' => $documentType->getId(),
                            'teamId' => $team->getId(),
                            'deadline' => $documentType->getDeadline()->format('Y-m-d')
                        ]
                    );
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Supprime un document
     */
    public function deleteDocument(Document $document): void
    {
        // Supprimer le fichier physique
        $filePath = $this->uploadDirectory . '/' . $document->getFilePath();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Supprimer l'entité
        $this->entityManager->remove($document);
        $this->entityManager->flush();
    }
} 