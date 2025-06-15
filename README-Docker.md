# Configuration Docker pour GestAsso

Cette configuration Docker permet de faire fonctionner votre application GestAsso avec :
- **Backend Symfony** avec PHP 8.2, PostgreSQL et Redis
- **Frontend SvelteKit** 
- **Communication** entre les deux applications

## 🏗️ Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   SvelteKit     │    │    Symfony      │    │   PostgreSQL    │
│   (Frontend)    │◄──►│   (API Backend) │◄──►│   (Database)    │
│   Port: 3000    │    │   Port: 8000    │    │   Port: 5432    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌─────────────────┐
                    │      Redis      │
                    │     (Cache)     │
                    │   Port: 6379    │
                    └─────────────────┘
```

## 🚀 Démarrage rapide

### Prérequis
- Docker
- Docker Compose

### Installation

1. **Cloner le projet** (si ce n'est pas déjà fait)
```bash
git clone <votre-repo>
cd GestAsso
```

2. **Démarrer l'application**
```bash
# Méthode 1 : Utiliser le script de démarrage
chmod +x start.sh
./start.sh

# Méthode 2 : Commandes manuelles
cp env.example .env
docker-compose up --build -d
```

3. **Accéder à l'application**
- Frontend : http://localhost:3000
- API : http://localhost:8000/api
- Application complète (via Nginx) : http://localhost

## 📋 Services disponibles

| Service | Port | Description |
|---------|------|-------------|
| SvelteKit | 3000 | Frontend de l'application |
| Symfony | 8000 | API Backend |
| PostgreSQL | 5432 | Base de données |
| Redis | 6379 | Cache et sessions |
| Nginx | 80 | Reverse proxy (optionnel) |

## 🔧 Commandes utiles

### Gestion des conteneurs
```bash
# Démarrer tous les services
docker-compose up -d

# Arrêter tous les services
docker-compose down

# Redémarrer un service spécifique
docker-compose restart symfony

# Voir les logs
docker-compose logs -f
docker-compose logs -f symfony  # Logs d'un service spécifique
```

### Accès aux conteneurs
```bash
# Accéder au conteneur Symfony
docker-compose exec symfony bash

# Accéder au conteneur SvelteKit
docker-compose exec sveltekit sh

# Accéder à PostgreSQL
docker-compose exec postgres psql -U gestasso -d gestasso
```

### Commandes Symfony dans le conteneur
```bash
# Entrer dans le conteneur Symfony
docker-compose exec symfony bash

# Puis exécuter les commandes Symfony
php bin/console cache:clear
php bin/console doctrine:migrations:migrate
php bin/console make:entity
composer install
```

### Commandes SvelteKit dans le conteneur
```bash
# Entrer dans le conteneur SvelteKit
docker-compose exec sveltekit sh

# Puis exécuter les commandes npm
npm install
npm run build
npm run dev
```

## ⚙️ Configuration

### Variables d'environnement

Le fichier `.env` contient toutes les variables de configuration :

```env
# Base de données
POSTGRES_DB=gestasso
POSTGRES_USER=gestasso
POSTGRES_PASSWORD=gestasso_password

# Symfony
APP_ENV=dev
DATABASE_URL=postgresql://gestasso:gestasso_password@postgres:5432/gestasso
REDIS_URL=redis://redis:6379

# Frontend
VITE_API_URL=http://localhost:8000/api
```

### Communication Frontend ↔ Backend

Le frontend SvelteKit communique avec l'API Symfony via :
- **URL de l'API** : `http://localhost:8000/api`
- **CORS** configuré pour permettre les appels depuis `http://localhost:3000`
- **Headers** automatiquement gérés pour l'authentification JWT

## 🔍 Dépannage

### Problèmes courants

**Les conteneurs ne démarrent pas**
```bash
# Vérifier les logs
docker-compose logs

# Reconstruire les images
docker-compose build --no-cache
docker-compose up -d
```

**Erreur de connexion à la base de données**
```bash
# Vérifier que PostgreSQL est démarré
docker-compose ps postgres

# Vérifier les logs PostgreSQL
docker-compose logs postgres
```

**Le frontend ne peut pas contacter l'API**
- Vérifier que `VITE_API_URL` est correctement configuré
- Vérifier que le service Symfony est accessible sur le port 8000
- Vérifier la configuration CORS dans Symfony

**Problèmes de permissions**
```bash
# Corriger les permissions dans le conteneur Symfony
docker-compose exec symfony chown -R www-data:www-data /var/www/html
docker-compose exec symfony chmod -R 775 /var/www/html/var
```

### Commandes de diagnostic
```bash
# Vérifier l'état des conteneurs
docker-compose ps

# Vérifier les ressources utilisées
docker stats

# Tester la connectivité réseau
docker-compose exec sveltekit wget -qO- http://symfony:8000/api/health
```

## 🏭 Production

Pour la production, vous devriez :

1. **Modifier les variables d'environnement**
   - Changer les mots de passe
   - Utiliser `APP_ENV=prod`
   - Configurer des secrets sécurisés

2. **Utiliser des volumes persistants**
   - Pour les uploads de fichiers
   - Pour les logs

3. **Configurer HTTPS**
   - Utiliser un reverse proxy (Traefik, Nginx)
   - Certificats SSL

4. **Optimiser les images Docker**
   - Images multi-stage
   - Réduire la taille des images

## 📚 Structure des fichiers Docker

```
├── docker-compose.yml              # Configuration principale
├── backend/
│   ├── Dockerfile                  # Image Symfony
│   └── docker/
│       ├── php/php.ini            # Configuration PHP
│       ├── nginx/default.conf     # Configuration Nginx
│       ├── supervisor/supervisord.conf
│       └── start.sh               # Script de démarrage
├── frontend/
│   └── Dockerfile                 # Image SvelteKit
├── infrastructure/
│   ├── nginx/nginx.conf           # Reverse proxy
│   └── postgres/init.sql          # Initialisation DB
└── start.sh                       # Script de démarrage global
``` 