<?php

namespace App\Repository;

use App\Entity\DocumentType;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentType>
 */
class DocumentTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentType::class);
    }

    /**
     * Trouve tous les types de documents actifs pour une équipe
     */
    public function findActiveByTeam(Team $team): array
    {
        return $this->createQueryBuilder('dt')
            ->andWhere('dt.team = :team')
            ->andWhere('dt.isActive = true')
            ->setParameter('team', $team)
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les types de documents requis pour une équipe
     */
    public function findRequiredByTeam(Team $team): array
    {
        return $this->createQueryBuilder('dt')
            ->andWhere('dt.team = :team')
            ->andWhere('dt.isActive = true')
            ->andWhere('dt.isRequired = true')
            ->setParameter('team', $team)
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les types de documents par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('dt')
            ->andWhere('dt.type = :type')
            ->andWhere('dt.isActive = true')
            ->setParameter('type', $type)
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les types de documents avec date d'expiration
     */
    public function findExpirable(): array
    {
        return $this->createQueryBuilder('dt')
            ->andWhere('dt.hasExpirationDate = true')
            ->andWhere('dt.validityDurationInDays IS NOT NULL')
            ->andWhere('dt.isActive = true')
            ->orderBy('dt.validityDurationInDays', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les types de documents par équipe
     */
    public function countByTeam(Team $team): int
    {
        return $this->createQueryBuilder('dt')
            ->select('COUNT(dt.id)')
            ->andWhere('dt.team = :team')
            ->andWhere('dt.isActive = true')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les types de documents avec leurs statistiques de documents
     */
    public function findWithDocumentStats(Team $team): array
    {
        return $this->createQueryBuilder('dt')
            ->select('dt', 'COUNT(d.id) as documentCount')
            ->leftJoin('dt.documents', 'd')
            ->andWhere('dt.team = :team')
            ->andWhere('dt.isActive = true')
            ->setParameter('team', $team)
            ->groupBy('dt.id')
            ->orderBy('dt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par nom
     */
    public function findByNameSearch(string $search, ?Team $team = null): array
    {
        $qb = $this->createQueryBuilder('dt')
            ->andWhere('dt.name LIKE :search')
            ->andWhere('dt.isActive = true')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('dt.name', 'ASC');

        if ($team) {
            $qb->andWhere('dt.team = :team')
               ->setParameter('team', $team);
        }

        return $qb->getQuery()->getResult();
    }
} 