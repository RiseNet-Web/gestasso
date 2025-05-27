<?php

namespace App\Repository;

use App\Entity\ClubFinance;
use App\Entity\Club;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClubFinance>
 */
class ClubFinanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClubFinance::class);
    }

    /**
     * Trouve les finances d'un club
     */
    public function findByClub(Club $club): ?ClubFinance
    {
        return $this->createQueryBuilder('cf')
            ->andWhere('cf.club = :club')
            ->setParameter('club', $club)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les clubs avec des finances actives
     */
    public function findActiveFinances(): array
    {
        return $this->createQueryBuilder('cf')
            ->andWhere('cf.totalCommission > :zero')
            ->setParameter('zero', '0.00')
            ->orderBy('cf.totalCommission', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les clubs avec un solde positif
     */
    public function findWithPositiveBalance(): array
    {
        return $this->createQueryBuilder('cf')
            ->andWhere('cf.currentBalance > :zero')
            ->setParameter('zero', '0.00')
            ->orderBy('cf.currentBalance', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les clubs avec un solde négatif
     */
    public function findWithNegativeBalance(): array
    {
        return $this->createQueryBuilder('cf')
            ->andWhere('cf.currentBalance < :zero')
            ->setParameter('zero', '0.00')
            ->orderBy('cf.currentBalance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les clubs avec un solde insuffisant pour un montant donné
     */
    public function findWithInsufficientFunds(string $requiredAmount): array
    {
        return $this->createQueryBuilder('cf')
            ->andWhere('cf.currentBalance < :amount')
            ->setParameter('amount', $requiredAmount)
            ->orderBy('cf.currentBalance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le total des commissions de tous les clubs
     */
    public function getTotalCommissionsSum(): string
    {
        $result = $this->createQueryBuilder('cf')
            ->select('SUM(cf.totalCommission) as total')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Calcule le total des soldes de tous les clubs
     */
    public function getTotalBalanceSum(): string
    {
        $result = $this->createQueryBuilder('cf')
            ->select('SUM(cf.currentBalance) as total')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Statistiques financières globales
     */
    public function getGlobalFinancialStats(): array
    {
        $qb = $this->createQueryBuilder('cf')
            ->select([
                'COUNT(cf.id) as totalClubs',
                'SUM(cf.totalCommission) as totalCommissions',
                'SUM(cf.currentBalance) as totalBalance',
                'AVG(cf.totalCommission) as avgCommission',
                'AVG(cf.currentBalance) as avgBalance'
            ]);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'totalClubs' => (int) $result['totalClubs'],
            'totalCommissions' => $result['totalCommissions'] ?? '0.00',
            'totalBalance' => $result['totalBalance'] ?? '0.00',
            'avgCommission' => $result['avgCommission'] ?? '0.00',
            'avgBalance' => $result['avgBalance'] ?? '0.00',
            'totalSpent' => bcsub($result['totalCommissions'] ?? '0.00', $result['totalBalance'] ?? '0.00', 2)
        ];
    }

    /**
     * Trouve les finances par plage de solde
     */
    public function findByBalanceRange(string $minBalance, string $maxBalance): array
    {
        return $this->createQueryBuilder('cf')
            ->andWhere('cf.currentBalance >= :min')
            ->andWhere('cf.currentBalance <= :max')
            ->setParameter('min', $minBalance)
            ->setParameter('max', $maxBalance)
            ->orderBy('cf.currentBalance', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les finances modifiées récemment
     */
    public function findRecentlyUpdated(int $days = 7): array
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval("P{$days}D"));

        return $this->createQueryBuilder('cf')
            ->andWhere('cf.updatedAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('cf.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les clubs par statut financier
     */
    public function countByFinancialStatus(): array
    {
        $qb = $this->createQueryBuilder('cf');
        
        $positive = $qb->select('COUNT(cf.id)')
            ->andWhere('cf.currentBalance > :zero')
            ->setParameter('zero', '0.00')
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('cf');
        $negative = $qb->select('COUNT(cf.id)')
            ->andWhere('cf.currentBalance < :zero')
            ->setParameter('zero', '0.00')
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('cf');
        $zero = $qb->select('COUNT(cf.id)')
            ->andWhere('cf.currentBalance = :zero')
            ->setParameter('zero', '0.00')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'positive' => (int) $positive,
            'negative' => (int) $negative,
            'zero' => (int) $zero,
            'total' => (int) $positive + (int) $negative + (int) $zero
        ];
    }
} 