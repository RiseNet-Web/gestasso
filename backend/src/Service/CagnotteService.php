<?php

namespace App\Service;

use App\Entity\Cagnotte;
use App\Entity\CagnotteTransaction;
use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\ClubFinance;
use App\Entity\ClubTransaction;
use App\Repository\CagnotteRepository;
use App\Repository\EventParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CagnotteService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CagnotteRepository $cagnotteRepository,
        private EventParticipantRepository $eventParticipantRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Crée ou récupère la cagnotte d'un utilisateur pour une équipe
     */
    public function getOrCreateCagnotte(User $user, Team $team): Cagnotte
    {
        $cagnotte = $this->cagnotteRepository->findByUserAndTeam($user, $team);
        
        if (!$cagnotte) {
            $cagnotte = new Cagnotte();
            $cagnotte->setUser($user);
            $cagnotte->setTeam($team);
            $cagnotte->setCurrentAmount('0.00');
            $cagnotte->setTotalEarned('0.00');
            
            $this->entityManager->persist($cagnotte);
            $this->entityManager->flush();
            
            $this->logger->info('Nouvelle cagnotte créée', [
                'user_id' => $user->getId(),
                'team_id' => $team->getId(),
                'cagnotte_id' => $cagnotte->getId()
            ]);
        }
        
        return $cagnotte;
    }

    /**
     * Distribue les gains d'un événement aux participants
     */
    public function distributeEventEarnings(Event $event): array
    {
        if ($event->getStatus() !== Event::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('L\'événement doit être terminé pour distribuer les gains');
        }

        if ($event->isDistributed()) {
            throw new \InvalidArgumentException('Les gains de cet événement ont déjà été distribués');
        }

        $participants = $this->eventParticipantRepository->findByEvent($event);
        
        if (empty($participants)) {
            throw new \InvalidArgumentException('Aucun participant trouvé pour cet événement');
        }

        $totalBudget = (float) $event->getTotalBudget();
        $clubPercentage = $event->getClubPercentage();
        
        // Calculer la commission du club
        $clubCommission = $event->getClubCommission();
        $availableAmount = $event->getAvailableAmount();
        $amountPerParticipant = $event->getAmountPerParticipant();

        $results = [
            'event_id' => $event->getId(),
            'total_budget' => $totalBudget,
            'club_commission' => $clubCommission,
            'available_amount' => $availableAmount,
            'participants_count' => count($participants),
            'amount_per_participant' => $amountPerParticipant,
            'distributions' => []
        ];

        $this->entityManager->beginTransaction();

        try {
            // 1. Ajouter la commission au club
            $this->addClubCommission($event, $clubCommission);

            // 2. Distribuer aux participants
            foreach ($participants as $participant) {
                $distribution = $this->distributeToParticipant($participant, $amountPerParticipant);
                $results['distributions'][] = $distribution;
            }

            // 3. Marquer l'événement comme distribué
            $event->setStatus(Event::STATUS_COMPLETED);
            $this->entityManager->persist($event);

            $this->entityManager->commit();
            
            $this->logger->info('Gains d\'événement distribués avec succès', [
                'event_id' => $event->getId(),
                'total_distributed' => $availableAmount,
                'participants_count' => count($participants)
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Erreur lors de la distribution des gains', [
                'event_id' => $event->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Distribue les gains à un participant spécifique
     */
    private function distributeToParticipant(EventParticipant $participant, float $amount): array
    {
        $user = $participant->getUser();
        $event = $participant->getEvent();
        $team = $event->getTeam();

        // Récupérer ou créer la cagnotte
        $cagnotte = $this->getOrCreateCagnotte($user, $team);

        // Ajouter le montant à la cagnotte
        $cagnotte->addAmount($amount);
        
        // Mettre à jour les gains du participant
        $participant->setAmountEarned((string) $amount);
        
        // Créer la transaction de cagnotte
        $transaction = new CagnotteTransaction();
        $transaction->setCagnotte($cagnotte);
        $transaction->setEvent($event);
        $transaction->setAmount((string) $amount);
        $transaction->setType(CagnotteTransaction::TYPE_EARNING);
        $transaction->setDescription("Gains de l'événement : " . $event->getTitle());

        $this->entityManager->persist($cagnotte);
        $this->entityManager->persist($participant);
        $this->entityManager->persist($transaction);

        return [
            'user_id' => $user->getId(),
            'user_name' => $user->getFullName(),
            'amount_earned' => $amount,
            'new_cagnotte_balance' => (float) $cagnotte->getCurrentAmount()
        ];
    }

    /**
     * Ajoute la commission du club
     */
    private function addClubCommission(Event $event, float $commission): void
    {
        $club = $event->getTeam()->getClub();
        
        // Récupérer ou créer les finances du club
        $clubFinance = $club->getClubFinance();
        if (!$clubFinance) {
            $clubFinance = new ClubFinance();
            $clubFinance->setClub($club);
            $clubFinance->setTotalCommission('0.00');
            $clubFinance->setCurrentBalance('0.00');
            $this->entityManager->persist($clubFinance);
        }

        // Ajouter la commission
        $clubFinance->addCommission($commission);

        // Créer la transaction du club
        $clubTransaction = new ClubTransaction();
        $clubTransaction->setClub($club);
        $clubTransaction->setEvent($event);
        $clubTransaction->setAmount((string) $commission);
        $clubTransaction->setType(ClubTransaction::TYPE_COMMISSION);
        $clubTransaction->setDescription("Commission sur l'événement : " . $event->getTitle());

        $this->entityManager->persist($clubFinance);
        $this->entityManager->persist($clubTransaction);
    }

    /**
     * Utilise une partie de la cagnotte pour un paiement
     */
    public function useCagnotteForPayment(User $user, Team $team, float $amount, string $description = 'Utilisation pour paiement'): bool
    {
        $cagnotte = $this->cagnotteRepository->findByUserAndTeam($user, $team);
        
        if (!$cagnotte) {
            throw new \InvalidArgumentException('Aucune cagnotte trouvée pour cet utilisateur et cette équipe');
        }

        if (!$cagnotte->hasSufficientFunds($amount)) {
            throw new \InvalidArgumentException('Fonds insuffisants dans la cagnotte');
        }

        $this->entityManager->beginTransaction();

        try {
            // Déduire le montant de la cagnotte
            $cagnotte->subtractAmount($amount);

            // Créer la transaction
            $transaction = new CagnotteTransaction();
            $transaction->setCagnotte($cagnotte);
            $transaction->setAmount((string) $amount);
            $transaction->setType(CagnotteTransaction::TYPE_USAGE);
            $transaction->setDescription($description);

            $this->entityManager->persist($cagnotte);
            $this->entityManager->persist($transaction);
            $this->entityManager->commit();

            $this->logger->info('Utilisation de cagnotte pour paiement', [
                'user_id' => $user->getId(),
                'team_id' => $team->getId(),
                'amount_used' => $amount,
                'remaining_balance' => (float) $cagnotte->getCurrentAmount()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Erreur lors de l\'utilisation de la cagnotte', [
                'user_id' => $user->getId(),
                'team_id' => $team->getId(),
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Ajuste manuellement le montant d'une cagnotte (pour corrections)
     */
    public function adjustCagnotte(User $user, Team $team, float $amount, string $reason, User $adjustedBy): void
    {
        $cagnotte = $this->getOrCreateCagnotte($user, $team);

        $this->entityManager->beginTransaction();

        try {
            if ($amount > 0) {
                $cagnotte->addAmount($amount);
            } else {
                $cagnotte->subtractAmount(abs($amount));
            }

            // Créer la transaction d'ajustement
            $transaction = new CagnotteTransaction();
            $transaction->setCagnotte($cagnotte);
            $transaction->setAmount((string) abs($amount));
            $transaction->setType(CagnotteTransaction::TYPE_ADJUSTMENT);
            $transaction->setDescription("Ajustement par {$adjustedBy->getFullName()}: {$reason}");

            $this->entityManager->persist($cagnotte);
            $this->entityManager->persist($transaction);
            $this->entityManager->commit();

            $this->logger->info('Ajustement de cagnotte effectué', [
                'user_id' => $user->getId(),
                'team_id' => $team->getId(),
                'adjustment_amount' => $amount,
                'adjusted_by' => $adjustedBy->getId(),
                'reason' => $reason,
                'new_balance' => (float) $cagnotte->getCurrentAmount()
            ]);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Erreur lors de l\'ajustement de cagnotte', [
                'user_id' => $user->getId(),
                'team_id' => $team->getId(),
                'adjustment_amount' => $amount,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Calcule les statistiques des cagnottes pour une équipe
     */
    public function getTeamCagnotteStats(Team $team): array
    {
        $cagnottes = $this->cagnotteRepository->findByTeam($team);
        
        $stats = [
            'total_cagnottes' => count($cagnottes),
            'total_current_amount' => 0.0,
            'total_earned' => 0.0,
            'total_used' => 0.0,
            'average_balance' => 0.0,
            'active_cagnottes' => 0,
            'top_earners' => []
        ];

        foreach ($cagnottes as $cagnotte) {
            $currentAmount = (float) $cagnotte->getCurrentAmount();
            $totalEarned = (float) $cagnotte->getTotalEarned();
            
            $stats['total_current_amount'] += $currentAmount;
            $stats['total_earned'] += $totalEarned;
            $stats['total_used'] += $cagnotte->getTotalUsed();
            
            if ($currentAmount > 0) {
                $stats['active_cagnottes']++;
            }
            
            $stats['top_earners'][] = [
                'user_id' => $cagnotte->getUser()->getId(),
                'user_name' => $cagnotte->getUser()->getFullName(),
                'current_amount' => $currentAmount,
                'total_earned' => $totalEarned
            ];
        }

        // Calculer la moyenne
        if ($stats['total_cagnottes'] > 0) {
            $stats['average_balance'] = $stats['total_current_amount'] / $stats['total_cagnottes'];
        }

        // Trier les top earners par montant total gagné
        usort($stats['top_earners'], function($a, $b) {
            return $b['total_earned'] <=> $a['total_earned'];
        });

        // Garder seulement les 10 premiers
        $stats['top_earners'] = array_slice($stats['top_earners'], 0, 10);

        return $stats;
    }

    /**
     * Vérifie la cohérence des données de cagnotte
     */
    public function validateCagnotteIntegrity(Cagnotte $cagnotte): array
    {
        $issues = [];
        
        // Vérifier que le montant actuel correspond aux transactions
        $transactions = $cagnotte->getCagnotteTransactions();
        $calculatedAmount = 0.0;
        
        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->getAmount();
            
            switch ($transaction->getType()) {
                case CagnotteTransaction::TYPE_EARNING:
                case CagnotteTransaction::TYPE_ADJUSTMENT:
                    $calculatedAmount += $amount;
                    break;
                case CagnotteTransaction::TYPE_USAGE:
                    $calculatedAmount -= $amount;
                    break;
            }
        }
        
        $currentAmount = (float) $cagnotte->getCurrentAmount();
        $difference = abs($calculatedAmount - $currentAmount);
        
        if ($difference > 0.01) { // Tolérance de 1 centime pour les arrondis
            $issues[] = [
                'type' => 'amount_mismatch',
                'message' => 'Le montant actuel ne correspond pas aux transactions',
                'expected' => $calculatedAmount,
                'actual' => $currentAmount,
                'difference' => $difference
            ];
        }
        
        // Vérifier que le montant actuel n'est pas négatif
        if ($currentAmount < 0) {
            $issues[] = [
                'type' => 'negative_balance',
                'message' => 'Le montant de la cagnotte est négatif',
                'amount' => $currentAmount
            ];
        }
        
        return $issues;
    }

    /**
     * Répare automatiquement les incohérences de cagnotte
     */
    public function repairCagnotteIntegrity(Cagnotte $cagnotte): bool
    {
        $issues = $this->validateCagnotteIntegrity($cagnotte);
        
        if (empty($issues)) {
            return true; // Rien à réparer
        }
        
        $this->entityManager->beginTransaction();
        
        try {
            // Recalculer le montant à partir des transactions
            $transactions = $cagnotte->getCagnotteTransactions();
            $calculatedAmount = 0.0;
            $totalEarned = 0.0;
            
            foreach ($transactions as $transaction) {
                $amount = (float) $transaction->getAmount();
                
                switch ($transaction->getType()) {
                    case CagnotteTransaction::TYPE_EARNING:
                        $calculatedAmount += $amount;
                        $totalEarned += $amount;
                        break;
                    case CagnotteTransaction::TYPE_ADJUSTMENT:
                        $calculatedAmount += $amount;
                        if ($amount > 0) {
                            $totalEarned += $amount;
                        }
                        break;
                    case CagnotteTransaction::TYPE_USAGE:
                        $calculatedAmount -= $amount;
                        break;
                }
            }
            
            // Mettre à jour les montants
            $cagnotte->setCurrentAmount((string) max(0, $calculatedAmount));
            $cagnotte->setTotalEarned((string) $totalEarned);
            
            $this->entityManager->persist($cagnotte);
            $this->entityManager->commit();
            
            $this->logger->info('Intégrité de cagnotte réparée', [
                'cagnotte_id' => $cagnotte->getId(),
                'old_amount' => $cagnotte->getCurrentAmount(),
                'new_amount' => $calculatedAmount,
                'issues_fixed' => count($issues)
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Erreur lors de la réparation de cagnotte', [
                'cagnotte_id' => $cagnotte->getId(),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
} 