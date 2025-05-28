# Prompt Symfony 7.2 - API Backend pour Gestion de Club Sportif

Créez un projet Symfony 7.2 complet avec API Platform pour une application de gestion de club sportif associatif.

## Architecture Technique Requise

- **Framework** : Symfony 7.2
- **API** : API Platform avec sérialisation JSON
- **Base de données** : PostgreSQL avec Doctrine ORM
- **Authentification** : JWT avec LexikJWTAuthenticationBundle + refresh tokens
- **Cache** : Redis pour sessions et mise en cache
- **Upload** : Gestion des fichiers avec validation
- **Sécurité** : Système de rôles avec Voter pattern

## Modèle de Données PostgreSQL

### Entités Principales

**User**
```php
- id (int, PK)
- email (string, UK)
- first_name (string)
- last_name (string)
- phone (string, nullable)
- roles (json)
- onboarding_type (string: 'owner', 'member', nullable) // Choix initial
- onboarding_completed (boolean, default: false)
- created_at (datetime)
- updated_at (datetime)
- is_active (boolean, default: true)
```

**UserAuthentication**
```php
- id (int, PK)
- user_id (int, FK User)
- provider (string: 'email', 'google', 'apple')
- provider_id (string, nullable) // Google ID ou Apple ID
- email (string) // Email du provider
- password (string, nullable, hashed) // Seulement pour provider 'email'
- is_verified (boolean, default: false)
- last_login_at (datetime, nullable)
- created_at (datetime)
- updated_at (datetime)
- is_active (boolean, default: true)
```

**JoinRequest** (nouvelle entité)
```php
- id (int, PK)
- user_id (int, FK User) // Utilisateur qui demande
- team_id (int, FK Team) // Équipe visée
- club_id (int, FK Club) // Club de l'équipe
- message (text, nullable) // Message de motivation
- status (string: 'pending', 'approved', 'rejected')
- requested_role (string: 'athlete', 'coach', nullable) // Rôle souhaité
- assigned_role (string: 'athlete', 'coach', nullable) // Rôle assigné par gestionnaire
- reviewed_by_id (int, FK User, nullable) // Gestionnaire/Coach qui a validé
- reviewed_at (datetime, nullable)
- review_notes (text, nullable) // Notes du validateur
- created_at (datetime)
- updated_at (datetime)
```

**Club**
```php
- id (int, PK)
- name (string)
- description (text, nullable)
- image_path (string, nullable)
- owner_id (int, FK User)
- is_public (boolean, default: true) // Visible dans la recherche publique
- allow_join_requests (boolean, default: true)
- created_at (datetime)
- updated_at (datetime)
- is_active (boolean, default: true)
```

**Season**
```php
- id (int, PK)
- name (string)
- start_date (date)
- end_date (date)
- club_id (int, FK Club)
- is_active (boolean, default: false)
- created_at (datetime)
```

**Team**
```php
- id (int, PK)
- name (string)
- description (text, nullable)
- image_path (string, nullable)
- club_id (int, FK Club)
- season_id (int, FK Season)
- annual_price (decimal(10,2))
- created_at (datetime)
- updated_at (datetime)
- is_active (boolean, default: true)
```

**ClubManager** (Table de liaison)
```php
- id (int, PK)
- user_id (int, FK User)
- club_id (int, FK Club)
- created_at (datetime)
```

**TeamMember** (Table de liaison)
```php
- id (int, PK)
- user_id (int, FK User)
- team_id (int, FK Team)
- role (string: 'athlete', 'coach')
- joined_at (datetime)
- left_at (datetime, nullable)
- is_active (boolean, default: true)
```

**PaymentSchedule**
```php
- id (int, PK)
- team_id (int, FK Team)
- amount (decimal(10,2))
- due_date (date)
- description (string)
- created_at (datetime)
```

**Payment**
```php
- id (int, PK)
- user_id (int, FK User)
- team_id (int, FK Team)
- payment_schedule_id (int, FK PaymentSchedule)
- amount (decimal(10,2))
- amount_paid (decimal(10,2), default: 0)
- due_date (date)
- paid_at (datetime, nullable)
- status (string: 'pending', 'partial', 'paid', 'overdue')
- notes (text, nullable)
- created_at (datetime)
```

**Cagnotte**
```php
- id (int, PK)
- user_id (int, FK User)
- team_id (int, FK Team)
- current_amount (decimal(10,2), default: 0)
- total_earned (decimal(10,2), default: 0)
- created_at (datetime)
- updated_at (datetime)
```

