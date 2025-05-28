<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\PaymentDeduction;
use App\Entity\PaymentSchedule;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Repository\PaymentDeductionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentRepository $paymentRepository,
        private PaymentDeductionRepository $paymentDeductionRepository,
        private CagnotteService $cagnotteService,
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ) {}

    /**
     * Crée un paiement avec calcul automatique des déductions
     */
    public function createPaymentWithDeductions(
        User $user,
        Team $team,
        PaymentSchedule $paymentSchedule,
        array $selectedDeductions = []
    ): Payment {
        $baseAmount = (float) $paymentSchedule->getAmount();
        
        // Calculer les déductions applicables
        $deductionDetails = $this->calculateApplicableDeductions($user, $team, $baseAmount, $selectedDeductions);
        
        $totalDeductions = $deductionDetails['total_deductions'];
        $finalAmount = $baseAmount - $totalDeductions;
        
        $this->entityManager->beginTransaction();
        
        try {
            // Créer le paiement
            $payment = new Payment();
            $payment->setUser($user);
            $payment->setTeam($team);
            $payment->setPaymentSchedule($paymentSchedule);
            $payment->setAmount((string) $baseAmount);
            $payment->setAmountPaid('0.00');
            $payment->setDueDate($paymentSchedule->getDueDate());
            $payment->setStatus(Payment::STATUS_PENDING);
            
            // Ajouter les notes sur les déductions appliquées
            if (!empty($deductionDetails['applied_deductions'])) {
                $notes = "Déductions appliquées:\n";
                foreach ($deductionDetails['applied_deductions'] as $deduction) {
                    $notes .= "- {$deduction['name']}: {$deduction['amount']}€\n";
                }
                $notes .= "Montant final: {$finalAmount}€";
                $payment->setNotes($notes);
            }
            
            $this->entityManager->persist($payment);
            
            // Appliquer les déductions de cagnotte si présentes
            foreach ($deductionDetails['applied_deductions'] as $deductionDetail) {
                if ($deductionDetail['type'] === PaymentDeduction::TYPE_CAGNOTTE && $deductionDetail['amount'] > 0) {
                    $this->cagnotteService->useCagnotteForPayment(
                        $user,
                        $team,
                        $deductionDetail['amount'],
                        "Utilisation pour paiement: {$paymentSchedule->getDescription()}"
                    );
                    
                    // Marquer le paiement comme partiellement payé
                    $payment->addPayment($deductionDetail['amount']);
                }
            }
            
            $this->entityManager->commit();
            
            $this->logger->info('Paiement créé avec déductions', [
                'payment_id' => $payment->getId(),
                'user_id' => $user->getId(),
                'team_id' => $team->getId(),
                'base_amount' => $baseAmount,
                'total_deductions' => $totalDeductions,
                'final_amount' => $finalAmount
            ]);
            
            // Envoyer une notification si le paiement n'est pas entièrement payé
            if ($payment->getStatus() !== Payment::STATUS_PAID) {
                $this->notificationService->sendPaymentDueNotification($payment);
            }
            
            return $payment;
            
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Erreur lors de la création du paiement', [
                'user_id' => $user->getId(),
                'team_id' => $team->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Calcule les déductions applicables pour un paiement
     */
    public function calculateApplicableDeductions(
        User $user,
        Team $team,
        float $baseAmount,
        array $selectedDeductions = []
    ): array {
        $result = [
            'base_amount' => $baseAmount,
            'total_deductions' => 0.0,
            'final_amount' => $baseAmount,
            'applied_deductions' => [],
            'available_deductions' => []
        ];
        
        // Récupérer toutes les déductions disponibles
        $availableDeductions = $this->paymentDeductionRepository->findApplicableForUser($user, $team, $baseAmount);
        
        // Si aucune déduction spécifique n'est sélectionnée, utiliser les déductions automatiques
        if (empty($selectedDeductions)) {
            $selectedDeductions = array_filter($availableDeductions, fn($d) => $d->isAutomatic());
        } else {
            // Filtrer les déductions sélectionnées parmi celles disponibles
            $selectedDeductions = array_filter(
                $availableDeductions,
                fn($d) => in_array($d->getId(), $selectedDeductions)
            );
        }
        
        $remainingAmount = $baseAmount;
        $cagnotte = $user->getCagnotteForTeam($team);
        $availableCagnotte = $cagnotte ? (float) $cagnotte->getCurrentAmount() : 0.0;
        
        // Trier les déductions par priorité (cagnotte en dernier pour optimiser l'utilisation)
        usort($selectedDeductions, function($a, $b) {
            if ($a->isCagnotteType() && !$b->isCagnotteType()) return 1;
            if (!$a->isCagnotteType() && $b->isCagnotteType()) return -1;
            return 0;
        });
        
        foreach ($selectedDeductions as $deduction) {
            if ($remainingAmount <= 0) break;
            
            $deductionAmount = $deduction->calculateDeduction(
                $remainingAmount,
                $deduction->isCagnotteType() ? $availableCagnotte : null
            );
            
            if ($deductionAmount > 0) {
                $result['applied_deductions'][] = [
                    'id' => $deduction->getId(),
                    'name' => $deduction->getName(),
                    'type' => $deduction->getType(),
                    'amount' => $deductionAmount,
                    'calculation_type' => $deduction->getCalculationType(),
                    'value' => $deduction->getValue()
                ];
                
                $result['total_deductions'] += $deductionAmount;
                $remainingAmount -= $deductionAmount;
                
                // Si c'est une déduction de cagnotte, réduire le montant disponible
                if ($deduction->isCagnotteType()) {
                    $availableCagnotte -= $deductionAmount;
                }
            }
        }
        
        // Ajouter les déductions disponibles non appliquées
        foreach ($availableDeductions as $deduction) {
            $isApplied = array_filter(
                $result['applied_deductions'],
                fn($applied) => $applied['id'] === $deduction->getId()
            );
            
            if (empty($isApplied)) {
                $potentialAmount = $deduction->calculateDeduction(
                    $baseAmount,
                    $deduction->isCagnotteType() ? $availableCagnotte : null
                );
                
                $result['available_deductions'][] = [
                    'id' => $deduction->getId(),
                    'name' => $deduction->getName(),
                    'type' => $deduction->getType(),
                    'potential_amount' => $potentialAmount,
                    'calculation_type' => $deduction->getCalculationType(),
                    'value' => $deduction->getValue(),
                    'is_automatic' => $deduction->isAutomatic()
                ];
            }
        }
        
        $result['final_amount'] = $remainingAmount;
        
        return $result;
    }

    /**
     * Traite un paiement manuel (espèces, chèque, virement)
     */
    public function processManualPayment(Payment $payment, float $amount, string $method = 'manual'): Payment
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant du paiement doit être positif');
        }
        
        $this->entityManager->beginTransaction();
        
        try {
            $payment->addPayment($amount);
            
            $notes = $payment->getNotes() ?? '';
            $notes .= "\nPaiement {$method}: {$amount}€ le " . (new \DateTime())->format('d/m/Y H:i');
            $payment->setNotes($notes);
            
            $this->entityManager->persist($payment);
            $this->entityManager->commit();
            
            $this->logger->info('Paiement manuel traité', [
                'payment_id' => $payment->getId(),
                'amount' => $amount,
                'method' => $method,
                'new_status' => $payment->getStatus()
            ]);
            
            // Envoyer une notification de confirmation si le paiement est complet
            if ($payment->getStatus() === Payment::STATUS_PAID) {
                $this->notificationService->sendPaymentConfirmationNotification($payment);
            }
            
            return $payment;
            
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Erreur lors du traitement du paiement manuel', [
                'payment_id' => $payment->getId(),
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Applique une déduction supplémentaire à un paiement existant
     */
    public function applyAdditionalDeduction(Payment $payment, PaymentDeduction $deduction): Payment
    {
        $user = $payment->getUser();
        $team = $payment->getTeam();
        $remainingAmount = $payment->getRemainingAmount();
        
        if ($remainingAmount <= 0) {
            throw new \InvalidArgumentException('Ce paiement est déjà entièrement payé');
        }
        
        if (!$deduction->canBeAppliedTo($user, $remainingAmount)) {
            throw new \InvalidArgumentException('Cette déduction ne peut pas être appliquée à ce paiement');
        }
        
        $this->entityManager->beginTransaction();
        
        try {
            $cagnotte = $user->getCagnotteForTeam($team);
            $availableCagnotte = $cagnotte ? (float) $cagnotte->getCurrentAmount() : 0.0;
            
            $deductionAmount = $deduction->calculateDeduction(
                $remainingAmount,
                $deduction->isCagnotteType() ? $availableCagnotte : null
            );
            
            if ($deductionAmount > 0) {
                // Si c'est une déduction de cagnotte, utiliser le service de cagnotte
                if ($deduction->isCagnotteType()) {
                    $this->cagnotteService->useCagnotteForPayment(
                        $user,
                        $team,
                        $deductionAmount,
                        "Déduction supplémentaire: {$deduction->getName()}"
                    );
                }
                
                // Ajouter le paiement
                $payment->addPayment($deductionAmount);
                
                // Mettre à jour les notes
                $notes = $payment->getNotes() ?? '';
                $notes .= "\nDéduction appliquée: {$deduction->getName()} - {$deductionAmount}€";
                $payment->setNotes($notes);
                
                $this->entityManager->persist($payment);
            }
            
            $this->entityManager->commit();
            
            $this->logger->info('Déduction supplémentaire appliquée', [
                'payment_id' => $payment->getId(),
                'deduction_id' => $deduction->getId(),
                'amount' => $deductionAmount
            ]);
            
            return $payment;
            
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Erreur lors de l\'application de la déduction', [
                'payment_id' => $payment->getId(),
                'deduction_id' => $deduction->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Génère les paiements pour tous les membres d'une équipe à partir d'un échéancier
     */
    public function generateTeamPayments(PaymentSchedule $paymentSchedule): array
    {
        $team = $paymentSchedule->getTeam();
        $activeMembers = $team->getActiveMembers();
        $createdPayments = [];
        
        $this->entityManager->beginTransaction();
        
        try {
            foreach ($activeMembers as $teamMember) {
                $user = $teamMember->getUser();
                
                // Vérifier qu'un paiement n'existe pas déjà pour cet utilisateur et cet échéancier
                $existingPayment = $this->paymentRepository->findByUserAndSchedule($user, $paymentSchedule);
                
                if (!$existingPayment) {
                    $payment = $this->createPaymentWithDeductions($user, $team, $paymentSchedule);
                    $createdPayments[] = $payment;
                }
            }
            
            $this->entityManager->commit();
            
            $this->logger->info('Paiements générés pour l\'équipe', [
                'team_id' => $team->getId(),
                'schedule_id' => $paymentSchedule->getId(),
                'payments_created' => count($createdPayments)
            ]);
            
            return $createdPayments;
            
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Erreur lors de la génération des paiements d\'équipe', [
                'team_id' => $team->getId(),
                'schedule_id' => $paymentSchedule->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Calcule les statistiques de paiement pour une équipe
     */
    public function getTeamPaymentStats(Team $team): array
    {
        $payments = $this->paymentRepository->findByTeam($team);
        
        $stats = [
            'total_payments' => count($payments),
            'total_amount' => 0.0,
            'total_paid' => 0.0,
            'total_remaining' => 0.0,
            'pending_count' => 0,
            'partial_count' => 0,
            'paid_count' => 0,
            'overdue_count' => 0,
            'overdue_amount' => 0.0
        ];
        
        foreach ($payments as $payment) {
            $amount = (float) $payment->getAmount();
            $amountPaid = (float) $payment->getAmountPaid();
            $remaining = $payment->getRemainingAmount();
            
            $stats['total_amount'] += $amount;
            $stats['total_paid'] += $amountPaid;
            $stats['total_remaining'] += $remaining;
            
            switch ($payment->getStatus()) {
                case Payment::STATUS_PENDING:
                    $stats['pending_count']++;
                    break;
                case Payment::STATUS_PARTIAL:
                    $stats['partial_count']++;
                    break;
                case Payment::STATUS_PAID:
                    $stats['paid_count']++;
                    break;
                case Payment::STATUS_OVERDUE:
                    $stats['overdue_count']++;
                    $stats['overdue_amount'] += $remaining;
                    break;
            }
        }
        
        // Calculer les pourcentages
        if ($stats['total_payments'] > 0) {
            $stats['payment_rate'] = ($stats['paid_count'] / $stats['total_payments']) * 100;
        } else {
            $stats['payment_rate'] = 0;
        }
        
        if ($stats['total_amount'] > 0) {
            $stats['collection_rate'] = ($stats['total_paid'] / $stats['total_amount']) * 100;
        } else {
            $stats['collection_rate'] = 0;
        }
        
        return $stats;
    }

    /**
     * Envoie des rappels pour les paiements en retard
     */
    public function sendOverdueReminders(): int
    {
        $overduePayments = $this->paymentRepository->findOverdue();
        $remindersSent = 0;
        
        foreach ($overduePayments as $payment) {
            try {
                $this->notificationService->sendPaymentOverdueNotification($payment);
                $remindersSent++;
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi du rappel de paiement', [
                    'payment_id' => $payment->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->info('Rappels de paiement envoyés', [
            'total_overdue' => count($overduePayments),
            'reminders_sent' => $remindersSent
        ]);
        
        return $remindersSent;
    }

    public function createPaymentFromSchedule(PaymentSchedule $schedule, User $user): Payment
    {
        $payment = new Payment();
        $payment->setPaymentSchedule($schedule);
        $payment->setTeam($schedule->getTeam());
        $payment->setUser($user);
        $payment->setAmount($schedule->getAmount());
        $payment->setDueDate($schedule->getDueDate());
        $payment->setStatus('pending');
        return $payment;
    }

    public function updatePayment(Payment $payment, array $data): Payment
    {
        if (isset($data['amountPaid'])) {
            $payment->setAmountPaid($data['amountPaid']);
            if ($payment->getAmountPaid() >= $payment->getAmount()) {
                $payment->setStatus('paid');
                $payment->setPaidAt(new \DateTime());
            } elseif ($payment->getAmountPaid() > 0) {
                $payment->setStatus('partial');
            }
        }
        if (isset($data['notes'])) {
            $payment->setNotes($data['notes']);
        }
        return $payment;
    }
} 