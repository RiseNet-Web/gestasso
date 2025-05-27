<?php

namespace App\Repository;

use App\Entity\UserAuthentication;
use App\Enum\AuthProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAuthentication>
 */
class UserAuthenticationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAuthentication::class);
    }

    /**
     * Trouve une authentification par provider et provider ID
     */
    public function findByProviderAndProviderId(AuthProvider $provider, string $providerId): ?UserAuthentication
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.provider = :provider')
            ->andWhere('ua.providerId = :providerId')
            ->andWhere('ua.isActive = true')
            ->setParameter('provider', $provider)
            ->setParameter('providerId', $providerId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve une authentification par provider et email
     */
    public function findByProviderAndEmail(AuthProvider $provider, string $email): ?UserAuthentication
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.provider = :provider')
            ->andWhere('ua.email = :email')
            ->andWhere('ua.isActive = true')
            ->setParameter('provider', $provider)
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les authentifications d'un utilisateur
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.user = :userId')
            ->andWhere('ua.isActive = true')
            ->setParameter('userId', $userId)
            ->orderBy('ua.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve l'authentification principale (email) d'un utilisateur
     */
    public function findPrimaryByUser(int $userId): ?UserAuthentication
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.user = :userId')
            ->andWhere('ua.provider = :provider')
            ->andWhere('ua.isActive = true')
            ->setParameter('userId', $userId)
            ->setParameter('provider', AuthProvider::EMAIL)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les authentifications sociales d'un utilisateur
     */
    public function findSocialByUser(int $userId): array
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.user = :userId')
            ->andWhere('ua.provider IN (:providers)')
            ->andWhere('ua.isActive = true')
            ->setParameter('userId', $userId)
            ->setParameter('providers', [AuthProvider::GOOGLE, AuthProvider::APPLE])
            ->orderBy('ua.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un email est déjà utilisé pour un provider donné
     */
    public function isEmailUsedForProvider(string $email, AuthProvider $provider, ?int $excludeUserId = null): bool
    {
        $qb = $this->createQueryBuilder('ua')
            ->select('COUNT(ua.id)')
            ->andWhere('ua.email = :email')
            ->andWhere('ua.provider = :provider')
            ->andWhere('ua.isActive = true')
            ->setParameter('email', $email)
            ->setParameter('provider', $provider);

        if ($excludeUserId) {
            $qb->andWhere('ua.user != :excludeUserId')
               ->setParameter('excludeUserId', $excludeUserId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Met à jour la dernière connexion
     */
    public function updateLastLogin(UserAuthentication $userAuth): void
    {
        $userAuth->updateLastLogin();
        $this->getEntityManager()->flush();
    }

    /**
     * Désactive toutes les authentifications d'un utilisateur
     */
    public function deactivateAllForUser(int $userId): void
    {
        $this->createQueryBuilder('ua')
            ->update()
            ->set('ua.isActive', ':isActive')
            ->andWhere('ua.user = :userId')
            ->setParameter('isActive', false)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
} 