FROM node:20-alpine

# Installation des dépendances système
RUN apk add --no-cache curl

# Définition du répertoire de travail
WORKDIR /app

# Copie des fichiers de configuration npm
COPY package*.json ./

# Installation des dépendances
RUN npm ci

# Copie du code source
COPY . .

# Exposition du port
EXPOSE 3000

# Variables d'environnement par défaut
ENV NODE_ENV=development
ENV VITE_API_URL=http://localhost:8000/api

# Commande de démarrage
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "3000"] 