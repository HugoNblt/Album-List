# Déploiement d'Album/List sur le VPS

Guide pas à pas pour mettre l'application en ligne sur `https://album-list.hugonoblot.fr`.
Chaque étape explique **ce qu'on fait**, **pourquoi**, et **comment vérifier** que ça a marché.

- Serveur : Debian 12, Apache 2.4, PHP 8.2, MariaDB 10.11, IP `45.147.96.188`
- Autres sites déjà hébergés (portfolio, SAE…) : **on n'y touche pas**

---

## Vue d'ensemble

```
Internet ──HTTPS──> Apache (vhost album-list.hugonoblot.fr)
                      │  fichiers statiques (CSS, JS, images) servis directement
                      │  fichiers .php transmis à ──> PHP-FPM, pool « album-list »
                      │                                (tourne sous l'utilisateur albumlist)
                      ▼
            /var/www/album-list/current/public   ──> MariaDB, base album_list
```

### Pourquoi ces choix

| Choix | Pourquoi |
|---|---|
| **Utilisateur système dédié `albumlist`** | L'application et ses fichiers lui appartiennent. Si l'app était compromise, l'attaquant n'aurait accès ni à ton portfolio ni aux autres sites. Et plus de problèmes de droits : le même utilisateur déploie, écrit le cache et enregistre les photos. |
| **PHP-FPM au lieu de `mod_php` pour ce site** | Aujourd'hui, tous tes sites exécutent PHP sous `www-data` (le module Apache). Avec PHP-FPM, Album/List a son propre « pool » de processus PHP qui tourne sous `albumlist`, avec ses propres réglages (mémoire, taille d'upload). Les autres sites continuent avec `mod_php`, rien ne change pour eux. |
| **Dossiers `releases/`, `shared/`, `current`** | Structure utilisée par Deployer (phase 2). Chaque version est installée dans son propre dossier, puis le lien `current` bascule dessus d'un coup : pas de coupure pendant un déploiement, et retour arrière instantané. `shared/` contient ce qui doit survivre d'une version à l'autre (configuration, photos de profil, logs). |
| **Base et utilisateur MariaDB dédiés** | L'utilisateur `album_list` n'a accès qu'à sa base, pas à celles des autres sites. |

```
/var/www/album-list/            (propriétaire : albumlist)
├── releases/
│   ├── 1/                      une version complète de l'app
│   └── 2/
├── shared/
│   ├── .env.local              configuration de prod (secrets)
│   ├── public/uploads/         photos de profil
│   └── var/log/                logs Symfony
└── current -> releases/2       la version en ligne
```

---

## Phase 1 — Préparer le serveur

Toutes les commandes se lancent **sur le VPS**, connecté avec ton compte (`ssh mmi24e14@45.147.96.188`).

### 1.1 Mémoire d'échange (swap)

Le serveur a 1 Go de RAM. `composer install` peut en demander plusieurs centaines de Mo d'un coup :
un fichier d'échange évite qu'il soit tué faute de mémoire.

```bash
swapon --show
```

**Si la commande n'affiche rien** (pas de swap), crée un fichier de 1 Go :

```bash
sudo fallocate -l 1G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

- `chmod 600` : seul root peut lire ce fichier (il peut contenir des morceaux de mémoire).
- la ligne ajoutée à `/etc/fstab` réactive le swap à chaque redémarrage.

✅ Vérification : `free -h` affiche une ligne `Swap:` de 1,0Gi.

### 1.2 Paquets manquants : PHP-FPM, extension XML, Composer

```bash
sudo apt update
sudo apt install php8.2-fpm php8.2-xml
```

- `php8.2-fpm` : le gestionnaire de processus PHP qu'Apache contactera pour ce site.
- `php8.2-xml` : extension indispensable à Symfony (lecture de la configuration, des formulaires…).

Le paquet crée un pool par défaut `www` dont on n'a pas besoin. On le désactive pour économiser la mémoire :

```bash
sudo mv /etc/php/8.2/fpm/pool.d/www.conf /etc/php/8.2/fpm/pool.d/www.conf.disabled
```

Composer s'installe avec l'installeur officiel, dont on **vérifie la signature** avant de l'exécuter :

```bash
cd ~
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === trim(file_get_contents('https://composer.github.io/installer.sig'))) { echo 'Installeur vérifié'.PHP_EOL; } else { echo 'Installeur CORROMPU'.PHP_EOL; unlink('composer-setup.php'); exit(1); }"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

