# Guide SvelteKit - Infrastructure GestAsso

## 🎯 Vue d'ensemble

L'infrastructure GestAsso est maintenant optimisée pour **SvelteKit**, le framework full-stack moderne de Svelte. Cette configuration fournit un environnement de développement complet avec hot-reload et intégration API.

## 🚀 Configuration SvelteKit

### Ports Configurés
- **Développement** : `5173` (port par défaut Vite/SvelteKit)
- **Preview** : `4173` (pour tester le build de production)
- **Production** : `8080` (via Nginx, même port que l'API)

### Variables d'Environnement SvelteKit
```bash
# Dans .env
FRONTEND_DEV_PORT=5173
FRONTEND_PREVIEW_PORT=4173
VITE_API_URL=http://localhost:8080/api
NODE_ENV=development
FRONTEND_BUILD_PATH=build
```

## 📁 Structure Attendue

```
frontend/
├── src/
│   ├── routes/
│   │   ├── +layout.svelte
│   │   ├── +page.svelte
│   │   └── api/
│   ├── lib/
│   │   ├── components/
│   │   └── stores/
│   └── app.html
├── static/
├── build/              # ← Dossier de build (servi par Nginx)
├── package.json
├── svelte.config.js
├── vite.config.js
└── .env.local
```

## ⚙️ Configuration Recommandée

### 1. svelte.config.js
```javascript
import adapter from '@sveltejs/adapter-static';
import { vitePreprocess } from '@sveltejs/kit/vite';

/** @type {import('@sveltejs/kit').Config} */
const config = {
  preprocess: vitePreprocess(),
  
  kit: {
    adapter: adapter({
      pages: 'build',
      assets: 'build',
      fallback: 'index.html',
      precompress: false,
      strict: true
    }),
    
    // Configuration pour l'API backend
    alias: {
      $api: 'src/lib/api'
    }
  }
};

export default config;
```

### 2. vite.config.js
```javascript
import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [sveltekit()],
  
  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://nginx:80',
        changeOrigin: true,
        secure: false
      }
    }
  },
  
  preview: {
    host: '0.0.0.0',
    port: 4173
  }
});
```

### 3. package.json
```json
{
  "name": "gestasso-frontend",
  "version": "1.0.0",
  "private": true,
  "scripts": {
    "build": "vite build",
    "dev": "vite dev",
    "preview": "vite preview",
    "check": "svelte-kit sync && svelte-check --tsconfig ./tsconfig.json",
    "check:watch": "svelte-kit sync && svelte-check --tsconfig ./tsconfig.json --watch",
    "lint": "prettier --plugin-search-dir . --check .",
    "format": "prettier --plugin-search-dir . --write ."
  },
  "devDependencies": {
    "@sveltejs/adapter-static": "^2.0.3",
    "@sveltejs/kit": "^1.20.4",
    "svelte": "^4.0.5",
    "svelte-check": "^3.4.3",
    "typescript": "^5.0.0",
    "vite": "^4.4.2"
  },
  "dependencies": {
    "@sveltejs/adapter-auto": "^2.0.0"
  }
}
```

### 4. .env.local (dans le dossier frontend)
```bash
# Variables pour SvelteKit
VITE_API_URL=http://localhost:8080/api
VITE_APP_TITLE=GestAsso
VITE_APP_VERSION=1.0.0
```

## 🔌 Intégration API

### Service API (src/lib/api/client.js)
```javascript
import { browser } from '$app/environment';
import { env } from '$env/dynamic/public';

const API_URL = env.VITE_API_URL || 'http://localhost:8080/api';

class ApiClient {
  constructor() {
    this.baseURL = API_URL;
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;
    
    const config = {
      headers: {
        'Content-Type': 'application/json',
        ...options.headers
      },
      ...options
    };

    // Ajouter le token JWT si disponible
    if (browser) {
      const token = localStorage.getItem('auth_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    }

    try {
      const response = await fetch(url, config);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      return await response.json();
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  // Méthodes utilitaires
  get(endpoint) {
    return this.request(endpoint);
  }

  post(endpoint, data) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  put(endpoint, data) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  delete(endpoint) {
    return this.request(endpoint, {
      method: 'DELETE'
    });
  }
}

export const api = new ApiClient();
```

### Store d'authentification (src/lib/stores/auth.js)
```javascript
import { writable } from 'svelte/store';
import { browser } from '$app/environment';
import { api } from '$lib/api/client.js';

function createAuthStore() {
  const { subscribe, set, update } = writable({
    user: null,
    token: null,
    isAuthenticated: false,
    loading: false
  });

  return {
    subscribe,
    
    async login(email, password) {
      update(state => ({ ...state, loading: true }));
      
      try {
        const response = await api.post('/auth/login', { email, password });
        
        if (browser) {
          localStorage.setItem('auth_token', response.token);
        }
        
        set({
          user: response.user,
          token: response.token,
          isAuthenticated: true,
          loading: false
        });
        
        return response;
      } catch (error) {
        set({
          user: null,
          token: null,
          isAuthenticated: false,
          loading: false
        });
        throw error;
      }
    },
    
    logout() {
      if (browser) {
        localStorage.removeItem('auth_token');
      }
      
      set({
        user: null,
        token: null,
        isAuthenticated: false,
        loading: false
      });
    },
    
    async checkAuth() {
      if (!browser) return;
      
      const token = localStorage.getItem('auth_token');
      if (!token) return;
      
      try {
        const user = await api.get('/auth/me');
        set({
          user,
          token,
          isAuthenticated: true,
          loading: false
        });
      } catch (error) {
        this.logout();
      }
    }
  };
}

export const auth = createAuthStore();
```

## 🛠️ Commandes de Développement

### Commandes Make Disponibles
```bash
# Installation des dépendances
make frontend-install

# Développement avec hot-reload
make frontend-dev

# Build pour production
make frontend-build

# Preview du build de production
make frontend-preview

# Installation complète (backend + frontend)
make install
```

### Commandes Docker Directes
```bash
# Accès au conteneur frontend
docker-compose exec frontend sh

# Installation manuelle des dépendances
docker-compose exec frontend npm install

# Lancer SvelteKit en mode dev
docker-compose exec frontend npm run dev -- --host 0.0.0.0

# Build de production
docker-compose exec frontend npm run build
```

## 🌐 Accès aux Services

| Service | URL | Description |
|---------|-----|-------------|
| **SvelteKit Dev** | http://localhost:5173 | Serveur de développement avec HMR |
| **SvelteKit Preview** | http://localhost:4173 | Preview du build de production |
| **Production** | http://localhost:8080 | Frontend servi par Nginx |
| **API Backend** | http://localhost:8080/api | API Symfony |

## 🔧 Configuration Nginx pour SvelteKit

La configuration Nginx est optimisée pour SvelteKit :

```nginx
# Frontend SvelteKit
location / {
    root /var/www/frontend/build;
    index index.html;
    try_files $uri $uri/ /index.html;
    
    # Assets SvelteKit avec cache long
    location /_app/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}

# API Backend
location /api {
    # Configuration pour l'API Symfony
    # ...
}
```

## 📱 Fonctionnalités SvelteKit Supportées

### ✅ Fonctionnalités Activées
- **Hot Module Replacement (HMR)** - Rechargement instantané
- **Server-Side Rendering (SSR)** - Rendu côté serveur
- **Static Site Generation (SSG)** - Génération de site statique
- **API Routes** - Routes API intégrées
- **TypeScript** - Support TypeScript complet
- **Vite** - Build ultra-rapide avec Vite
- **Adapter Static** - Déploiement statique optimisé

### 🔄 Intégrations
- **Symfony API** - Communication avec l'API backend
- **JWT Authentication** - Authentification sécurisée
- **Proxy API** - Proxy des requêtes API en développement
- **Environment Variables** - Variables d'environnement Vite

## 🚨 Dépannage SvelteKit

### Problème : Port 5173 déjà utilisé
```bash
# Changer le port dans .env
FRONTEND_DEV_PORT=5174
```

### Problème : API non accessible
```bash
# Vérifier la variable VITE_API_URL
echo $VITE_API_URL

# Ou dans le frontend/.env.local
VITE_API_URL=http://localhost:8080/api
```

### Problème : Build ne fonctionne pas
```bash
# Nettoyer et reconstruire
make frontend-build
```

### Problème : HMR ne fonctionne pas
```bash
# Redémarrer le serveur de dev
make frontend-dev
```

## 📚 Ressources SvelteKit

- [Documentation SvelteKit](https://kit.svelte.dev/)
- [Svelte Tutorial](https://svelte.dev/tutorial)
- [SvelteKit Examples](https://github.com/sveltejs/kit/tree/master/examples)
- [Vite Documentation](https://vitejs.dev/)

---

**Note** : Cette configuration est optimisée pour le développement et la production avec SvelteKit. Le hot-reload fonctionne parfaitement et l'intégration avec l'API Symfony est transparente. 