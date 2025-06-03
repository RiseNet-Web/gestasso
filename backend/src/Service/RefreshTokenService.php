<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class RefreshTokenService
{
    private const DEFAULT_TTL = 2592000; // 30 jours
    private const MAX_TOKENS_PER_USER = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RefreshTokenRepository $refreshTokenRepository
    ) {}

    public function createRefreshToken(
        User $user, 
        Request $request = null, 
        int $ttl = self::DEFAULT_TTL
    ): RefreshToken {
        // Limite du nombre de tokens par utilisateur
        $this->refreshTokenRepository->limitUserTokens($user, self::MAX_TOKENS_PER_USER);
        
        $refreshToken = new RefreshToken();
        $refreshToken->setUser($user)
                    ->setToken($this->generateSecureToken())
                    ->setExpiresAt(new \DateTime('+' . $ttl . ' seconds'));

        if ($request) {
            $refreshToken->setIpAddress($this->getClientIp($request))
                        ->setUserAgent($request->headers->get('User-Agent', ''));
        }

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return $refreshToken;
    }

    public function findValidToken(string $token): ?RefreshToken
    {
        return $this->refreshTokenRepository->findValidTokenByToken($token);
    }

    public function refreshToken(RefreshToken $refreshToken, Request $request = null): RefreshToken
    {
        if (!$refreshToken->isValid()) {
            throw new \InvalidArgumentException('Token de rafraîchissement invalide ou expiré');
        }

        $user = $refreshToken->getUser();
        
        // Mettre à jour l'utilisation du token existant
        $refreshToken->setLastUsedAt(new \DateTime());
        
        if ($request) {
            $refreshToken->setIpAddress($this->getClientIp($request));
        }

        // Créer un nouveau refresh token (rotation des tokens)
        $newRefreshToken = $this->createRefreshToken($user, $request);
        
        // Révoquer l'ancien token
        $refreshToken->setIsRevoked(true);
        
        $this->entityManager->flush();

        return $newRefreshToken;
    }

    public function revokeToken(string $token): void
    {
        $this->refreshTokenRepository->revokeTokenByToken($token);
    }

    public function revokeAllUserTokens(User $user): void
    {
        $this->refreshTokenRepository->revokeAllUserTokens($user);
    }

    public function cleanupExpiredTokens(): int
    {
        return $this->refreshTokenRepository->deleteExpiredTokens();
    }

    public function getUserActiveTokens(User $user): array
    {
        return $this->refreshTokenRepository->findValidTokensByUser($user);
    }

    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 caractères hexadécimaux
    }

    private function getClientIp(Request $request): string
    {
        $ipAddress = $request->getClientIp();
        
        // Tronquer l'IP si elle est trop longue (IPv6)
        if (strlen($ipAddress) > 45) {
            $ipAddress = substr($ipAddress, 0, 45);
        }
        
        return $ipAddress ?? 'unknown';
    }
} 