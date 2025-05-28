<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Predis\Client as RedisClient;

#[AsCommand(
    name: 'app:cleanup-expired-tokens',
    description: 'Nettoie les tokens JWT expirés du cache Redis',
)]
class CleanupExpiredTokensCommand extends Command
{
    private RedisClient $redis;
    private int $tokenTtl;

    public function __construct(
        string $redisUrl,
        ParameterBagInterface $params
    ) {
        parent::__construct();
        $this->redis = new RedisClient($redisUrl);
        $this->tokenTtl = $params->get('lexik_jwt_authentication.token_ttl');
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afficher les tokens à supprimer sans les supprimer')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Nettoyage des tokens JWT expirés');

        if ($dryRun) {
            $io->warning('Mode dry-run activé - Aucun token ne sera supprimé');
        }

        // Parcourir toutes les clés de type token
        $pattern = 'jwt:*';
        $keys = $this->redis->keys($pattern);
        
        if (empty($keys)) {
            $io->success('Aucun token à nettoyer.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Nombre de tokens trouvés : %d', count($keys)));

        $expiredCount = 0;
        $activeCount = 0;

        foreach ($keys as $key) {
            $ttl = $this->redis->ttl($key);
            
            // Si TTL = -1, la clé n'a pas d'expiration (ne devrait pas arriver)
            // Si TTL = -2, la clé n'existe plus
            if ($ttl === -1) {
                if (!$dryRun) {
                    // Définir une expiration basée sur le TTL configuré
                    $this->redis->expire($key, $this->tokenTtl);
                }
                $io->warning(sprintf('Token sans expiration corrigé : %s', $key));
            } elseif ($ttl === -2 || $ttl === 0) {
                $expiredCount++;
                if (!$dryRun) {
                    $this->redis->del($key);
                }
            } else {
                $activeCount++;
            }
        }

        if ($dryRun) {
            $io->info(sprintf('Tokens expirés à supprimer : %d', $expiredCount));
            $io->info(sprintf('Tokens actifs : %d', $activeCount));
        } else {
            $io->success(sprintf('%d token(s) expiré(s) supprimé(s).', $expiredCount));
            $io->info(sprintf('Tokens actifs restants : %d', $activeCount));
        }

        // Nettoyer aussi les blacklisted tokens
        $this->cleanupBlacklistedTokens($io, $dryRun);

        return Command::SUCCESS;
    }

    /**
     * Nettoie les tokens blacklistés expirés
     */
    private function cleanupBlacklistedTokens(SymfonyStyle $io, bool $dryRun): void
    {
        $pattern = 'jwt:blacklist:*';
        $keys = $this->redis->keys($pattern);
        
        if (empty($keys)) {
            return;
        }

        $io->section('Nettoyage des tokens blacklistés');
        $io->info(sprintf('Nombre de tokens blacklistés trouvés : %d', count($keys)));

        $expiredCount = 0;

        foreach ($keys as $key) {
            $data = $this->redis->get($key);
            if ($data) {
                $tokenData = json_decode($data, true);
                if (isset($tokenData['exp']) && $tokenData['exp'] < time()) {
                    $expiredCount++;
                    if (!$dryRun) {
                        $this->redis->del($key);
                    }
                }
            }
        }

        if ($dryRun) {
            $io->info(sprintf('Tokens blacklistés expirés à supprimer : %d', $expiredCount));
        } else {
            $io->success(sprintf('%d token(s) blacklisté(s) expiré(s) supprimé(s).', $expiredCount));
        }
    }
} 