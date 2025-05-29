<?php

namespace App\Repository;

use App\Entity\PaymentSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentSchedule>
 *
 * @method PaymentSchedule|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaymentSchedule|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaymentSchedule[]    findAll()
 * @method PaymentSchedule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentSchedule::class);
    }
} 