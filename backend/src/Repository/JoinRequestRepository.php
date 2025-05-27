<?php

namespace App\Repository;

use App\Entity\JoinRequest;
use App\Entity\User;
use App\Entity\Team;
use App\Entity\Club;
use App\Enum\JoinRequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JoinRequest>
 */
class JoinRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JoinRequest::class);
    }

    /**
     * Trouve les demandes en attente pour un club
     */
    public function findPendingByClub(Club $club): array
    {
        return $this->createQueryBuilder('jr')
            ->andWhere('jr.club = :club')
            ->andWhere('jr.status = :status')
            ->setParameter('club', $club)
            ->setParameter('status', JoinRequestStatus::PENDING)
            ->orderBy('jr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes en attente pour une équipe
     */
    public function findPendingByTeam(Team $team): array
    {
        return $this->createQueryBuilder('jr')
            ->andWhere('jr.team = :team')
            ->andWhere('jr.status = :status')
            ->setParameter('team', $team)
            ->setParameter('status', JoinRequestStatus::PENDING)
            ->orderBy('jr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les demandes d'un utilisateur
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('jr')
            ->andWhere('jr.user = :user')
            ->setParameter('user', $user)
            ->orderBy('jr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes en attente d'un utilisateur
     */
    public function findPendingByUser(User $user): array
    {
        return $this->createQueryBuilder('jr')
            ->andWhere('jr.user = :user')
            ->andWhere('jr.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', JoinRequestStatus::PENDING)
            ->orderBy('jr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur a déjà une demande en attente pour une équipe
     */
    public function hasPendingRequestForTeam(User $user, Team $team): bool
    {
        $count = $this->createQueryBuilder('jr')
            ->select('COUNT(jr.id)')
            ->andWhere('jr.user = :user')
            ->andWhere('jr.team = :team')
            ->andWhere('jr.status = :status')
            ->setParameter('user', $user)
            ->setParameter('team', $team)
            ->setParameter('status', JoinRequestStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Trouve les demandes anciennes (non traitées depuis X jours)
     */
    public function findOldPendingRequests(int $daysThreshold = 7): array
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval("P{$daysThreshold}D"));

        return $this->createQueryBuilder('jr')
            ->andWhere('jr.status = :status')
            ->andWhere('jr.createdAt <= :date')
            ->setParameter('status', JoinRequestStatus::PENDING)
            ->setParameter('date', $date)
            ->orderBy('jr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes traitées par un utilisateur
     */
    public function findReviewedBy(User $reviewer): array
    {
        return $this->createQueryBuilder('jr')
            ->andWhere('jr.reviewedBy = :reviewer')
            ->andWhere('jr.status IN (:statuses)')
            ->setParameter('reviewer', $reviewer)
            ->setParameter('statuses', [JoinRequestStatus::APPROVED, JoinRequestStatus::REJECTED])
            ->orderBy('jr.reviewedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des demandes pour un club
     */
    public function getStatsForClub(Club $club): array
    {
        $qb = $this->createQueryBuilder('jr')
            ->select('jr.status, COUNT(jr.id) as count')
            ->andWhere('jr.club = :club')
            ->setParameter('club', $club)
            ->groupBy('jr.status');

        $results = $qb->getQuery()->getResult();
        
        $stats = [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'total' => 0
        ];

        foreach ($results as $result) {
            $stats[$result['status']] = (int) $result['count'];
            $stats['total'] += (int) $result['count'];
        }

        return $stats;
    }

    /**
     * Trouve les demandes récentes (dernières 24h)
     */
    public function findRecentRequests(): array
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('P1D'));

        return $this->createQueryBuilder('jr')
            ->andWhere('jr.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('jr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes par statut avec pagination
     */
    public function findByStatusWithPagination(string $status, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('jr')
            ->andWhere('jr.status = :status')
            ->setParameter('status', $status)
            ->orderBy('jr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les demandes en attente pour un club
     */
    public function countPendingForClub(Club $club): int
    {
        return $this->createQueryBuilder('jr')
            ->select('COUNT(jr.id)')
            ->andWhere('jr.club = :club')
            ->andWhere('jr.status = :status')
            ->setParameter('club', $club)
            ->setParameter('status', JoinRequestStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les demandes avec les informations utilisateur et équipe
     */
    public function findWithUserAndTeam(): array
    {
        return $this->createQueryBuilder('jr')
            ->leftJoin('jr.user', 'u')
            ->leftJoin('jr.team', 't')
            ->leftJoin('jr.club', 'c')
            ->addSelect('u', 't', 'c')
            ->orderBy('jr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
} 