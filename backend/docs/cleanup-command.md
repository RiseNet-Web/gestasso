# Commande de nettoyage automatique des documents

## Vue d'ensemble

La commande `app:clean-expired-documents` permet de supprimer automatiquement les documents des saisons expirées pour libérer de l'espace disque.

## Fonctionnement

### Critères de suppression

1. **Saisons expirées** : Les saisons dont la date de fin (`endDate`) est antérieure à la date limite
2. **Période de grâce** : Par défaut, 30 jours après la fin de saison (configurable)
3. **Saisons inactives** : Seules les saisons marquées comme `isActive = false` sont considérées

### Données supprimées

- **Fichiers physiques** : Suppression des fichiers sur le disque
- **Enregistrements en base** : Suppression des entrées `Document` dans la base de données
- **Logs de traçabilité** : Toutes les actions sont loggées

## Utilisation

### Options disponibles

```bash
# Mode dry-run : affiche ce qui sera supprimé sans rien supprimer
php bin/console app:clean-expired-documents --dry-run

# Modifier la période de grâce (en jours)
php bin/console app:clean-expired-documents --grace-period=60

# Forcer la suppression sans confirmation
php bin/console app:clean-expired-documents --force

# Combinaison d'options
php bin/console app:clean-expired-documents --grace-period=45 --force
```

### Exemples d'utilisation

#### 1. Test en mode dry-run
```bash
docker exec gestasso-php-1 php bin/console app:clean-expired-documents --dry-run
```

#### 2. Nettoyage manuel avec confirmation
```bash
docker exec gestasso-php-1 php bin/console app:clean-expired-documents
```

#### 3. Nettoyage automatique (pour cron)
```bash
docker exec gestasso-php-1 php bin/console app:clean-expired-documents --force --no-interaction
```

## Configuration cron

### 1. Via crontab classique

```bash
# Éditer le crontab
crontab -e

# Ajouter cette ligne pour exécuter tous les jours à 2h du matin
0 2 * * * /path/to/your/project/backend/bin/cron-cleanup.sh >> /path/to/your/project/backend/var/log/cron.log 2>&1
```

### 2. Via script automatisé

Le script `backend/bin/cron-cleanup.sh` est fourni pour faciliter l'exécution :

```bash
# Rendre le script exécutable
chmod +x backend/bin/cron-cleanup.sh

# Tester le script
./backend/bin/cron-cleanup.sh

# Configurer dans cron
0 2 * * * /path/to/your/project/backend/bin/cron-cleanup.sh
```

### 3. Configuration Docker Compose (optionnel)

Vous pouvez aussi ajouter un service cron dans votre `docker-compose.yml` :

```yaml
services:
  # ... vos autres services

  cron:
    image: alpine:latest
    volumes:
      - ./backend:/app
      - /var/run/docker.sock:/var/run/docker.sock
    command: >
      sh -c "
        apk add --no-cache docker-cli dcron &&
        echo '0 2 * * * cd /app && ./bin/cron-cleanup.sh' | crontab - &&
        crond -f
      "
    depends_on:
      - php
```

## Logs et monitoring

### Fichiers de logs

- **Logs de la commande** : `backend/var/log/prod.log` (ou `dev.log`)
- **Logs du script cron** : `backend/var/log/cleanup.log`
- **Logs cron système** : `/var/log/cron` (système)

### Surveillance

```bash
# Voir les derniers logs de nettoyage
tail -f backend/var/log/cleanup.log

# Vérifier l'historique des exécutions cron
grep "clean-expired-documents" /var/log/syslog

# Statistiques d'espace disque
du -sh backend/public/uploads/documents/
```

## Sécurité et bonnes pratiques

### 1. Tests préalables

**Toujours tester en mode dry-run** avant la première exécution :

```bash
docker exec gestasso-php-1 php bin/console app:clean-expired-documents --dry-run
```

### 2. Sauvegardes

Avant d'activer la suppression automatique :

```bash
# Sauvegarder les documents
tar -czf documents-backup-$(date +%Y%m%d).tar.gz backend/public/uploads/documents/

# Sauvegarder la base de données
docker exec gestasso-postgres-1 pg_dump -U your_user your_db > backup-$(date +%Y%m%d).sql
```

### 3. Monitoring

- **Alertes d'échec** : Configurer des alertes si le script échoue
- **Surveillance d'espace** : Monitorer l'espace disque libéré
- **Logs d'audit** : Tous les fichiers supprimés sont loggés avec leurs métadonnées

### 4. Configuration de la période de grâce

Ajustez la période de grâce selon vos besoins :

- **Clubs de loisir** : 60-90 jours (documents moins critiques)
- **Clubs compétitifs** : 30-45 jours (documents renouvelés régulièrement)
- **Conformité légale** : Vérifiez les obligations de conservation

## Dépannage

### Problèmes courants

#### Container Docker non trouvé
```bash
# Lister les containers actifs
docker ps

# Adapter le nom du container dans le script
# Modifier PHP_CONTAINER dans backend/bin/cron-cleanup.sh
```

#### Permissions insuffisantes
```bash
# Vérifier les permissions du répertoire uploads
ls -la backend/public/uploads/

# Corriger si nécessaire
chown -R www-data:www-data backend/public/uploads/
```

#### Échec de suppression de fichiers
- Vérifier que les fichiers ne sont pas en cours d'utilisation
- Vérifier les permissions de suppression
- Vérifier l'espace disque disponible

### Tests et validation

```bash
# 1. Test de la commande
docker exec gestasso-php-1 php bin/console app:clean-expired-documents --dry-run

# 2. Test du script cron
./backend/bin/cron-cleanup.sh

# 3. Vérification des logs
tail -n 20 backend/var/log/cleanup.log

# 4. Vérification de l'espace libéré
df -h
``` 