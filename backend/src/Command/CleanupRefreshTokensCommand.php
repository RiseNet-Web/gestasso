<?php

namespace App\Command;

use App\Service\RefreshTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-refresh-tokens',
    description: 'Nettoie les refresh tokens expirés et révoqués'
)]
class CleanupRefreshTokensCommand extends Command
{
    public function __construct(
        private RefreshTokenService $refreshTokenService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deletedCount = $this->refreshTokenService->cleanupExpiredTokens();

        $io->success(sprintf(
            '%d refresh tokens expirés ou révoqués ont été supprimés.',
            $deletedCount
        ));

        return Command::SUCCESS;
    }
} 