✅ Vérification :

```bash
composer --version
php -m | grep -ix xml
```

### 1.3 Utilisateur dédié et dossiers

```bash
sudo adduser --disabled-password --gecos "" --home /var/www/album-list albumlist
sudo chmod 755 /var/www/album-list
```

- `--disabled-password` : pas de mot de passe, on ne pourra s'y connecter que par clé SSH (phase 2) ou via `sudo -u albumlist` depuis ton compte.
- `chmod 755` : Apache (`www-data`) doit pouvoir **lire** les fichiers publics (CSS, JS, images), sans pouvoir y écrire.

On crée l'arborescence, plus une **page d'attente** qui servira à obtenir le certificat HTTPS avant le premier vrai déploiement :

```bash
sudo -u albumlist mkdir -p /var/www/album-list/releases/attente/public /var/www/album-list/shared/public/uploads/avatars /var/www/album-list/shared/var/log
echo '<h1>Album/List arrive bientôt</h1>' | sudo -u albumlist tee /var/www/album-list/releases/attente/public/index.html
sudo -u albumlist ln -sfn /var/www/album-list/releases/attente /var/www/album-list/current
```

✅ Vérification : `ls -la /var/www/album-list` montre `current -> /var/www/album-list/releases/attente`, le tout appartenant à `albumlist`.

### 1.4 Base de données

Génère d'abord un mot de passe solide (sans caractères spéciaux qui poseraient problème dans l'URL de connexion), et **note-le** :

```bash
openssl rand -base64 24 | tr -d '/+='
```

Puis ouvre la console MariaDB en administrateur :

```bash
sudo mariadb
```

```sql
CREATE DATABASE album_list CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'album_list'@'localhost' IDENTIFIED BY 'LE_MOT_DE_PASSE_GÉNÉRÉ';
GRANT ALL PRIVILEGES ON album_list.* TO 'album_list'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

- `'album_list'@'localhost'` : cet utilisateur ne peut se connecter que **depuis le serveur lui-même**, jamais depuis Internet.
- `album_list.*` : droits sur cette base uniquement.

✅ Vérification : `mariadb -u album_list -p album_list -e "SELECT 'connexion OK';"` (saisis le mot de passe).

### 1.5 Pool PHP-FPM dédié

Crée le fichier `/etc/php/8.2/fpm/pool.d/album-list.conf` :

```bash
sudo nano /etc/php/8.2/fpm/pool.d/album-list.conf
```

```ini
[album-list]
; Les processus PHP de ce site tournent sous l'utilisateur dédié
user = albumlist
group = albumlist

; Apache dialogue avec PHP-FPM par ce fichier socket (lisible par www-data uniquement)
listen = /run/php/album-list.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; « ondemand » : aucun processus ne tourne quand personne ne visite le site (économise la RAM)
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 30s
pm.max_requests = 500

; Réglages PHP propres à ce site
php_admin_value[memory_limit] = 192M
php_admin_value[upload_max_filesize] = 4M
php_admin_value[post_max_size] = 6M
php_admin_value[expose_php] = Off
php_admin_value[error_log] = /var/www/album-list/shared/var/log/php-error.log
php_admin_flag[log_errors] = on
php_admin_flag[display_errors] = off
php_admin_value[opcache.memory_consumption] = 64
php_admin_value[realpath_cache_ttl] = 600
```

- `upload_max_filesize = 4M` : les photos de profil sont limitées à 2 Mo par l'app, on laisse un peu de marge.
- `display_errors = off` : en prod, une erreur ne doit jamais afficher de détails techniques au visiteur.

```bash
sudo php-fpm8.2 -t
sudo systemctl restart php8.2-fpm
```

✅ Vérification : `ls -la /run/php/album-list.sock` existe, propriétaire `www-data`.

### 1.6 Site Apache

Active les modules nécessaires pour parler à PHP-FPM et ajouter des en-têtes HTTP :

```bash
sudo a2enmod proxy_fcgi setenvif headers
```

Crée `/etc/apache2/sites-available/album-list.conf` :

```bash
sudo nano /etc/apache2/sites-available/album-list.conf
```

```apache
<VirtualHost *:80>
    ServerName album-list.hugonoblot.fr
    DocumentRoot /var/www/album-list/current/public

    # Ce site n'utilise pas le module PHP d'Apache (mod_php) mais son pool PHP-FPM
    php_admin_flag engine off

    <Directory /var/www/album-list/current/public>
        # FollowSymLinks : nécessaire au lien « current » ; -Indexes : pas de listage des dossiers
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        # Toute URL qui ne correspond pas à un fichier existant est confiée à Symfony
        FallbackResource /index.php
    </Directory>

    # Les fichiers .php sont exécutés par le pool album-list (sous l'utilisateur albumlist)
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:/run/php/album-list.sock|fcgi://album-list"
    </FilesMatch>

    # Dossier des photos de profil : aucun script ne doit jamais y être exécuté
    <Directory /var/www/album-list/current/public/uploads>
        <FilesMatch "\.php$">
            SetHandler none
            Require all denied
        </FilesMatch>
    </Directory>

    # Mise en cache navigateur des fichiers statiques
    <FilesMatch "\.(css|js|svg|webp|png|jpg|woff2)$">
        Header set Cache-Control "public, max-age=604800"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/album-list-error.log
    CustomLog ${APACHE_LOG_DIR}/album-list-access.log combined