**Event**
```php
- id (int, PK)
- title (string)
- description (text, nullable)
- total_budget (decimal(10,2))
- club_percentage (decimal(5,2))
- team_id (int, FK Team)
- created_by_id (int, FK User)
- image_path (string, nullable)
- event_date (datetime)
- created_at (datetime)
- status (string: 'draft', 'active', 'completed', 'cancelled')
```

**EventParticipant** (Table de liaison)
```php
- id (int, PK)
- event_id (int, FK Event)
- user_id (int, FK User)
- amount_earned (decimal(10,2), default: 0)
- created_at (datetime)
```

**RememberMeToken** (entité Symfony standard)
```php
- series (string, PK) // Série du token
- value (string) // Valeur hashée du token
- lastUsed (datetime) // Dernière utilisation
- class (string) // Classe de l'utilisateur
- username (string) // Username/email utilisateur
```
```php
- id (int, PK)
- cagnotte_id (int, FK Cagnotte)
- event_id (int, FK Event, nullable)
- amount (decimal(10,2))
- type (string: 'credit', 'debit')
- description (text)
- created_at (datetime)
```

**DocumentType**
```php
- id (int, PK)
- team_id (int, FK Team)
- name (string)
- description (text, nullable)
- is_required (boolean, default: true)
- deadline (date, nullable)
- created_at (datetime)
```

**Document**
```php
- id (int, PK)
- user_id (int, FK User)
- document_type_id (int, FK DocumentType)
- original_name (string)
- file_path (string)
- status (string: 'pending', 'approved', 'rejected')
- validation_notes (text, nullable)
- validated_by_id (int, FK User, nullable)
- validated_at (datetime, nullable)
- created_at (datetime)
- updated_at (datetime)
```

**Notification**
```php
- id (int, PK)
- user_id (int, FK User)
- type (string)
- title (string)
- message (text)
- is_read (boolean, default: false)
- data (json, nullable)
- created_at (datetime)
```

**PaymentDeduction**
```php
- id (int, PK)
- team_id (int, FK Team)
- name (string)
- type (string: 'percentage', 'fixed')
- value (decimal(10,2))
- max_amount (decimal(10,2), nullable)
- is_active (boolean, default: true)
- created_at (datetime)
```

**ClubFinance**
```php
- id (int, PK)
- club_id (int, FK Club)
- total_commission (decimal(10,2), default: 0)
- current_balance (decimal(10,2), default: 0)
- created_at (datetime)
- updated_at (datetime)
```

**ClubTransaction**
```php
- id (int, PK)
- club_id (int, FK Club)
- event_id (int, FK Event, nullable)
- amount (decimal(10,2))
- type (string: 'commission', 'expense', 'revenue')
- description (text)
- created_at (datetime)
```

## Système de Rôles et Permissions

### Rôles Hiérarchiques
1. **ROLE_CLUB_OWNER** : Propriétaire du club
   - Toutes permissions sur son club
   - Gestion des gestionnaires
   - Suppression du club

2. **ROLE_CLUB_MANAGER** : Gestionnaire du club
   - Gestion des équipes
   - Gestion financière complète
   - Validation des documents
   - Création d'événements et gestion des cagnottes

3. **ROLE_COACH** : Entraîneur
   - Accès à ses équipes
   - Consultation documents de ses athlètes
   - Bilan financier de ses athlètes
   - Envoi de rappels de paiement

4. **ROLE_ATHLETE** : Athlète
   - Profil personnel
   - Upload de documents
   - Consultation cagnotte (lecture seule)
   - Historique des paiements

5. **ROLE_MEMBER** : Membre en attente (après inscription)
   - Consultation des clubs publics
   - Demandes d'adhésion aux équipes
   - Profil personnel limité

### Voters à Implémenter
- `ClubVoter` : Permissions sur les clubs
- `TeamVoter` : Permissions sur les équipes
- `DocumentVoter` : Validation/consultation documents
- `CagnotteVoter` : Gestion des cagnottes
- `PaymentVoter` : Gestion des paiements
- `JoinRequestVoter` : Validation des demandes d'adhésion
- `OnboardingVoter` : Accès aux fonctionnalités selon l'onboarding

## API Endpoints Requis

### Authentification avec Remember Me Natif
- `POST /api/login` : Connexion JWT avec paramètre `_remember_me=true`
- `POST /api/login/google` : Connexion Google OAuth avec remember me
- `POST /api/login/apple` : Connexion Apple Sign-In avec remember me
- `POST /api/refresh` : Refresh token JWT standard
- `POST /api/register` : Inscription classique
- `POST /api/register/social` : Inscription via OAuth
- `GET /api/profile` : Profil utilisateur
- `PUT /api/profile` : Modification profil
- `POST /api/auth/link-provider` : Lier un nouveau provider
- `DELETE /api/auth/unlink-provider/{provider}` : Délier un provider
- `POST /api/logout` : Déconnexion (supprime remember me cookie)

