# Vérifications PWA & WhatsApp

Checklist pour tester l'installation PWA et les liens WhatsApp sur mobile/desktop.

Pré-conditions

- Déployer le site en HTTPS ou tester en `localhost`.
- Avoir au moins une annonce avec `whatsappPhone` renseigné dans le profil.

Tests PWA

1. Ouvrir le site dans Chrome/Edge (HTTPS).
2. Ouvrir les DevTools > Application > Manifest : vérifier que `manifest.webmanifest` est chargé et que les icônes listées existent.
3. Vérifier que le Service Worker `/sw.js` est installé (Application > Service Workers).
4. Vérifier que le site est proposé à l'installation (beforeinstallprompt) — voir bannière personnalisée.
5. Sur Android/Chrome : Installer l'application via le menu → Installer.
6. Sur iOS/Safari : utiliser Share → Add to Home Screen (le manifest aide mais iOS est manuel).

Tests WhatsApp

1. Vérifier le bouton flottant WhatsApp (coin inférieur droit) : il doit ouvrir un lien `https://wa.me/` ou `https://api.whatsapp.com/send?phone=`.
2. Sur une annonce : le bouton « Contacter sur WhatsApp » doit ouvrir la conversation avec le texte pré-rempli.
3. Sur une carte d’annonce : l'icône WhatsApp (avec label sur md+) doit s'ouvrir vers WhatsApp web/mobile.
4. Tester sans être connecté (app.user absent) et connecté (app.user present) — le lien doit rester disponible pour les numéros publics.
5. Tester la sécurité : l'attribut `data-require-safety-ack` est utilisé pour rappeler la checklist de sécurité.

Commandes utiles

- Générer les icônes (nécessite Node + npm + sharp) :

```bash
npm install
npm run icons:generate
```

- Vérifier le PWA manifest et SW :

```bash
# Symfony local server
php -S 127.0.0.1:8000 -t public
# ou via Symfony CLI
symfony server:start
```

Remarques

- Pour Android/iOS natifs (Capacitor), suivez `docs/PAQUETAGE.md` pour initialiser et builder les plateformes.
- Remplacez les icônes générées par des images finales (logo haute qualité) pour publication en production.
