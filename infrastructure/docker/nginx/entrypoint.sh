#!/bin/sh

echo "🚀 Démarrage de Nginx avec configuration personnalisée..."

# Créer les répertoires nécessaires
mkdir -p /var/log/nginx
mkdir -p /etc/nginx/ssl
mkdir -p /var/www/symfony/public
mkdir -p /var/www/frontend/build

# Copier les configurations depuis un volume temporaire si elles existent
if [ -f "/tmp/nginx-config/nginx.conf" ]; then
    echo "📄 Copie de nginx.conf..."
    cp /tmp/nginx-config/nginx.conf /etc/nginx/nginx.conf
fi

if [ -f "/tmp/nginx-config/default.conf" ]; then
    echo "📄 Copie de default.conf..."
    cp /tmp/nginx-config/default.conf /etc/nginx/conf.d/default.conf
fi

# Vérifier la configuration
echo "🔍 Vérification de la configuration Nginx..."
nginx -t

if [ $? -eq 0 ]; then
    echo "✅ Configuration Nginx valide"
else
    echo "❌ Erreur dans la configuration Nginx"
    exit 1
fi

# Démarrer Nginx
echo "🌐 Démarrage de Nginx..."
exec nginx -g "daemon off;" 