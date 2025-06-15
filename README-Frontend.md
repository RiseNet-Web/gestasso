# Frontend GestAsso - Configuration et Authentification

## Configuration des Variables d'Environnement

Le frontend utilise des variables d'environnement pour configurer l'API backend. Créez un fichier `.env` dans le dossier `frontend/` :

```bash
# Configuration de l'API Backend
VITE_API_URL=http://localhost:8000
VITE_API_BASE_PATH=/api

# Configuration de développement
VITE_NODE_ENV=development

# Configuration des cookies (développement)
VITE_COOKIE_SECURE=false
VITE_COOKIE_DOMAIN=localhost
```

### Variables Disponibles

- `VITE_API_URL` : URL de base du serveur API (défaut: http://localhost:8000)
- `VITE_API_BASE_PATH` : Chemin de base de l'API (défaut: /api)
- `VITE_NODE_ENV` : Environnement (development/production)
- `VITE_COOKIE_SECURE` : Utiliser HTTPS pour les cookies (true/false)
- `VITE_COOKIE_DOMAIN` : Domaine pour les cookies

## Authentification

### Architecture

L'authentification utilise un système double niveau :

1. **Côté serveur** (sécurité primaire) : Middleware dans `lib/server/auth.ts`
2. **Côté client** (UX) : Store réactif dans `lib/stores/auth.svelte.ts`

### Routes d'API Utilisées

- `POST /api/login` - Connexion utilisateur
- `POST /api/register` - Inscription utilisateur  
- `POST /api/refresh-token` - Renouvellement des tokens
- `POST /api/logout` - Déconnexion
- `GET /api/profile` - Profil utilisateur

### Flux d'Authentification

1. **Inscription/Connexion** : L'utilisateur saisit ses identifiants
2. **Tokens JWT** : Le backend retourne `accessToken` et `refreshToken`
3. **Stockage** : Les tokens sont stockés dans `localStorage`
4. **Requêtes** : Le `accessToken` est envoyé dans l'en-tête `Authorization`
5. **Refresh** : Le `refreshToken` permet de renouveler l'`accessToken`

### Gestion des Rôles

Deux types d'utilisateurs :
- **Member** (`onboardingType: 'member'`) : Peut rejoindre des clubs
- **Owner** (`onboardingType: 'owner'`) : Peut créer et gérer des clubs

### Pages Protégées

- `/create-club` : Réservée aux propriétaires (owners)
- `/join-club` : Réservée aux membres (members)

## Services

### ApiService (`lib/services/api.ts`)

Service centralisé pour toutes les requêtes HTTP :

```typescript
import { apiService } from '$lib/services/api';

// GET
const response = await apiService.get('/endpoint');

// POST
const response = await apiService.post('/endpoint', data);

// Upload
const response = await apiService.upload('/endpoint', formData);
```

### AuthStore (`lib/stores/auth.svelte.ts`)

Store réactif pour la gestion de l'authentification :

```typescript
import { auth } from '$lib/stores/auth.svelte';

// Connexion
await auth.login(email, password);

// Inscription
await auth.register(userData);

// Déconnexion
await auth.logout();

// Vérifications
auth.isOwner(); // true si propriétaire
auth.isMember(); // true si membre
auth.hasRole('ROLE_CLUB_OWNER'); // vérification de rôle
```

## Composants de Sécurité

### ProtectedRoute

Composant pour protéger les pages selon le rôle :

```svelte
<ProtectedRoute requiredRole="owner">
  {#snippet children()}
    <!-- Contenu réservé aux propriétaires -->
  {/snippet}
</ProtectedRoute>
```

### Middleware Serveur

Protection côté serveur dans `+page.server.ts` :

```typescript
import { requireAuth } from '$lib/server/auth';

export async function load(event) {
  const user = await requireAuth(event);
  return { user };
}
```

## Développement

### Démarrage

```bash
cd frontend
npm install
npm run dev
```

### Build

```bash
npm run build
npm run preview
```

### Docker

Le frontend est containerisé avec Nginx pour la production :

```bash
docker build -t gestasso-frontend .
docker run -p 3000:80 gestasso-frontend
```

## Sécurité

### Recommandations Production

1. **Variables d'environnement** :
   ```bash
   VITE_API_URL=https://api.votre-domaine.com
   VITE_COOKIE_SECURE=true
   VITE_COOKIE_DOMAIN=votre-domaine.com
   ```

2. **Middleware serveur** : Implémenter la vraie validation JWT
3. **HTTPS** : Obligatoire en production
4. **CSP** : Configurer Content Security Policy
5. **Rate limiting** : Limiter les tentatives de connexion

### Tokens

- **AccessToken** : Durée de vie courte (15-30 minutes)
- **RefreshToken** : Durée de vie longue (7-30 jours)
- **Stockage** : `localStorage` en développement, cookies HttpOnly en production

## Structure des Fichiers

```
frontend/src/
├── lib/
│   ├── components/
│   │   └── ProtectedRoute.svelte
│   ├── config/
│   │   └── env.ts
│   ├── server/
│   │   └── auth.ts
│   ├── services/
│   │   └── api.ts
│   ├── stores/
│   │   └── auth.svelte.ts
│   └── types/
│       └── entities.ts
├── routes/
│   ├── login/
│   ├── register/
│   ├── create-club/
│   └── join-club/
└── app.html
```

## Troubleshooting

### Erreurs Communes

1. **CORS** : Vérifier la configuration du backend
2. **Variables d'environnement** : Préfixer avec `VITE_`
3. **Tokens expirés** : Le refresh automatique gère ce cas
4. **Redirections** : Vérifier les paramètres `redirect` dans les URLs

### Debug

```typescript
// Activer les logs de l'API
console.log('API Response:', response);

// Vérifier l'état d'authentification
console.log('Auth state:', auth.user, auth.token);
``` 