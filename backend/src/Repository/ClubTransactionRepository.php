<?php

namespace App\Repository;

use App\Entity\ClubTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClubTransaction>
 *
 * @method ClubTransaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClubTransaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClubTransaction[]    findAll()
 * @method ClubTransaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClubTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClubTransaction::class);
    }
} 