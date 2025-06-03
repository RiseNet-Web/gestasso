<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findValidTokenByToken(string $token): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.token = :token')
            ->andWhere('rt.isRevoked = false')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findValidTokensByUser(User $user): array
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.user = :user')
            ->andWhere('rt.isRevoked = false')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('rt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function revokeAllUserTokens(User $user): int
    {
        return $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.isRevoked', 'true')
            ->where('rt.user = :user')
            ->andWhere('rt.isRevoked = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function revokeTokenByToken(string $token): int
    {
        return $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.isRevoked', 'true')
            ->where('rt.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->execute();
    }

    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :now')
            ->orWhere('rt.isRevoked = true')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    public function countUserTokens(User $user): int
    {
        return $this->createQueryBuilder('rt')
            ->select('COUNT(rt.id)')
            ->where('rt.user = :user')
            ->andWhere('rt.isRevoked = false')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Limite le nombre de tokens actifs par utilisateur
     * Révoque les plus anciens si la limite est dépassée
     */
    public function limitUserTokens(User $user, int $maxTokens = 5): void
    {
        $tokens = $this->findValidTokensByUser($user);
        
        if (count($tokens) > $maxTokens) {
            $tokensToRevoke = array_slice($tokens, $maxTokens);
            
            foreach ($tokensToRevoke as $token) {
                $token->setIsRevoked(true);
            }
            
            $this->getEntityManager()->flush();
        }
    }
} 