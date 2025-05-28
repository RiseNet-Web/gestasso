<?php

namespace App\Command;

use App\Entity\Payment;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:payment-reminders',
    description: 'Envoie des rappels automatiques pour les paiements en retard ou à venir',
)]
class PaymentRemindersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days-before', null, InputOption::VALUE_REQUIRED, 'Nombre de jours avant l\'échéance pour envoyer un rappel', 7)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Exécuter sans envoyer réellement les notifications')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $daysBefore = (int) $input->getOption('days-before');
        $dryRun = $input->getOption('dry-run');

        $io->title('Envoi des rappels de paiement');

        // Date limite pour les rappels
        $reminderDate = new \DateTime();
        $reminderDate->modify("+{$daysBefore} days");

        // Récupérer les paiements à rappeler
        $payments = $this->getPaymentsToRemind($reminderDate);
        
        if (empty($payments)) {
            $io->success('Aucun rappel à envoyer.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Nombre de rappels à envoyer : %d', count($payments)));

        if ($dryRun) {
            $io->warning('Mode dry-run activé - Aucune notification ne sera envoyée');
            
            foreach ($payments as $payment) {
                $io->writeln(sprintf(
                    '- %s %s : %s€ (échéance : %s)',
                    $payment->getUser()->getFirstName(),
                    $payment->getUser()->getLastName(),
                    $payment->getAmount(),
                    $payment->getDueDate()->format('d/m/Y')
                ));
            }
        } else {
            $count = $this->notificationService->sendPaymentReminders($payments);
            $io->success(sprintf('%d rappels envoyés avec succès.', $count));
        }

        // Mettre à jour les statuts des paiements en retard
        $this->updateOverduePayments();

        return Command::SUCCESS;
    }

    /**
     * Récupère les paiements nécessitant un rappel
     */
    private function getPaymentsToRemind(\DateTime $reminderDate): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        return $qb->select('p')
            ->from(Payment::class, 'p')
            ->join('p.user', 'u')
            ->join('p.team', 't')
            ->where('p.status IN (:statuses)')
            ->andWhere('p.dueDate <= :reminderDate')
            ->andWhere('p.dueDate >= :today')
            ->andWhere('u.isActive = :active')
            ->andWhere('t.isActive = :active')
            ->setParameter('statuses', ['pending', 'partial'])
            ->setParameter('reminderDate', $reminderDate)
            ->setParameter('today', new \DateTime())
            ->setParameter('active', true)
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour le statut des paiements en retard
     */
    private function updateOverduePayments(): void
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(Payment::class, 'p')
            ->set('p.status', ':overdue')
            ->where('p.status = :pending')
            ->andWhere('p.dueDate < :today')
            ->setParameter('overdue', 'overdue')
            ->setParameter('pending', 'pending')
            ->setParameter('today', $today)
            ->getQuery()
            ->execute();

        $this->entityManager->flush();
    }
} 