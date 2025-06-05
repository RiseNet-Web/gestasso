<?php

namespace App\Tests\Integration;

use App\Tests\ApiTestCase;

/**
 * Test d'intégration du parcours utilisateur complet
 * Couvre : création d'utilisateurs, authentification, clubs, équipes, documents, demandes d'adhésion
 */
class UserJourneyTest extends ApiTestCase
{
    public function testCompleteUserJourneyScenario(): void
    {
        // Parcours complet : Propriétaire → Coach → Athlète
        $this->executeOwnerJourney();
        $this->executeCoachJourney();
        $this->executeAthleteJourney();
        $this->executeJoinRequestJourney();
    }

    private function executeOwnerJourney(): void
    {
        // 1. Marc s'inscrit en tant que propriétaire de club
        $marcData = [
            'email' => 'marc.dubois@owner.com',
            'password' => 'password123',
            'firstName' => 'Marc',
            'lastName' => 'Dubois',
            'onboardingType' => 'owner'
        ];

        $this->unauthenticatedRequest('POST', '/api/users', $marcData);
        $marcResponse = $this->assertJsonResponse(201);

        $marc = $this->createTestUser($marcData['email'], ['ROLE_CLUB_OWNER'], [
            'firstName' => $marcData['firstName'],
            'lastName' => $marcData['lastName']
        ]);

        // 2. Marc se connecte
        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => $marcData['email'],
            'password' => $marcData['password']
        ]);
        $marcLoginResponse = $this->assertJsonResponse(200);

        $this->assertArrayHasKey('token', $marcLoginResponse);
        $this->assertEquals($marcData['email'], $marcLoginResponse['user']['email']);

        // 3. Marc crée son club "Tennis Club de Saintonge"
        $clubData = [
            'name' => 'Tennis Club de Saintonge',
            'description' => 'Club de tennis familial situé en Charente-Maritime',
            'address' => '15 Avenue des Tilleuls, 17100 Saintes',
            'phone' => '05.46.74.88.92',
            'email' => 'contact@tennis-saintonge.fr',
            'website' => 'https://tennis-saintonge.fr'
        ];

        $this->authenticatedRequest('POST', '/api/clubs', $marc, $clubData);
        $clubResponse = $this->assertJsonResponse(201);
        $clubId = $clubResponse['id'];

        $this->assertEquals($clubData['name'], $clubResponse['name']);
        $this->assertEquals($marc->getId(), $clubResponse['owner']['id']);

        // 4. Marc crée une saison 2024-2025
        $seasonData = [
            'name' => 'Saison 2024-2025',
            'startDate' => '2024-09-01',
            'endDate' => '2025-08-31',
            'isActive' => true,
            'club' => '/api/clubs/' . $clubId
        ];

        $this->authenticatedRequest('POST', '/api/seasons', $marc, $seasonData);
        $seasonResponse = $this->assertJsonResponse(201);
        $seasonId = $seasonResponse['id'];

        // 5. Marc crée l'équipe U18 Filles
        $teamU18Data = [
            'name' => 'U18 Filles',
            'description' => 'Équipe des filles de moins de 18 ans',
            'category' => 'youth',
            'gender' => 'female',
            'annualPrice' => 320.00,
            'minBirthYear' => 2006, // 18 ans maximum
            'maxBirthYear' => 2012, // 12 ans minimum
            'club' => '/api/clubs/' . $clubId,
            'season' => '/api/seasons/' . $seasonId
        ];

        $this->authenticatedRequest('POST', '/api/teams', $marc, $teamU18Data);
        $teamU18Response = $this->assertJsonResponse(201);
        $teamU18Id = $teamU18Response['id'];

        // Vérifier les restrictions d'âge de l'équipe U18
        $this->assertEquals(2006, $teamU18Response['minBirthYear']);
        $this->assertEquals(2012, $teamU18Response['maxBirthYear']);

        // 6. Marc configure les documents obligatoires
        $documentTypes = [
            [
                'name' => 'Certificat médical',
                'description' => 'Certificat médical obligatoire',
                'type' => 'medical',
                'isRequired' => true,
                'hasExpirationDate' => true,
                'validityDurationInDays' => 365,
                'team' => '/api/teams/' . $teamU18Id
            ],
            [
                'name' => 'Licence FFT',
                'description' => 'Licence FFT pour équipe U18',
                'type' => 'license',
                'isRequired' => true,
                'hasExpirationDate' => true,
                'validityDurationInDays' => 365,
                'team' => '/api/teams/' . $teamU18Id
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
        $this->assertJsonResponse(201);

        $julie = $this->createTestUser($julieData['email'], ['ROLE_COACH'], [
            'firstName' => $julieData['firstName'],
            'lastName' => $julieData['lastName'],
            'dateOfBirth' => $julieData['dateOfBirth']
        ]);

        // 2. Marc assigne Julie comme coach de l'équipe U18 Filles
        $assignmentData = [
            'user' => '/api/users/' . $julie->getId(),
            'team' => '/api/teams/' . $teamU18Data['id'],
            'role' => 'coach'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $marc, $assignmentData);
        $this->assertJsonResponse(201);

        // 3. Julie se connecte et vérifie son rôle
        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => $julieData['email'],
            'password' => $julieData['password']
        ]);
        $this->assertJsonResponse(200);

        // 4. Julie consulte les détails de son équipe
        $this->authenticatedRequest('GET', '/api/teams/' . $teamU18Data['id'], $julie);
        $teamDetails = $this->assertJsonResponse(200);
        
        $this->assertEquals('U18 Filles', $teamDetails['name']);
        
        // 5. Vérifier que Julie peut voir les restrictions d'âge de l'équipe
        $this->assertEquals(2006, $teamDetails['minBirthYear']);
        $this->assertEquals(2012, $teamDetails['maxBirthYear']);

        // 6. Julie tente d'accéder à une autre équipe (doit échouer)
        $this->authenticatedRequest('GET', '/api/teams/999', $julie);
        $this->assertErrorResponse(403);

        $this->storeTestData('coach', ['user' => $julie]);
    }

    private function executeAthleteJourney(): void
    {
        // Récupérer les données précédentes
        $clubData = $this->getTestData('club');
        $teamU18Data = $this->getTestData('teamU18');
        $marc = $clubData['owner'];

        // 1. Emma s'inscrit
        $emmaData = [
            'email' => 'emma.leblanc@athlete.com',
            'password' => 'password123',
            'firstName' => 'Emma',
            'lastName' => 'Leblanc',
            'dateOfBirth' => '2008-06-15' // 16 ans, dans la tranche d'âge
        ];

        $this->unauthenticatedRequest('POST', '/api/users', $emmaData);
        $this->assertJsonResponse(201);

        $emma = $this->createTestUser($emmaData['email'], ['ROLE_ATHLETE'], [
            'firstName' => $emmaData['firstName'],
            'lastName' => $emmaData['lastName'],
            'dateOfBirth' => $emmaData['dateOfBirth']
        ]);

        // 2. Marc ajoute Emma à l'équipe U18 Filles
        $membershipData = [
            'user' => '/api/users/' . $emma->getId(),
            'team' => '/api/teams/' . $teamU18Data['id'],
            'role' => 'athlete'
        ];

        $this->authenticatedRequest('POST', '/api/team-members', $marc, $membershipData);
        $this->assertJsonResponse(201);

        // 3. Emma se connecte
        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => $emmaData['email'],
            'password' => $emmaData['password']
        ]);
        $this->assertJsonResponse(200);

        // 4. Emma upload son certificat médical
        $documentData = [
            'originalName' => 'certificat-medical-emma.pdf',
            'user' => '/api/users/' . $emma->getId(),
            'documentType' => 1 // Supposé être le certificat médical
        ];

        $this->authenticatedRequest('POST', '/api/documents', $emma, $documentData);
        $this->assertJsonResponse(201);

        // 5. Emma consulte ses documents
        $this->authenticatedRequest('GET', '/api/users/' . $emma->getId() . '/documents', $emma);
        $documentsData = $this->assertJsonResponse(200);
        
        $this->assertGreaterThan(0, count($documentsData['hydra:member']));

        // 6. Emma tente d'accéder aux données d'un autre utilisateur (doit échouer)
        $this->authenticatedRequest('GET', '/api/users/999/documents', $emma);
        $this->assertErrorResponse(403);

        $this->storeTestData('athlete', ['user' => $emma]);
    }

    private function executeJoinRequestJourney(): void
    {
        // Récupérer les données précédentes
        $clubData = $this->getTestData('club');
        $teamU18Data = $this->getTestData('teamU18');
        $marc = $clubData['owner'];

        // 1. Lucas s'inscrit et demande à rejoindre l'équipe
        $lucasData = [
            'email' => 'lucas.martin@athlete.com',
            'password' => 'password123',
            'firstName' => 'Lucas',
            'lastName' => 'Martin',
            'dateOfBirth' => '2009-03-10' // 15 ans, dans la tranche d'âge
        ];

        $this->unauthenticatedRequest('POST', '/api/users', $lucasData);
        $this->assertJsonResponse(201);

        $lucas = $this->createTestUser($lucasData['email'], ['ROLE_ATHLETE'], [
            'firstName' => $lucasData['firstName'],
            'lastName' => $lucasData['lastName'],
            'dateOfBirth' => $lucasData['dateOfBirth']
        ]);

        // 2. Lucas fait une demande pour rejoindre l'équipe
        $joinRequestData = [
            'user' => '/api/users/' . $lucas->getId(),
            'team' => '/api/teams/' . $teamU18Data['id'],
            'message' => 'Je souhaiterais rejoindre votre équipe U18'
        ];

        $this->authenticatedRequest('POST', '/api/join-requests', $lucas, $joinRequestData);
        $joinRequestResponse = $this->assertJsonResponse(201);
        $joinRequestId = $joinRequestResponse['id'];

        // 3. Marc consulte les demandes en attente
        $this->authenticatedRequest('GET', '/api/join-requests?status=pending', $marc);
        $pendingRequests = $this->assertJsonResponse(200);
        
        $this->assertGreaterThan(0, count($pendingRequests['hydra:member']));

        // 4. Marc approuve la demande de Lucas
        $approvalData = [
            'status' => 'approved',
            'reviewComment' => 'Bienvenue dans l\'équipe !'
        ];

        $this->authenticatedRequest('PATCH', '/api/join-requests/' . $joinRequestId, $marc, $approvalData);
        $this->assertJsonResponse(200);

        // 5. Vérifier que Lucas a été ajouté à l'équipe
        $this->authenticatedRequest('GET', '/api/teams/' . $teamU18Data['id'] . '/members', $marc);
        $teamMembers = $this->assertJsonResponse(200);
        
        $lucasFound = false;
        foreach ($teamMembers['hydra:member'] as $member) {
            if ($member['user']['id'] === $lucas->getId()) {
                $lucasFound = true;
                break;
            }
        }
        $this->assertTrue($lucasFound, 'Lucas devrait être dans l\'équipe après approbation');

        // 6. Lucas se connecte et vérifie qu'il fait partie de l'équipe
        $this->unauthenticatedRequest('POST', '/api/login', [
            'email' => $lucasData['email'],
            'password' => $lucasData['password']
        ]);
        $this->assertJsonResponse(200);

        $this->authenticatedRequest('GET', '/api/users/' . $lucas->getId() . '/teams', $lucas);
        $lucasTeams = $this->assertJsonResponse(200);
        
        $this->assertGreaterThan(0, count($lucasTeams['hydra:member']));
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