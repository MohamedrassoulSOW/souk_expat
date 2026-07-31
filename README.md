# SoukExpat

Marketplace pour la communauté expatriée au Maroc (Symfony 8).

## Développement local

```bash
composer install
# Configurer .env.local (DATABASE_URL, etc.)
php bin/console doctrine:migrations:migrate
symfony server:start
# ou : php -S 127.0.0.1:8001 -t public
```

## Mise en production

### 1. Variables d’environnement

Copier `.env.prod.example` vers `.env.local` sur le serveur et renseigner :

- `APP_ENV=prod` / `APP_DEBUG=0`
- `APP_SECRET` (unique)
- `DATABASE_URL`
- `DEFAULT_URI=https://votre-domaine`
- `MAILER_DSN` (SMTP réel)
- `JWT_SECRET` (recommandé pour l’API mobile)
- Mercure (optionnel pour le chat live)

### 2. Déploiement

**Document root = dossier `public/`** (cPanel → Domaines → « Racine du document »).

Si l’hébergeur force `public_html` = racine du projet, le dépôt contient `index.php` + `.htaccess` à la racine qui redirigent vers `public/`. Mieux : pointer le domaine directement sur `public/`.

```bash
composer install --no-dev --optimize-autoloader
php bin/prepare-prod.php
```

Permissions : dossiers `755`, fichiers `644`, écriture sur `var/` et `public/uploads/`.

**403 Forbidden** = presque toujours mauvaise racine web ou permissions. Vérifier que `public/index.php` est bien dans le document root.

### 3. Vérifications

```bash
php bin/console about --env=prod
php bin/console app:mailer:smoke-test votre@email.com --env=prod
curl -I https://votre-domaine/api/v1
```

### API mobile

Base : `/api/v1` (JWT Bearer). Voir `GET /api/v1` pour la liste des endpoints.

## Structure utile

| Chemin | Rôle |
|--------|------|
| `public/` | Racine web |
| `src/Controller/Api/V1/` | API mobile |
| `src/Controller/Admin/` | Back-office |
| `templates/` | Twig site + admin |
| `var/` | Cache / logs (non versionné) |
