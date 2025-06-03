# Tests Backend GestAsso - Suite Complète

## 📋 Vue d'ensemble

Cette suite de tests couvre l'intégralité des fonctionnalités backend de l'API GestAsso selon les personas définis dans le prompt :
- **Marc Dubois** : Gestionnaire de club
- **Julie Moreau** : Coach d'équipe
- **Emma Leblanc** : Athlète

## 🏗️ Architecture des Tests

### Structure des Fichiers

```
tests/
├── ApiTestCase.php              # Classe de base pour tous les tests API
├── Functional/                  # Tests fonctionnels par feature
│   ├── AuthenticationTest.php   # Tests d'authentification JWT
│   ├── ClubManagementTest.php   # Tests gestion des clubs
│   ├── FinancialTest.php        # Tests financiers et cagnottes
│   ├── SecurityTest.php         # Tests sécurité et permissions
│   └── DocumentTest.php         # Tests gestion des documents
├── Integration/                 # Tests d'intégration
│   └── UserJourneyTest.php      # Parcours utilisateurs complets
├── bootstrap.php                # Initialisation environnement de test
└── README.md                    # Cette documentation
```

## 🧪 Types de Tests

### 1. Tests d'Authentification (`AuthenticationTest.php`)
- ✅ Inscription utilisateur avec validation
- ✅ Connexion JWT et récupération token
- ✅ Refresh token et renouvellement
- ✅ Sécurité des mots de passe (hashage)
- ✅ Gestion des tokens expirés
- ✅ Validation des credentials
- ✅ Structure et contenu des tokens JWT

### 2. Tests de Gestion des Clubs (`ClubManagementTest.php`)
- ✅ Création de club avec propriétaire
- ✅ Gestion des saisons actives
- ✅ Création d'équipes avec prix et calendriers
- ✅ **Restrictions d'âge des équipes** : minBirthYear/maxBirthYear
- ✅ **Validation d'âge lors ajout membres** : Athlètes vs Coachs
- ✅ Ajout de co-gestionnaires avec permissions
- ✅ Assignation des coachs aux équipes
- ✅ Isolation des données entre clubs
- ✅ Génération des échéanciers de paiement

### 3. Tests Financiers (`FinancialTest.php`)
- ✅ **Calculs de cagnottes critiques** : Événement 2000€, 15% commission = 212.50€/participant
- ✅ Attribution automatique aux cagnottes
- ✅ Accumulation multi-événements
- ✅ Règles de déduction et commissions
- ✅ Arrondi monétaire précis
- ✅ Historique des transactions
- ✅ Tableau de bord financier club
- ✅ Retraits et validation des soldes

### 4. Tests de Sécurité (`SecurityTest.php`)
- ✅ **Isolation des données par rôle** : Coach A ne voit pas équipes Coach B
- ✅ Hiérarchie des permissions : Gestionnaire > Coach > Athlète
- ✅ Tests négatifs d'accès (erreurs 403)
- ✅ Protection contre l'injection SQL
- ✅ Validation des entrées utilisateur
- ✅ Prévention de l'escalation de privilèges
- ✅ Isolation cross-club

### 5. Tests de Documents (`DocumentTest.php`)
- ✅ Upload sécurisé avec validation types/tailles
- ✅ Workflow : Soumis → En cours → Validé/Refusé
- ✅ Notifications documents expirés
- ✅ Permissions de validation par rôle
- ✅ Organisation stockage par utilisateur/équipe
- ✅ Versioning des documents
- ✅ Statuts globaux d'équipe

### 6. Tests de Validation d'Âge (`AgeValidationTest.php`)
- ✅ **Gestion date de naissance utilisateurs** : Validation format et cohérence
- ✅ **Restrictions d'âge équipes** : minBirthYear/maxBirthYear par équipe
- ✅ **Validation d'âge athlètes** : Refus si trop jeune/âgé pour équipe
- ✅ **Exception coachs** : Coachs non soumis aux restrictions d'âge
- ✅ **Équipes ouvertes** : Équipes sans restriction acceptent tous âges
- ✅ **Messages d'erreur explicites** : "trop jeune", "trop âgé", "date obligatoire"
- ✅ **Calcul automatique tranche d'âge** : Affichage "12-16 ans" depuis années naissance
- ✅ **Validation cohérence** : minBirthYear ≤ maxBirthYear