</VirtualHost>
```

- `DocumentRoot …/current/public` : seul le dossier `public/` de Symfony est exposé. Le code source, la configuration et les secrets restent inaccessibles depuis le web.
- `php_admin_flag engine off` : garantit que `mod_php` (toujours actif pour tes autres sites) n'exécute rien ici.

```bash
sudo a2ensite album-list
sudo apache2ctl configtest
sudo systemctl reload apache2
```

✅ Vérification : `http://album-list.hugonoblot.fr` affiche « Album/List arrive bientôt ».
`configtest` doit afficher `Syntax OK` (l'avertissement existant sur `public_html_erasmus` concerne un autre site et ne bloque rien).

### 1.7 Certificat HTTPS

Comme pour tes autres sites, `certbot` crée le certificat Let's Encrypt et la version HTTPS du vhost :

```bash
sudo certbot --apache -d album-list.hugonoblot.fr
```

Accepte la **redirection** de HTTP vers HTTPS si la question est posée. Le renouvellement est automatique (tâche `certbot.timer` déjà active sur le serveur).

✅ Vérification : `https://album-list.hugonoblot.fr` s'affiche avec le cadenas, et `http://…` redirige vers `https://…`.

### 1.8 Tester que PHP-FPM répond

On dépose un petit script de test dans la page d'attente :

```bash
echo '<?php echo "PHP " . PHP_VERSION . " via " . PHP_SAPI . " sous " . get_current_user();' | sudo -u albumlist tee /var/www/album-list/releases/attente/public/test.php
```

✅ Vérification : `https://album-list.hugonoblot.fr/test.php` affiche `PHP 8.2.x via fpm-fcgi sous albumlist`.
`fpm-fcgi` prouve que c'est bien PHP-FPM qui répond (et pas `mod_php`, qui afficherait `apache2handler`).

**Supprime ensuite ce fichier** : il ne doit pas rester en ligne.

```bash
sudo -u albumlist rm /var/www/album-list/releases/attente/public/test.php
```

### 1.9 Configuration de production de l'app

Ce fichier contient les secrets. Il vit dans `shared/` et ne quitte jamais le serveur (il n'est pas dans Git).

Génère le secret de l'application :

```bash
openssl rand -hex 32
```

```bash
sudo -u albumlist nano /var/www/album-list/shared/.env.local
```

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=LE_SECRET_GÉNÉRÉ

DATABASE_URL="mysql://album_list:LE_MOT_DE_PASSE_BDD@127.0.0.1:3306/album_list?serverVersion=10.11.19-MariaDB&charset=utf8mb4"

SPOTIFY_CLIENT_ID=ton_client_id
SPOTIFY_CLIENT_SECRET=ton_client_secret

# Bêta privée : inscriptions fermées
APP_REGISTRATION_ENABLED=0
```

```bash
sudo chmod 600 /var/www/album-list/shared/.env.local
```

- `APP_DEBUG=0` : désactive la barre de debug et les pages d'erreur détaillées.
- `chmod 600` : seul `albumlist` peut lire ce fichier.
- Les identifiants Spotify sont ceux de ton `.env.local` de dev, ou mieux, une application Spotify dédiée à la prod (https://developer.spotify.com/dashboard).

---

## Phase 2 — Déploiement automatisé (Deployer + GitHub Actions)

### Comment ça marche

```
tu pousses un tag v1.1.0 ──> GitHub Actions
                               1. job « qa » : composer qa (tests, PHPStan, audit) sur une base MariaDB jetable
                               2. si tout passe, job « deploy » : Deployer se connecte en SSH au VPS
                                  (utilisateur albumlist, clé SSH stockée dans les secrets GitHub)
                                                      │
                                                      ▼
VPS : releases/N   ← télécharge le code du tag depuis GitHub
                   ← relie shared/.env.local, shared/var/log, shared/public/uploads
                   ← composer install --no-dev (+ cache:clear)
                   ← doctrine:migrations:migrate
      current ──> releases/N   (bascule instantanée)
      PHP-FPM rechargé (vide le cache OPcache de l'ancienne version)
      anciennes versions au-delà de 5 supprimées
```

Les fichiers du dépôt qui pilotent tout ça :

| Fichier | Rôle |
|---|---|
| `deploy.php` | Configuration de Deployer : serveur, dossiers partagés, étapes ajoutées (migrations, rechargement de PHP-FPM) |
| `.github/workflows/ci.yml` | CI : `composer qa` sur chaque pull request et chaque push sur `develop` / `main` |
| `.github/workflows/deploy.yml` | CD : sur un tag `v*` (ou lancé à la main), relance la CI puis déploie |

### 2.1 Autoriser `albumlist` à recharger PHP-FPM

Après chaque déploiement, Deployer doit recharger PHP-FPM. On autorise **cette seule commande** à `albumlist`, sans mot de passe :

```bash
sudo visudo -f /etc/sudoers.d/albumlist
```

```
albumlist ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.2-fpm
```

- `visudo` vérifie la syntaxe avant d'enregistrer : une erreur dans un fichier sudoers peut bloquer `sudo` sur tout le serveur.
- La règle est volontairement restrictive : `albumlist` ne peut rien faire d'autre en root.

✅ Vérification : `sudo -u albumlist sudo -n /usr/bin/systemctl reload php8.2-fpm && echo OK` affiche `OK`.

### 2.2 Clé SSH de déploiement

GitHub Actions doit pouvoir se connecter au VPS en tant que `albumlist`. On crée une paire de clés **dédiée** à cet usage
(si elle fuitait, il suffirait de la supprimer du serveur, sans toucher à tes propres clés).

**Sur ton PC** (PowerShell ou terminal WSL), sans phrase de passe car GitHub Actions doit l'utiliser seul :

```bash
ssh-keygen -t ed25519 -C "album-list-deploy" -f ~/.ssh/album_list_deploy -N ""
```

Deux fichiers sont créés : `album_list_deploy` (clé **privée**, secrète) et `album_list_deploy.pub` (clé **publique**).

**Sur le VPS**, autorise la clé publique à se connecter en tant que `albumlist`. Le dossier personnel de `albumlist`
est `/var/www/album-list`, donc ses clés autorisées vont dans `/var/www/album-list/.ssh/authorized_keys` :

```bash
sudo -u albumlist mkdir -p -m 700 /var/www/album-list/.ssh
sudo -u albumlist nano /var/www/album-list/.ssh/authorized_keys
```

Colle le contenu **du fichier `.pub`** (une seule ligne commençant par `ssh-ed25519`), enregistre, puis :

```bash
sudo chmod 600 /var/www/album-list/.ssh/authorized_keys
```

Vérifie aussi que la configuration SSH du serveur n'autorise pas que certains utilisateurs :

```bash
sudo sshd -T | grep -iE "allowusers|allowgroups"
```

S'il n'y a aucune ligne, tout utilisateur peut se connecter par clé. Sinon, il faudra y ajouter `albumlist`.

✅ Vérification, **depuis ton PC** :

```bash
ssh -i ~/.ssh/album_list_deploy albumlist@45.147.96.188 'whoami && sudo -n /usr/bin/systemctl reload php8.2-fpm && echo "reload OK"'
```

Doit afficher `albumlist` puis `reload OK`, **sans demander de mot de passe**.

### 2.3 Empreinte du serveur (known_hosts)

Pour éviter de se connecter à un faux serveur, GitHub Actions doit connaître à l'avance l'empreinte SSH du VPS.

Depuis ton PC :

```bash
ssh-keyscan -t ed25519 45.147.96.188
```

Compare l'empreinte avec celle affichée **sur le VPS** :

```bash
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
```

et depuis ton PC :

```bash
ssh-keyscan -t ed25519 45.147.96.188 | ssh-keygen -lf -
```

Les deux lignes `SHA256:…` doivent être **identiques**. Garde la sortie de la première commande (`45.147.96.188 ssh-ed25519 AAAA…`) pour l'étape suivante.

### 2.4 Secrets GitHub

Sur GitHub : *Settings → Environments → New environment*, nomme-le **`production`**.

Dans cet environnement, section *Environment secrets*, ajoute :

| Nom | Valeur |
|---|---|
| `SSH_PRIVATE_KEY` | le contenu **complet** du fichier `~/.ssh/album_list_deploy` (clé privée, de `-----BEGIN` à `-----END…KEY-----` inclus) |
| `SSH_KNOWN_HOSTS` | la ligne obtenue à l'étape 2.3 (`45.147.96.188 ssh-ed25519 AAAA…`) |

Option recommandée, dans le même écran : *Deployment branches and tags → Selected branches and tags*, ajoute la règle de tag `v*`
et la branche `develop`. Ainsi, seuls les tags de version (et le lancement manuel depuis `develop`) peuvent déployer.

### 2.5 Ajouter les fichiers au dépôt

Les fichiers `deploy.php`, `.github/workflows/` et ce guide sont à fusionner dans `develop` via une pull request.
Cette première PR déclenche aussi **la première exécution de la CI** : vérifie dans l'onglet *Actions* que le job `composer qa` est vert.

---

## Phase 3 — Premier déploiement

### 3.1 Déployer `v1.0.0`

Le tag `v1.0.0` a été posé avant que les workflows n'existent : on le déploie **à la main** depuis GitHub.

*Actions → Deploy → Run workflow* :
- **Use workflow from** : `develop` (la branche qui contient `deploy.php`)
- **Tag à déployer** : `v1.0.0`

Le workflow relance la CI puis déploie. Suis les étapes en direct dans les logs du job « Déployer en production ».

✅ Vérification : `https://album-list.hugonoblot.fr` affiche l'application (fil vide, bouton « Connexion », pas de lien « Inscription »).

Sur le VPS :

```bash
ls -la /var/www/album-list /var/www/album-list/releases
```

`current` pointe maintenant vers `releases/1`.

### 3.2 Créer les comptes testeurs

```bash
cd /var/www/album-list/current
sudo -u albumlist php bin/console app:user:create user1 user1@exemple.fr
```

Recommence pour `user2`, `user3`, `user4`. Chaque commande affiche le mot de passe généré **une seule fois** : note-le
et transmets-le au testeur par un canal privé. La connexion se fait avec l'**e-mail**.

### 3.3 Nettoyer la page d'attente

```bash
sudo -u albumlist rm -rf /var/www/album-list/releases/attente
```

---

## Au quotidien

| Je veux… | Je fais |
|---|---|
| Publier une nouvelle version | PR `develop` → `main`, fusion, puis tag : `git tag -a v1.1.0 -m "…"` et `git push origin v1.1.0` → déploiement automatique |
| Revenir à la version précédente | Sur le VPS : `cd /var/www/album-list && ls releases`, puis `sudo -u albumlist ln -sfn /var/www/album-list/releases/<N-1> current && sudo systemctl reload php8.2-fpm` (ou `vendor/bin/dep rollback` depuis un poste qui a la clé SSH) |
| Voir les erreurs de l'app | `tail -f /var/www/album-list/shared/var/log/prod.log` |
| Voir les erreurs Apache / PHP | `sudo tail -f /var/log/apache2/album-list-error.log` et `/var/www/album-list/shared/var/log/php-error.log` |
| Rouvrir les inscriptions | `APP_REGISTRATION_ENABLED=1` dans `shared/.env.local`, puis `cd current && sudo -u albumlist php bin/console cache:clear` |

> Attention au retour arrière : si la version annulée contenait une migration de base de données, la base reste migrée.
> Les migrations doivent donc rester compatibles avec la version précédente (ajouter une colonne plutôt que d'en supprimer une).