### Clubs
- `GET /api/clubs` : Liste des clubs utilisateur
- `POST /api/clubs` : Création club
- `GET /api/clubs/{id}` : Détail club
- `PUT /api/clubs/{id}` : Modification club
- `DELETE /api/clubs/{id}` : Suppression club
- `GET /api/clubs/{id}/stats` : Statistiques club

### Équipes
- `GET /api/clubs/{clubId}/teams` : Équipes du club
- `POST /api/clubs/{clubId}/teams` : Création équipe
- `GET /api/teams/{id}` : Détail équipe
- `PUT /api/teams/{id}` : Modification équipe
- `POST /api/teams/{id}/members` : Ajout membre
- `DELETE /api/teams/{id}/members/{userId}` : Suppression membre

### Gestion Financière
- `GET /api/teams/{id}/payments` : Paiements équipe
- `POST /api/teams/{id}/payment-schedules` : Création échéancier
- `PUT /api/payments/{id}` : Mise à jour paiement
- `GET /api/teams/{id}/cagnottes` : Cagnottes équipe
- `POST /api/teams/{id}/events` : Création événement
- `POST /api/events/{id}/participants` : Ajout participants
- `PUT /api/events/{id}/distribute` : Distribution gains

### Documents
- `GET /api/teams/{id}/document-types` : Types documents requis
- `POST /api/teams/{id}/document-types` : Création type document
- `POST /api/documents` : Upload document
- `GET /api/documents/{id}` : Téléchargement document
- `PUT /api/documents/{id}/validate` : Validation document

### Notifications
- `GET /api/notifications` : Notifications utilisateur
- `PUT /api/notifications/{id}/read` : Marquer comme lu
- `POST /api/notifications/payment-reminders` : Envoi rappels

## Fonctionnalités Métier Importantes

### Système de Cagnotte Automatisé
- **Création d'événement** par gestionnaires uniquement
- **Calcul automatique** : `(budget_total * (100 - club_percentage) / 100) / nb_participants`
- **Attribution automatique** aux cagnottes des participants
- **Traçabilité** avec CagnotteTransaction

### Gestion des Paiements
- **Échéanciers personnalisables** par équipe
- **Calcul des déductions** (pourcentage ou montant fixe)
- **Statuts automatiques** : pending → overdue selon due_date
- **Rappels automatiques** configurables

### Validation de Documents
- **Workflow de validation** : pending → approved/rejected
- **Types de documents** configurables par équipe
- **Dates limites** avec notifications automatiques
- **Stockage sécurisé** des fichiers

## Configuration Technique

### Packages Symfony Requis
```bash
composer require api-platform/core
composer require lexik/jwt-authentication-bundle
composer require doctrine/doctrine-bundle
composer require doctrine/doctrine-migrations-bundle
composer require symfony/redis-bundle
composer require symfony/mailer
composer require symfony/validator
composer require vich/uploader-bundle
composer require nelmio/cors-bundle
composer require knpuniversity/oauth2-client-bundle
composer require league/oauth2-google
composer require firebase/php-jwt
```

### Configuration Base de Données
```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        url: '%env(DATABASE_URL)%'
        driver: 'pdo_pgsql'
        charset: utf8
    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
```

### Configuration Redis
```yaml
# config/packages/redis.yaml
redis:
    clients:
        default:
            type: predis
            alias: default
            dsn: '%env(REDIS_URL)%'
        cache:
            type: predis
            alias: cache
            dsn: '%env(REDIS_URL)%/1'
        session:
            type: predis
            alias: session
            dsn: '%env(REDIS_URL)%/2'
```

### Authentification avec Remember Me Natif
- `POST /api/login` : Connexion JWT avec paramètre `_remember_me=true`
- `POST /api/login/google` : Connexion Google OAuth avec remember me
- `POST /api/login/apple` : Connexion Apple Sign-In avec remember me
- `POST /api/refresh` : Refresh token JWT standard
- `POST /api/register` : Inscription classique
- `POST /api/register/social` : Inscription via OAuth
- `GET /api/profile` : Profil utilisateur
- `PUT /api/profile` : Modification profil
- `POST /api/auth/link-provider` : Lier un nouveau provider
- `DELETE /api/auth/unlink-provider/{provider}` : Délier un provider
- `POST /api/logout` : Déconnexion (supprime remember me cookie)
```yaml
# config/packages/knpu_oauth2_client.yaml
knpu_oauth2_client:
    clients:
        google:
            type: google
            client_id: '%env(GOOGLE_OAUTH_CLIENT_ID)%'
            client_secret: '%env(GOOGLE_OAUTH_CLIENT_SECRET)%'
            redirect_route: connect_google_check
            redirect_params: {}
            use_state: true
        apple:
            type: generic
            provider_class: App\OAuth\AppleProvider
            client_id: '%env(APPLE_OAUTH_CLIENT_ID)%'
            client_secret: '%env(APPLE_OAUTH_CLIENT_SECRET)%'
            redirect_route: connect_apple_check
            redirect_params: {}
```

