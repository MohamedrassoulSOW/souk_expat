# Déploiement SoukExpat (Hostinger / mutualisé)

Domaine cible : `https://soukexpat.com`

## 1. Avant l’upload (machine locale)

### Option A — paquet propre (recommandé Hostinger)

Génère un dossier `prod-package/` **sans** les fichiers inutiles (`.git`, `node_modules`, `tests`, `docs`, `electron`, `var`, `.env*`, etc.) :

```bash
composer install --no-dev --optimize-autoloader
php bin/console asset-map:compile --env=prod
php bin/export-prod.php --with-vendor --zip
```

Uploadez **uniquement** le contenu de `prod-package/` (ou le ZIP).

Liste d’exclusion : `deploy/exclude-prod.txt`

### Option B — commandes manuelles

```bash
# Depuis la racine du projet souk_expat
composer install --no-dev --optimize-autoloader
php bin/console asset-map:compile --env=prod
php bin/console cache:warmup --env=prod
```

Ou en une fois sur le serveur après upload : `php bin/prepare-prod.php`

Inclure dans l’upload :
- le code (dont `migrations/`, notamment `Version20260805190000` = type média slider)
- `vendor/` **ou** lancer Composer sur le serveur
- `public/assets/` si déjà compilé en local (sinon `prepare-prod` le régénère)

**Ne jamais uploader** : `node_modules/` (~400 Mo), `.git/`, `var/`, `tests/`, `electron/`, `docs/`, fichiers `.env*`.

## 2. Fichier d’environnement sur le serveur (contournement Hostinger)

En prod, Symfony lit **`.env.local`** à la racine. Comme l’upload de `.env*` est bloqué :

### Méthode recommandée (FTP + SSH)

1. En local, remplir `deploy/env.prod.template` (même contenu que `.env.prod.example`).
2. Uploader **`deploy/env.prod.template`** (ce nom **passe** l’upload).
3. En SSH à la racine du projet :

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # → APP_SECRET
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # → JWT_SECRET
# Coller les clés dans deploy/env.prod.template (nano) si besoin, puis :
php bin/install-env-local.php
```

Cela crée `.env.local` sur le serveur.

### Alternative sans template

```bash
nano .env.local
# coller les variables, enregistrer
```

Ou File Manager Hostinger → **Nouveau fichier** nommé `.env.local` (création sur place, pas d’upload).

Remplir au minimum :

| Variable | Exemple / note |
|----------|----------------|
| `APP_ENV` | `prod` |
| `APP_DEBUG` | `0` |
| `APP_SECRET` | clé unique (≥ 32 hex) |
| `DATABASE_URL` | MySQL Hostinger |
| `DEFAULT_URI` | `https://soukexpat.com` (sans `/` final) |
| `MAILER_DSN` | SMTP Hostinger (pas Mailtrap) |
| `JWT_SECRET` | clé **distincte** de `APP_SECRET` |

Optionnel : Mercure (chat live) — sans hub réel, laisser les URLs factices du template.

## 3. Document root (évite le 403)

- **Idéal** : Domaines → soukexpat.com → racine = `…/public`
- **Sinon** : racine = dossier projet (le dépôt fournit `index.php` + `.htaccess` qui délèguent à `public/`)

## 4. Commandes SSH (racine du projet)

```bash
composer install --no-dev --optimize-autoloader
php bin/prepare-prod.php
```

Le script :
- crée `var/` et `public/uploads/{annonces,avatars,categories,sliders,messages}`
- vérifie / génère les icônes PWA
- compile les assets
- applique les **migrations** (slider `media_type`, etc.)
- chauffe le cache `prod`

### Permissions
- Dossiers `755`, fichiers `644`
- Écriture obligatoire : `var/`, `public/uploads/`

## 5. Après mise en ligne

```bash
php bin/console about --env=prod
php bin/console app:mailer:smoke-test votre@email.com --env=prod
php bin/health-check.php https://soukexpat.com
```

Checklist manuelle :
1. Accueil + `/api/v1` + `manifest.webmanifest` + `sw.js` répondent en HTTPS
2. Admin → Paramètres : numéro WhatsApp du site (FAB)
3. Compte admin : `php bin/console app:user:create-editor --env=prod`
4. Tester upload slider (image + vidéo) et annonce multi-photos
5. Cron purge messages (> 30 j) :
   ```
   15 3 * * * cd /chemin/projet && php bin/console app:messages:purge-expired --env=prod
   ```
   Exemple aussi dans `deploy/cron.example`

## 6. API mobile

Base : `https://soukexpat.com/api/v1`  
Auth : JWT Bearer (`JWT_SECRET` / `JWT_TTL`)  
Images API = **BLOB** en base (pas de fichiers disque).

## 7. Fonctionnalités récentes à valider en prod

- Analytics admin + export Excel / Word / PDF
- Purge annonces / messages (durée jours ou mois)
- Slider : plusieurs images **et** vidéos MP4/WEBM
- Annonces : jusqu’à 8 photos

## Dépannage

| Symptôme | Cause fréquente |
|----------|-----------------|
| 403 | Document root ≠ `public/` ou permissions |
| Page blanche | Cache / `.env.local` manquant / `APP_DEBUG=0` sans logs |
| Upload `.env*` refusé | Normal sur Hostinger → utiliser `deploy/env.prod.template` + `php bin/install-env-local.php` |
| Assets cassés | Relancer `asset-map:compile` + `cache:warmup` |
| Migration échoue | `DATABASE_URL` incorrect ou droits MySQL |
| Chat non temps réel | Mercure absent (normal sur mutualisé sans hub) |
