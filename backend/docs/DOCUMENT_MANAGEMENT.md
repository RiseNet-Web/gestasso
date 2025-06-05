# Système de Gestion de Documents Unifié et Sécurisé

## Vue d'ensemble

Le système de gestion de documents de GestAsso offre un stockage sécurisé et un contrôle d'accès granulaire pour tous les documents sensibles (passeports, cartes d'identité, licences, certificats, etc.).

## Architecture de Sécurité

### Stockage Sécurisé
- **Répertoire privé** : `var/secure_documents/` (hors de la racine web)
- **Noms de fichiers cryptographiques** : SHA-256 avec éléments aléatoires
- **Permissions système** : 750 (lecture/écriture owner, lecture groupe)
- **Suppression sécurisée** : Écrasement en 3 passes avant suppression

### Contrôle d'Accès
- **Authentification requise** pour tous les endpoints
- **Autorisation basée sur les rôles** :
  - Propriétaire du document : accès complet
  - Propriétaire du club : accès complet aux documents du club
  - Gestionnaire du club : accès complet aux documents du club (sauf suppression)
  - Admin global : accès complet

### Audit et Logging
- **Logs sécurisés** pour tous les accès et modifications
- **Compteur d'accès** pour chaque document
- **Horodatage** de tous les événements
- **Traçabilité** des validations et rejets

## API Endpoints

### Upload de Document
```http
POST /api/documents
Content-Type: multipart/form-data

{
  "document": [fichier],
  "documentTypeId": 123,
  "description": "Description optionnelle"
}
```

**Réponse** :
```json
{
  "document": {
    "id": 456,
    "originalName": "passeport.pdf",
    "description": "Passeport français",
    "status": "pending",
    "mimeType": "application/pdf",
    "fileSize": 1234567,
    "isConfidential": true,
    "uploadedAt": "2024-01-20T10:30:00+00:00",
    "user": {
      "id": 789,
      "firstName": "Jean",
      "lastName": "Dupont",
      "email": "jean.dupont@example.com"
    },
    "documentType": {
      "id": 123,
      "name": "Passeport",
      "isRequired": true
    }
  },
  "message": "Document uploadé avec succès et stocké de manière sécurisée"
}
```

### Informations Document
```http
GET /api/documents/{id}
```

### Téléchargement Sécurisé
```http
GET /api/documents/{id}/download
```

**Headers de sécurité** :
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Cache-Control: no-cache, no-store, must-revalidate`

### Validation de Document
```http
PUT /api/documents/{id}/validate

{
  "status": "approved", // ou "rejected"
  "notes": "Notes de validation optionnelles"
}
```

### Suppression Sécurisée
```http
DELETE /api/documents/{id}
```

### Listes de Documents

#### Par Équipe
```http
GET /api/documents/team/{teamId}?status=pending&userId=123
```

#### Par Club
```http
GET /api/documents/club/{clubId}?status=approved&teamId=456&userId=123
```

#### Par Utilisateur
```http
GET /api/documents/user/{userId}?teamId=456&status=pending
```

### Types de Documents

#### Liste des Types
```http
GET /api/documents/types/team/{teamId}
```

#### Créer un Type
```http
POST /api/documents/types

{
  "teamId": 123,
  "name": "Certificat médical",
  "description": "Certificat médical d'aptitude",
  "isRequired": true,
  "isExpirable": true,
  "validityDurationInDays": 365,
  "deadline": "2024-12-31"
}
```

## Modèle de Données

### Document Entity
```php
class Document
{
    private int $id;
    private User $user;
    private DocumentType $documentTypeEntity;
    private string $originalFileName;     // Nom original chiffré
    private string $securePath;          // Chemin sécurisé
    private string $description;
    private string $mimeType;
    private int $fileSize;
    private DocumentStatus $status;      // pending, approved, rejected, expired
    private DateTime $createdAt;
    private DateTime $updatedAt;
    private DateTime $validatedAt;
    private User $validatedBy;
    private string $validationNotes;
    private DateTime $expirationDate;
    private int $accessCount;
    private DateTime $lastAccessedAt;
}
```

### DocumentType Entity
```php
class DocumentType
{
    private int $id;
    private Team $team;
    private string $name;
    private string $description;
    private bool $isRequired;
    private bool $isExpirable;
    private int $validityDurationInDays;
    private DateTime $deadline;
    private DateTime $createdAt;
}
```

### DocumentStatus Enum
```php
enum DocumentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
}
```

## Fonctionnalités de Sécurité

### Validation des Fichiers
- **Types autorisés** : PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG, GIF, WebP, TXT
- **Taille maximale** : 50 MB
- **Validation MIME** et d'extension
- **Vérification d'intégrité** du fichier

### Gestion des Permissions

#### Accès aux Documents
```php
// L'utilisateur peut accéder à un document si :
// 1. Il est propriétaire du document
// 2. Il est admin global
// 3. Il est propriétaire ou gestionnaire du club

