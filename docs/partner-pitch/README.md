# Documents partenaires SoukExpat

Dossier **séparé de l’application web**, destiné aux présentations avec futurs partenaires.

## Sur le site (admin uniquement)

Accessible depuis le **dashboard** (sidebar + bouton) :

- Page : `/partenaires` — dossier détaillé + **Imprimer / PDF / PowerPoint**
- Impression : `/partenaires/imprimer`
- Téléchargements : `/partenaires/dossier.pdf` et `/partenaires/presentation.pptx`

Non listé dans la navbar / footer publics.

## Fichiers générés

| Fichier | Usage |
|---------|--------|
| `SoukExpat-Presentation-Partenaires.pptx` | PowerPoint (14 slides, format 16:9) |
| `SoukExpat-Dossier-Partenaires.pdf` | Dossier PDF détaillé (A4) |

## Régénérer

```bash
cd docs/partner-pitch
npm install
node generate-partner-deck.js
```
