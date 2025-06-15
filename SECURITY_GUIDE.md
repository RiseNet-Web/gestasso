# 🔒 Guide de Sécurité - GestAsso

## Architecture de Sécurité à Double Niveau

### 🛡️ **1. Protection Côté Serveur (Primaire)**

**Fichiers concernés :**
- `src/lib/server/auth.ts` - Middleware d'authentification centralisé
- `src/routes/create-club/+page.server.ts` - Protection owners
- `src/routes/join-club/+page.server.ts` - Protection members

**Principe :** Vérification AVANT le rendu de la page
```typescript
// Protection automatique côté serveur
export const load = async (event) => {
    const user = await requireAuth(event, 'owner'); // Ou 'member'
    return { user };
};
```

**Avantages :**
✅ **Sécurité garantie** - Impossible à contourner côté client
✅ **SEO préservé** - Les pages protégées sont rendues côté serveur
✅ **Accessible sans JS** - Fonctionne même si JavaScript est désactivé
✅ **Performance** - Pas de flash de contenu non autorisé

### 🎨 **2. Protection Côté Client (Secondaire - UX)**

**Fichier :** `src/lib/components/ProtectedRoute.svelte`

**Principe :** Amélioration de l'expérience utilisateur
```svelte
<ProtectedRoute requiredRole={UserRole.OWNER}>
    <!-- Contenu protégé -->
</ProtectedRoute>
```

**Avantages :**
✅ **Transitions fluides** - Pas de rechargement de page
✅ **Feedback visuel** - Spinner de chargement
✅ **Navigation intelligente** - Redirection avec contexte

## 🔄 **Flux de Sécurité Complet**

### **Étape 1 : Vérification Serveur**
1. L'utilisateur accède à `/create-club`
2. Le serveur vérifie le token dans les cookies
3. Si invalide/absent → Redirection 302 vers `/register?role=owner`
4. Si valide mais mauvais rôle → Redirection avec bon rôle
5. Si autorisé → Rendu de la page

### **Étape 2 : Amélioration Client**
1. Le composant `ProtectedRoute` vérifie l'état local
2. Affiche un loader pendant la vérification
3. Si déjà connecté → Affichage immédiat
4. Si non connecté → Redirection fluide

## 🚧 **Implémentation en Production**

### **À remplacer dans `src/lib/server/auth.ts` :**

```typescript
// 🚧 ACTUEL (Simulation)
const simulatedUser: AuthUser = {
    id: 'user-123',
    role: requiredRole || 'member'
};

// ✅ PRODUCTION (Vraie API)
const response = await fetch(`${env.API_URL}/auth/verify`, {
    headers: {
        'Authorization': `Bearer ${authToken}`,
        'Content-Type': 'application/json'
    }
});

if (!response.ok) {
    throw redirect(302, `/register?role=${requiredRole}`);
}

const user: AuthUser = await response.json();
```

### **Variables d'environnement requises :**
```env
API_URL=https://votre-backend.com
JWT_SECRET=votre-secret-super-securise
```

## 🔐 **Stockage des Tokens**

### **Recommandations :**
1. **HttpOnly Cookies** pour le token principal (non accessible en JS)
2. **Secure flag** en production (HTTPS uniquement)
3. **SameSite=Strict** pour CSRF protection
4. **Refresh token** séparé avec durée de vie longue

### **Configuration SvelteKit :**
```typescript
// Dans votre API
export async function POST({ cookies }) {
    cookies.set('auth-token', token, {
        httpOnly: true,
        secure: true,
        sameSite: 'strict',
        maxAge: 60 * 60 * 24 * 7 // 7 jours
    });
}
```

## 🛡️ **Mesures de Sécurité Additionnelles**

### **1. Rate Limiting**
- Limite les tentatives de connexion
- Protection contre les attaques par force brute

### **2. CSRF Protection**
- Tokens CSRF pour les formulaires sensibles
- Validation des origins

### **3. Validation des Entrées**
- Sanitisation côté serveur ET client
- Validation des types TypeScript

### **4. Headers de Sécurité**
```typescript
// Dans app.html ou hooks.server.ts
headers: {
    'X-Content-Type-Options': 'nosniff',
    'X-Frame-Options': 'DENY',
    'X-XSS-Protection': '1; mode=block',
    'Strict-Transport-Security': 'max-age=31536000'
}
```

## 🧪 **Tests de Sécurité**

### **Scénarios à tester :**
1. ✅ Accès direct sans authentification
2. ✅ Accès avec mauvais rôle
3. ✅ Token expiré/invalide
4. ✅ JavaScript désactivé
5. ✅ Manipulation des cookies
6. ✅ Injection XSS/SQL

### **Commandes de test :**
```bash
# Test sans cookies
curl -I http://localhost:5173/create-club

# Test avec mauvais token
curl -I -H "Cookie: auth-token=invalid" http://localhost:5173/create-club

# Test SEO (contenu SSR)
curl -s http://localhost:5173/create-club | grep -o "<title>.*</title>"
```

## 📊 **Monitoring & Logs**

### **Événements à logger :**
- Tentatives d'accès non autorisées
- Échecs d'authentification répétés
- Tokens invalides/expirés
- Changements de rôles

### **Métriques importantes :**
- Taux de redirections d'authentification
- Temps de réponse des vérifications
- Nombre d'utilisateurs actifs par rôle

---

## 🚀 **Résumé : Sécurité Maximale**

Cette architecture garantit :
- **🔒 Sécurité côté serveur** incontournable
- **🎨 UX fluide** côté client  
- **📱 SEO préservé** avec SSR
- **♿ Accessibilité** même sans JavaScript
- **🔄 Redirections intelligentes** avec contexte

La sécurité est assurée **même si un attaquant** :
- Désactive JavaScript
- Modifie le code côté client
- Manipule les cookies
- Utilise des outils de debugging

**La règle d'or :** Ne jamais faire confiance au client ! 🛡️ 