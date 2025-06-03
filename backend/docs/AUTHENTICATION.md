# 🔐 Système d'Authentification avec Refresh Tokens

## Vue d'ensemble

Ce projet utilise un système d'authentification moderne basé sur **JWT avec Refresh Tokens** pour maintenir les utilisateurs connectés de manière sécurisée sur une API stateless.

### Architecture

- **Access Token (JWT)** : Durée de vie courte (15 minutes)
- **Refresh Token** : Durée de vie longue (30 jours)
- **Rotation des tokens** : Chaque utilisation d'un refresh token génère un nouveau refresh token
- **Stockage sécurisé** : Les refresh tokens sont stockés en base de données avec métadonnées

## 🚀 Endpoints

### 📝 Inscription
```http
POST /api/register
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "motdepasse123",
  "firstName": "Jean",
  "lastName": "Dupont",
  "onboardingType": "member|owner",
  "phone": "+33123456789",
  "dateOfBirth": "1990-05-15"
}
```

**Réponse :**
```json
{
  "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refreshToken": "a1b2c3d4e5f6789...",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "firstName": "Jean",
    "lastName": "Dupont",
    "roles": ["ROLE_MEMBER"],
    "onboardingCompleted": false
  }
}
```

### 🔑 Connexion
```http
POST /api/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "motdepasse123"
}
```

**Réponse :** Identique à l'inscription

### 🔄 Renouvellement des tokens
```http
POST /api/refresh-token
Content-Type: application/json

{
  "refreshToken": "a1b2c3d4e5f6789..."
}
```

**Réponse :**
```json
{
  "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refreshToken": "z9y8x7w6v5u4321...",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "firstName": "Jean",
    "lastName": "Dupont",
    "roles": ["ROLE_MEMBER"]
  }
}
```

### 🚪 Déconnexion
```http
POST /api/logout
Content-Type: application/json

{
  "refreshToken": "a1b2c3d4e5f6789...",
  "allDevices": false
}
```

### 👤 Profil utilisateur
```http
GET /api/profile
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

## 🛡️ Sécurité

### Durées de vie
- **Access Token** : 15 minutes
- **Refresh Token** : 30 jours
- **Nettoyage automatique** : Les tokens expirés sont supprimés automatiquement

### Mécanismes de sécurité
1. **Rotation des tokens** : Chaque refresh génère un nouveau refresh token
2. **Révocation immédiate** : Les anciens tokens sont révoqués
3. **Limitation par utilisateur** : Maximum 5 refresh tokens actifs par utilisateur
4. **Métadonnées de sécurité** : IP, User-Agent, horodatage d'utilisation
5. **Index de base de données** : Recherche optimisée des tokens

## 💻 Implémentation côté client

### Stockage des tokens
```javascript
// Stocker les tokens après connexion/inscription
localStorage.setItem('accessToken', response.accessToken);
localStorage.setItem('refreshToken', response.refreshToken);
```

### Intercepteur pour les requêtes
```javascript
// Ajouter l'access token à chaque requête
axios.defaults.headers.common['Authorization'] = `Bearer ${accessToken}`;

// Intercepteur pour gérer l'expiration
axios.interceptors.response.use(
  response => response,
  async error => {
    if (error.response?.status === 401) {
      // Tenter de renouveler les tokens
      try {
        const refreshToken = localStorage.getItem('refreshToken');
        const response = await axios.post('/api/refresh-token', { refreshToken });
        
        // Mettre à jour les tokens
        localStorage.setItem('accessToken', response.data.accessToken);
        localStorage.setItem('refreshToken', response.data.refreshToken);
        
        // Rejouer la requête originale
        error.config.headers.Authorization = `Bearer ${response.data.accessToken}`;
        return axios.request(error.config);
      } catch (refreshError) {
        // Rediriger vers la page de connexion
        localStorage.removeItem('accessToken');
        localStorage.removeItem('refreshToken');
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);
```

### Déconnexion
```javascript
const logout = async (allDevices = false) => {
  const refreshToken = localStorage.getItem('refreshToken');
  
  try {
    await axios.post('/api/logout', { refreshToken, allDevices });
  } finally {
    // Nettoyer le stockage local même en cas d'erreur
    localStorage.removeItem('accessToken');
    localStorage.removeItem('refreshToken');
    window.location.href = '/login';
  }
};
```

## 🔧 Administration

### Commande de nettoyage
```bash
# Nettoyer les tokens expirés (à exécuter régulièrement)
php bin/console app:cleanup-refresh-tokens
```

### Révocation manuelle
```php
// Révoquer tous les tokens d'un utilisateur
$refreshTokenService->revokeAllUserTokens($user);

// Révoquer un token spécifique
$refreshTokenService->revokeToken($tokenString);
```

### Surveillance
```sql
-- Compter les tokens actifs par utilisateur
SELECT 
    u.email,
    COUNT(rt.id) as active_tokens
FROM "user" u
LEFT JOIN refresh_token rt ON u.id = rt.user_id 
WHERE rt.is_revoked = false AND rt.expires_at > NOW()
GROUP BY u.id, u.email
ORDER BY active_tokens DESC;

-- Tokens récemment utilisés
SELECT 
    u.email,
    rt.token,
    rt.last_used_at,
    rt.ip_address
FROM refresh_token rt
JOIN "user" u ON rt.user_id = u.id
WHERE rt.last_used_at > NOW() - INTERVAL '24 hours'
ORDER BY rt.last_used_at DESC;
```

## 🧪 Tests

Lancer les tests d'authentification :
```bash
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:migrations:migrate --no-interaction
./vendor/bin/phpunit tests/Functional/RefreshTokenTest.php
```

## 📊 Avantages du système

1. **Sécurité maximale** : Tokens courte durée + rotation automatique
2. **Expérience utilisateur** : Connexion transparente et longue durée
3. **Contrôle granulaire** : Révocation par appareil ou globale
4. **Audit complet** : Traçabilité des connexions et utilisations
5. **Performance** : Index optimisés pour les recherches de tokens
6. **Scalabilité** : Complètement stateless pour l'API

## 🔄 Migration depuis remember_me

L'ancien système `remember_me` de Symfony a été remplacé par ce système de refresh tokens pour :
- Meilleure sécurité avec la rotation des tokens
- Contrôle granulaire des sessions
- Compatibilité avec les applications mobiles
- API vraiment stateless 