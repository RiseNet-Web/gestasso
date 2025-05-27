<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\DocumentType;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Trouve tous les documents d'un utilisateur
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->setParameter('user', $user)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les documents d'un utilisateur pour une équipe spécifique
     */
    public function findByUserAndTeam(User $user, Team $team): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.documentType', 'dt')
            ->andWhere('d.user = :user')
            ->andWhere('dt.team = :team')
            ->setParameter('user', $user)
            ->setParameter('team', $team)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les documents par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->setParameter('status', $status)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les documents en attente de validation
     */
    public function findPending(): array
    {
        return $this->findByStatus(Document::STATUS_PENDING);
    }

    /**
     * Trouve tous les documents validés
     */
    public function findValidated(): array
    {
        return $this->findByStatus(Document::STATUS_VALIDATED);
    }

    /**
     * Trouve tous les documents expirés
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->andWhere('d.expirationDate < :now')
            ->setParameter('status', Document::STATUS_VALIDATED)
            ->setParameter('now', new \DateTime())
            ->orderBy('d.expirationDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les documents qui expirent bientôt
     */
    public function findExpiringSoon(int $daysThreshold = 30): array
    {
        $threshold = new \DateTime();
        $threshold->add(new \DateInterval("P{$daysThreshold}D"));

        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->andWhere('d.expirationDate IS NOT NULL')
            ->andWhere('d.expirationDate BETWEEN :now AND :threshold')
            ->setParameter('status', Document::STATUS_VALIDATED)
            ->setParameter('now', new \DateTime())
            ->setParameter('threshold', $threshold)
            ->orderBy('d.expirationDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les documents par type de document
     */
    public function findByDocumentType(DocumentType $documentType): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.documentType = :documentType')
            ->setParameter('documentType', $documentType)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le document d'un utilisateur pour un type de document spécifique
     */
    public function findByUserAndDocumentType(User $user, DocumentType $documentType): ?Document
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->andWhere('d.documentType = :documentType')
            ->setParameter('user', $user)
            ->setParameter('documentType', $documentType)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les documents d'une équipe
     */
    public function findByTeam(Team $team): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.documentType', 'dt')
            ->andWhere('dt.team = :team')
            ->setParameter('team', $team)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les documents d'une équipe par statut
     */
    public function findByTeamAndStatus(Team $team, string $status): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.documentType', 'dt')
            ->andWhere('dt.team = :team')
            ->andWhere('d.status = :status')
            ->setParameter('team', $team)
            ->setParameter('status', $status)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les documents par statut pour une équipe
     */
    public function countByTeamAndStatus(Team $team, string $status): int
    {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.documentType', 'dt')
            ->andWhere('dt.team = :team')
            ->andWhere('d.status = :status')
            ->setParameter('team', $team)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les documents manquants pour un utilisateur dans une équipe
     */
    public function findMissingDocuments(User $user, Team $team): array
    {
        // Récupère tous les types de documents requis pour l'équipe
        $requiredDocumentTypes = $this->getEntityManager()
            ->getRepository(DocumentType::class)
            ->findRequiredByTeam($team);

        $missingDocuments = [];

        foreach ($requiredDocumentTypes as $documentType) {
            $existingDocument = $this->findByUserAndDocumentType($user, $documentType);
            
            if (!$existingDocument || 
                $existingDocument->getStatus() === Document::STATUS_REJECTED ||
                $existingDocument->isExpired()) {
                $missingDocuments[] = $documentType;
            }
        }

        return $missingDocuments;
    }

    /**
     * Vérifie si un utilisateur a tous les documents requis pour une équipe
     */
    public function hasAllRequiredDocuments(User $user, Team $team): bool
    {
        return empty($this->findMissingDocuments($user, $team));
    }

    /**
     * Trouve les documents récents (derniers X jours)
     */
    public function findRecent(int $days = 7): array
    {
        $since = new \DateTime();
        $since->sub(new \DateInterval("P{$days}D"));

        return $this->createQueryBuilder('d')
            ->andWhere('d.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par nom de fichier
     */
    public function findByFileName(string $search): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.originalFileName LIKE :search OR d.name LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des documents par équipe
     */
    public function getTeamDocumentStats(Team $team): array
    {
        return [
            'total' => $this->countByTeam($team),
            'pending' => $this->countByTeamAndStatus($team, Document::STATUS_PENDING),
            'validated' => $this->countByTeamAndStatus($team, Document::STATUS_VALIDATED),
            'rejected' => $this->countByTeamAndStatus($team, Document::STATUS_REJECTED),
            'expired' => $this->countExpiredByTeam($team),
            'expiring_soon' => $this->countExpiringSoonByTeam($team)
        ];
    }

    private function countByTeam(Team $team): int
    {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.documentType', 'dt')
            ->andWhere('dt.team = :team')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countExpiredByTeam(Team $team): int
    {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.documentType', 'dt')
            ->andWhere('dt.team = :team')
            ->andWhere('d.status = :status')
            ->andWhere('d.expirationDate < :now')
            ->setParameter('team', $team)
            ->setParameter('status', Document::STATUS_VALIDATED)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countExpiringSoonByTeam(Team $team, int $daysThreshold = 30): int
    {
        $threshold = new \DateTime();
        $threshold->add(new \DateInterval("P{$daysThreshold}D"));

        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.documentType', 'dt')
            ->andWhere('dt.team = :team')
            ->andWhere('d.status = :status')
            ->andWhere('d.expirationDate IS NOT NULL')
            ->andWhere('d.expirationDate BETWEEN :now AND :threshold')
            ->setParameter('team', $team)
            ->setParameter('status', Document::STATUS_VALIDATED)
            ->setParameter('now', new \DateTime())
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();
    }
}