### 7. Tests d'Intégration (`UserJourneyTest.php`)
- ✅ **Parcours complet Marc Dubois** : Création club → équipes avec âge → événements → calculs
- ✅ **Parcours complet Julie Moreau** : Assignation coach → gestion équipe → visualisation restrictions âge
- ✅ **Parcours complet Emma Leblanc** : Inscription avec âge → validation adhésion → participation → cagnotte
- ✅ **Tests négatifs intégrés** : Tentative ajout athlète trop jeune dans parcours

## ⚙️ Configuration et Exécution

### Prérequis
```bash
# Base de données PostgreSQL de test
createdb gestasso_test
createuser gestasso_test --password

# Clés JWT de test
mkdir -p config/jwt
openssl genrsa -out config/jwt/private-test.pem -aes256 -passout pass:test 4096
openssl rsa -pubout -in config/jwt/private-test.pem -passin pass:test -out config/jwt/public-test.pem
```

### Variables d'Environnement Test
Créer `.env.test` :
```env
APP_ENV=test
DATABASE_URL="postgresql://gestasso_test:password@localhost:5432/gestasso_test"
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private-test.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public-test.pem
JWT_PASSPHRASE=test
```

### Commandes d'Exécution

```bash
# Tous les tests
./vendor/bin/phpunit

# Tests par catégorie
./vendor/bin/phpunit tests/Functional/AuthenticationTest.php
./vendor/bin/phpunit tests/Functional/FinancialTest.php
./vendor/bin/phpunit tests/Integration/UserJourneyTest.php

# Tests avec couverture
./vendor/bin/phpunit --coverage-html var/coverage

# Tests spécifiques
./vendor/bin/phpunit --filter testCagnotteCalculationPrecision
./vendor/bin/phpunit --filter testCoachCannotAccessOtherTeam
```

## 🎯 Cas de Tests Critiques Couverts

### Calculs Financiers (Priorité Haute)
```php
// Test événement : 2000€ budget, 15% commission, 8 participants
// Résultat attendu : (2000 * 0.85) / 8 = 212.50€ par participant
$this->assertEquals(212.50, $responseData['individualGain']);
```

### Permissions et Sécurité (Priorité Haute)
```php
// Julie (coach) ne peut pas accéder à l'équipe de Pierre
$this->authenticatedRequest('GET', '/api/teams/' . $otherTeam->getId(), $julie);
$this->assertErrorResponse(403, 'accès refusé');
```

### Validation d'Âge (Priorité Haute)
```php
// Équipe U18 : nés entre 2006 (18 ans max) et 2012 (12 ans min)
$teamData = [
    'name' => 'U18 Filles',
    'minBirthYear' => 2006, // 18 ans max en 2024
    'maxBirthYear' => 2012, // 12 ans min en 2024
];

// Athlète de 16 ans (née en 2008) : ACCEPTÉE
$validAthlete = ['dateOfBirth' => '2008-05-20'];
$this->assertJsonResponse(201);

// Athlète de 9 ans (née en 2015) : REFUSÉE
$tooYoungAthlete = ['dateOfBirth' => '2015-03-10'];
$this->assertErrorResponse(400, 'trop jeune');

// Coach de 35 ans : ACCEPTÉ (exception pour coachs)
$oldCoach = ['dateOfBirth' => '1989-07-15', 'role' => 'coach'];
$this->assertJsonResponse(201);
```

### Workflow Documents (Priorité Moyenne)
```php
// Cycle complet : Upload → Validation → Resoumission
testDocumentValidationWorkflow()
testDocumentRejection()
testDocumentResubmission()
```

## 📊 Couverture et Métriques

