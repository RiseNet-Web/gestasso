<?php

namespace App\Repository;

use App\Entity\ClubManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClubManager>
 *
 * @method ClubManager|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClubManager|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClubManager[]    findAll()
 * @method ClubManager[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClubManagerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClubManager::class);
    }
} 