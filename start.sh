#!/bin/bash

echo "🚀 Démarrage de GestAsso..."

# Vérifier si Docker est installé
if ! command -v docker &> /dev/null; then
    echo "❌ Docker n'est pas installé. Veuillez l'installer avant de continuer."
    exit 1
fi

# Vérifier si Docker Compose est installé
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose n'est pas installé. Veuillez l'installer avant de continuer."
    exit 1
fi

# Créer le fichier .env s'il n'existe pas
if [ ! -f .env ]; then
    echo "📝 Création du fichier .env..."
    cp env.example .env
    echo "✅ Fichier .env créé. Vous pouvez le modifier selon vos besoins."
fi

# Arrêter les conteneurs existants
echo "🛑 Arrêt des conteneurs existants..."
docker-compose down

# Construire et démarrer les conteneurs
echo "🔨 Construction et démarrage des conteneurs..."
docker-compose up --build -d

# Attendre que les services soient prêts
echo "⏳ Attente du démarrage des services..."
sleep 10

# Vérifier l'état des conteneurs
echo "📊 État des conteneurs :"
docker-compose ps

echo ""
echo "🎉 GestAsso est maintenant en cours d'exécution !"
echo ""
echo "📍 URLs d'accès :"
echo "   - Frontend SvelteKit : http://localhost:3000"
echo "   - API Symfony : http://localhost:8000/api"
echo "   - Application complète (via Nginx) : http://localhost"
echo "   - Base de données PostgreSQL : localhost:5432"
echo "   - Redis : localhost:6379"
echo ""
echo "🔧 Commandes utiles :"
echo "   - Voir les logs : docker-compose logs -f"
echo "   - Arrêter l'application : docker-compose down"
echo "   - Redémarrer l'application : docker-compose restart"
echo "   - Accéder au conteneur Symfony : docker-compose exec symfony bash"
echo "   - Accéder au conteneur SvelteKit : docker-compose exec sveltekit sh" 