# Guide des Variables d'Environnement - Infrastructure GestAsso

## 📋 Vue d'ensemble

L'infrastructure GestAsso utilise un système de variables d'environnement centralisé pour une configuration flexible et sécurisée. Toutes les variables sont définies dans le fichier `.env` et utilisées par Docker Compose.

## 🚀 Configuration Rapide

### 1. Création du fichier .env
```bash
# Depuis le dossier infrastructure/
make env-check
```
Cette commande copie automatiquement `env.example` vers `.env` si le fichier n'existe pas.

### 2. Personnalisation
Éditez le fichier `.env` selon vos besoins :
```bash
# Exemple de personnalisation
NGINX_PORT=9000          # Changer le port Nginx
POSTGRES_PASSWORD=monmdp # Changer le mot de passe PostgreSQL
BACKEND_PATH=./mon-backend # Changer le chemin du backend
```

## 📊 Variables Disponibles

### 🔧 Configuration Générale
| Variable | Défaut | Description |
|----------|--------|-------------|
| `COMPOSE_PROJECT_NAME` | `gestasso` | Nom du projet Docker Compose |
| `APP_ENV` | `dev` | Environnement de l'application |
| `APP_DEBUG` | `true` | Mode debug |

### 🌐 Ports d'Exposition
| Variable | Défaut | Description |
|----------|--------|-------------|
| `NGINX_PORT` | `8080` | Port du serveur web principal |
| `NGINX_SSL_PORT` | `8443` | Port HTTPS |
| `FRONTEND_DEV_PORT` | `5173` | Port du serveur de développement SvelteKit |
| `FRONTEND_PREVIEW_PORT` | `4173` | Port du serveur de preview SvelteKit |
| `POSTGRES_PORT` | `5432` | Port PostgreSQL |
| `REDIS_PORT` | `6379` | Port Redis |
| `PGADMIN_PORT` | `8081` | Port pgAdmin |
| `MAILHOG_SMTP_PORT` | `1025` | Port SMTP MailHog |
| `MAILHOG_WEB_PORT` | `8025` | Port interface web MailHog |

### 🗄️ Base de Données
| Variable | Défaut | Description |
|----------|--------|-------------|
| `POSTGRES_DB` | `gestasso_db` | Nom de la base de données |
| `POSTGRES_USER` | `gestasso_user` | Utilisateur PostgreSQL |
| `POSTGRES_PASSWORD` | `gestasso_password` | Mot de passe PostgreSQL |
| `POSTGRES_ROOT_PASSWORD` | `root_password` | Mot de passe root |

### 🔐 Sécurité
| Variable | Défaut | Description |
|----------|--------|-------------|
| `REDIS_PASSWORD` | `gestasso_redis_password` | Mot de passe Redis |
| `JWT_PASSPHRASE` | `gestasso_jwt_passphrase` | Phrase secrète JWT |
| `PGADMIN_DEFAULT_EMAIL` | `admin@gestasso.fr` | Email pgAdmin |
| `PGADMIN_DEFAULT_PASSWORD` | `admin_password` | Mot de passe pgAdmin |

### 📁 Chemins des Volumes
| Variable | Défaut | Description |
|----------|--------|-------------|
| `BACKEND_PATH` | `../backend` | Chemin vers le dossier backend |
| `FRONTEND_PATH` | `../frontend` | Chemin vers le dossier frontend |

### 🐳 Noms des Conteneurs
| Variable | Défaut | Description |
|----------|--------|-------------|
| `NGINX_CONTAINER_NAME` | `gestasso_infra_nginx` | Nom du conteneur Nginx |
| `PHP_CONTAINER_NAME` | `gestasso_infra_php` | Nom du conteneur PHP |
| `POSTGRES_CONTAINER_NAME` | `gestasso_infra_postgres` | Nom du conteneur PostgreSQL |
| `REDIS_CONTAINER_NAME` | `gestasso_infra_redis` | Nom du conteneur Redis |
| `FRONTEND_CONTAINER_NAME` | `gestasso_infra_frontend` | Nom du conteneur Frontend |
| `WORKER_CONTAINER_NAME` | `gestasso_infra_worker` | Nom du conteneur Worker |

## 🛠️ Utilisation Avancée

### Environnements Multiples
Créez différents fichiers d'environnement :
```bash
# Développement
cp .env .env.dev

# Production
cp .env .env.prod
# Modifiez .env.prod avec les valeurs de production

# Utilisation
docker-compose --env-file .env.prod up -d
```

### Variables Dynamiques dans le Makefile
Le Makefile lit automatiquement les variables du fichier `.env` :
```bash
# Affiche les URLs avec les ports configurés
make install

# Affiche toutes les variables
make env-show
```

### Intégration avec le Backend Symfony
Les variables sont automatiquement transmises au conteneur PHP :
```yaml
# Dans docker-compose.yml
environment:
  - DATABASE_URL=postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@postgres:5432/${POSTGRES_DB}
```

### Intégration avec SvelteKit Frontend
Variables disponibles dans le conteneur SvelteKit :
```yaml
environment:
  - NODE_ENV=${NODE_ENV:-development}
  - VITE_API_URL=http://localhost:${NGINX_PORT:-8080}/api
```

## 🔒 Sécurité

### Bonnes Pratiques
1. **Ne jamais commiter le fichier `.env`** en production
2. **Utiliser des mots de passe forts** pour les services
3. **Changer les valeurs par défaut** en production
4. **Limiter les permissions** du fichier `.env`

### Fichier .env en Production
```bash
# Permissions restrictives
chmod 600 .env

# Propriétaire uniquement
chown root:root .env
```

## 📝 Exemples de Configuration

### Configuration Développement
```bash
# .env.dev
APP_ENV=dev
APP_DEBUG=true
NGINX_PORT=8080
POSTGRES_PASSWORD=dev_password
```

### Configuration Production
```bash
# .env.prod
APP_ENV=prod
APP_DEBUG=false
NGINX_PORT=80
NGINX_SSL_PORT=443
POSTGRES_PASSWORD=super_secure_password_123!
REDIS_PASSWORD=redis_secure_password_456!
JWT_PASSPHRASE=jwt_super_secret_789!
```

### Configuration Test
```bash
# .env.test
APP_ENV=test
NGINX_PORT=8090
POSTGRES_DB=gestasso_test
POSTGRES_PASSWORD=test_password
```

## 🚨 Dépannage

### Problème : Variables non prises en compte
```bash
# Vérifier le fichier .env
make env-show

# Reconstruire les conteneurs
make build
make restart
```

### Problème : Ports déjà utilisés
```bash
# Changer les ports dans .env
NGINX_PORT=9080
FRONTEND_DEV_PORT=5174
FRONTEND_PREVIEW_PORT=4174
PGADMIN_PORT=9081
```

### Problème : Chemins incorrects
```bash
# Vérifier les chemins dans .env
BACKEND_PATH=./mon-dossier-backend
FRONTEND_PATH=./mon-dossier-frontend
```

## 📚 Commandes Utiles

```bash
# Créer/vérifier le fichier .env
make env-check

# Afficher les variables actuelles
make env-show

# Installation avec variables personnalisées
make install

# Redémarrer avec nouvelles variables
make restart

# Nettoyer et reconstruire
make clean
make build
```

---

**Note** : Après modification du fichier `.env`, redémarrez les services avec `make restart` pour appliquer les changements. 