# Configuration et Correction des Permissions

## Problème

Si vous rencontrez des erreurs de permissions avec Composer dans le conteneur Docker, comme :
```
Cannot create cache directory /var/www/.cache/composer/repo/flex/, or directory is not writable.
./composer.json is not writable.
```

## Solutions

### Solution Automatique (Recommandée)

#### Sur Windows (PowerShell)
```powershell
cd infrastructure
.\fix-permissions.ps1
```

#### Sur Linux/Mac (Bash)
```bash
cd infrastructure
chmod +x fix-permissions.sh
./fix-permissions.sh
```

### Solution Manuelle

1. **Reconstruire le conteneur avec les bonnes permissions :**
```bash
cd infrastructure
docker-compose down
docker-compose build --no-cache php
docker-compose up -d
```

2. **Corriger les permissions :**
```bash
docker-compose exec --user root php bash -c "
    chown -R www-data:www-data /var/www &&
    chmod -R 755 /var/www &&
    chmod 664 /var/www/symfony/composer.json 2>/dev/null || true
"
```

3. **Installer le Maker Bundle :**
```bash
docker-compose exec php composer require symfony/maker-bundle --dev
```

### Solution d'Urgence

Si rien ne fonctionne, vous pouvez temporairement installer en tant que root :
```bash
docker-compose exec --user root php composer require symfony/maker-bundle --dev
```

## Vérification

Une fois l'installation terminée, vérifiez que tout fonctionne :
```bash
docker-compose exec php php bin/console list make
```

Vous devriez voir les commandes make disponibles :
- `make:entity`
- `make:migration` 
- `make:controller`
- etc.

## Utilisation des Commandes Make

### Générer une migration
```bash
# Avec xDebug désactivé (recommandé)
docker-compose exec -e XDEBUG_MODE=off php php bin/console make:migration

# Ou normalement
docker-compose exec php php bin/console make:migration
```

### Créer une entité
```bash
docker-compose exec php php bin/console make:entity
```

### Créer un contrôleur
```bash
docker-compose exec php php bin/console make:controller
```

## Corrections Apportées

1. **Dockerfile modifié** : Ajout des répertoires Composer et configuration des variables d'environnement
2. **Script de démarrage amélioré** : Correction automatique des permissions au démarrage
3. **Configuration xDebug optimisée** : Mode `off` par défaut pour éviter les problèmes CLI
4. **Composer.json mis à jour** : Version spécifique du maker bundle

## Troubleshooting

### Si les permissions se réinitialisent
Cela peut arriver sur Windows. Relancez le script de correction :
```powershell
.\fix-permissions.ps1
```

### Si xDebug cause des problèmes
Utilisez toujours `XDEBUG_MODE=off` pour les commandes CLI :
```bash
docker-compose exec -e XDEBUG_MODE=off php php bin/console [commande]
```

### Si Composer est lent
Le cache Composer est maintenant configuré dans `/var/www/.cache/composer` avec les bonnes permissions. 