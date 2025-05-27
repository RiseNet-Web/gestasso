#!/bin/bash

# Script de démarrage rapide pour l'infrastructure GestAsso
# Usage: ./start.sh

set -e

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction pour afficher les messages
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

echo -e "${BLUE}🚀 Démarrage de l'infrastructure GestAsso${NC}"
echo ""

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "docker-compose.yml" ]; then
    log_error "Ce script doit être exécuté depuis le répertoire infrastructure/"
    exit 1
fi

# Vérifier si Docker est en cours d'exécution
if ! docker info > /dev/null 2>&1; then
    log_error "Docker n'est pas en cours d'exécution ou n'est pas accessible"
    exit 1
fi

# Vérifier si le fichier .env existe
if [ ! -f ".env" ]; then
    log_warning "Fichier .env manquant, création depuis env.example..."
    cp env.example .env
    log_success "Fichier .env créé"
fi

# Arrêter les conteneurs existants
log_info "Arrêt des conteneurs existants..."
docker-compose down

# Construire les images
log_info "Construction des images Docker..."
docker-compose build

# Démarrer les services
log_info "Démarrage des services..."
docker-compose up -d

# Attendre que les services soient prêts
log_info "Attente que les services soient prêts..."
sleep 15

# Vérifier l'état des conteneurs
log_info "Vérification de l'état des conteneurs..."
docker-compose ps

# Afficher les informations utiles
echo ""
log_success "Infrastructure démarrée avec succès !"
echo ""
echo -e "${YELLOW}📋 Informations utiles :${NC}"
echo "  🌐 API Backend : http://localhost:8080/api"
echo "  🎨 Frontend : http://localhost:5173"
echo "  🗄️  Base de données : localhost:5432"
echo "  📧 MailHog : http://localhost:8025"
echo ""
echo -e "${YELLOW}🛠️  Commandes utiles :${NC}"
echo "  📦 Installer les dépendances : docker-compose exec php composer install"
echo "  🗃️  Créer la base de données : docker-compose exec php php bin/console doctrine:database:create"
echo "  📝 Créer une migration : docker-compose exec php php bin/console make:migration"
echo "  🔄 Exécuter les migrations : docker-compose exec php php bin/console doctrine:migrations:migrate"
echo "  🔧 Corriger les permissions : ./fix-permissions.sh"
echo ""
echo -e "${GREEN}✅ Prêt à développer !${NC}" 