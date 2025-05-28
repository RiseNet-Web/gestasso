<?php

namespace App\Command;

use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-payment-status',
    description: 'Met à jour automatiquement les statuts des paiements (pending vers overdue)',
)]
class UpdatePaymentStatusCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Mise à jour des statuts de paiement');

        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        // Mettre à jour les paiements en retard
        $overdueCount = $this->updateOverduePayments($today);
        
        if ($overdueCount > 0) {
            $io->success(sprintf('%d paiement(s) marqué(s) comme en retard.', $overdueCount));
        } else {
            $io->info('Aucun paiement à mettre à jour.');
        }

        // Afficher un résumé
        $this->displaySummary($io);

        return Command::SUCCESS;
    }

    /**
     * Met à jour les paiements en retard
     */
    private function updateOverduePayments(\DateTime $today): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Récupérer les paiements à mettre à jour
        $payments = $qb->select('p')
            ->from(Payment::class, 'p')
            ->where('p.status = :pending')
            ->andWhere('p.dueDate < :today')
            ->setParameter('pending', 'pending')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($payments as $payment) {
            $payment->setStatus('overdue');
            $count++;
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    /**
     * Affiche un résumé des statuts de paiement
     */
    private function displaySummary(SymfonyStyle $io): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        // Compter par statut
        $summary = $qb->select('p.status, COUNT(p.id) as count')
            ->from(Payment::class, 'p')
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        $io->section('Résumé des statuts de paiement');
        
        $rows = [];
        $total = 0;
        
        foreach ($summary as $row) {
            $rows[] = [
                $this->getStatusLabel($row['status']),
                $row['count']
            ];
            $total += $row['count'];
        }
        
        $io->table(['Statut', 'Nombre'], $rows);
        $io->info(sprintf('Total : %d paiements', $total));
    }

    /**
     * Obtient le libellé d'un statut
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'En attente',
            'partial' => 'Partiel',
            'paid' => 'Payé',
            'overdue' => 'En retard',
            default => $status
        };
    }
}