### Configuration JWT Sécurisée + Remember Me
```yaml
# config/packages/lexik_jwt_authentication.yaml
lexik_jwt_authentication:
    secret_key: '%env(JWT_SECRET_KEY)%'
    public_key: '%env(JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: 3600 # 1 heure (sécurisé)
    refresh_token_ttl: 604800 # 7 jours

# config/packages/security.yaml
security:
    firewalls:
        api:
            remember_me:
                secret: '%env(APP_SECRET)%'
                lifetime: 31536000 # 1 an
                path: /
                domain: ~
                secure: auto
                httponly: true
                samesite: 'lax'
                always_remember_me: false
                remember_me_parameter: '_remember_me'
```

### Configuration Sessions Redis Standards
```yaml
# config/packages/redis.yaml
redis:
    clients:
        default:
            type: predis
            alias: default
            dsn: '%env(REDIS_URL)%'
        cache:
            type: predis
            alias: cache
            dsn: '%env(REDIS_URL)%/1'
        session:
            type: predis
            alias: session
            dsn: '%env(REDIS_URL)%/2'

# config/packages/framework.yaml
framework:
    session:
        handler_id: Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler
        cookie_lifetime: 0 # Session cookie
        cookie_secure: auto
        cookie_samesite: lax
        gc_maxlifetime: 3600 # 1 heure standard
```

## Données de Test à Générer

### Fixtures à Créer
1. **3 clubs** avec propriétaires différents (certains publics, certains privés)
2. **2-3 saisons** par club (une active)
3. **4-6 équipes** réparties sur les clubs
4. **15-20 utilisateurs** avec rôles variés et différents providers d'authentification
5. **Méthodes d'authentification variées** : email/password, Google, Apple
6. **5-10 demandes d'adhésion** avec statuts différents (pending, approved, rejected)
7. **Utilisateurs en onboarding** (owner/member) avec statuts différents
8. **Échéanciers de paiement** avec statuts différents
9. **5-10 événements** avec participants
10. **Documents** en cours de validation
11. **Notifications** non lues (incluant notifications de demandes)
12. **Transactions de cagnotte** historiques

### Commandes Console à Créer
- `app:generate-fixtures` : Génération données de test
- `app:payment-reminders` : Envoi rappels automatiques  
- `app:update-payment-status` : Mise à jour statuts paiements
- `app:cleanup-expired-tokens` : Nettoyage tokens JWT expirés
- `app:cleanup-remember-me-tokens` : Nettoyage remember me tokens expirés

## Performance et Optimisation

### Index Base de Données
- Index sur `email` (User)
- Index sur `onboarding_type, onboarding_completed` (User)
- Index sur `provider, provider_id` (UserAuthentication)
- Index sur `provider, email` (UserAuthentication)
- Index sur `user_id, is_active` (UserAuthentication)
- Index sur `series` (RememberMeToken) 
- Index sur `lastUsed` (RememberMeToken)
- Index sur `is_public, allow_join_requests` (Club)
- Index sur `user_id, status` (JoinRequest)
- Index sur `team_id, status` (JoinRequest)
- Index sur `club_id, status` (JoinRequest)
- Index sur `reviewed_by_id, reviewed_at` (JoinRequest)
- Index sur `club_id, is_active` (Team)
- Index sur `user_id, status` (Payment)
- Index sur `team_id, is_required` (DocumentType)
- Index composé sur `event_id, user_id` (EventParticipant)

### Cache Redis Standard
- Cache des permissions utilisateur (TTL: 1h)
- Cache des statistiques club (TTL: 30min)  
- Cache des rapports financiers (TTL: 1h)
- Sessions utilisateur standard
- Remember me tokens (gérés par Symfony)

### Validation et Sécurité Standard
- Validation stricte des montants financiers
- Contraintes d'intégrité référentielle
- Hashage sécurisé des mots de passe (Argon2id)
- Upload sécurisé avec validation MIME
- Rate limiting sur les endpoints sensibles
- **Remember Me tokens sécurisés** (rotation automatique)
- **Protection CSRF native** Symfony
- **Validation des cookies HttpOnly/Secure**
- **Audit trail des connexions** avec remember me

Créez un projet Symfony 7.2 complet, fonctionnel et optimisé qui implémente toute cette architecture avec des données de test réalistes.