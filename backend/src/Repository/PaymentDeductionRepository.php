<?php

namespace App\Repository;

use App\Entity\PaymentDeduction;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentDeduction>
 */
class PaymentDeductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentDeduction::class);
    }

    /**
     * Trouve toutes les déductions actives pour une équipe
     */
    public function findActiveByTeam(Team $team): array
    {
        return $this->createQueryBuilder('pd')
            ->andWhere('pd.team = :team')
            ->andWhere('pd.isActive = true')
            ->setParameter('team', $team)
            ->orderBy('pd.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les déductions automatiques actives pour une équipe
     */
    public function findAutomaticByTeam(Team $team): array
    {
        return $this->createQueryBuilder('pd')
            ->andWhere('pd.team = :team')
            ->andWhere('pd.isActive = true')
            ->andWhere('pd.isAutomatic = true')
            ->setParameter('team', $team)
            ->orderBy('pd.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les déductions par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('pd')
            ->andWhere('pd.type = :type')
            ->andWhere('pd.isActive = true')
            ->setParameter('type', $type)
            ->orderBy('pd.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les déductions de type cagnotte
     */
    public function findCagnotteDeductions(): array
    {
        return $this->findByType(PaymentDeduction::TYPE_CAGNOTTE);
    }

    /**
     * Trouve toutes les déductions valides à une date donnée
     */
    public function findValidAt(\DateTimeInterface $date, ?Team $team = null): array
    {
        $qb = $this->createQueryBuilder('pd')
            ->andWhere('pd.isActive = true')
            ->andWhere('(pd.validFrom IS NULL OR pd.validFrom <= :date)')
            ->andWhere('(pd.validUntil IS NULL OR pd.validUntil >= :date)')
            ->setParameter('date', $date)
            ->orderBy('pd.name', 'ASC');

        if ($team) {
            $qb->andWhere('pd.team = :team')
               ->setParameter('team', $team);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve toutes les déductions applicables pour un utilisateur et une équipe
     */
    public function findApplicableForUser(User $user, Team $team, float $baseAmount): array
    {
        $activeDeductions = $this->findActiveByTeam($team);
        $applicableDeductions = [];

        foreach ($activeDeductions as $deduction) {
            if ($deduction->canBeAppliedTo($user, $baseAmount)) {
                $applicableDeductions[] = $deduction;
            }
        }

        return $applicableDeductions;
    }

    /**
     * Trouve toutes les déductions qui expirent bientôt
     */
    public function findExpiringSoon(int $daysThreshold = 30): array
    {
        $threshold = new \DateTime();
        $threshold->add(new \DateInterval("P{$daysThreshold}D"));

        return $this->createQueryBuilder('pd')
            ->andWhere('pd.isActive = true')
            ->andWhere('pd.validUntil IS NOT NULL')
            ->andWhere('pd.validUntil BETWEEN :now AND :threshold')
            ->setParameter('now', new \DateTime())
            ->setParameter('threshold', $threshold)
            ->orderBy('pd.validUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les déductions expirées
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('pd')
            ->andWhere('pd.validUntil IS NOT NULL')
            ->andWhere('pd.validUntil < :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('pd.validUntil', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les déductions par mode de calcul
     */
    public function findByCalculationType(string $calculationType): array
    {
        return $this->createQueryBuilder('pd')
            ->andWhere('pd.calculationType = :calculationType')
            ->andWhere('pd.isActive = true')
            ->setParameter('calculationType', $calculationType)
            ->orderBy('pd.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les déductions à montant fixe
     */
    public function findFixedAmount(): array
    {
        return $this->findByCalculationType(PaymentDeduction::CALCULATION_FIXED);
    }

    /**
     * Trouve toutes les déductions en pourcentage
     */
    public function findPercentage(): array
    {
        return $this->findByCalculationType(PaymentDeduction::CALCULATION_PERCENTAGE);
    }

    /**
     * Compte les déductions par équipe
     */
    public function countByTeam(Team $team): int
    {
        return $this->createQueryBuilder('pd')
            ->select('COUNT(pd.id)')
            ->andWhere('pd.team = :team')
            ->andWhere('pd.isActive = true')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche par nom
     */
    public function findByNameSearch(string $search, ?Team $team = null): array
    {
        $qb = $this->createQueryBuilder('pd')
            ->andWhere('pd.name LIKE :search')
            ->andWhere('pd.isActive = true')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('pd.name', 'ASC');

        if ($team) {
            $qb->andWhere('pd.team = :team')
               ->setParameter('team', $team);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les déductions avec leurs statistiques d'utilisation
     */
    public function findWithUsageStats(Team $team): array
    {
        // Cette méthode nécessiterait une table de liaison pour tracker l'utilisation
        // Pour l'instant, on retourne juste les déductions actives
        return $this->findActiveByTeam($team);
    }

    /**
     * Calcule le montant total des déductions applicables pour un utilisateur
     */
    public function calculateTotalDeductions(User $user, Team $team, float $baseAmount): float
    {
        $applicableDeductions = $this->findApplicableForUser($user, $team, $baseAmount);
        $totalDeduction = 0.0;
        $remainingAmount = $baseAmount;

        // Récupérer la cagnotte de l'utilisateur pour cette équipe
        $cagnotte = $user->getCagnotteForTeam($team);
        $availableCagnotte = $cagnotte ? $cagnotte->getCurrentAmount() : 0.0;

        foreach ($applicableDeductions as $deduction) {
            $deductionAmount = $deduction->calculateDeduction(
                $remainingAmount, 
                $deduction->isCagnotteType() ? $availableCagnotte : null
            );
            
            $totalDeduction += $deductionAmount;
            $remainingAmount -= $deductionAmount;
            
            // Si c'est une déduction de cagnotte, réduire le montant disponible
            if ($deduction->isCagnotteType()) {
                $availableCagnotte -= $deductionAmount;
            }
            
            // Arrêter si le montant restant est nul
            if ($remainingAmount <= 0) {
                break;
            }
        }

        return round($totalDeduction, 2);
    }

    /**
     * Trouve les meilleures déductions pour un utilisateur (optimisation du montant)
     */
    public function findOptimalDeductions(User $user, Team $team, float $baseAmount): array
    {
        $applicableDeductions = $this->findApplicableForUser($user, $team, $baseAmount);
        
        // Trier par efficacité (montant de déduction décroissant)
        usort($applicableDeductions, function($a, $b) use ($baseAmount, $user, $team) {
            $cagnotte = $user->getCagnotteForTeam($team);
            $availableCagnotte = $cagnotte ? $cagnotte->getCurrentAmount() : 0.0;
            
            $deductionA = $a->calculateDeduction($baseAmount, $a->isCagnotteType() ? $availableCagnotte : null);
            $deductionB = $b->calculateDeduction($baseAmount, $b->isCagnotteType() ? $availableCagnotte : null);
            
            return $deductionB <=> $deductionA; // Ordre décroissant
        });

        return $applicableDeductions;
    }
} 