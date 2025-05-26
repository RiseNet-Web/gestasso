# Makefile pour l'Infrastructure GestAsso - Symfony 7.2
.PHONY: help install start stop restart build logs clean test

# Variables
DOCKER_COMPOSE = docker-compose
PHP_CONTAINER = $(shell grep PHP_CONTAINER_NAME .env 2>/dev/null | cut -d '=' -f2 || echo "gestasso_infra_php")
POSTGRES_CONTAINER = $(shell grep POSTGRES_CONTAINER_NAME .env 2>/dev/null | cut -d '=' -f2 || echo "gestasso_infra_postgres")
REDIS_CONTAINER = $(shell grep REDIS_CONTAINER_NAME .env 2>/dev/null | cut -d '=' -f2 || echo "gestasso_infra_redis")

# Couleurs pour l'affichage
GREEN = \033[0;32m
YELLOW = \033[1;33m
RED = \033[0;31m
BLUE = \033[0;34m
NC = \033[0m # No Color

## Affiche l'aide
help:
	@echo "$(GREEN)GestAsso Infrastructure - Symfony 7.2$(NC)"
	@echo ""
	@echo "$(YELLOW)Installation et configuration:$(NC)"
	@echo "  install     - Installation complète de l'infrastructure"
	@echo "  setup       - Configuration initiale"
	@echo "  init-symfony - Initialise le projet Symfony"
	@echo ""
	@echo "$(YELLOW)Gestion des conteneurs:$(NC)"
	@echo "  start       - Démarre tous les services"
	@echo "  stop        - Arrête tous les services"
	@echo "  restart     - Redémarre tous les services"
	@echo "  build       - Reconstruit les images Docker"
	@echo "  logs        - Affiche les logs en temps réel"
	@echo "  status      - Affiche le statut des conteneurs"
	@echo ""
	@echo "$(YELLOW)Base de données:$(NC)"
	@echo "  db-create   - Crée la base de données"
	@echo "  db-migrate  - Exécute les migrations"
	@echo "  db-fixtures - Charge les fixtures"
	@echo "  db-reset    - Recrée la base de données"
	@echo "  db-backup   - Sauvegarde la base de données"
	@echo ""
	@echo "$(YELLOW)Développement Symfony:$(NC)"
	@echo "  cache-clear - Vide le cache Symfony"
	@echo "  jwt-keys    - Génère les clés JWT"
	@echo "  make-entity - Crée une nouvelle entité"
	@echo "  make-controller - Crée un nouveau contrôleur"
	@echo "  make-migration - Crée une nouvelle migration"
	@echo ""
	@echo "$(YELLOW)Frontend:$(NC)"
	@echo "  frontend-install - Installe les dépendances frontend"
	@echo "  frontend-build - Build le frontend pour production"
	@echo "  frontend-dev - Démarre le serveur de développement frontend"
	@echo ""
	@echo "$(YELLOW)Tests et qualité:$(NC)"
	@echo "  test        - Lance tous les tests"
	@echo "  phpunit     - Lance PHPUnit"
	@echo "  phpstan     - Analyse statique du code"
	@echo "  cs-fix      - Corrige le style de code"
	@echo ""
	@echo "$(YELLOW)Maintenance:$(NC)"
	@echo "  clean       - Nettoie les fichiers temporaires"
	@echo "  permissions - Corrige les permissions"
	@echo "  shell       - Accès au conteneur PHP"
	@echo "  env-show    - Affiche les variables d'environnement"

## Installation complète
install: env-check build start init-symfony
	@echo "$(GREEN)✅ Infrastructure installée!$(NC)"
	@echo "$(YELLOW)Accès:$(NC)"
	@echo "  - Frontend: http://localhost:$(shell grep NGINX_PORT .env 2>/dev/null | cut -d '=' -f2 || echo "8080")"
	@echo "  - API Symfony: http://localhost:$(shell grep NGINX_PORT .env 2>/dev/null | cut -d '=' -f2 || echo "8080")/api"
	@echo "  - API Docs: http://localhost:$(shell grep NGINX_PORT .env 2>/dev/null | cut -d '=' -f2 || echo "8080")/api/docs"
	@echo "  - Profiler: http://localhost:$(shell grep NGINX_PORT .env 2>/dev/null | cut -d '=' -f2 || echo "8080")/_profiler"
	@echo "  - pgAdmin: http://localhost:$(shell grep PGADMIN_PORT .env 2>/dev/null | cut -d '=' -f2 || echo "8081")"
	@echo "  - MailHog: http://localhost:$(shell grep MAILHOG_WEB_PORT .env 2>/dev/null | cut -d '=' -f2 || echo "8025")"
	@echo "  - Frontend Dev: http://localhost:$(shell grep FRONTEND_DEV_PORT .env 2>/dev/null | cut -d '=' -f2 || echo "3000")"

## Vérification du fichier .env
env-check:
	@if [ ! -f .env ]; then \
		echo "$(YELLOW)📝 Création du fichier .env depuis env.example...$(NC)"; \
		cp env.example .env; \
		echo "$(GREEN)✅ Fichier .env créé. Vous pouvez le modifier selon vos besoins.$(NC)"; \
	fi

## Configuration initiale
setup: env-check
	@echo "$(YELLOW)🔧 Configuration initiale...$(NC)"
	@mkdir -p $(shell grep BACKEND_PATH .env 2>/dev/null | cut -d '=' -f2 || echo "../backend")/var/log
	@mkdir -p $(shell grep BACKEND_PATH .env 2>/dev/null | cut -d '=' -f2 || echo "../backend")/var/cache
	@mkdir -p $(shell grep BACKEND_PATH .env 2>/dev/null | cut -d '=' -f2 || echo "../backend")/public/uploads
	@mkdir -p $(shell grep BACKEND_PATH .env 2>/dev/null | cut -d '=' -f2 || echo "../backend")/config/jwt
	@mkdir -p $(shell grep FRONTEND_PATH .env 2>/dev/null | cut -d '=' -f2 || echo "../frontend")/dist
	@mkdir -p $(shell grep FRONTEND_PATH .env 2>/dev/null | cut -d '=' -f2 || echo "../frontend")/src
	@chmod -R 755 $(shell grep BACKEND_PATH .env 2>/dev/null | cut -d '=' -f2 || echo "../backend")/
	@chmod -R 755 $(shell grep FRONTEND_PATH .env 2>/dev/null | cut -d '=' -f2 || echo "../frontend")/
	@echo "$(GREEN)✅ Configuration terminée$(NC)"

## Initialise Symfony
init-symfony:
	@echo "$(YELLOW)🚀 Initialisation de Symfony 7.2...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bash -c "if [ ! -f composer.json ]; then symfony new . --version='7.2.*' --webapp --no-git; fi"
	@echo "$(GREEN)✅ Symfony initialisé$(NC)"

## Démarre les services
start:
	@echo "$(YELLOW)🚀 Démarrage des services...$(NC)"
	@$(DOCKER_COMPOSE) up -d
	@echo "$(GREEN)✅ Services démarrés$(NC)"

## Arrête les services
stop:
	@echo "$(YELLOW)🛑 Arrêt des services...$(NC)"
	@$(DOCKER_COMPOSE) down
	@echo "$(GREEN)✅ Services arrêtés$(NC)"

## Redémarre les services
restart: stop start

## Reconstruit les images
build:
	@echo "$(YELLOW)🔨 Construction des images Docker...$(NC)"
	@$(DOCKER_COMPOSE) build --no-cache
	@echo "$(GREEN)✅ Images construites$(NC)"

## Affiche les logs
logs:
	@$(DOCKER_COMPOSE) logs -f

## Affiche le statut des conteneurs
status:
	@$(DOCKER_COMPOSE) ps

## Crée la base de données
db-create:
	@echo "$(YELLOW)🗄️ Création de la base de données...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console doctrine:database:create --if-not-exists
	@echo "$(GREEN)✅ Base de données créée$(NC)"

## Exécute les migrations
db-migrate:
	@echo "$(YELLOW)🔄 Exécution des migrations...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console doctrine:migrations:migrate --no-interaction
	@echo "$(GREEN)✅ Migrations exécutées$(NC)"

## Charge les fixtures
db-fixtures:
	@echo "$(YELLOW)🌱 Chargement des fixtures...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console doctrine:fixtures:load --no-interaction
	@echo "$(GREEN)✅ Fixtures chargées$(NC)"

## Recrée la base de données
db-reset:
	@echo "$(YELLOW)🗄️ Recréation de la base de données...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console doctrine:database:drop --force --if-exists
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console doctrine:database:create
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console doctrine:migrations:migrate --no-interaction
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console doctrine:fixtures:load --no-interaction
	@echo "$(GREEN)✅ Base de données recréée$(NC)"

## Sauvegarde la base de données
db-backup:
	@echo "$(YELLOW)💾 Sauvegarde de la base de données...$(NC)"
	@mkdir -p backups
	@$(DOCKER_COMPOSE) exec $(POSTGRES_CONTAINER) pg_dump -U gestasso_user gestasso_db > backups/gestasso_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "$(GREEN)✅ Sauvegarde créée dans backups/$(NC)"

## Vide le cache Symfony
cache-clear:
	@echo "$(YELLOW)🗑️ Vidage du cache Symfony...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console cache:clear
	@echo "$(GREEN)✅ Cache vidé$(NC)"

## Génère les clés JWT
jwt-keys:
	@echo "$(YELLOW)🔐 Génération des clés JWT...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console lexik:jwt:generate-keypair --skip-if-exists
	@echo "$(GREEN)✅ Clés JWT générées$(NC)"

## Crée une nouvelle entité
make-entity:
	@echo "$(YELLOW)📝 Création d'une nouvelle entité...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console make:entity

## Crée un nouveau contrôleur
make-controller:
	@echo "$(YELLOW)🎮 Création d'un nouveau contrôleur...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console make:controller

## Crée une nouvelle migration
make-migration:
	@echo "$(YELLOW)🔄 Création d'une nouvelle migration...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console make:migration

## Lance tous les tests
test:
	@echo "$(YELLOW)🧪 Lancement des tests...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/phpunit
	@echo "$(GREEN)✅ Tests terminés$(NC)"

## Lance PHPUnit
phpunit:
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/phpunit

## Analyse statique du code
phpstan:
	@echo "$(YELLOW)🔍 Analyse statique du code...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) vendor/bin/phpstan analyse src --level=8

## Corrige le style de code
cs-fix:
	@echo "$(YELLOW)🔧 Correction du style de code...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) vendor/bin/php-cs-fixer fix src
	@echo "$(GREEN)✅ Style de code corrigé$(NC)"

## Nettoie les fichiers temporaires
clean:
	@echo "$(YELLOW)🧹 Nettoyage...$(NC)"
	@$(DOCKER_COMPOSE) down --volumes --remove-orphans
	@docker system prune -f
	@rm -rf ../backend/var/cache/* ../backend/var/log/*
	@echo "$(GREEN)✅ Nettoyage terminé$(NC)"

## Corrige les permissions
permissions:
	@echo "$(YELLOW)🔐 Correction des permissions...$(NC)"
	@chmod -R 755 ../backend/var ../backend/public/uploads
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) chown -R www-data:www-data /var/www/symfony/var /var/www/symfony/public/uploads
	@echo "$(GREEN)✅ Permissions corrigées$(NC)"

## Accès au conteneur PHP
shell:
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bash

## Accès à PostgreSQL
psql:
	@$(DOCKER_COMPOSE) exec $(POSTGRES_CONTAINER) psql -U gestasso_user -d gestasso_db

## Accès à Redis CLI
redis-cli:
	@$(DOCKER_COMPOSE) exec $(REDIS_CONTAINER) redis-cli -a gestasso_redis_password

## Installation des dépendances Composer
composer-install:
	@echo "$(YELLOW)📦 Installation des dépendances Composer...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) composer install --optimize-autoloader
	@echo "$(GREEN)✅ Dépendances installées$(NC)"

## Mise à jour des dépendances
composer-update:
	@echo "$(YELLOW)🔄 Mise à jour des dépendances...$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) composer update
	@echo "$(GREEN)✅ Dépendances mises à jour$(NC)"

## Installe les dépendances frontend
frontend-install:
	@echo "$(YELLOW)📦 Installation des dépendances frontend...$(NC)"
	@$(DOCKER_COMPOSE) exec frontend npm install
	@echo "$(GREEN)✅ Dépendances frontend installées$(NC)"

## Build le frontend pour production
frontend-build:
	@echo "$(YELLOW)🔨 Build du frontend...$(NC)"
	@$(DOCKER_COMPOSE) exec frontend npm run build
	@echo "$(GREEN)✅ Frontend buildé$(NC)"

## Démarre le serveur de développement frontend
frontend-dev:
	@echo "$(YELLOW)🚀 Démarrage du serveur de développement frontend...$(NC)"
	@$(DOCKER_COMPOSE) exec frontend npm run dev

## Affiche les variables d'environnement
env-show:
	@echo "$(YELLOW)📋 Variables d'environnement actuelles:$(NC)"
	@if [ -f .env ]; then \
		echo "$(GREEN)Fichier .env trouvé:$(NC)"; \
		cat .env | grep -v '^#' | grep -v '^$$'; \
	else \
		echo "$(RED)❌ Fichier .env non trouvé. Utilisez 'make env-check' pour le créer.$(NC)"; \
	fi 