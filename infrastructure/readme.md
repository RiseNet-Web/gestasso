# Infrastructure GestAsso - Symfony 7.2

## 🏗️ Architecture Technique

Cette infrastructure fournit une base solide pour l'API GestAsso basée sur **Symfony 7.2** avec toutes les technologies modernes requises.

### 🛠️ Stack Technique

| Composant | Technologie | Version | Description |
|-----------|-------------|---------|-------------|
| **Framework** | Symfony | 7.2 | Framework PHP moderne avec composants découplés |
| **API** | API Platform | 3.2+ | Génération automatique d'API REST/GraphQL |
| **Base de données** | PostgreSQL | 16 | Base de données relationnelle performante |
| **ORM** | Doctrine | 3.0+ | Mapping objet-relationnel avec migrations |
| **Authentification** | JWT | LexikJWTAuthenticationBundle | Tokens JWT avec refresh tokens |
| **Cache** | Redis | 7 | Cache en mémoire pour sessions et données |
| **Upload** | Symfony Upload | - | Gestion sécurisée des fichiers |
| **Sécurité** | Symfony Security | Voter Pattern | Système de rôles et permissions |
| **Serveur Web** | Nginx | Alpine | Serveur web haute performance |
| **Conteneurisation** | Docker | Compose | Orchestration des services |

## 📋 Services Inclus

### Services Principaux
- **nginx** : Serveur web (port 8080)
- **php** : PHP 8.3-FPM avec Symfony 7.2
- **postgres** : Base de données PostgreSQL 16
- **redis** : Cache et sessions Redis 7

### Services de Développement
- **pgadmin** : Interface de gestion PostgreSQL (port 8081)
- **mailhog** : Serveur de test d'emails (port 8025)
- **elasticsearch** : Moteur de recherche (port 9200)
- **kibana** : Interface Elasticsearch (port 5601)
- **worker** : Worker pour tâches asynchrones

## 🚀 Installation Rapide

### Prérequis
- Docker et Docker Compose installés
- Make (optionnel mais recommandé)
- Git

### Installation en une commande
```bash
# Cloner le repository principal
git clone https://github.com/RiseNet-Web/GestAsso.git
cd GestAsso/infrastructure

# Installation complète
make install
```

### Installation manuelle
```bash
# 1. Construction des images
docker-compose build

# 2. Démarrage des services
docker-compose up -d

# 3. Initialisation de Symfony
docker-compose exec php symfony new . --version="7.2.*" --webapp --no-git

# 4. Installation des bundles
docker-compose exec php composer require api-platform/api-platform
docker-compose exec php composer require lexik/jwt-authentication-bundle
# ... autres dépendances
```

## 🌐 Accès aux Services

| Service | URL | Identifiants |
|---------|-----|--------------|
| **API Symfony** | http://localhost:8080/api | - |
| **API Documentation** | http://localhost:8080/api/docs | - |
| **Symfony Profiler** | http://localhost:8080/_profiler | - |
| **pgAdmin** | http://localhost:8081 | admin@gestasso.fr / admin_password |
| **MailHog** | http://localhost:8025 | - |
| **Elasticsearch** | http://localhost:9200 | - |
| **Kibana** | http://localhost:5601 | - |

## 📊 Configuration des Bundles

### API Platform
```yaml
# config/packages/api_platform.yaml
api_platform:
    title: 'GestAsso API'
    version: '1.0.0'
    description: 'API de gestion des associations sportives'
    formats:
        json: ['application/json']
        jsonld: ['application/ld+json']
    docs_formats:
        jsonld: ['application/ld+json']
        json: ['application/json']
        html: ['text/html']
    defaults:
        pagination_enabled: true
        pagination_items_per_page: 20
        pagination_maximum_items_per_page: 100
```

### JWT Authentication
```yaml
# config/packages/lexik_jwt_authentication.yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: 3600
    refresh_token_ttl: 604800
```

### Doctrine ORM
```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        charset: utf8
        default_table_options:
            charset: utf8mb4
            collate: utf8mb4_unicode_ci
    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App
```

### Redis Configuration
```yaml
# config/packages/redis.yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: 'redis://redis:6379'
    session:
        handler_id: 'redis://redis:6379'
        cookie_secure: auto
        cookie_samesite: lax
```

## 🔐 Sécurité et Authentification

### Configuration Security
```yaml
# config/packages/security.yaml
security:
    password_hashers:
        App\Entity\User:
            algorithm: auto
    
    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email
    
    firewalls:
        api_login:
            pattern: ^/api/auth/login
            stateless: true
            json_login:
                check_path: /api/auth/login
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure
        
        api:
            pattern: ^/api
            stateless: true
            jwt: ~
        
        main:
            lazy: true
            provider: app_user_provider
    
    access_control:
        - { path: ^/api/auth/login, roles: PUBLIC_ACCESS }
        - { path: ^/api/docs, roles: PUBLIC_ACCESS }
        - { path: ^/api, roles: IS_AUTHENTICATED_FULLY }
```

### Voter Pattern pour les Permissions
```php
// src/Security/Voter/ClubVoter.php
class ClubVoter extends Voter
{
    const VIEW = 'view';
    const EDIT = 'edit';
    const DELETE = 'delete';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Club;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        if (!$user instanceof User) {
            return false;
        }

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($subject, $user);
            case self::EDIT:
                return $this->canEdit($subject, $user);
            case self::DELETE:
                return $this->canDelete($subject, $user);
        }

        return false;
    }
}
```

## 📁 Structure du Projet Symfony

