#!/bin/bash

# Script pour corriger les permissions dans l'environnement Docker GestAsso
# Usage: ./fix-permissions.sh [--help]

set -e

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction d'aide
show_help() {
    echo -e "${BLUE}Script de correction des permissions pour GestAsso${NC}"
    echo ""
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  --help, -h     Afficher cette aide"
    echo "  --backend      Corriger uniquement les permissions du backend"
    echo "  --frontend     Corriger uniquement les permissions du frontend"
    echo "  --all          Corriger toutes les permissions (défaut)"
    echo ""
    echo "Ce script corrige les permissions pour:"
    echo "  - Les fichiers et dossiers Symfony (backend)"
    echo "  - Les fichiers et dossiers SvelteKit (frontend)"
    echo "  - Les volumes Docker"
    echo ""
}

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

# Vérifier si Docker est en cours d'exécution
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        log_error "Docker n'est pas en cours d'exécution ou n'est pas accessible"
        exit 1
    fi
}

# Corriger les permissions du backend
fix_backend_permissions() {
    log_info "Correction des permissions du backend Symfony..."
    
    # Vérifier si le conteneur PHP existe
    if ! docker-compose ps php > /dev/null 2>&1; then
        log_warning "Le conteneur PHP n'est pas en cours d'exécution"
        return 1
    fi
    
    # Exécuter les corrections dans le conteneur PHP
    docker-compose exec php bash -c "
        echo '🔧 Correction des permissions Symfony...'
        
        # Créer les répertoires s'ils n'existent pas
        mkdir -p var/log var/cache public/uploads config/jwt migrations
        
        # Corriger les permissions des répertoires
        chown -R www-data:www-data /var/www/symfony
        chmod -R 755 /var/www/symfony
        
        # Permissions spéciales
        chmod -R 777 var/
        chmod -R 775 migrations/
        chmod -R 755 public/
        chmod -R 755 config/
        
        # Permissions pour les fichiers de migration
        find migrations/ -type f -name '*.php' -exec chmod 664 {} \; 2>/dev/null || true
        find migrations/ -type f -name '*.php' -exec chown www-data:www-data {} \; 2>/dev/null || true
        
        # Permissions pour bin/console
        if [ -f bin/console ]; then
            chmod +x bin/console
            chown www-data:www-data bin/console
        fi
        
        echo '✅ Permissions Symfony corrigées'
    "
    
    log_success "Permissions du backend corrigées"
}

# Corriger les permissions du frontend
fix_frontend_permissions() {
    log_info "Correction des permissions du frontend SvelteKit..."
    
    # Vérifier si le répertoire frontend existe
    if [ ! -d "../frontend" ]; then
        log_warning "Le répertoire frontend n'existe pas"
        return 1
    fi
    
    # Corriger les permissions du frontend sur l'hôte
    if [ -d "../frontend" ]; then
        log_info "Correction des permissions du répertoire frontend..."
        
        # Permissions pour le répertoire frontend
        find ../frontend -type d -exec chmod 755 {} \; 2>/dev/null || true
        find ../frontend -type f -exec chmod 644 {} \; 2>/dev/null || true
        
        # Permissions spéciales pour les scripts
        if [ -f "../frontend/package.json" ]; then
            chmod 644 ../frontend/package.json
        fi
        
        # Permissions pour node_modules si il existe
        if [ -d "../frontend/node_modules" ]; then
            chmod -R 755 ../frontend/node_modules 2>/dev/null || true
        fi
        
        log_success "Permissions du frontend corrigées"
    fi
}

# Corriger toutes les permissions
fix_all_permissions() {
    log_info "Correction de toutes les permissions..."
    
    fix_backend_permissions
    fix_frontend_permissions
    
    # Corriger les permissions des volumes Docker
    log_info "Correction des permissions des volumes Docker..."
    
    # Redémarrer les conteneurs pour appliquer les changements
    log_info "Redémarrage des conteneurs pour appliquer les changements..."
    docker-compose restart php nginx
    
    log_success "Toutes les permissions ont été corrigées"
}

# Fonction principale
main() {
    # Vérifier les arguments
    case "${1:-}" in
        --help|-h)
            show_help
            exit 0
            ;;
        --backend)
            check_docker
            fix_backend_permissions
            ;;
        --frontend)
            fix_frontend_permissions
            ;;
        --all|"")
            check_docker
            fix_all_permissions
            ;;
        *)
            log_error "Option inconnue: $1"
            show_help
            exit 1
            ;;
    esac
}

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "docker-compose.yml" ]; then
    log_error "Ce script doit être exécuté depuis le répertoire infrastructure/"
    exit 1
fi

# Exécuter la fonction principale
main "$@" 