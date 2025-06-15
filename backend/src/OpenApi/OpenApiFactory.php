<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;
use ApiPlatform\OpenApi\Model;

final class OpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated
    ) {}

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        
        // Ajouter toutes les routes des contrôleurs personnalisés
        $this->addAuthRoutes($openApi);
        $this->addClubRoutes($openApi);
        $this->addDocumentRoutes($openApi);
        $this->addTeamRoutes($openApi);
        $this->addJoinRequestRoutes($openApi);
        $this->addNotificationRoutes($openApi);
        $this->addProfileRoutes($openApi);
        $this->addTeamMemberRoutes($openApi);
        $this->addHealthRoute($openApi);

        return $openApi;
    }

    private function addAuthRoutes(OpenApi $openApi): void
    {
        $pathItem = new Model\PathItem(
            ref: 'Auth',
            post: new Model\Operation(
                operationId: 'postCredentialsItem',
                tags: ['Authentification'],
                responses: [
                    '200' => new Model\Response(
                        description: 'Connexion réussie',
                        content: new \ArrayObject([
                            'application/json' => new Model\MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'accessToken' => ['type' => 'string'],
                                        'refreshToken' => ['type' => 'string'],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer'],
                                                'email' => ['type' => 'string'],
                                                'firstName' => ['type' => 'string'],
                                                'lastName' => ['type' => 'string'],
                                                'roles' => ['type' => 'array', 'items' => ['type' => 'string']]
                                            ]
                                        ]
                                    ]
                                ])
                            )
                        ])
                    ),
                    '401' => new Model\Response(description: 'Identifiants invalides')
                ],
                summary: 'Connexion utilisateur',
                description: 'Authentifie un utilisateur avec email/mot de passe et retourne des tokens JWT.',
                requestBody: new Model\RequestBody(
                    description: 'Données de connexion',
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['email', 'password'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'password' => ['type' => 'string', 'format' => 'password']
                                ]
                            ])
                        )
                    ])
                )
            )
        );
        $openApi->getPaths()->addPath('/api/login', $pathItem);

        // Route d'inscription
        $registerPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'postRegisterItem',
                tags: ['Authentification'],
                responses: [
                    '201' => new Model\Response(description: 'Inscription réussie'),
                    '400' => new Model\Response(description: 'Données invalides')
                ],
                summary: 'Inscription utilisateur',
                description: 'Crée un nouveau compte utilisateur',
                requestBody: new Model\RequestBody(
                    description: 'Données d\'inscription',
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['email', 'password', 'firstName', 'lastName', 'onboardingType'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'password' => ['type' => 'string'],
                                    'firstName' => ['type' => 'string'],
                                    'lastName' => ['type' => 'string'],
                                    'onboardingType' => ['type' => 'string', 'enum' => ['owner', 'member']]
                                ]
                            ])
                        )
                    ])
                )
            )
        );
        $openApi->getPaths()->addPath('/api/register', $registerPathItem);

        // Route refresh token
        $refreshTokenPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'refreshToken',
                tags: ['Authentification'],
                summary: 'Renouvellement des tokens JWT',
                description: 'Génère de nouveaux tokens JWT à partir d\'un refresh token valide',
                requestBody: new Model\RequestBody(
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['refreshToken'],
                                'properties' => [
                                    'refreshToken' => ['type' => 'string', 'example' => 'a1b2c3d4e5f6...']
                                ]
                            ])
                        )
                    ])
                ),
                responses: [
                    '200' => new Model\Response(description: 'Tokens renouvelés avec succès'),
                    '400' => new Model\Response(description: 'Refresh token manquant'),
                    '401' => new Model\Response(description: 'Refresh token invalide ou expiré')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/refresh-token', $refreshTokenPathItem);

        // Route logout
        $logoutPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'logout',
                tags: ['Authentification'],
                summary: 'Déconnexion utilisateur',
                description: 'Révoque le refresh token pour déconnecter l\'utilisateur',
                requestBody: new Model\RequestBody(
                    required: false,
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'properties' => [
                                    'refreshToken' => ['type' => 'string', 'example' => 'a1b2c3d4e5f6...'],
                                    'allDevices' => ['type' => 'boolean', 'example' => false, 'description' => 'Déconnecter de tous les appareils']
                                ]
                            ])
                        )
                    ])
                ),
                responses: [
                    '200' => new Model\Response(description: 'Déconnexion réussie')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/logout', $logoutPathItem);

        // Route de profil
        $profilePathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getProfileItem',
                tags: ['Authentification'],
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Profil utilisateur'),
                    '401' => new Model\Response(description: 'Non authentifié')
                ],
                summary: 'Profil utilisateur',
                description: 'Récupère les informations du profil de l\'utilisateur connecté'
            ),
            put: new Model\Operation(
                operationId: 'updateProfileItem',
                tags: ['Authentification'],
                security: [['JWT' => []]],
                summary: 'Mise à jour du profil',
                description: 'Met à jour les informations du profil utilisateur',
                responses: [
                    '200' => new Model\Response(description: 'Profil mis à jour'),
                    '400' => new Model\Response(description: 'Données invalides'),
                    '401' => new Model\Response(description: 'Non authentifié')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/profile', $profilePathItem);
    }

    private function addClubRoutes(OpenApi $openApi): void
    {
        // Route liste des clubs
        $clubsPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getClubsCollection',
                tags: ['Clubs'],
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Liste des clubs'),
                    '401' => new Model\Response(description: 'Non authentifié')
                ],
                summary: 'Mes clubs',
                description: 'Récupère la liste des clubs où l\'utilisateur est propriétaire ou gestionnaire'
            ),
            post: new Model\Operation(
                operationId: 'postClubsItem',
                tags: ['Clubs'],
                security: [['JWT' => []]],
                responses: [
                    '201' => new Model\Response(description: 'Club créé'),
                    '400' => new Model\Response(description: 'Données invalides'),
                    '401' => new Model\Response(description: 'Non authentifié')
                ],
                summary: 'Créer un club',
                description: 'Crée un nouveau club'
            )
        );
        $openApi->getPaths()->addPath('/api/clubs', $clubsPathItem);

        // Route clubs publics
        $publicClubsPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getPublicClubs',
                tags: ['Clubs'],
                summary: 'Clubs publics',
                description: 'Récupère la liste des clubs publics avec pagination',
                parameters: [
                    new Model\Parameter('page', 'query', 'Numéro de page', false, schema: ['type' => 'integer', 'minimum' => 1, 'example' => 1]),
                    new Model\Parameter('limit', 'query', 'Nombre d\'éléments par page (max 50)', false, schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'example' => 20])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Liste des clubs publics')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/clubs/public', $publicClubsPathItem);

        // Route club spécifique
        $clubPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getClubItem',
                tags: ['Clubs'],
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Détails du club'),
                    '404' => new Model\Response(description: 'Club non trouvé'),
                    '403' => new Model\Response(description: 'Accès interdit')
                ],
                summary: 'Détails d\'un club',
                parameters: [
                    new Model\Parameter('id', 'path', 'ID du club', true, schema: ['type' => 'integer'])
                ]
            ),
            put: new Model\Operation(
                operationId: 'putClubItem',
                tags: ['Clubs'],
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Club mis à jour'),
                    '400' => new Model\Response(description: 'Données invalides'),
                    '404' => new Model\Response(description: 'Club non trouvé'),
                    '403' => new Model\Response(description: 'Accès interdit')
                ],
                summary: 'Mettre à jour un club',
                parameters: [
                    new Model\Parameter('id', 'path', 'ID du club', true, schema: ['type' => 'integer'])
                ]
            ),
            delete: new Model\Operation(
                operationId: 'deleteClubItem',
                tags: ['Clubs'],
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Club supprimé'),
                    '404' => new Model\Response(description: 'Club non trouvé'),
                    '403' => new Model\Response(description: 'Accès interdit')
                ],
                summary: 'Supprimer un club',
                parameters: [
                    new Model\Parameter('id', 'path', 'ID du club', true, schema: ['type' => 'integer'])
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/clubs/{id}', $clubPathItem);

        // Route upload logo
        $logoPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'uploadClubLogo',
                tags: ['Clubs'],
                security: [['JWT' => []]],
                summary: 'Upload du logo d\'un club',
                description: 'Upload et mise à jour du logo d\'un club',
                parameters: [
                    new Model\Parameter('id', 'path', 'ID du club', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Logo uploadé avec succès'),
                    '400' => new Model\Response(description: 'Erreur lors de l\'upload'),
                    '401' => new Model\Response(description: 'Non authentifié'),
                    '403' => new Model\Response(description: 'Accès interdit'),
                    '404' => new Model\Response(description: 'Club non trouvé')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/clubs/{id}/logo', $logoPathItem);

        // Route statistiques club
        $statsPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getClubStats',
                tags: ['Clubs'],
                security: [['JWT' => []]],
                summary: 'Statistiques d\'un club',
                description: 'Récupère les statistiques détaillées d\'un club',
                parameters: [
                    new Model\Parameter('id', 'path', 'ID du club', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Statistiques du club'),
                    '404' => new Model\Response(description: 'Club non trouvé'),
                    '403' => new Model\Response(description: 'Accès interdit')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/clubs/{id}/stats', $statsPathItem);
    }

    private function addDocumentRoutes(OpenApi $openApi): void
    {
        // Upload document
        $uploadPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'uploadDocument',
                tags: ['Documents'],
                summary: 'Upload sécurisé d\'un document',
                description: 'Upload d\'un document avec stockage sécurisé et notifications aux gestionnaires',
                security: [['JWT' => []]],
                responses: [
                    '201' => new Model\Response(description: 'Document uploadé avec succès'),
                    '400' => new Model\Response(description: 'Données invalides'),
                    '401' => new Model\Response(description: 'Non authentifié')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/documents', $uploadPathItem);

        // Document info et suppression
        $docPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getDocumentInfo',
                tags: ['Documents'],
                summary: 'Informations d\'un document',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID du document', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Informations du document'),
                    '404' => new Model\Response(description: 'Document non trouvé')
                ]
            ),
            delete: new Model\Operation(
                operationId: 'deleteDocument',
                tags: ['Documents'],
                summary: 'Suppression sécurisée d\'un document',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID du document', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Document supprimé'),
                    '404' => new Model\Response(description: 'Document non trouvé')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/documents/{id}', $docPathItem);

        // Download document
        $downloadPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'downloadDocument',
                tags: ['Documents'],
                summary: 'Téléchargement sécurisé d\'un document',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID du document', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Fichier téléchargé'),
                    '404' => new Model\Response(description: 'Document non trouvé')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/documents/{id}/download', $downloadPathItem);

        // Validate document
        $validatePathItem = new Model\PathItem(
            put: new Model\Operation(
                operationId: 'validateDocument',
                tags: ['Documents'],
                summary: 'Validation d\'un document',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID du document', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Document validé'),
                    '400' => new Model\Response(description: 'Données invalides')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/documents/{id}/validate', $validatePathItem);
    }

    private function addTeamRoutes(OpenApi $openApi): void
    {
        // Équipes d'un club
        $clubTeamsPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getClubTeams',
                tags: ['Équipes'],
                summary: 'Équipes d\'un club',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('clubId', 'path', 'ID du club', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Liste des équipes du club'),
                    '404' => new Model\Response(description: 'Club non trouvé')
                ]
            ),
            post: new Model\Operation(
                operationId: 'createTeam',
                tags: ['Équipes'],
                summary: 'Créer une équipe',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('clubId', 'path', 'ID du club', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '201' => new Model\Response(description: 'Équipe créée'),
                    '400' => new Model\Response(description: 'Données invalides')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/clubs/{clubId}/teams', $clubTeamsPathItem);

        // Équipe spécifique
        $teamPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getTeam',
                tags: ['Équipes'],
                summary: 'Détails d\'une équipe',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID de l\'équipe', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Détails de l\'équipe'),
                    '404' => new Model\Response(description: 'Équipe non trouvée')
                ]
            ),
            put: new Model\Operation(
                operationId: 'updateTeam',
                tags: ['Équipes'],
                summary: 'Mettre à jour une équipe',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID de l\'équipe', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Équipe mise à jour'),
                    '400' => new Model\Response(description: 'Données invalides')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/teams/{id}', $teamPathItem);

        // Ajouter membre à une équipe
        $addMemberPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'addTeamMember',
                tags: ['Équipes'],
                summary: 'Ajouter un membre à l\'équipe',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID de l\'équipe', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '201' => new Model\Response(description: 'Membre ajouté'),
                    '400' => new Model\Response(description: 'Données invalides')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/teams/{id}/members', $addMemberPathItem);

        // Retirer membre d'une équipe
        $removeMemberPathItem = new Model\PathItem(
            delete: new Model\Operation(
                operationId: 'removeTeamMember',
                tags: ['Équipes'],
                summary: 'Retirer un membre de l\'équipe',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID de l\'équipe', true, schema: ['type' => 'integer']),
                    new Model\Parameter('userId', 'path', 'ID de l\'utilisateur', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Membre retiré'),
                    '404' => new Model\Response(description: 'Équipe ou utilisateur non trouvé')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/teams/{id}/members/{userId}', $removeMemberPathItem);
    }

    private function addJoinRequestRoutes(OpenApi $openApi): void
    {
        // Créer demande d'adhésion
        $createRequestPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'createJoinRequest',
                tags: ['Demandes d\'adhésion'],
                summary: 'Créer une demande d\'adhésion',
                security: [['JWT' => []]],
                responses: [
                    '201' => new Model\Response(description: 'Demande créée'),
                    '400' => new Model\Response(description: 'Données invalides')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/join-requests', $createRequestPathItem);

        // Mes demandes
        $myRequestsPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getMyJoinRequests',
                tags: ['Demandes d\'adhésion'],
                summary: 'Mes demandes d\'adhésion',
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Liste de mes demandes')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/join-requests/my-requests', $myRequestsPathItem);

        // Demandes d'une équipe
        $teamRequestsPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getTeamJoinRequests',
                tags: ['Demandes d\'adhésion'],
                summary: 'Demandes d\'adhésion d\'une équipe',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('teamId', 'path', 'ID de l\'équipe', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Liste des demandes de l\'équipe')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/join-requests/team/{teamId}', $teamRequestsPathItem);

        // Approuver demande
        $approvePathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'approveJoinRequest',
                tags: ['Demandes d\'adhésion'],
                summary: 'Approuver une demande d\'adhésion',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID de la demande', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Demande approuvée'),
                    '400' => new Model\Response(description: 'Demande déjà traitée')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/join-requests/{id}/approve', $approvePathItem);

        // Rejeter demande
        $rejectPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'rejectJoinRequest',
                tags: ['Demandes d\'adhésion'],
                summary: 'Rejeter une demande d\'adhésion',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID de la demande', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Demande rejetée'),
                    '400' => new Model\Response(description: 'Demande déjà traitée')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/join-requests/{id}/reject', $rejectPathItem);

        // Annuler demande
        $cancelPathItem = new Model\PathItem(
            delete: new Model\Operation(
                operationId: 'cancelJoinRequest',
                tags: ['Demandes d\'adhésion'],
                summary: 'Annuler une demande d\'adhésion',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID de la demande', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Demande annulée'),
                    '400' => new Model\Response(description: 'Impossible d\'annuler')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/join-requests/{id}/cancel', $cancelPathItem);
    }

    private function addNotificationRoutes(OpenApi $openApi): void
    {
        // Liste des notifications
        $notificationsPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getNotifications',
                tags: ['Notifications'],
                summary: 'Notifications de l\'utilisateur',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('page', 'query', 'Numéro de page', false, schema: ['type' => 'integer', 'minimum' => 1]),
                    new Model\Parameter('limit', 'query', 'Nombre par page', false, schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]),
                    new Model\Parameter('unreadOnly', 'query', 'Seulement non lues', false, schema: ['type' => 'boolean'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Liste des notifications')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/notifications', $notificationsPathItem);

        // Marquer comme lue
        $markReadPathItem = new Model\PathItem(
            put: new Model\Operation(
                operationId: 'markNotificationAsRead',
                tags: ['Notifications'],
                summary: 'Marquer une notification comme lue',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID de la notification', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Notification marquée comme lue'),
                    '404' => new Model\Response(description: 'Notification non trouvée')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/notifications/{id}/read', $markReadPathItem);

        // Marquer toutes comme lues
        $markAllReadPathItem = new Model\PathItem(
            put: new Model\Operation(
                operationId: 'markAllNotificationsAsRead',
                tags: ['Notifications'],
                summary: 'Marquer toutes les notifications comme lues',
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Toutes les notifications marquées comme lues')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/notifications/read-all', $markAllReadPathItem);

        // Supprimer notification
        $deleteNotificationPathItem = new Model\PathItem(
            delete: new Model\Operation(
                operationId: 'deleteNotification',
                tags: ['Notifications'],
                summary: 'Supprimer une notification',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID de la notification', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '204' => new Model\Response(description: 'Notification supprimée'),
                    '404' => new Model\Response(description: 'Notification non trouvée')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/notifications/{id}', $deleteNotificationPathItem);

        // Nombre de notifications non lues
        $unreadCountPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getUnreadNotificationsCount',
                tags: ['Notifications'],
                summary: 'Nombre de notifications non lues',
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Nombre de notifications non lues')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/notifications/unread-count', $unreadCountPathItem);
    }

    private function addProfileRoutes(OpenApi $openApi): void
    {
        // Profil utilisateur (déjà ajouté dans addAuthRoutes, mais ajoutons les autres routes)
        
        // Changer mot de passe
        $changePasswordPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'changePassword',
                tags: ['Profil'],
                summary: 'Changer le mot de passe',
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Mot de passe modifié'),
                    '400' => new Model\Response(description: 'Données invalides')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/profile/change-password', $changePasswordPathItem);

        // Compléter onboarding
        $completeOnboardingPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'completeOnboarding',
                tags: ['Profil'],
                summary: 'Compléter l\'onboarding',
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Onboarding complété'),
                    '400' => new Model\Response(description: 'Données invalides')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/profile/complete-onboarding', $completeOnboardingPathItem);

        // Statistiques du profil
        $profileStatsPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'getProfileStats',
                tags: ['Profil'],
                summary: 'Statistiques du profil',
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Statistiques du profil utilisateur')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/profile/stats', $profileStatsPathItem);
    }

    private function addTeamMemberRoutes(OpenApi $openApi): void
    {
        // Ajouter membre d'équipe
        $addMemberPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'addTeamMemberDirect',
                tags: ['Membres d\'équipe'],
                summary: 'Ajouter un membre à une équipe',
                security: [['JWT' => []]],
                responses: [
                    '201' => new Model\Response(description: 'Membre ajouté à l\'équipe'),
                    '400' => new Model\Response(description: 'Données invalides')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/team-members', $addMemberPathItem);

        // Valider membre d'équipe
        $validateMemberPathItem = new Model\PathItem(
            post: new Model\Operation(
                operationId: 'validateTeamMember',
                tags: ['Membres d\'équipe'],
                summary: 'Valider qu\'un utilisateur peut rejoindre une équipe',
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Validation effectuée'),
                    '400' => new Model\Response(description: 'Données invalides')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/team-members/validate', $validateMemberPathItem);

        // Retirer membre d'équipe
        $removeMemberPathItem = new Model\PathItem(
            delete: new Model\Operation(
                operationId: 'removeTeamMemberDirect',
                tags: ['Membres d\'équipe'],
                summary: 'Retirer un membre d\'une équipe',
                security: [['JWT' => []]],
                parameters: [
                    new Model\Parameter('id', 'path', 'ID du membre d\'équipe', true, schema: ['type' => 'integer'])
                ],
                responses: [
                    '200' => new Model\Response(description: 'Membre retiré de l\'équipe'),
                    '404' => new Model\Response(description: 'Membre non trouvé')
                ]
            )
        );
        $openApi->getPaths()->addPath('/api/team-members/{id}', $removeMemberPathItem);
    }

    private function addHealthRoute(OpenApi $openApi): void
    {
        // Route de santé
        $healthPathItem = new Model\PathItem(
            get: new Model\Operation(
                operationId: 'healthCheck',
                tags: ['Système'],
                summary: 'Vérification de l\'état du service',
                description: 'Endpoint de santé pour vérifier que l\'API fonctionne correctement',
                responses: [
                    '200' => new Model\Response(
                        description: 'Service en bonne santé',
                        content: new \ArrayObject([
                            'application/json' => new Model\MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'status' => ['type' => 'string', 'example' => 'healthy'],
                                        'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                                        'service' => ['type' => 'string', 'example' => 'symfony-backend']
                                    ]
                                ])
                            )
                        ])
                    )
                ]
            )
        );
        $openApi->getPaths()->addPath('/health', $healthPathItem);
    }
} 