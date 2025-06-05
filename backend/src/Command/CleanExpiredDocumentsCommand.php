<?php

namespace App\Command;

use App\Repository\SeasonRepository;
use App\Repository\DocumentRepository;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:clean-expired-documents',
    description: 'Supprime les documents des saisons expirées'
)]
class CleanExpiredDocumentsCommand extends Command
{
    public function __construct(
        private SeasonRepository $seasonRepository,
        private DocumentRepository $documentRepository,
        private DocumentService $documentService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $uploadsDirectory
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui sera supprimé sans supprimer réellement')
            ->addOption('grace-period', null, InputOption::VALUE_REQUIRED, 'Période de grâce en jours après expiration', 30)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force la suppression sans confirmation')
            ->setHelp('
Cette commande supprime automatiquement les documents des saisons expirées.
Elle inclut une période de grâce configurable (30 jours par défaut) après la fin de saison.

Exemples d\'utilisation :
  php bin/console app:clean-expired-documents --dry-run
  php bin/console app:clean-expired-documents --grace-period=60
  php bin/console app:clean-expired-documents --force
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $gracePeriod = (int) $input->getOption('grace-period');
        $force = $input->getOption('force');

        $io->title('Nettoyage des documents des saisons expirées');

        // Calculer la date limite (fin de saison + période de grâce)
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$gracePeriod} days");

        $io->info("Date limite : {$cutoffDate->format('d/m/Y')} (période de grâce : {$gracePeriod} jours)");

        // Trouver les saisons expirées
        $expiredSeasons = $this->seasonRepository->findExpiredSeasons($cutoffDate);

        if (empty($expiredSeasons)) {
            $io->success('Aucune saison expirée trouvée.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Saisons expirées trouvées : %d', count($expiredSeasons)));

        $totalDocuments = 0;
        $totalSize = 0;
        $documentsToDelete = [];

        // Collecter tous les documents à supprimer
        foreach ($expiredSeasons as $season) {
            $io->section("Saison : {$season->getName()} (fin : {$season->getEndDate()->format('d/m/Y')})");
            
            $seasonDocuments = $this->documentRepository->findByExpiredSeason($season);
            
            if (empty($seasonDocuments)) {
                $io->text('  Aucun document trouvé');
                continue;
            }

            $io->text(sprintf('  Documents trouvés : %d', count($seasonDocuments)));

            foreach ($seasonDocuments as $document) {
                $filePath = $this->uploadsDirectory . '/' . $document->getFilePath();
                
                if (file_exists($filePath)) {
                    $fileSize = filesize($filePath);
                    $totalSize += $fileSize;
                    
                    $documentsToDelete[] = [
                        'document' => $document,
                        'filePath' => $filePath,
                        'size' => $fileSize,
                        'season' => $season
                    ];
                }
            }

            $totalDocuments += count($seasonDocuments);
        }

        if (empty($documentsToDelete)) {
            $io->warning('Aucun fichier physique à supprimer.');
            return Command::SUCCESS;
        }

        // Afficher le résumé
        $io->section('Résumé');
        $io->text([
            sprintf('Documents à supprimer : %d', count($documentsToDelete)),
            sprintf('Espace à libérer : %s', $this->formatBytes($totalSize))
        ]);

        if ($dryRun) {
            $io->warning('Mode dry-run activé - Aucune suppression ne sera effectuée');
            
            $io->table(
                ['Saison', 'Document', 'Taille', 'Chemin'],
                array_map(function($item) {
                    return [
                        $item['season']->getName(),
                        $item['document']->getOriginalName(),
                        $this->formatBytes($item['size']),
                        $item['filePath']
                    ];
                }, array_slice($documentsToDelete, 0, 10)) // Afficher seulement les 10 premiers
            );

            if (count($documentsToDelete) > 10) {
                $io->text(sprintf('... et %d autres documents', count($documentsToDelete) - 10));
            }

            return Command::SUCCESS;
        }

        // Demander confirmation si pas en mode force
        if (!$force) {
            if (!$io->confirm(sprintf(
                'Voulez-vous vraiment supprimer %d documents (%s) ?',
                count($documentsToDelete),
                $this->formatBytes($totalSize)
            ))) {
                $io->info('Opération annulée.');
                return Command::SUCCESS;
            }
        }

        // Procéder à la suppression
        $io->section('Suppression en cours...');
        $progressBar = $io->createProgressBar(count($documentsToDelete));

        $deletedCount = 0;
        $freedSpace = 0;
        $errors = [];

        foreach ($documentsToDelete as $item) {
            try {
                $document = $item['document'];
                $filePath = $item['filePath'];
                $fileSize = $item['size'];

                // Supprimer le fichier physique
                if (unlink($filePath)) {
                    $freedSpace += $fileSize;
                    
                    // Supprimer l'enregistrement de la base de données
                    $this->entityManager->remove($document);
                    
                    $deletedCount++;
                    
                    // Log de l'action
                    $this->logger->info('Document supprimé lors du nettoyage automatique', [
                        'document_id' => $document->getId(),
                        'document_name' => $document->getOriginalName(),
                        'season_id' => $item['season']->getId(),
                        'season_name' => $item['season']->getName(),
                        'file_size' => $fileSize,
                        'file_path' => $filePath
                    ]);
                } else {
                    $errors[] = "Impossible de supprimer le fichier : {$filePath}";
                }

            } catch (\Exception $e) {
                $errors[] = sprintf(
                    'Erreur lors de la suppression du document %d : %s',
                    $item['document']->getId(),
                    $e->getMessage()
                );
            }

            $progressBar->advance();
        }

        // Sauvegarder les changements en base
        $this->entityManager->flush();

        $progressBar->finish();
        $io->newLine(2);

        // Afficher les résultats
        $io->success([
            sprintf('Nettoyage terminé !'),
            sprintf('Documents supprimés : %d/%d', $deletedCount, count($documentsToDelete)),
            sprintf('Espace libéré : %s', $this->formatBytes($freedSpace))
        ]);

        if (!empty($errors)) {
            $io->warning('Erreurs rencontrées :');
            foreach ($errors as $error) {
                $io->text("  - {$error}");
            }
        }

        // Log du résumé
        $this->logger->info('Nettoyage automatique des documents terminé', [
            'documents_deleted' => $deletedCount,
            'space_freed' => $freedSpace,
            'errors_count' => count($errors)
        ]);

        return Command::SUCCESS;
    }

    private function formatBytes(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
} 