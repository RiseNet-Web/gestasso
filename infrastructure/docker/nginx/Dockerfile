FROM nginx:alpine

# Supprimer la configuration par défaut
RUN rm -f /etc/nginx/nginx.conf /etc/nginx/conf.d/default.conf

# Créer les répertoires nécessaires
RUN mkdir -p /var/log/nginx \
    && mkdir -p /etc/nginx/ssl \
    && mkdir -p /var/www/symfony/public \
    && mkdir -p /var/www/frontend/build

# Copier les fichiers de configuration un par un
COPY default.conf /etc/nginx/conf.d/default.conf
COPY nginx.conf /etc/nginx/nginx.conf

# Vérifier que les fichiers sont bien copiés
RUN ls -la /etc/nginx/ && ls -la /etc/nginx/conf.d/

# Permissions
RUN chown -R nginx:nginx /var/www \
    && chmod -R 755 /var/www

# Test de la configuration nginx
RUN nginx -t

# Exposer les ports
EXPOSE 80 443

# Commande par défaut
CMD ["nginx", "-g", "daemon off;"] 