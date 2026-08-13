SoukExpat — mise en production (Hostinger)
==========================================

1) Upload
   - Uploadez le contenu de prod-package/ (ou dézippez prod-package.zip)
   - Idéal : racine du domaine = dossier public/
   - Sinon : racine = dossier projet (le .htaccess racine délègue vers public/)
   - Vérifier que public/vendor/ est présent (Bootstrap, Icons, Tom Select)
     sinon la navbar / recherche cassent. prepare-prod.php refuse de continuer sinon.

2) Secrets
   - Remplissez deploy/env.prod.template :
       APP_SECRET, DATABASE_URL, DEFAULT_URI, MAILER_DSN, JWT_SECRET…
   - MAILER_DSN = SMTP Hostinger pour contact@soukexpat.com (pas Mailtrap, pas null://)
   - En SSH à la racine du projet :
       php bin/install-env-local.php
       php bin/prepare-prod.php
       php bin/console doctrine:migrations:migrate --env=prod --no-interaction

3) Vérifications
       php bin/health-check.php https://soukexpat.com
       php bin/console app:mailer:smoke-test --env=prod votre@email.com
       curl -I https://soukexpat.com/manifest.webmanifest
       curl -I https://soukexpat.com/sw.js
       curl -I https://soukexpat.com/vendor/bootstrap/css/bootstrap.min.css

4) Après go-live
   - Admin → Paramètres : WhatsApp du site
   - Compte admin : php bin/console app:user:create-editor --env=prod
   - Google OAuth : deploy/GOOGLE_OAUTH.txt (+ GOOGLE_CLIENT_ID/SECRET dans .env.local)
   - Cron purge messages (optionnel) : voir deploy/cron.example
   - HTTPS obligatoire (PWA + cookies)
   - Après chaque déploiement code :
       php bin/console asset-map:compile --env=prod
       php bin/console cache:clear --env=prod
   - Si le site s’affiche sans CSS : le .htaccess racine ne doit PAS bloquer
     /vendor/bootstrap/ (voir règle RewriteRule vendor/(bootstrap|…) → public/vendor/)

Ne jamais committer / uploader un .env.local avec de vrais secrets dans Git.
