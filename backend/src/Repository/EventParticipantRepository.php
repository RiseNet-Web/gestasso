<?php

namespace App\Repository;

use App\Entity\EventParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventParticipant>
 *
 * @method EventParticipant|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventParticipant|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventParticipant[]    findAll()
 * @method EventParticipant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventParticipant::class);
    }
} 