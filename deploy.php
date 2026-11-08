<?php

/**
 * Déploiement d'Album/List avec Deployer (https://deployer.org).
 *
 * Usage (depuis le conteneur de dev ou GitHub Actions) :
 *   vendor/bin/dep deploy --tag=v1.0.0     déploie un tag
 *   vendor/bin/dep rollback                revient à la version précédente
 *   vendor/bin/dep releases                liste les versions installées
 *
 * Voir docs/deploiement.md pour le fonctionnement détaillé.
 */

namespace Deployer;

require 'recipe/symfony.php';

// --- Projet ------------------------------------------------------------------

set('application', 'album-list');
// Dépôt public : le serveur télécharge le code sans clé GitHub
set('repository', 'https://github.com/HugoNblt/Album-List.git');
// Nombre de versions conservées dans releases/ (pour pouvoir revenir en arrière)
set('keep_releases', 5);

// --- Fichiers partagés entre les versions (dossier shared/ du serveur) -------

// .env.local : configuration de prod et secrets, créé une fois à la main sur le serveur
set('shared_files', ['.env.local']);
// var/log : logs Symfony ; public/uploads : photos de profil des utilisateurs
set('shared_dirs', ['var/log', 'public/uploads']);

// Le même utilisateur (albumlist) déploie ET exécute PHP-FPM :
// aucun réglage de droits d'écriture n'est nécessaire
set('writable_dirs', []);

// --- Serveur -----------------------------------------------------------------

host('production')
    ->set('hostname', '45.147.96.188')
    ->set('remote_user', 'albumlist')
    ->set('deploy_path', '/var/www/album-list')
    ->set('http_user', 'albumlist');

// --- Étapes ajoutées au déploiement standard Symfony -------------------------

// Applique les migrations de base de données avant de mettre la version en ligne
after('deploy:vendors', 'database:migrate');

// PHP-FPM garde en mémoire (OPcache) le code de l'ancienne version :
// on le recharge juste après la bascule du lien « current »
desc('Recharge PHP-FPM pour vider OPcache');
task('php-fpm:reload', function () {
    run('sudo /usr/bin/systemctl reload php8.2-fpm');
});
after('deploy:symlink', 'php-fpm:reload');

// Si une étape échoue, on libère le verrou pour pouvoir relancer un déploiement
after('deploy:failed', 'deploy:unlock');
