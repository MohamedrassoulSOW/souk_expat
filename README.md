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

Guide détaillé : **[DEPLOY.md](DEPLOY.md)** (Hostinger).

### Résumé

1. Copier `.env.prod.example` → `.env.local` sur le serveur (secrets + `DEFAULT_URI=https://soukexpat.com`)
2. Document root = dossier **`public/`**
3. Sur le serveur :

```bash
composer install --no-dev --optimize-autoloader
php bin/prepare-prod.php
```

4. Vérifier : `https://soukexpat.com/` et `https://soukexpat.com/api/v1`

Permissions : dossiers `755`, fichiers `644` ; écriture sur `var/` et `public/uploads/`.

**403 Forbidden** = mauvaise racine web ou permissions.

### API mobile

Base : `/api/v1` (JWT Bearer). Voir `GET /api/v1`.  
CRUD annonces + messagerie ; images API en **BLOB** (base de données).

## Structure utile

| Chemin | Rôle |
|--------|------|
| `public/` | Racine web |
| `src/Controller/Api/V1/` | API mobile |
| `src/Controller/Admin/` | Back-office |
| `templates/` | Twig site + admin |
| `var/` | Cache / logs (non versionné) |
| `DEPLOY.md` | Checklist mise en ligne |
