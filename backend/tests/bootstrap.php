<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Charger les variables d'environnement de test
if (file_exists(dirname(__DIR__).'/.env.test')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env.test');
} elseif (file_exists(dirname(__DIR__).'/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Nettoyer l'état global des tests précédents
if (isset($_ENV['BOOTSTRAP_CLEAR_CACHE_ENV'])) {
    // Nettoyer le cache si nécessaire
    passthru(sprintf(
        'APP_ENV=%s bin/console cache:clear --no-warmup',
        $_ENV['BOOTSTRAP_CLEAR_CACHE_ENV']
    ));
}

// Initialiser la base de données de test
if (isset($_ENV['BOOTSTRAP_RESET_DATABASE']) && $_ENV['BOOTSTRAP_RESET_DATABASE']) {
    passthru('APP_ENV=test bin/console doctrine:database:drop --if-exists --force');
    passthru('APP_ENV=test bin/console doctrine:database:create');
    passthru('APP_ENV=test bin/console doctrine:schema:create');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