```
symfony/
├── config/
│   ├── packages/           # Configuration des bundles
│   ├── routes/            # Configuration des routes
│   └── jwt/               # Clés JWT
├── src/
│   ├── Controller/        # Contrôleurs API
│   ├── Entity/           # Entités Doctrine
│   ├── Repository/       # Repositories
│   ├── Security/         # Voters et authentification
│   ├── Service/          # Services métier
│   └── EventListener/    # Event listeners
├── migrations/           # Migrations Doctrine
├── tests/               # Tests PHPUnit
├── var/
│   ├── cache/           # Cache Symfony
│   └── log/             # Logs
└── public/
    ├── index.php        # Point d'entrée
    └── uploads/         # Fichiers uploadés
```

## 🗄️ Gestion de la Base de Données

### Commandes Doctrine Utiles
```bash
# Créer la base de données
make db-create

# Créer une migration
make make-migration

# Exécuter les migrations
make db-migrate

# Créer une entité
make make-entity

# Charger les fixtures
make db-fixtures

# Reset complet de la base
make db-reset
```

### Exemple d'Entité
```php
// src/Entity/User.php
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('DELETE', object)")
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read', 'user:write'])]
    #[Assert\Email]
    #[Assert\NotBlank]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private array $roles = [];

    #[ORM\Column]
    #[Groups(['user:write'])]
    private ?string $password = null;
    
    // ... getters et setters
}
```

## 🔧 Commandes Make Disponibles

### Installation et Configuration
```bash
make help           # Affiche toutes les commandes disponibles
make install        # Installation complète
make setup          # Configuration initiale
make init-symfony   # Initialise Symfony 7.2
```

### Gestion des Services
```bash
make start          # Démarre tous les services
make stop           # Arrête tous les services
make restart        # Redémarre tous les services
make build          # Reconstruit les images Docker
make logs           # Affiche les logs en temps réel
make status         # Statut des conteneurs
```

### Développement Symfony
```bash
make cache-clear    # Vide le cache Symfony
make jwt-keys       # Génère les clés JWT
make make-entity    # Crée une nouvelle entité
make make-controller # Crée un nouveau contrôleur
make make-migration # Crée une nouvelle migration
```

### Base de Données
```bash
make db-create      # Crée la base de données
make db-migrate     # Exécute les migrations
make db-fixtures    # Charge les fixtures
make db-reset       # Recrée la base complètement
make db-backup      # Sauvegarde la base
```

### Tests et Qualité
```bash
make test           # Lance tous les tests
make phpunit        # Lance PHPUnit
make phpstan        # Analyse statique
make cs-fix         # Corrige le style de code
```

### Accès aux Conteneurs
```bash
make shell          # Accès au conteneur PHP
make psql           # Accès à PostgreSQL
make redis-cli      # Accès à Redis CLI
```

## 🧪 Tests et Qualité de Code

### Configuration PHPUnit
```xml
<!-- phpunit.xml.dist -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    <php>
        <ini name="display_errors" value="1" />
        <ini name="error_reporting" value="-1" />
        <server name="APP_ENV" value="test" force="true" />
        <server name="SHELL_VERBOSITY" value="-1" />
    </php>

    <testsuites>
        <testsuite name="Project Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>

    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
```

### Exemple de Test API
```php
// tests/Api/UserTest.php
class UserTest extends ApiTestCase
{
    public function testGetUsers(): void
    {
        $client = self::createClient();
        
        $client->request('GET', '/api/users', [
            'headers' => ['Authorization' => 'Bearer ' . $this->getToken()]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains(['@context' => '/api/contexts/User']);
    }
}
```

## 📈 Performance et Monitoring

### Configuration OPcache
```ini
; docker/php/php.ini
opcache.enable=1
opcache.memory_consumption=512
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
```

### Monitoring avec Elasticsearch
```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        elasticsearch:
            type: elasticsearch
            hosts: ['elasticsearch:9200']
            index: gestasso-logs
            level: info
```

## 🔄 Déploiement

### Variables d'Environnement
```bash
# .env.prod
APP_ENV=prod
APP_DEBUG=false
DATABASE_URL=postgresql://user:pass@postgres:5432/gestasso_prod
REDIS_URL=redis://redis:6379
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
```

### Commandes de Déploiement
```bash
# Production
docker-compose -f docker-compose.prod.yml up -d
make composer-install
make cache-clear
make db-migrate
```

## 🛡️ Sécurité

### Bonnes Pratiques Implémentées
- ✅ **JWT avec refresh tokens** pour l'authentification
- ✅ **Voter pattern** pour les autorisations granulaires
- ✅ **Validation des données** avec Symfony Validator
- ✅ **CORS configuré** pour les appels cross-origin
- ✅ **Rate limiting** sur les endpoints sensibles
- ✅ **Headers de sécurité** configurés dans Nginx
- ✅ **Upload sécurisé** avec validation des types MIME
- ✅ **Chiffrement des mots de passe** avec bcrypt
- ✅ **Sessions sécurisées** avec Redis

## 📞 Support et Contribution

### Logs et Debugging
```bash
# Voir les logs Symfony
make logs

# Accéder au profiler
http://localhost:8080/_profiler

# Logs PHP
docker-compose exec php tail -f /var/www/symfony/var/log/dev.log
```

### Contribution
1. Fork le projet
2. Créer une branche feature
3. Développer avec les tests
4. Soumettre une Pull Request

## 🗺️ Roadmap

- [ ] **GraphQL** avec API Platform
- [ ] **WebSockets** pour le temps réel
- [ ] **Microservices** avec Symfony Messenger
- [ ] **CI/CD** avec GitHub Actions
- [ ] **Monitoring** avec Prometheus
- [ ] **Documentation** automatique avec OpenAPI
- [ ] **Tests E2E** avec Panther

---

**Infrastructure GestAsso** - Symfony 7.2 | Développé avec ❤️ pour les associations sportives