### Objectifs de Couverture
- **Cible globale** : >85% couverture du code
- **Critique** : 100% sur calculs financiers et sécurité
- **Entités** : 100% sur User, Club, Team, Event, Cagnotte

### Métriques de Performance
- **Temps réponse** : <200ms pour endpoints simples
- **Tests d'isolation** : Rollback automatique des transactions
- **Mémoire** : <128MB par processus test

## 🔧 Données de Test (Fixtures)

### Utilisateurs de Base
```php
$marcDubois = $this->createTestUser(
    'marc.dubois@racingclub.com',
    ['ROLE_CLUB_OWNER'],
    ['firstName' => 'Marc', 'lastName' => 'Dubois']
);
```

### Scénarios Financiers
```php
// Cas de test avec différents budgets et commissions
$testCases = [
    ['budget' => 2000.00, 'commission' => 15.0, 'participants' => 8, 'expected' => 212.50],
    ['budget' => 1500.00, 'commission' => 10.0, 'participants' => 6, 'expected' => 225.00],
];
```

## 🚀 Exécution Continue (CI/CD)

### Pipeline Recommandé
```yaml
# .github/workflows/tests.yml
- name: Run Tests
  run: |
    php bin/console doctrine:database:create --env=test
    php bin/console doctrine:schema:create --env=test
    ./vendor/bin/phpunit --coverage-clover coverage.xml
    
- name: Security Scan
  run: ./vendor/bin/phpunit tests/Functional/SecurityTest.php
```

## 📝 Cas d'Usage Validés

### ✅ Persona Marc Dubois - Gestionnaire
- Création Racing Club Paris avec équipes Seniors (450€, 18+ ans) et U18 Filles (320€, 12-18 ans)
- **Configuration restrictions d'âge** : Seniors (maxBirthYear: 2006), U18 (minBirthYear: 2006, maxBirthYear: 2012)
- Configuration échéanciers 3 paiements (106.67€ chacun)
- Création événement "Tournoi National U18" (2000€, 15% commission)
- **Validation d'âge automatique** lors ajout membres aux équipes
- Ajout Sophie Martin comme co-gestionnaire
- Configuration documents obligatoires

### ✅ Persona Julie Moreau - Coach
- Assignation équipe U18 Filles par Marc (possible malgré âge 32 ans)
- **Visualisation restrictions d'âge équipe** : 12-18 ans (2006-2012)
- Consultation tableau de bord financier équipe
- Accès restreint aux données de son équipe uniquement
- Impossibilité de créer événements ou modifier prix
- Envoi rappels de paiement personnalisés

### ✅ Persona Emma Leblanc - Athlète
- **Inscription avec date de naissance** : 15/03/2008 (16 ans)
- **Validation automatique d'âge** : Éligible pour équipe U18 (12-18 ans)
- Ajout équipe U18 Filles avec succès (âge valide)
- Upload certificat médical et licence FFT
- Suivi échéances paiement (3 x 106.67€)
- Participation tournoi → Attribution cagnotte 212.50€
- Consultation historique et retrait 100€
- Interface lecture seule pour données financières

### ✅ Cas d'Erreur d'Âge Validés
- **Athlète trop jeune** : Refus athlète 9 ans pour équipe U18 (message "trop jeune")
- **Athlète trop âgé** : Refus athlète 25 ans pour équipe U18 (message "trop âgé")
- **Date manquante** : Refus si pas de date de naissance pour équipe avec restrictions
- **Équipe incohérente** : Erreur si minBirthYear > maxBirthYear
- **Coach exception** : Coach 35 ans accepté sur équipe U18 (rôle non soumis aux restrictions)

## 🎉 Résultats Attendus

Cette suite de tests valide l'intégralité des fonctionnalités critiques de l'API GestAsso avec :
- **Couverture exhaustive** des cas d'erreur et de succès
- **Isolation parfaite** des données entre utilisateurs/clubs
- **Calculs financiers précis** au centime près
- **Sécurité robuste** contre les attaques communes
- **Workflow complet** de gestion documentaire

**Status** : ✅ Prêt pour validation et déploiement 