private function canUserAccessDocument(Document $document, User $user): bool
```

#### Validation de Documents
```php
// L'utilisateur peut valider un document si :
// 1. Il est admin global
// 2. Il est propriétaire ou gestionnaire du club

private function canUserValidateDocument(Document $document, User $user): bool
```

#### Suppression de Documents
```php
// L'utilisateur peut supprimer un document si :
// 1. Il est propriétaire du document
// 2. Il est admin global
// 3. Il est propriétaire du club (pas les gestionnaires)

private function canUserDeleteDocument(Document $document, User $user): bool
```

### Gestion des Expirations
- **Calcul automatique** basé sur la durée de validité
- **Vérification périodique** des documents expirés
- **Notifications** avant expiration
- **Marquage automatique** comme expiré

### Audit et Traçabilité
- **Logs de sécurité** pour tous les événements :
  - `document_uploaded` : Upload d'un nouveau document
  - `document_accessed` : Accès à un document
  - `document_validated` : Validation/rejet d'un document
  - `document_deleted` : Suppression d'un document
  - `document_expired` : Expiration automatique

## Notifications

### Upload de Document
- **Destinataires** : Propriétaire et gestionnaires du club
- **Type** : `document_uploaded`
- **Message** : "Nouveau document à valider"

### Validation de Document
- **Destinataire** : Propriétaire du document
- **Type** : `document_validation`
- **Message** : "Document approuvé/rejeté"

## Statistiques

### Par Club
```json
{
  "total": 150,
  "pending": 25,
  "approved": 100,
  "rejected": 15,
  "expired": 10
}
```

## Maintenance et Nettoyage

### Nettoyage Automatique
```php
// Marque les documents expirés
$expiredCount = $documentService->cleanupExpiredDocuments();
```

### Vérification des Documents Manquants
```php
// Pour un utilisateur dans une équipe
$missingDocuments = $documentService->checkMissingDocuments($user, $team);
```

## Configuration

### Services YAML
```yaml
services:
    App\Service\DocumentService:
        arguments:
            $projectDir: '%kernel.project_dir%'
        tags:
            - { name: 'monolog.logger', channel: 'security' }
```

### Répertoires
- **Stockage sécurisé** : `var/secure_documents/`
- **Logs** : `var/log/security.log`
- **Permissions** : 750 pour les répertoires, 640 pour les fichiers

## Sécurité et Conformité

### RGPD
- **Chiffrement** des noms de fichiers originaux
- **Audit complet** des accès
- **Suppression sécurisée** des données
- **Contrôle d'accès strict**

### Bonnes Pratiques
- **Validation stricte** des entrées
- **Headers de sécurité** sur les téléchargements
- **Logs détaillés** pour la conformité
- **Suppression en 3 passes** pour les données sensibles

### Surveillance
- **Monitoring** des accès suspects
- **Alertes** sur les tentatives d'accès non autorisées
- **Rapports** d'audit périodiques

## Migration et Intégration

### Migration depuis l'ancien système
1. **Backup** des documents existants
2. **Migration** vers le stockage sécurisé
3. **Mise à jour** des références en base
4. **Vérification** de l'intégrité

### Tests
- **Tests unitaires** pour tous les services
- **Tests fonctionnels** pour les API
- **Tests de sécurité** pour les permissions
- **Tests de charge** pour les uploads

## Dépannage

### Problèmes Courants
- **Permissions filesystem** : Vérifier les droits sur `var/secure_documents/`
- **Taille des fichiers** : Ajuster `upload_max_filesize` et `post_max_size`
- **Types MIME** : Vérifier la configuration du serveur

### Logs à Consulter
- `var/log/security.log` : Événements de sécurité
- `var/log/prod.log` : Erreurs générales
- `var/log/doctrine.log` : Problèmes de base de données 