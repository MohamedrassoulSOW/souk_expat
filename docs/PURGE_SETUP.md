# Purge automatique des messages (>30 jours)

Ce document explique comment exécuter et planifier la purge automatique des messages de discussion. La commande Symfony fournie nettoie les messages créés il y a plus de 30 jours, supprime les fichiers images associés et supprime les conversations vides.

## Commandes utiles

Simulation (dry-run) — n'effectue aucune suppression :

```bash
cd /chemin/vers/projet
php bin/console app:messages:purge-expired --dry-run
```

Exécution réelle :

```bash
cd /chemin/vers/projet
php bin/console app:messages:purge-expired --no-interaction
```

Remplacez `/chemin/vers/projet` par le chemin absolu de votre projet. Exemple Windows avec espaces :

```powershell
cd /d "C:\Users\hp\Desktop\project\Expat-Souk\teste responsive\souk_expat"
php bin/console app:messages:purge-expired --no-interaction
```

---

## Planification (Linux / macOS)

Ajouter cette ligne au crontab pour exécuter la purge tous les jours à 03:00 :

```cron
0 3 * * * cd "/chemin/vers/projet" && php bin/console app:messages:purge-expired --no-interaction >> /chemin/vers/projet/var/log/purge-messages.log 2>&1
```

- Remplacez `"/chemin/vers/projet"` par le chemin absolu (conservez les guillemets si le chemin contient des espaces).
- Le résultat est journalisé dans `var/log/purge-messages.log`.

Pour éditer le crontab :

```bash
crontab -e
```

---

## Planification (Windows Task Scheduler)

1. Ouvrez le Planificateur de tâches (Task Scheduler).
2. Créez une nouvelle tâche basique ou avancée.
3. Définissez la fréquence (quotidienne) et l'heure (ex. 03:00).
4. Action → Démarrer un programme :
    - Programme/script : `php`
    - Arguments : `C:\\Users\\hp\\Desktop\\project\\Expat-Souk\\teste responsive\\souk_expat\\bin\\console app:messages:purge-expired --no-interaction`
    - Démarrer dans : `C:\\Users\\hp\\Desktop\\project\\Expat-Souk\\teste responsive\\souk_expat`

Assurez-vous que la tâche s'exécute avec un compte ayant accès au système de fichiers et à PHP CLI.

---

## Remarques

- La durée de rétention par défaut est définie dans `src/Service/MessageRetentionService::RETENTION_DAYS` (30 jours).
- Vous pouvez tester en exécution manuelle avant d'ajouter la tâche planifiée.
- Optionnel : rediriger la sortie vers un fichier de log et configurer une rotation des logs.
- L’administrateur peut activer ou désactiver la purge automatique depuis le tableau de bord : `Administration → Paramètres du site` (case "Activer la purge automatique des messages (>30 jours)"). Si la purge est désactivée, la commande renverra une alerte et n’effectuera aucune suppression.
