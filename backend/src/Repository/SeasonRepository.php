<?php

namespace App\Repository;

use App\Entity\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Season>
 *
 * @method Season|null find($id, $lockMode = null, $lockVersion = null)
 * @method Season|null findOneBy(array $criteria, array $orderBy = null)
 * @method Season[]    findAll()
 * @method Season[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SeasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Season::class);
    }

    /**
     * Trouve les saisons expirées avant la date donnée
     * 
     * @param \DateTimeInterface $cutoffDate Date limite (fin de saison + période de grâce)
     * @return Season[]
     */
    public function findExpiredSeasons(\DateTimeInterface $cutoffDate): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.endDate < :cutoffDate')
            ->andWhere('s.isActive = false')
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('s.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les saisons actives
     * 
     * @return Season[]
     */
    public function findActiveSeasons(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = true')
            ->orderBy('s.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la saison active pour un club donné
     * 
     * @param int $clubId
     * @return Season|null
     */
    public function findActiveSeasonByClub(int $clubId): ?Season
    {
        return $this->createQueryBuilder('s')
            ->where('s.club = :clubId')
            ->andWhere('s.isActive = true')
            ->setParameter('clubId', $clubId)
            ->getQuery()
            ->getOneOrNullResult();
    }
} 