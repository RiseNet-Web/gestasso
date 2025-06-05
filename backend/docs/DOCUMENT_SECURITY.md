# 🔒 Sécurité des Documents - Documentation Critique

## ⚠️ AVERTISSEMENT SÉCURITÉ

**CRITIQUE** : Cette documentation traite de la sécurité des documents confidentiels (passeports, pièces d'identité, documents médicaux, etc.). La violation de ces mesures peut entraîner des fuites de données personnelles graves.

## 🛡️ Architecture Sécurisée

### Stockage Privé
```
❌ AVANT (DANGEREUX) : public/uploads/documents/
✅ APRÈS (SÉCURISÉ) : var/secure_documents/
```

### Contrôle d'Accès Multi-Niveaux

1. **Authentification** : JWT token obligatoire
2. **Autorisation** : Vérification des permissions utilisateur
3. **Tokenisation** : Tokens temporaires pour accès limité
4. **Audit** : Logs complets de tous les accès

## 🔐 Fonctionnalités Sécurisées

### 1. Upload Sécurisé

```http
POST /api/secure-documents
Content-Type: multipart/form-data
Authorization: Bearer {jwt_token}

Form Data:
- document: [fichier_confidentiel.pdf]
- documentType: "passport"
- description: "Passeport de M. Dupont"
- relatedUserId: 123
```

**Protections :**
- ✅ Stockage hors dossier public
- ✅ Nom de fichier cryptographiquement sécurisé
- ✅ Chiffrement du nom original
- ✅ Validation stricte des types MIME
- ✅ Limite de taille (50MB)
- ✅ Métadonnées de sécurité

### 2. Accès Contrôlé

```http
GET /api/secure-documents/{id}/download
Authorization: Bearer {jwt_token}
Query: ?token={access_token}
```

**Vérifications :**
- ✅ Utilisateur authentifié
- ✅ Permissions d'accès au document
- ✅ Token temporaire valide (optionnel)
- ✅ Incrémentation compteur d'accès
- ✅ Log de sécurité

### 3. Tokens Temporaires

```http
POST /api/secure-documents/{id}/access-token
{
  "validityMinutes": 30
}
```

**Avantages :**
- ✅ Accès limité dans le temps
- ✅ Révocable instantanément
- ✅ Hashé en base de données
- ✅ Audit complet

## 🔒 Contrôles d'Accès

### Matrice des Permissions

| Action | Propriétaire | Utilisateur Concerné | Admin | Gestionnaire Doc |
|--------|-------------|---------------------|-------|-----------------|
| Upload | ✅ | ❌ | ✅ | ✅ |
| Lecture | ✅ | ✅ | ✅ | ✅ |
| Suppression | ✅ | ❌ | ✅ | ❌ |
| Token | ✅ | ✅ | ✅ | ✅ |

### Types de Documents Supportés

```php
const DOCUMENT_TYPES = [
    'passport'          => 'Passeport',
    'identity_card'     => 'Carte d\'identité',
    'license'           => 'Permis/Licence',
    'certificate'       => 'Certificat',
    'medical_document'  => 'Document médical',
    'insurance'         => 'Assurance',
    'contract'          => 'Contrat',
    'invoice'           => 'Facture',
    'photo'             => 'Photo',
    'other'             => 'Autre'
];
```

## 🛠️ Configuration Sécurisée

### Variables d'Environnement

```env
# Clé de chiffrement - OBLIGATOIRE en production
DOCUMENT_ENCRYPTION_KEY=your-super-secure-256-bit-key-here

# Répertoire de stockage sécurisé
SECURE_DOCUMENTS_PATH=/var/secure_documents

# Durée max des tokens (minutes)
MAX_TOKEN_VALIDITY=1440

# Logs de sécurité
SECURITY_LOG_LEVEL=info
SECURITY_LOG_FILE=/var/log/document_security.log
```

### Permissions Système

```bash
# Créer le répertoire sécurisé
mkdir -p /var/secure_documents
chmod 700 /var/secure_documents
chown www-data:www-data /var/secure_documents

# Sécuriser l'accès
echo "Deny from all" > /var/secure_documents/.htaccess
```

## 📊 Audit et Monitoring

### Logs de Sécurité

Tous les accès sont loggés avec :
- ✅ ID du document
- ✅ ID de l'utilisateur
- ✅ Adresse IP
- ✅ Timestamp
- ✅ Action effectuée
- ✅ Résultat (succès/échec)

```json
{
  "level": "info",
  "message": "Secure document accessed",
  "context": {
    "document_id": 123,
    "accessed_by": 456,
    "document_type": "passport",
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "timestamp": "2024-01-15T10:30:45+00:00"
  }
}
```

### Alertes de Sécurité

Actions déclenchant des alertes :
- ❌ Tentative d'accès non autorisé
- ❌ Token invalide utilisé
- ❌ Accès à un document inexistant
- ❌ Upload de type de fichier non autorisé
- ❌ Dépassement de limite de taille

## 🚨 Procédures d'Incident

### En cas de Breach Suspect

1. **IMMÉDIAT** : Invalider tous les tokens actifs
2. **URGENT** : Analyser les logs de sécurité
3. **24H** : Notifier les utilisateurs concernés
4. **48H** : Rapport d'incident complet

### Commandes d'Urgence

```bash
# Invalider tous les tokens
php bin/console app:security:invalidate-all-tokens

# Audit complet des accès
php bin/console app:security:audit-document-access --since="2024-01-01"

# Vérifier l'intégrité des fichiers
php bin/console app:security:check-file-integrity
```

## 🔐 Chiffrement et Hashing

### Chiffrement des Métadonnées

```php
// Nom de fichier original chiffré
$encryptedName = openssl_encrypt(
    $originalName, 
    'AES-256-CBC', 
    $encryptionKey, 
    0, 
    $iv
);
```

### Génération de Noms Sécurisés

```php
// Nom de fichier impossible à deviner
$secureFilename = hash('sha256', random_bytes(32) . microtime(true));
```

### Tokens Temporaires

```php
// Token hashé pour stockage sécurisé
$token = bin2hex(random_bytes(32));
$hashedToken = hash('sha256', $token);
```

## 📋 Checklist de Sécurité

### Avant Déploiement

- [ ] Répertoire `var/secure_documents` créé avec permissions 700
- [ ] Clé de chiffrement générée et configurée
- [ ] Logs de sécurité configurés
- [ ] Tests de pénétration effectués
- [ ] Audit de code sécurité validé
- [ ] Sauvegarde des documents testée
- [ ] Procédures d'incident documentées

### Monitoring Continu

- [ ] Surveillance des accès anormaux
- [ ] Vérification périodique des permissions
- [ ] Rotation des clés de chiffrement (annuel)
- [ ] Tests de restauration (mensuel)
- [ ] Audit des logs (hebdomadaire)

## 🎯 Tests de Sécurité

### Tests Unitaires

```php
// Test d'accès non autorisé
public function testUnauthorizedAccess(): void
{
    $this->expectException(AccessDeniedHttpException::class);
    $this->secureDocumentService->getSecureDocument(123, $unauthorizedUser);
}

// Test de token expiré
public function testExpiredToken(): void
{
    $expiredToken = $document->generateSecureAccessToken(-1);
    $this->assertFalse($document->isAccessTokenValid($expiredToken));
}
```

### Tests d'Intrusion

```bash
# Test d'énumération de fichiers
curl -H "Authorization: Bearer $TOKEN" \
  "https://api.example.com/api/secure-documents/999999/download"

# Test de traversée de répertoires
curl -H "Authorization: Bearer $TOKEN" \
  "https://api.example.com/uploads/../../../etc/passwd"
```

## ⚖️ Conformité Légale

### RGPD

- ✅ Consentement explicite pour stockage
- ✅ Droit à l'effacement (suppression sécurisée)
- ✅ Portabilité des données
- ✅ Notification de breach < 72h
- ✅ Pseudonymisation des identifiants
- ✅ Minimisation des données

### Retention des Données

```php
// Suppression automatique après expiration
if ($document->isExpired()) {
    $this->secureDocumentService->secureFileDelete($document);
}
```

## 🔄 Migration Sécurisée

### Depuis l'Ancien Système

```bash
# Script de migration sécurisée
php bin/console app:migrate:secure-documents \
  --from="/public/uploads" \
  --to="/var/secure_documents" \
  --encrypt-metadata \
  --audit-trail
```

### Vérification Post-Migration

- [ ] Tous les fichiers déplacés
- [ ] Anciens fichiers supprimés de manière sécurisée
- [ ] Métadonnées chiffrées
- [ ] Liens mis à jour
- [ ] Tests d'accès validés

## 📞 Contacts d'Urgence

### Équipe Sécurité
- **Lead Sécurité** : security@gestasso.com
- **DPO** : dpo@gestasso.com
- **Admin Système** : admin@gestasso.com

### Escalade d'Incident
1. **L1** : Développeur responsable
2. **L2** : Lead technique
3. **L3** : CISO
4. **L4** : Direction + Juridique

---

**⚠️ RAPPEL IMPORTANT** : La sécurité des documents est une responsabilité partagée. Chaque développeur DOIT comprendre et appliquer ces mesures de sécurité. 