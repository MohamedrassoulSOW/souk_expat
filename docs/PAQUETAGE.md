# Paquetage et distribution — SoukExpat

Ce document décrit les options pour rendre l'application téléchargeable sur le web (PWA), Android, iOS et desktop (Windows/macOS/Linux).

Prérequis généraux

- Node.js (16+ recommandé) et npm ou yarn
- Android Studio (pour Android)
- Xcode + Mac (pour iOS)
- Un certificat / compte développeur pour publication sur Play Store / App Store

A. PWA (Web)

1. Vérifier que le site est servi en HTTPS (requis pour l'installation PWA en production).
2. Vérifier `public/manifest.webmanifest` et les icônes (`public/icons/`).
3. S'assurer que `public/sw.js` est présent et enregistré (le code d'enregistrement se trouve dans `assets/app.js`).
4. Construire les assets et déployer sur un domaine HTTPS. Les navigateurs modernes proposeront l'installation (Chrome/Edge/Firefox). Sur iOS, suivre la procédure manuelle « Partager → Sur l’écran d’accueil ».

B. Android & iOS via Capacitor (recommandé pour une app native)

1. Installer Capacitor :

```bash
npm install --save @capacitor/core @capacitor/cli
npx cap init soukexpat com.soukexpat
```

2. Construire vos assets web (adapter selon votre build system) :

```bash
# exemple (à adapter) :
npm run build
```

3. Copier les fichiers web dans le projet natif :

```bash
npx cap copy
```

4. Ajouter les plateformes :

```bash
npx cap add android
npx cap add ios
```

5. Ouvrir Android Studio / Xcode pour configurer la signature et tester :

```bash
npx cap open android
npx cap open ios
```

6. Pour iOS, il faut un Mac et un compte développeur Apple pour l’archive/Publication.

C. Desktop (Electron)

1. Ajouter Electron et electron-builder :

```bash
npm install --save-dev electron electron-builder
```

2. Ajouter un `main.js` minimal et configurer `package.json` avec les scripts `electron:dev` et `electron:build`.
3. Construire les assets web (ex : `npm run build`), puis lancer le bundle :

```bash
npm run electron:build
```

D. Remarques pratiques

- Icônes : générez des variantes (72, 96, 128, 144, 152, 192, 384, 512) et `apple-touch-icon.png` pour de meilleurs résultats sur stores et iOS.
- Tests : tester l’installation PWA en local via `localhost` (autorisé) ou un tunnel HTTPS (ngrok) pour vérifier `beforeinstallprompt`.
- Sécurité : assurez-vous que vos endpoints API sont protégés (auth/session) — les applications empaquetées appellent vos mêmes endpoints.

E. Fichiers créés par l’agent

- `capacitor.config.json` : configuration de base pour Capacitor
- `package.json` : scripts et dépendances utiles pour scaffolding
- `electron/main.js` : wrapper Electron minimal
- `docs/PAQUETAGE.md` : ce guide

Si vous voulez, je peux maintenant :

- exécuter `php bin/console cache:clear` et vous aider à construire `npm install` + `npx cap init` localement (si vous avez Node installé), ou
- uniquement commiter les fichiers et vous donner la checklist pour la suite.
