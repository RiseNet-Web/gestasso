<?php

namespace App\Repository;

use App\Entity\CagnotteTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CagnotteTransaction>
 *
 * @method CagnotteTransaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method CagnotteTransaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method CagnotteTransaction[]    findAll()
 * @method CagnotteTransaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CagnotteTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CagnotteTransaction::class);
    }
} 