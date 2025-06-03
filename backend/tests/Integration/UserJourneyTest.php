<?php

namespace App\Tests\Integration;

use App\Tests\ApiTestCase;

/**
 * Test d'intégration complet suivant les parcours des trois personas
 * Marc Dubois (Gestionnaire), Julie Moreau (Coach), Emma Leblanc (Athlète)
 */
class UserJourneyTest extends ApiTestCase
{
    public function testCompleteUserJourneyScenario(): void
    {
        // 🔥 PARCOURS 1: MARC DUBOIS - GESTIONNAIRE DE CLUB
        $this->executeOwnerJourney();
        
        // 🔥 PARCOURS 2: JULIE MOREAU - COACH
        $this->executeCoachJourney();
        
        // 🔥 PARCOURS 3: EMMA LEBLANC - ATHLÈTE
        $this->executeAthleteJourney();
    }

    private function executeOwnerJourney(): void
    {
        // 1. Marc s'inscrit et se connecte
        $marcData = [
            'email' => 'marc.dubois@racingclub.com',
            'password' => 'password123',
            'firstName' => 'Marc',
            'lastName' => 'Dubois',
            'dateOfBirth' => '1985-04-12' // 39 ans
        ];

        $this->unauthenticatedRequest('POST', '/api/users', $marcData);
        $marcResponse = $this->assertJsonResponse(201);
        $marcId = $marcResponse['id'];

        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => $marcData['email'],
            'password' => $marcData['password']
        ]);
        $loginResponse = $this->assertJsonResponse(200);
        $marcToken = $loginResponse['token'];

        // Créer l'utilisateur Marc pour les tests suivants
        $marc = $this->createTestUser($marcData['email'], ['ROLE_CLUB_OWNER'], [
            'firstName' => $marcData['firstName'],
            'lastName' => $marcData['lastName'],
            'dateOfBirth' => $marcData['dateOfBirth']
        ]);

        // 2. Marc crée le Racing Club Paris
        $clubData = [
            'name' => 'Racing Club Paris',
            'description' => 'Club de tennis parisien fondé en 1987',
            'address' => '123 Avenue des Sports, 75015 Paris',
            'phone' => '0145678900',
            'email' => 'contact@racingclub.com'
        ];

        $this->authenticatedRequest('POST', '/api/clubs', $marc, $clubData);
        $clubResponse = $this->assertJsonResponse(201);
        $clubId = $clubResponse['id'];

        // 3. Marc crée la saison 2024-2025
        $seasonData = [
            'name' => '2024-2025',
            'startDate' => '2024-09-01',
            'endDate' => '2025-06-30',
            'isActive' => true,
            'club' => '/api/clubs/' . $clubId
        ];

        $this->authenticatedRequest('POST', '/api/seasons', $marc, $seasonData);
        $seasonResponse = $this->assertJsonResponse(201);
        $seasonId = $seasonResponse['id'];

        // 4. Marc crée l'équipe "Seniors Masculins" (18 ans et plus)
        $teamSeniorsData = [
            'name' => 'Seniors Masculins',
            'description' => 'Équipe principale masculine',
            'category' => 'senior',
            'gender' => 'male',
            'minBirthYear' => null, // Pas de limite haute d'âge
            'maxBirthYear' => 2006, // Né en 2006 ou avant (18 ans min en 2024)
            'annualPrice' => 450.00,
            'maxMembers' => 25,
            'club' => '/api/clubs/' . $clubId,
            'season' => '/api/seasons/' . $seasonId
        ];

        $this->authenticatedRequest('POST', '/api/teams', $marc, $teamSeniorsData);
        $teamSeniorsResponse = $this->assertJsonResponse(201);

        // 5. Marc crée l'équipe "U18 Filles" (12-18 ans)
        $teamU18Data = [
            'name' => 'U18 Filles',
            'description' => 'Équipe jeunes filles',
            'category' => 'youth',
            'gender' => 'female',
            'minBirthYear' => 2006, // Né en 2006 ou après (18 ans max en 2024)
            'maxBirthYear' => 2012, // Né en 2012 ou avant (12 ans min en 2024)
            'annualPrice' => 320.00,
            'maxMembers' => 15,
            'club' => '/api/clubs/' . $clubId,
            'season' => '/api/seasons/' . $seasonId
        ];

        $this->authenticatedRequest('POST', '/api/teams', $marc, $teamU18Data);
        $teamU18Response = $this->assertJsonResponse(201);
        $teamU18Id = $teamU18Response['id'];

        // Vérifier les restrictions d'âge de l'équipe U18
        $this->assertEquals(2006, $teamU18Response['minBirthYear']);
        $this->assertEquals(2012, $teamU18Response['maxBirthYear']);

        // 6. Marc configure les échéanciers de paiement (3 échéances pour U18)
        $scheduleData = [
            'team' => '/api/teams/' . $teamU18Id,
            'numberOfPayments' => 3,
            'firstPaymentDate' => '2024-09-15',
            'paymentInterval' => 'monthly'
        ];

        $this->authenticatedRequest('POST', '/api/payment-schedules', $marc, $scheduleData);
        $scheduleResponse = $this->assertJsonResponse(201);
        
        // Vérifier le calcul: 320€ / 3 = 106.67€ par échéance
        $this->assertEquals(106.67, $scheduleResponse['amountPerPayment']);

        // 7. Marc crée l'événement "Tournoi National U18"
        $eventData = [
            'name' => 'Tournoi National U18',
            'description' => 'Tournoi national pour les équipes U18',
            'eventDate' => '2024-11-15',
            'budget' => 2000.00,
            'clubCommissionPercent' => 15.0,
            'maxParticipants' => 8,
            'team' => '/api/teams/' . $teamU18Id,
            'club' => '/api/clubs/' . $clubId
        ];

        $this->authenticatedRequest('POST', '/api/events', $marc, $eventData);
        $eventResponse = $this->assertJsonResponse(201);
        $eventId = $eventResponse['id'];
        
        // Vérifier le calcul du gain: (2000€ * 85%) / 8 = 212.50€
        $this->assertEquals(212.50, $eventResponse['individualGain']);

        // 8. Marc configure les documents obligatoires
        $documentTypes = [
            [
                'name' => 'Certificat médical',
                'description' => 'Certificat médical obligatoire',
                'isRequired' => true,
                'allowedExtensions' => ['pdf'],
                'maxSizeInMb' => 5,
                'validityPeriodInMonths' => 12,
                'club' => '/api/clubs/' . $clubId
            ],
            [
                'name' => 'Licence FFT',
                'description' => 'Licence FFT pour équipe Seniors',
                'isRequired' => true,
                'allowedExtensions' => ['pdf', 'jpg', 'png'],
                'maxSizeInMb' => 3,
                'validityPeriodInMonths' => 12,
                'club' => '/api/clubs/' . $clubId
            ]
        ];

        foreach ($documentTypes as $docType) {
            $this->authenticatedRequest('POST', '/api/document-types', $marc, $docType);
            $this->assertJsonResponse(201);
        }

        // Stocker les IDs pour les tests suivants
        $this->storeTestData('club', ['id' => $clubId, 'owner' => $marc]);
        $this->storeTestData('season', ['id' => $seasonId]);
        $this->storeTestData('teamU18', ['id' => $teamU18Id]);
        $this->storeTestData('event', ['id' => $eventId]);
    }

    private function executeCoachJourney(): void
    {
        // Récupérer les données du parcours précédent
        $clubData = $this->getTestData('club');
        $teamU18Data = $this->getTestData('teamU18');
        $marc = $clubData['owner'];

        // 1. Julie s'inscrit
        $julieData = [
            'email' => 'julie.moreau@coach.com',
            'password' => 'password123',
            'firstName' => 'Julie',
            'lastName' => 'Moreau',
            'dateOfBirth' => '1992-08-25' // 32 ans
        ];

        $this->unauthenticatedRequest('POST', '/api/users', $julieData);
        $julieResponse = $this->assertJsonResponse(201);

        $julie = $this->createTestUser($julieData['email'], ['ROLE_COACH'], [
            'firstName' => $julieData['firstName'],
            'lastName' => $julieData['lastName'],
            'dateOfBirth' => $julieData['dateOfBirth']
        ]);

        // 2. Marc assigne Julie comme coach de l'équipe U18 Filles
        $assignmentData = [
            'user' => '/api/users/' . $julie->getId(),
            'team' => '/api/teams/' . $teamU18Data['id'],
            'role' => 'coach',
            'assignedAt' => date('Y-m-d H:i:s')
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $marc, $assignmentData);
        $this->assertJsonResponse(201);

        // 3. Julie se connecte et vérifie son rôle
        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => $julieData['email'],
            'password' => $julieData['password']
        ]);
        $julieLoginResponse = $this->assertJsonResponse(200);

        // 4. Julie consulte les détails de son équipe
        $this->authenticatedRequest('GET', '/api/teams/' . $teamU18Data['id'], $julie);
        $teamDetails = $this->assertJsonResponse(200);
        
        $this->assertEquals('U18 Filles', $teamDetails['name']);
        
        // 5. Vérifier que Julie peut voir les restrictions d'âge de l'équipe
        $this->assertEquals(2006, $teamDetails['minBirthYear']);
        $this->assertEquals(2012, $teamDetails['maxBirthYear']);

        // 6. Julie consulte le tableau de bord financier de ses athlètes
        $this->authenticatedRequest('GET', '/api/teams/' . $teamU18Data['id'] . '/finances', $julie);
        $financesData = $this->assertJsonResponse(200);
        
        $this->assertArrayHasKey('totalPayments', $financesData);
        $this->assertArrayHasKey('pendingPayments', $financesData);

        // 7. Julie tente d'accéder à une autre équipe (doit échouer)
        $this->authenticatedRequest('GET', '/api/teams/999', $julie);
        $this->assertErrorResponse(403);

        // 8. Julie tente de créer un événement (doit échouer)
        $eventData = [
            'name' => 'Événement par coach',
            'budget' => 1000.00
        ];

        $this->authenticatedRequest('POST', '/api/events', $julie, $eventData);
        $this->assertErrorResponse(403);

        $this->storeTestData('coach', ['user' => $julie]);
    }

    private function executeAthleteJourney(): void
    {
        // Récupérer les données précédentes
        $clubData = $this->getTestData('club');
        $teamU18Data = $this->getTestData('teamU18');
        $eventData = $this->getTestData('event');
        $marc = $clubData['owner'];

        // 1. Emma s'inscrit
        $emmaData = [
            'email' => 'emma.leblanc@athlete.com',
            'password' => 'password123',
            'firstName' => 'Emma',
            'lastName' => 'Leblanc'
        ];

        $this->unauthenticatedRequest('POST', '/api/users', $emmaData);
        $this->assertJsonResponse(201);

        $emma = $this->createTestUser($emmaData['email'], ['ROLE_ATHLETE'], [
            'firstName' => $emmaData['firstName'],
            'lastName' => $emmaData['lastName']
        ]);

        // 2. Marc ajoute Emma à l'équipe U18 Filles
        $membershipData = [
            'user' => '/api/users/' . $emma->getId(),
            'team' => '/api/teams/' . $teamU18Data['id'],
            'role' => 'athlete',
            'joinedAt' => date('Y-m-d H:i:s')
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $marc, $membershipData);
        $this->assertJsonResponse(201);

        // 3. Emma se connecte
        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => $emmaData['email'],
            'password' => $emmaData['password']
        ]);
        $this->assertJsonResponse(200);

        // 4. Emma consulte ses échéances de paiement
        $this->authenticatedRequest('GET', '/api/users/' . $emma->getId() . '/payments', $emma);
        $paymentsData = $this->assertJsonResponse(200);
        
        // Doit avoir 3 échéances de 106.67€
        $this->assertCount(3, $paymentsData['hydra:member']);

        // 5. Emma upload son certificat médical
        $documentData = [
            'name' => 'Certificat médical Emma',
            'documentType' => '/api/document-types/1', // Supposé être le certificat médical
            'user' => '/api/users/' . $emma->getId(),
            'team' => '/api/teams/' . $teamU18Data['id'],
            'filePath' => '/uploads/documents/certificat-emma.pdf',
            'originalFilename' => 'certificat-medical.pdf',
            'fileSize' => 2048000,
            'mimeType' => 'application/pdf'
        ];

        $this->authenticatedRequest('POST', '/api/documents', $emma, $documentData);
        $this->assertJsonResponse(201);

        // 6. Marc inscrit Emma au tournoi
        $participantData = [
            'event' => '/api/events/' . $eventData['id'],
            'user' => '/api/users/' . $emma->getId(),
            'registrationDate' => date('Y-m-d H:i:s'),
            'status' => 'registered'
        ];

        $this->authenticatedRequest('POST', '/api/event-participants', $marc, $participantData);
        $this->assertJsonResponse(201);

        // 7. Marc finalise l'événement (déclenche attribution cagnotte)
        $this->authenticatedRequest('PATCH', '/api/events/' . $eventData['id'], $marc, [
            'status' => 'completed'
        ]);
        $this->assertJsonResponse(200);

        // 8. Emma consulte sa cagnotte
        $this->authenticatedRequest('GET', '/api/users/' . $emma->getId() . '/cagnotte', $emma);
        $cagnotteData = $this->assertJsonResponse(200);
        
        // Doit avoir 212.50€ du tournoi
        $this->assertEquals(212.50, $cagnotteData['balance']);

        // 9. Emma consulte l'historique de sa cagnotte
        $this->authenticatedRequest('GET', '/api/users/' . $emma->getId() . '/cagnotte/transactions', $emma);
        $transactionsData = $this->assertJsonResponse(200);
        
        $this->assertGreaterThan(0, count($transactionsData['hydra:member']));

        // 10. Emma tente d'accéder aux données d'un autre utilisateur (doit échouer)
        $this->authenticatedRequest('GET', '/api/users/999/cagnotte', $emma);
        $this->assertErrorResponse(403);

        // 11. Emma effectue un retrait de sa cagnotte
        $withdrawalData = [
            'amount' => 100.00,
            'description' => 'Achat équipement',
            'type' => 'withdrawal'
        ];

        $this->authenticatedRequest('POST', '/api/users/' . $emma->getId() . '/cagnotte/transactions', $emma, $withdrawalData);
        $this->assertJsonResponse(201);

        // 12. Vérifier le nouveau solde
        $this->authenticatedRequest('GET', '/api/users/' . $emma->getId() . '/cagnotte', $emma);
        $newCagnotteData = $this->assertJsonResponse(200);
        
        $this->assertEquals(112.50, $newCagnotteData['balance']); // 212.50 - 100.00
    }

    // Méthodes helper pour stocker/récupérer des données entre les parcours

    private array $testData = [];

    private function storeTestData(string $key, array $data): void
    {
        $this->testData[$key] = $data;
    }

    private function getTestData(string $key): array
    {
        return $this->testData[$key] ?? [];
    }
} 