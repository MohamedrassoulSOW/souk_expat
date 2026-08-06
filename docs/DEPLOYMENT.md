**Déploiement (rapide)**

Ce document résume les étapes pour mettre l'application en production.

- Préparer le serveur (Ubuntu 22.04+ recommandé) : PHP 8.4 FPM, MySQL/Postgres, Nginx, certbot
- Copier `/path/to/project` sur le serveur et placer les variables d'environnement dans `.env.local` (ne pas committer)
- Installer dépendances PHP : `composer install --no-dev --optimize-autoloader`
- Construire les assets : `npm ci && npm run build:web` (ou pipeline CI)
- Exécuter : `php bin/console doctrine:migrations:migrate --no-interaction --env=prod`
- Lancer `php bin/prepare-prod.php` pour vérifier prérequis et installer assets
- Configurer Nginx avec `deploy/nginx.souk_expat.conf.example` et activer SSL via Let's Encrypt
- Ajouter cron (voir `deploy/cron.example`) pour la purge quotidienne des messages

Sécurité :

- Ne commitez jamais `.env.local` ni secrets. Utilisez les variables d'environnement ou un store de secrets.
- Changez tous les secrets par défaut (JWT, MERCURE_JWT_SECRET, DB password, APP_SECRET).

Git :

- Si le dépôt contient de gros bins (ex. `electron.exe`), utilisez `git-filter-repo` ou BFG pour nettoyer l'historique avant `git push`.
