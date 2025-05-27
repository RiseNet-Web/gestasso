# 🏆 GestAssi API - Backend Symfony 7.2

API REST complète pour la gestion de clubs sportifs associatifs avec authentification multi-provider, gestion financière avancée et système de cagnottes automatisé.

## 📋 Table des Matières

- [Fonctionnalités](#-fonctionnalités)
- [Technologies](#-technologies)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Base de Données](#-base-de-données)
- [Authentification](#-authentification)
- [API Endpoints](#-api-endpoints)
- [Services Métier](#-services-métier)
- [Commandes Console](#-commandes-console)
- [Tests](#-tests)
- [Déploiement](#-déploiement)

## 🚀 Fonctionnalités

### Authentification Multi-Provider
- ✅ Email/Password classique
- ✅ Connexion Google OAuth
- ✅ Connexion Apple Sign-In
- ✅ Remember Me (1 an)
- ✅ JWT sécurisé + Refresh tokens

### Gestion des Clubs
- ✅ Création et administration de clubs
- ✅ Système de saisons
- ✅ Gestion des équipes
- ✅ Rôles hiérarchiques (Owner → Manager → Coach → Athlete)

### Workflow d'Onboarding
- ✅ Choix Owner/Member à l'inscription
- ✅ Navigation publique des clubs
- ✅ Demandes d'adhésion aux équipes
- ✅ Validation par gestionnaires/coachs

### Gestion Financière
- ✅ Échéanciers de paiement personnalisables
- ✅ Système de cagnottes automatisé
- ✅ Événements avec répartition des gains
- ✅ Commission club configurable
- ✅ Déductions et règles financières

### Gestion Documentaire
- ✅ Types de documents configurables
- ✅ Upload et validation sécurisés
- ✅ Workflow d'approbation
- ✅ Notifications automatiques

## 🛠 Technologies

- **Framework** : Symfony 7.2
- **API** : API Platform
- **Base de Données** : PostgreSQL 15+
- **Cache** : Redis 7+
- **Authentification** : JWT + Remember Me
- **Upload** : VichUploaderBundle
- **OAuth** : KnpUOAuth2ClientBundle

## 📦 Installation

### Prérequis

- PHP 8.2+
- Composer 2.5+
- PostgreSQL 15+
- Redis 7+
- OpenSSL (pour JWT)

### Installation du Projet

```bash
# Cloner le projet
git clone <repository-url> gestasso
cd gestasso

# Installer les dépendances
composer install

# Copier la configuration
cp .env .env.local

# Générer les clés JWT
php bin/console lexik:jwt:generate-keypair

# Créer la base de données
php bin/console doctrine:database:create

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Charger les fixtures (optionnel)
php bin/console doctrine:fixtures:load
```

## ⚙️ Configuration

### Variables d'Environnement

```bash
# .env.local

# Base de données
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/club_sportif?serverVersion=15&charset=utf8"

# Redis
REDIS_URL=redis://localhost:6379

# JWT
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_jwt_passphrase

# OAuth Google
GOOGLE_OAUTH_CLIENT_ID=your_google_client_id
GOOGLE_OAUTH_CLIENT_SECRET=your_google_client_secret

# OAuth Apple
APPLE_OAUTH_CLIENT_ID=your_apple_client_id
APPLE_OAUTH_CLIENT_SECRET=your_apple_client_secret

# Upload
UPLOAD_PATH=%kernel.project_dir%/public/uploads

# Mailing
MAILER_DSN=smtp://localhost:1025

# Remember Me
REMEMBER_ME_LIFETIME=31536000
```

### Configuration Docker (Optionnel)

```yaml
# docker-compose.yml
version: '3.8'
services:
  postgres:
    image: postgres:15
    environment:
      POSTGRES_DB: club_sportif
      POSTGRES_USER: symfony
      POSTGRES_PASSWORD: symfony
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

volumes:
  postgres_data:
```

## 🗄️ Base de Données

### Entités Principales

- **User** : Utilisateurs avec système d'onboarding
- **UserAuthentication** : Méthodes de connexion multi-provider
- **Club** : Clubs sportifs avec visibilité publique/privée
- **Team** : Équipes rattachées aux clubs et saisons
- **JoinRequest** : Demandes d'adhésion aux équipes
- **Payment** : Gestion des paiements et échéanciers
- **Cagnotte** : Cagnottes individuelles des athlètes
- **Event** : Événements générateurs de gains
- **Document** : Système documentaire avec validation

### Migrations

```bash
# Créer une nouvelle migration
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Rollback migration
php bin/console doctrine:migrations:migrate prev
```

## 🔐 Authentification

### JWT + Remember Me

L'API utilise un système hybride JWT/Remember Me pour allier sécurité et persistance :

- **JWT Access Token** : 1 heure (requêtes courantes)
- **JWT Refresh Token** : 7 jours (renouvellement)
- **Remember Me Cookie** : 1 an (reconnexion automatique)

### Connexion

```bash
# Connexion email/password
POST /api/login
{
    "email": "user@example.com",
    "password": "password",
    "_remember_me": true
}

# Réponse
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
    "refresh_token": "refresh_token_here",
    "user": {
        "id": 1,
        "email": "user@example.com",
        "roles": ["ROLE_CLUB_OWNER"]
    }
}
```

### Providers OAuth

```bash
# Google OAuth
POST /api/login/google
{
    "token": "google_access_token",
    "_remember_me": true
}

# Apple Sign-In
POST /api/login/apple
{
    "token": "apple_id_token",
    "_remember_me": true
}
```

## 📡 API Endpoints

### Authentification
```
POST   /api/register                    # Inscription
POST   /api/login                       # Connexion email/password
POST   /api/login/google                # Connexion Google
POST   /api/login/apple                 # Connexion Apple
POST   /api/refresh                     # Refresh token
POST   /api/logout                      # Déconnexion
```

### Onboarding
```
POST   /api/onboarding/choice           # Choix owner/member
GET    /api/clubs/public                # Clubs publics
GET    /api/clubs/{id}/teams/public     # Équipes publiques
```

### Demandes d'Adhésion
```
POST   /api/teams/{id}/join-request     # Demande d'adhésion
GET    /api/join-requests               # Liste demandes (managers)
PUT    /api/join-requests/{id}/review   # Valider/rejeter demande
GET    /api/my-join-requests            # Mes demandes
DELETE /api/join-requests/{id}          # Annuler demande
```

### Clubs et Équipes
```
GET    /api/clubs                       # Mes clubs
POST   /api/clubs                       # Créer club
GET    /api/clubs/{id}                  # Détail club
PUT    /api/clubs/{id}                  # Modifier club
GET    /api/clubs/{id}/teams            # Équipes du club
POST   /api/clubs/{id}/teams            # Créer équipe
```

### Gestion Financière
```
GET    /api/teams/{id}/payments         # Paiements équipe
POST   /api/teams/{id}/payment-schedules # Créer échéancier
GET    /api/teams/{id}/cagnottes        # Cagnottes équipe
POST   /api/teams/{id}/events           # Créer événement
POST   /api/events/{id}/participants    # Ajouter participants
PUT    /api/events/{id}/distribute      # Distribuer gains
```

### Documents
```
GET    /api/teams/{id}/document-types   # Types documents requis
POST   /api/documents                   # Upload document
PUT    /api/documents/{id}/validate     # Valider document
GET    /api/documents/{id}              # Télécharger document
```

## 🔧 Services Métier

### AuthenticationService
```php
// Authentification multi-provider
$user = $authService->authenticateWithEmail($email, $password, $rememberMe);
$user = $authService->authenticateWithGoogle($googleToken, $rememberMe);
$user = $authService->authenticateWithApple($appleToken, $rememberMe);

// Gestion des providers
$authService->linkProvider($user, 'google', $providerData);
$authService->unlinkProvider($user, 'google');
```

### OnboardingService
```php
// Gestion de l'onboarding
$onboardingService->setUserType($user, 'owner');
$publicClubs = $onboardingService->getPublicClubs();
$canJoin = $onboardingService->canUserJoinTeam($user, $team);
```

### JoinRequestService
```php
// Demandes d'adhésion
$request = $joinRequestService->createJoinRequest($user, $team, 'athlete');
$requests = $joinRequestService->getJoinRequestsForManager($managerId);
$teamMember = $joinRequestService->acceptJoinRequest($request, 'athlete', $reviewerId);
```

### CagnotteService
```php
// Gestion des cagnottes
$event = $cagnotteService->createEvent($team, $budget, $clubPercentage);
$cagnotteService->addParticipants($event, $participantIds);
$cagnotteService->distributeGains($event); // Calcul et attribution automatiques
```

## 🖥️ Commandes Console

```bash
# Gestion des données
php bin/console app:generate-fixtures        # Générer données de test

# Tâches financières
php bin/console app:payment-reminders        # Envoyer rappels de paiement
php bin/console app:update-payment-status    # Mettre à jour statuts paiements

# Nettoyage
php bin/console app:cleanup-expired-tokens   # Nettoyer tokens JWT expirés
php bin/console app:cleanup-remember-me-tokens # Nettoyer remember me expirés

# Maintenance
php bin/console cache:clear                  # Vider le cache
php bin/console doctrine:schema:validate     # Valider schéma DB
```

## 🧪 Tests

### Installation des Tests

```bash
# Installer PHPUnit
composer require --dev symfony/test-pack

# Base de données de test
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:migrations:migrate
```

### Exécution des Tests

```bash
# Tous les tests
php bin/phpunit

# Tests unitaires
php bin/phpunit tests/Unit

# Tests d'intégration
php bin/phpunit tests/Integration

# Tests API
php bin/phpunit tests/Api

# Couverture de code
php bin/phpunit --coverage-html coverage
```

### Structure des Tests

```
tests/
├── Unit/           # Tests unitaires des services
├── Integration/    # Tests d'intégration base de données
├── Api/           # Tests des endpoints API
└── Fixtures/      # Données de test
```

## 🚀 Déploiement

### Production

```bash
# Optimisation Composer
composer install --no-dev --optimize-autoloader

# Cache de production
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Assets
php bin/console assets:install --symlink

# Permissions
chmod -R 755 var/cache var/log public/uploads
```

### Variables d'Environnement Production

```bash
APP_ENV=prod
APP_DEBUG=false
DATABASE_URL="postgresql://prod_user:prod_pass@db_host:5432/club_sportif_prod"
REDIS_URL=redis://redis_host:6379/0
MAILER_DSN=smtp://smtp_host:587
```

### Docker Production

```dockerfile
FROM php:8.2-fpm

# Installation des extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo_pgsql zip

# Installation Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copie du code
WORKDIR /var/www/html
COPY . .

# Installation des dépendances
RUN composer install --no-dev --optimize-autoloader

# Permissions
RUN chown -R www-data:www-data var public/uploads

EXPOSE 9000
CMD ["php-fpm"]
```

## 📊 Monitoring

### Logs
```bash
# Logs Symfony
tail -f var/log/prod.log

# Logs d'authentification
tail -f var/log/security.log

# Logs de paiements
tail -f var/log/payment.log
```

### Métriques
- Connexions par provider
- Demandes d'adhésion par période
- Transactions de cagnotte
- Upload de documents
- Performance des endpoints

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/amazing-feature`)
3. Commit les changements (`git commit -m 'Add amazing feature'`)
4. Push la branche (`git push origin feature/amazing-feature`)
5. Ouvrir une Pull Request

## 📝 License

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

---

**Version :** 0.0.1
**Symfony :** 7.2  
**PHP :** 8.2+  
**Dernière mise à jour :** 2025