<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Auth',
    operations: [
        new Post(
            uriTemplate: '/login',
            controller: 'App\Controller\AuthController::login',
            name: 'api_login',
            openapi: new Model\Operation(
                summary: 'Connexion utilisateur',
                description: 'Authentifie un utilisateur avec email/mot de passe et retourne un token JWT. Remember Me automatique pour 1 an.',
                tags: ['Authentification'],
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['email', 'password'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'owner1@example.com'],
                                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'password123']
                                ]
                            ])
                        )
                    ])
                ),
                responses: [
                    '200' => new Model\Response(
                        description: 'Connexion réussie',
                        content: new \ArrayObject([
                            'application/json' => new Model\MediaType(
                                schema: new \ArrayObject([
                                    'type' => 'object',
                                    'properties' => [
                                        'token' => ['type' => 'string', 'example' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'],
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer', 'example' => 1],
                                                'email' => ['type' => 'string', 'example' => 'owner1@example.com'],
                                                'firstName' => ['type' => 'string', 'example' => 'Jean'],
                                                'lastName' => ['type' => 'string', 'example' => 'Dupont'],
                                                'roles' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['ROLE_CLUB_OWNER']],
                                                'onboardingCompleted' => ['type' => 'boolean', 'example' => true]
                                            ]
                                        ]
                                    ]
                                ])
                            )
                        ])
                    ),
                    '400' => new Model\Response(description: 'Données manquantes'),
                    '401' => new Model\Response(description: 'Identifiants invalides')
                ]
            )
        ),
        new Post(
            uriTemplate: '/register',
            controller: 'App\Controller\AuthController::register',
            name: 'api_register',
            openapi: new Model\Operation(
                summary: 'Inscription utilisateur',
                description: 'Crée un nouveau compte utilisateur avec authentification email',
                tags: ['Authentification'],
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['email', 'password', 'firstName', 'lastName', 'onboardingType'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'nouveau@example.com'],
                                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'motdepasse123'],
                                    'firstName' => ['type' => 'string', 'example' => 'Prénom'],
                                    'lastName' => ['type' => 'string', 'example' => 'Nom'],
                                    'onboardingType' => ['type' => 'string', 'enum' => ['owner', 'member'], 'example' => 'member'],
                                    'phone' => ['type' => 'string', 'example' => '+33123456789']
                                ]
                            ])
                        )
                    ])
                ),
                responses: [
                    '201' => new Model\Response(description: 'Inscription réussie'),
                    '400' => new Model\Response(description: 'Données invalides'),
                    '409' => new Model\Response(description: 'Email déjà utilisé')
                ]
            )
        ),
        new Post(
            uriTemplate: '/refresh-token',
            controller: 'App\Controller\AuthController::refreshToken',
            name: 'api_refresh_token',
            openapi: new Model\Operation(
                summary: 'Renouvellement du token JWT',
                description: 'Génère un nouveau token JWT via le cookie Remember Me (valide 1 an)',
                tags: ['Authentification'],
                responses: [
                    '200' => new Model\Response(description: 'Token renouvelé avec succès'),
                    '401' => new Model\Response(description: 'Cookie Remember Me invalide ou expiré')
                ]
            )
        ),
        new Get(
            uriTemplate: '/profile',
            controller: 'App\Controller\AuthController::profile',
            name: 'api_profile',
            openapi: new Model\Operation(
                summary: 'Profil utilisateur',
                description: 'Récupère les informations du profil de l\'utilisateur connecté',
                tags: ['Authentification'],
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Profil utilisateur'),
                    '401' => new Model\Response(description: 'Non authentifié')
                ]
            )
        )
    ],
    normalizationContext: ['groups' => ['auth:read']],
    denormalizationContext: ['groups' => ['auth:write']]
)]
class AuthResource
{
    // Cette classe est virtuelle, elle sert uniquement pour la documentation
} 