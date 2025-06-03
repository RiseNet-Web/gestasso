<?php

namespace App\Tests\Functional;

use App\Tests\ApiTestCase;
use App\Entity\User;
use App\Entity\Club;
use App\Entity\Team;
use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\Cagnotte;
use App\Entity\CagnotteTransaction;
use App\Entity\PaymentDeduction;

/**
 * Tests des fonctionnalités financières critiques
 * Couvre les calculs de cagnottes, événements, commissions et déductions
 */
class FinancialTest extends ApiTestCase
{
    private User $marcDubois;
    private User $emmaLeblanc;
    private Club $club;
    private Team $teamU18;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->marcDubois = $this->createTestUser(
            'marc.dubois@racingclub.com',
            ['ROLE_CLUB_OWNER'],
            ['firstName' => 'Marc', 'lastName' => 'Dubois']
        );

        $this->emmaLeblanc = $this->createTestUser(
            'emma.leblanc@athlete.com',
            ['ROLE_ATHLETE'],
            ['firstName' => 'Emma', 'lastName' => 'Leblanc']
        );

        $this->club = $this->createTestClub($this->marcDubois);
        $this->teamU18 = $this->createTestTeam($this->club, 'U18 Filles', 320.00);
    }

    public function testEventCreationWithBudget(): void
    {
        // Marc crée l'événement "Tournoi National U18"
        $eventData = [
            'name' => 'Tournoi National U18',
            'description' => 'Tournoi national pour les équipes U18',
            'eventDate' => '2024-11-15',
            'budget' => 2000.00,
            'clubCommissionPercent' => 15.0,
            'maxParticipants' => 8,
            'team' => '/api/teams/' . $this->teamU18->getId(),
            'club' => '/api/clubs/' . $this->club->getId()
        ];

        $this->authenticatedRequest('POST', '/api/events', $this->marcDubois, $eventData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($eventData['name'], $responseData['name']);
        $this->assertEquals($eventData['budget'], $responseData['budget']);
        $this->assertEquals($eventData['clubCommissionPercent'], $responseData['clubCommissionPercent']);
        $this->assertEquals($eventData['maxParticipants'], $responseData['maxParticipants']);
        
        // Vérifier le calcul automatique du gain par participant
        $expectedIndividualGain = (2000.00 * (100 - 15) / 100) / 8; // 212.50€
        $this->assertEquals($expectedIndividualGain, $responseData['individualGain']);
    }

    public function testCagnotteCalculationPrecision(): void
    {
        // Test avec différents budgets pour vérifier la précision des calculs
        $testCases = [
            ['budget' => 2000.00, 'commission' => 15.0, 'participants' => 8, 'expected' => 212.50],
            ['budget' => 1500.00, 'commission' => 10.0, 'participants' => 6, 'expected' => 225.00],
            ['budget' => 999.99, 'commission' => 12.5, 'participants' => 7, 'expected' => 124.99857], // Test arrondi
        ];

        foreach ($testCases as $case) {
            $eventData = [
                'name' => 'Test Event ' . $case['budget'],
                'budget' => $case['budget'],
                'clubCommissionPercent' => $case['commission'],
                'maxParticipants' => $case['participants'],
                'team' => '/api/teams/' . $this->teamU18->getId(),
                'club' => '/api/clubs/' . $this->club->getId()
            ];

            $this->authenticatedRequest('POST', '/api/events', $this->marcDubois, $eventData);
            $responseData = $this->assertJsonResponse(201);

            $calculatedGain = ($case['budget'] * (100 - $case['commission']) / 100) / $case['participants'];
            $this->assertEquals(
                round($calculatedGain, 2), 
                $responseData['individualGain'],
                "Calcul incorrect pour le budget {$case['budget']}"
            );
        }
    }

    public function testEventParticipantRegistration(): void
    {
        // Créer un événement
        $event = $this->createTestEvent($this->teamU18, 2000.00, 15.0, 8);

        // Inscrire Emma au tournoi
        $participantData = [
            'event' => '/api/events/' . $event->getId(),
            'user' => '/api/users/' . $this->emmaLeblanc->getId(),
            'registrationDate' => date('Y-m-d H:i:s'),
            'status' => 'registered'
        ];

        $this->authenticatedRequest('POST', '/api/event-participants', $this->marcDubois, $participantData);
        
        $responseData = $this->assertJsonResponse(201);
        
        $this->assertEquals($this->emmaLeblanc->getId(), $responseData['user']['id']);
        $this->assertEquals($event->getId(), $responseData['event']['id']);
        $this->assertEquals('registered', $responseData['status']);
    }

    public function testCagnotteAutomaticAttribution(): void
    {
        // Créer un événement et inscrire Emma
        $event = $this->createTestEvent($this->teamU18, 2000.00, 15.0, 8);
        $this->addParticipantToEvent($event, $this->emmaLeblanc);

        // Finaliser l'événement (déclenche l'attribution aux cagnottes)
        $this->authenticatedRequest('PATCH', '/api/events/' . $event->getId(), $this->marcDubois, [
            'status' => 'completed'
        ]);

        $this->assertJsonResponse(200);

        // Vérifier que la cagnotte d'Emma a été créditée
        $this->authenticatedRequest('GET', '/api/cagnottes', $this->emmaLeblanc);
        $responseData = $this->assertJsonResponse(200);

        $emmaCagnotte = null;
        foreach ($responseData['hydra:member'] as $cagnotte) {
            if ($cagnotte['user']['id'] === $this->emmaLeblanc->getId()) {
                $emmaCagnotte = $cagnotte;
                break;
            }
        }

        $this->assertNotNull($emmaCagnotte);
        $this->assertEquals(212.50, $emmaCagnotte['balance']); // 2000€ - 15% = 1700€ / 8 = 212.50€
    }

    public function testMultipleEventCagnotteAccumulation(): void
    {
        // Créer plusieurs événements et tester l'accumulation
        $event1 = $this->createTestEvent($this->teamU18, 2000.00, 15.0, 8); // 212.50€
        $event2 = $this->createTestEvent($this->teamU18, 1200.00, 10.0, 6); // 180.00€

        $this->addParticipantToEvent($event1, $this->emmaLeblanc);
        $this->addParticipantToEvent($event2, $this->emmaLeblanc);

        // Finaliser les deux événements
        foreach ([$event1, $event2] as $event) {
            $this->authenticatedRequest('PATCH', '/api/events/' . $event->getId(), $this->marcDubois, [
                'status' => 'completed'
            ]);
            $this->assertJsonResponse(200);
        }

        // Vérifier le solde total de la cagnotte
        $this->authenticatedRequest('GET', '/api/users/' . $this->emmaLeblanc->getId() . '/cagnotte', $this->emmaLeblanc);
        $responseData = $this->assertJsonResponse(200);

        $expectedTotal = 212.50 + 180.00; // 392.50€
        $this->assertEquals($expectedTotal, $responseData['balance']);
    }

    public function testCagnotteTransactionHistory(): void
    {
        $event = $this->createTestEvent($this->teamU18, 2000.00, 15.0, 8);
        $this->addParticipantToEvent($event, $this->emmaLeblanc);

        // Finaliser l'événement
        $this->authenticatedRequest('PATCH', '/api/events/' . $event->getId(), $this->marcDubois, [
            'status' => 'completed'
        ]);

        // Consulter l'historique des transactions
        $this->authenticatedRequest('GET', '/api/users/' . $this->emmaLeblanc->getId() . '/cagnotte/transactions', $this->emmaLeblanc);
        $responseData = $this->assertJsonResponse(200);

        $this->assertGreaterThan(0, count($responseData['hydra:member']));
        
        $transaction = $responseData['hydra:member'][0];
        $this->assertEquals('credit', $transaction['type']);
        $this->assertEquals(212.50, $transaction['amount']);
        $this->assertEquals($event->getId(), $transaction['event']['id']);
        $this->assertStringContainsString('Tournoi', $transaction['description']);
    }

    public function testPaymentDeductionRules(): void
    {
        // Créer des règles de déduction
        $deductionData = [
            'name' => 'Frais administratifs',
            'type' => 'percentage',
            'value' => 5.0, // 5%
            'club' => '/api/clubs/' . $this->club->getId(),
            'isActive' => true
        ];

        $this->authenticatedRequest('POST', '/api/payment-deductions', $this->marcDubois, $deductionData);
        $responseData = $this->assertJsonResponse(201);

        $this->assertEquals($deductionData['name'], $responseData['name']);
        $this->assertEquals($deductionData['type'], $responseData['type']);
        $this->assertEquals($deductionData['value'], $responseData['value']);
    }

    public function testPaymentDeductionApplication(): void
    {
        // Créer une déduction
        $deduction = $this->createTestDeduction('Frais admin', 'percentage', 5.0);

        $event = $this->createTestEvent($this->teamU18, 1000.00, 15.0, 5);
        $this->addParticipantToEvent($event, $this->emmaLeblanc);

        // Appliquer la déduction à l'événement
        $this->authenticatedRequest('POST', '/api/events/' . $event->getId() . '/apply-deductions', $this->marcDubois, [
            'deductions' => ['/api/payment-deductions/' . $deduction->getId()]
        ]);

        $this->assertJsonResponse(200);

        // Finaliser l'événement
        $this->authenticatedRequest('PATCH', '/api/events/' . $event->getId(), $this->marcDubois, [
            'status' => 'completed'
        ]);

        // Vérifier le calcul avec déduction
        // 1000€ - 15% club = 850€
        // 850€ - 5% frais admin = 807.50€
        // 807.50€ / 5 participants = 161.50€
        
        $this->authenticatedRequest('GET', '/api/users/' . $this->emmaLeblanc->getId() . '/cagnotte', $this->emmaLeblanc);
        $responseData = $this->assertJsonResponse(200);

        $this->assertEquals(161.50, $responseData['balance']);
    }

    public function testClubFinanceDashboard(): void
    {
        // Créer plusieurs événements et transactions
        $event1 = $this->createTestEvent($this->teamU18, 2000.00, 15.0, 8);
        $event2 = $this->createTestEvent($this->teamU18, 1500.00, 12.0, 6);

        foreach ([$event1, $event2] as $event) {
            $this->addParticipantToEvent($event, $this->emmaLeblanc);
            $this->authenticatedRequest('PATCH', '/api/events/' . $event->getId(), $this->marcDubois, [
                'status' => 'completed'
            ]);
        }

        // Consulter le tableau de bord financier du club
        $this->authenticatedRequest('GET', '/api/clubs/' . $this->club->getId() . '/finances', $this->marcDubois);
        $responseData = $this->assertJsonResponse(200);

        $this->assertArrayHasKey('totalRevenue', $responseData);
        $this->assertArrayHasKey('totalCommissions', $responseData);
        $this->assertArrayHasKey('totalDistributed', $responseData);
        $this->assertArrayHasKey('eventCount', $responseData);

        // Vérifier les calculs
        $expectedCommissions = (2000.00 * 0.15) + (1500.00 * 0.12); // 300 + 180 = 480€
        $this->assertEquals($expectedCommissions, $responseData['totalCommissions']);
    }

    public function testCagnotteWithdrawal(): void
    {
        // Créer une cagnotte avec du solde
        $event = $this->createTestEvent($this->teamU18, 2000.00, 15.0, 8);
        $this->addParticipantToEvent($event, $this->emmaLeblanc);
        
        $this->authenticatedRequest('PATCH', '/api/events/' . $event->getId(), $this->marcDubois, [
            'status' => 'completed'
        ]);

        // Emma demande un retrait
        $withdrawalData = [
            'amount' => 100.00,
            'description' => 'Retrait pour équipement',
            'type' => 'withdrawal'
        ];

        $this->authenticatedRequest('POST', '/api/users/' . $this->emmaLeblanc->getId() . '/cagnotte/transactions', $this->emmaLeblanc, $withdrawalData);
        $responseData = $this->assertJsonResponse(201);

        $this->assertEquals('withdrawal', $responseData['type']);
        $this->assertEquals(100.00, $responseData['amount']);

        // Vérifier le nouveau solde
        $this->authenticatedRequest('GET', '/api/users/' . $this->emmaLeblanc->getId() . '/cagnotte', $this->emmaLeblanc);
        $cagnotteData = $this->assertJsonResponse(200);

        $this->assertEquals(112.50, $cagnotteData['balance']); // 212.50 - 100.00
    }

    public function testInsufficientCagnotteBalance(): void
    {
        // Tenter un retrait supérieur au solde
        $withdrawalData = [
            'amount' => 1000.00,
            'description' => 'Retrait impossible',
            'type' => 'withdrawal'
        ];

        $this->authenticatedRequest('POST', '/api/users/' . $this->emmaLeblanc->getId() . '/cagnotte/transactions', $this->emmaLeblanc, $withdrawalData);
        $this->assertErrorResponse(400, 'solde insuffisant');
    }

    public function testMonetaryRounding(): void
    {
        // Test avec des montants qui génèrent des centimes
        $event = $this->createTestEvent($this->teamU18, 999.99, 12.5, 7);
        
        $this->authenticatedRequest('GET', '/api/events/' . $event->getId(), $this->marcDubois);
        $responseData = $this->assertJsonResponse(200);

        // 999.99 * 87.5% = 874.99125 / 7 = 124.99875
        // Doit être arrondi à 124.99€
        $this->assertEquals(124.99, $responseData['individualGain']);
    }

    // Méthodes helper

    private function createTestClub(User $owner): Club
    {
        $club = new Club();
        $club->setName('Test Club');
        $club->setOwner($owner);
        $club->setIsActive(true);
        
        $this->entityManager->persist($club);
        $this->entityManager->flush();
        
        return $club;
    }

    private function createTestTeam(Club $club, string $name, float $price): Team
    {
        $team = new Team();
        $team->setName($name);
        $team->setClub($club);
        $team->setAnnualPrice($price);
        $team->setCategory('youth');
        $team->setGender('female');
        $team->setMaxMembers(15);
        
        $this->entityManager->persist($team);
        $this->entityManager->flush();
        
        return $team;
    }

    private function createTestEvent(Team $team, float $budget, float $commission, int $maxParticipants): Event
    {
        $event = new Event();
        $event->setName('Test Event ' . uniqid());
        $event->setDescription('Event de test');
        $event->setEventDate(new \DateTime('+1 month'));
        $event->setBudget($budget);
        $event->setClubCommissionPercent($commission);
        $event->setMaxParticipants($maxParticipants);
        $event->setTeam($team);
        $event->setCreatedBy($this->marcDubois);
        $event->setStatus('open');
        
        // Calculer le gain individuel
        $netAmount = $budget * (100 - $commission) / 100;
        $individualGain = $netAmount / $maxParticipants;
        $event->setIndividualGain(round($individualGain, 2));
        
        $this->entityManager->persist($event);
        $this->entityManager->flush();
        
        return $event;
    }

    private function addParticipantToEvent(Event $event, User $user): EventParticipant
    {
        $participant = new EventParticipant();
        $participant->setEvent($event);
        $participant->setUser($user);
        $participant->setRegistrationDate(new \DateTime());
        $participant->setStatus('registered');
        
        $this->entityManager->persist($participant);
        $this->entityManager->flush();
        
        return $participant;
    }

    private function createTestDeduction(string $name, string $type, float $value): PaymentDeduction
    {
        $deduction = new PaymentDeduction();
        $deduction->setName($name);
        $deduction->setType($type);
        $deduction->setValue($value);
        $deduction->setClub($this->club);
        $deduction->setIsActive(true);
        
        $this->entityManager->persist($deduction);
        $this->entityManager->flush();
        
        return $deduction;
    }
} 