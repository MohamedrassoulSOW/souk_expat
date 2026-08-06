/**
 * Génère le PowerPoint + PDF de présentation partenaires SoukExpat.
 * Usage : node generate-partner-deck.js
 *
 * Sortie (hors application web) :
 *   - SoukExpat-Presentation-Partenaires.pptx
 *   - SoukExpat-Dossier-Partenaires.pdf
 */
const fs = require('fs');
const path = require('path');
const PptxGenJS = require('pptxgenjs');
const PDFDocument = require('pdfkit');

const ROOT = path.resolve(__dirname, '../..');
const OUT_DIR = __dirname;
const LOGO = path.join(ROOT, 'public', 'logo-souk-expat.png');
const LOGO_WHITE = path.join(ROOT, 'public', 'logo-souk-expat-blanc.png');

const COLORS = {
  navy: '1B2E4B',
  cyan: '4B79A1',
  light: 'F4F7FB',
  white: 'FFFFFF',
  muted: '64748B',
  dark: '0F172A',
  warn: 'B45309',
};

const CONTACT_EMAIL = 'contact@soukexpat.com';
const SITE = 'https://soukexpat.com';
const DATE_FR = new Date().toLocaleDateString('fr-FR', {
  year: 'numeric',
  month: 'long',
  day: 'numeric',
});

const slidesContent = [
  {
    title: 'SoukExpat',
    subtitle: 'Le Marché Mondial des Expatriés',
    type: 'cover',
    bullets: [
      'Marketplace communautaire pour les expatriés au Maroc',
      'Acheter, vendre et échanger en toute confiance',
      SITE.replace('https://', ''),
    ],
  },
  {
    title: 'La vision',
    type: 'content',
    lead: 'Faciliter la vie des expatriés en créant un espace simple et sécurisé pour se rencontrer autour d’annonces locales.',
    bullets: [
      'Communauté ciblée : expatriés et résidents au Maroc',
      'Mise en relation directe entre acheteurs et vendeurs',
      'Plateforme claire, modérée et gratuite à consulter',
      'Expérience web moderne, mobile et installable (PWA)',
      'Promesse : « Trouvez tout, partout. »',
    ],
  },
  {
    title: 'Le problème & la solution',
    type: 'two-col',
    leftTitle: 'Problème',
    left: [
      'Annonces dispersées (groupes, réseaux)',
      'Peu de confiance et peu de modération',
      'Contact difficile ou peu clair',
      'Pas d’outil pensé pour les expat au Maroc',
    ],
    rightTitle: 'Solution SoukExpat',
    right: [
      'Marketplace centralisée et claire',
      'Annonces modérées avant publication',
      'Messagerie + WhatsApp intégrés',
      'Filtres par catégorie et ville',
    ],
  },
  {
    title: 'Pour qui ?',
    type: 'cards',
    cards: [
      {
        title: 'Acheteurs',
        text: 'Trouvent rapidement un bien ou un service près de chez eux, filtrent par ville/catégorie, contactent le vendeur.',
      },
      {
        title: 'Vendeurs',
        text: 'Publient une annonce avec photos, suivent la validation, gèrent leurs offres et répondent aux messages.',
      },
      {
        title: 'Partenaires',
        text: 'Accèdent à une audience expatriée engagée (visibilité, sponsoring, distribution co-brandée).',
      },
    ],
  },
  {
    title: 'Parcours acheteur',
    type: 'steps',
    steps: [
      { n: '1', t: 'Découvrir', d: 'Accueil, recherche, filtres catégorie & ville' },
      { n: '2', t: 'Consulter', d: 'Fiche annonce, photos, profil vendeur' },
      { n: '3', t: 'Contacter', d: 'Messagerie SoukExpat et/ou WhatsApp' },
      { n: '4', t: 'Conclure', d: 'Échange hors plateforme, en personne' },
    ],
  },
  {
    title: 'Parcours vendeur',
    type: 'steps',
    steps: [
      { n: '1', t: 'S’inscrire', d: 'Compte + profil (WhatsApp optionnel)' },
      { n: '2', t: 'Publier', d: 'Titre, prix MAD, photos, catégorie, ville' },
      { n: '3', t: 'Modération', d: 'Validation par l’équipe avant mise en ligne' },
      { n: '4', t: 'Échanger', d: 'Messages / WhatsApp, suivi Mes annonces' },
    ],
  },
  {
    title: 'Fonctionnalités clés',
    type: 'content',
    bullets: [
      'Annonces avec photos, prix (MAD), catégories et villes',
      'Messagerie intégrée : texte, photo, partage de position',
      'Contact WhatsApp (profil, annonce, bouton site)',
      'Profil public vendeur et gestion « Mes annonces »',
      'PWA installable (desktop, Android, iOS)',
      'API mobile /api/v1 (JWT) pour clients futurs',
      'Pages : À propos, Comment ça marche, FAQ, Contact, Blog',
    ],
  },
  {
    title: 'Confiance & sécurité',
    type: 'content',
    lead: 'SoukExpat n’est pas une marketplace de paiement : son rôle est la mise en relation.',
    bullets: [
      'Modération des annonces avant publication',
      'Popup de sécurité : ne pas payer avant réception du produit',
      'Aucune gestion des paiements, livraisons ou litiges',
      'Responsabilité limitée à la mise en contact des membres',
      'Conservation des messages limitée (purge ~30 jours)',
      'Signalement possible via le formulaire de contact',
    ],
  },
  {
    title: 'Back-office & analytics',
    type: 'content',
    bullets: [
      'Tableau de bord éditeur / administrateur',
      'Validation, refus ou suspension des annonces',
      'Gestion des catégories, villes et slider d’accueil',
      'Messages de contact et supervision des conversations',
      'Analytics avec exports Excel, Word et PDF',
      'Paramètres du site (textes, réseaux, FAQ)',
      'Gestion des utilisateurs (rôle admin)',
    ],
  },
  {
    title: 'Technologie',
    type: 'content',
    bullets: [
      'Application web Symfony 8 / PHP 8.4',
      'Interface Twig + Bootstrap, responsive',
      'Base de données MySQL / MariaDB',
      'API REST JWT pour applications mobiles',
      'PWA (manifest + service worker)',
      'Déploiement production HTTPS (soukexpat.com)',
    ],
  },
  {
    title: 'Opportunités partenaires',
    type: 'content',
    lead: 'Des leviers de collaboration alignés avec la communauté expatriée.',
    bullets: [
      'Visibilité / sponsoring (espaces d’accueil, slider)',
      'Campagnes co-brandées vers l’audience expat au Maroc',
      'Distribution (web, PWA, apps via API)',
      'Rapports d’audience et d’activité (analytics)',
      'Partenariats locaux : immobilier, mobilité, services, lifestyle',
    ],
  },
  {
    title: 'Pourquoi SoukExpat ?',
    type: 'content',
    bullets: [
      'Positionnement clair : communauté expatriée au Maroc',
      'Produit déjà opérationnel en ligne',
      'Expérience mobile-first + installable',
      'Modèle de confiance transparent (pas de fausse promesse)',
      'Socle technique moderne et extensible (API)',
      'Valeurs : Sécurité · Communauté · Rapidité',
    ],
  },
  {
    title: 'Prochaines étapes',
    type: 'content',
    bullets: [
      'Découverte produit sur soukexpat.com',
      'Démo guidée (parcours acheteur / vendeur / admin)',
      'Définition du format de partenariat',
      'Calendrier de collaboration et indicateurs de succès',
    ],
  },
  {
    title: 'Merci',
    type: 'cover',
    subtitle: 'Ensemble, simplifions la vie des expatriés au Maroc',
    bullets: [
      SITE.replace('https://', ''),
      CONTACT_EMAIL,
      'SoukExpat — Le Marché Mondial des Expatriés',
    ],
  },
];

function addFooter(slide, pptx, page, total) {
  slide.addShape(pptx.ShapeType.rect, {
    x: 0, y: 5.25, w: 10, h: 0.375,
    fill: { color: COLORS.navy },
  });
  slide.addText('SoukExpat — Document partenaires confidentiel', {
    x: 0.4, y: 5.3, w: 7, h: 0.28,
    fontSize: 10, color: COLORS.white, fontFace: 'Calibri',
  });
  slide.addText(`${page} / ${total}`, {
    x: 8.2, y: 5.3, w: 1.4, h: 0.28,
    fontSize: 10, color: COLORS.white, fontFace: 'Calibri', align: 'right',
  });
}

function addHeaderBar(slide, pptx) {
  slide.addShape(pptx.ShapeType.rect, {
    x: 0, y: 0, w: 10, h: 0.12,
    fill: { color: COLORS.cyan },
  });
}

async function buildPptx() {
  const pptx = new PptxGenJS();
  pptx.defineLayout({ name: 'LAYOUT_16x9', width: 10, height: 5.625 });
  pptx.layout = 'LAYOUT_16x9';
  pptx.author = 'SoukExpat';
  pptx.title = 'SoukExpat — Présentation partenaires';
  pptx.subject = 'Marketplace expatriés au Maroc';

  const total = slidesContent.length;
  const hasLogo = fs.existsSync(LOGO);
  const hasLogoWhite = fs.existsSync(LOGO_WHITE);

  slidesContent.forEach((s, idx) => {
    const slide = pptx.addSlide();
    const page = idx + 1;

    if (s.type === 'cover') {
      slide.addShape(pptx.ShapeType.rect, {
        x: 0, y: 0, w: 10, h: 5.625,
        fill: { color: COLORS.navy },
      });
      slide.addShape(pptx.ShapeType.rect, {
        x: 0, y: 0, w: 0.2, h: 5.625,
        fill: { color: COLORS.cyan },
      });
      if (hasLogoWhite || hasLogo) {
        slide.addImage({
          path: hasLogoWhite ? LOGO_WHITE : LOGO,
          x: 0.7, y: 0.55, w: 2.5, h: 1.15,
        });
      }
      slide.addText(s.title, {
        x: 0.7, y: 2.0, w: 8.5, h: 0.7,
        fontSize: 40, bold: true, color: COLORS.white, fontFace: 'Calibri',
      });
      slide.addText(s.subtitle || '', {
        x: 0.7, y: 2.7, w: 8.5, h: 0.4,
        fontSize: 18, color: '93C5E4', fontFace: 'Calibri',
      });
      slide.addText((s.bullets || []).join('\n'), {
        x: 0.7, y: 3.35, w: 8.5, h: 1.5,
        fontSize: 15, color: COLORS.white, fontFace: 'Calibri',
        valign: 'top',
      });
      return;
    }

    slide.addShape(pptx.ShapeType.rect, {
      x: 0, y: 0, w: 10, h: 5.625,
      fill: { color: COLORS.light },
    });
    addHeaderBar(slide, pptx);
    if (hasLogo) {
      slide.addImage({ path: LOGO, x: 8.35, y: 0.22, w: 1.35, h: 0.62 });
    }
    slide.addText(s.title, {
      x: 0.45, y: 0.28, w: 7.6, h: 0.5,
      fontSize: 26, bold: true, color: COLORS.navy, fontFace: 'Calibri',
    });

    if (s.lead) {
      slide.addText(s.lead, {
        x: 0.45, y: 0.85, w: 9.1, h: 0.5,
        fontSize: 13, color: COLORS.muted, fontFace: 'Calibri', italic: true,
      });
    }

    if (s.type === 'content') {
      const y0 = s.lead ? 1.5 : 1.0;
      slide.addText(
        (s.bullets || []).map((b) => ({ text: b, options: { bullet: true, breakLine: true } })),
        {
          x: 0.55, y: y0, w: 8.9, h: 3.45,
          fontSize: 15, color: COLORS.dark, fontFace: 'Calibri',
          paraSpacing: 10,
        }
      );
    }

    if (s.type === 'two-col') {
      slide.addShape(pptx.ShapeType.roundRect, {
        x: 0.4, y: 1.0, w: 4.4, h: 3.85,
        fill: { color: COLORS.white },
      });
      slide.addShape(pptx.ShapeType.roundRect, {
        x: 5.2, y: 1.0, w: 4.4, h: 3.85,
        fill: { color: COLORS.white },
      });
      slide.addText(s.leftTitle, {
        x: 0.65, y: 1.2, w: 3.9, h: 0.4,
        fontSize: 16, bold: true, color: COLORS.warn, fontFace: 'Calibri',
      });
      slide.addText(s.rightTitle, {
        x: 5.45, y: 1.2, w: 3.9, h: 0.4,
        fontSize: 16, bold: true, color: COLORS.cyan, fontFace: 'Calibri',
      });
      slide.addText(s.left.map((b) => ({ text: b, options: { bullet: true, breakLine: true } })), {
        x: 0.65, y: 1.75, w: 3.9, h: 2.85,
        fontSize: 13, color: COLORS.dark, fontFace: 'Calibri', paraSpacing: 8,
      });
      slide.addText(s.right.map((b) => ({ text: b, options: { bullet: true, breakLine: true } })), {
        x: 5.45, y: 1.75, w: 3.9, h: 2.85,
        fontSize: 13, color: COLORS.dark, fontFace: 'Calibri', paraSpacing: 8,
      });
    }

    if (s.type === 'cards') {
      const w = 2.9;
      s.cards.forEach((c, i) => {
        const x = 0.45 + i * (w + 0.25);
        slide.addShape(pptx.ShapeType.roundRect, {
          x, y: 1.1, w, h: 3.65,
          fill: { color: COLORS.white },
        });
        slide.addShape(pptx.ShapeType.rect, {
          x, y: 1.1, w, h: 0.12,
          fill: { color: COLORS.cyan },
        });
        slide.addText(c.title, {
          x: x + 0.2, y: 1.45, w: w - 0.4, h: 0.45,
          fontSize: 16, bold: true, color: COLORS.navy, fontFace: 'Calibri',
        });
        slide.addText(c.text, {
          x: x + 0.2, y: 2.1, w: w - 0.4, h: 2.35,
          fontSize: 13, color: COLORS.dark, fontFace: 'Calibri',
        });
      });
    }

    if (s.type === 'steps') {
      s.steps.forEach((st, i) => {
        const x = 0.4 + i * 2.4;
        slide.addShape(pptx.ShapeType.roundRect, {
          x, y: 1.25, w: 2.2, h: 3.45,
          fill: { color: COLORS.white },
        });
        slide.addShape(pptx.ShapeType.ellipse, {
          x: x + 0.75, y: 1.5, w: 0.7, h: 0.7,
          fill: { color: COLORS.navy },
        });
        slide.addText(st.n, {
          x: x + 0.75, y: 1.6, w: 0.7, h: 0.5,
          fontSize: 18, bold: true, color: COLORS.white, align: 'center', fontFace: 'Calibri',
        });
        slide.addText(st.t, {
          x: x + 0.15, y: 2.45, w: 1.9, h: 0.4,
          fontSize: 15, bold: true, color: COLORS.cyan, align: 'center', fontFace: 'Calibri',
        });
        slide.addText(st.d, {
          x: x + 0.15, y: 3.0, w: 1.9, h: 1.35,
          fontSize: 12, color: COLORS.dark, align: 'center', fontFace: 'Calibri',
        });
      });
    }

    addFooter(slide, pptx, page, total);
  });

  const out = path.join(OUT_DIR, 'SoukExpat-Presentation-Partenaires.pptx');
  await pptx.writeFile({ fileName: out });
  return out;
}

function buildPdf() {
  return new Promise((resolve, reject) => {
    const out = path.join(OUT_DIR, 'SoukExpat-Dossier-Partenaires.pdf');
    const doc = new PDFDocument({
      size: 'A4',
      margins: { top: 56, bottom: 56, left: 56, right: 56 },
      info: {
        Title: 'SoukExpat — Dossier partenaires',
        Author: 'SoukExpat',
        Subject: 'Présentation de la marketplace pour futurs partenaires',
      },
    });
    const stream = fs.createWriteStream(out);
    doc.pipe(stream);

    const pageWidth = doc.page.width;
    const contentWidth = pageWidth - 112;
    let pageNum = 1;

    function drawFooter() {
      const bottom = doc.page.height - 36;
      doc.save();
      doc.rect(0, bottom - 8, pageWidth, 44).fill(`#${COLORS.navy}`);
      doc.fillColor('#FFFFFF').fontSize(9).font('Helvetica')
        .text('SoukExpat — Dossier partenaires · soukexpat.com', 56, bottom + 4, {
          width: contentWidth - 40,
        });
      doc.text(String(pageNum), 56, bottom + 4, {
        width: contentWidth,
        align: 'right',
      });
      doc.restore();
    }

    function ensureSpace(h) {
      if (doc.y + h > doc.page.height - 70) {
        drawFooter();
        doc.addPage();
        pageNum += 1;
        doc.y = 56;
      }
    }

    function h1(text) {
      ensureSpace(40);
      doc.fillColor(`#${COLORS.navy}`).font('Helvetica-Bold').fontSize(20).text(text, { width: contentWidth });
      doc.moveDown(0.4);
      doc.moveTo(56, doc.y).lineTo(56 + 80, doc.y).strokeColor(`#${COLORS.cyan}`).lineWidth(3).stroke();
      doc.moveDown(0.8);
    }

    function h2(text) {
      ensureSpace(28);
      doc.fillColor(`#${COLORS.cyan}`).font('Helvetica-Bold').fontSize(14).text(text, { width: contentWidth });
      doc.moveDown(0.35);
    }

    function para(text) {
      ensureSpace(36);
      doc.fillColor(`#${COLORS.dark}`).font('Helvetica').fontSize(11).text(text, {
        width: contentWidth,
        align: 'justify',
        lineGap: 2,
      });
      doc.moveDown(0.55);
    }

    function bullets(items) {
      items.forEach((item) => {
        ensureSpace(22);
        doc.fillColor(`#${COLORS.dark}`).font('Helvetica').fontSize(11)
          .text(`•  ${item}`, { width: contentWidth, indent: 8, lineGap: 2 });
        doc.moveDown(0.25);
      });
      doc.moveDown(0.35);
    }

    // Cover
    doc.rect(0, 0, pageWidth, doc.page.height).fill(`#${COLORS.navy}`);
    if (fs.existsSync(LOGO_WHITE) || fs.existsSync(LOGO)) {
      try {
        doc.image(fs.existsSync(LOGO_WHITE) ? LOGO_WHITE : LOGO, 56, 90, { width: 180 });
      } catch (_) { /* ignore */ }
    }
    doc.fillColor('#FFFFFF').font('Helvetica-Bold').fontSize(32)
      .text('SoukExpat', 56, 250, { width: contentWidth });
    doc.fillColor('#93C5E4').font('Helvetica').fontSize(16)
      .text('Le Marché Mondial des Expatriés', 56, 295, { width: contentWidth });
    doc.fillColor('#FFFFFF').fontSize(12)
      .text('Dossier de présentation — futurs partenaires', 56, 340, { width: contentWidth });
    doc.fontSize(11)
      .text('Marketplace communautaire pour les expatriés au Maroc\nsoukexpat.com', 56, 390, {
        width: contentWidth,
        lineGap: 4,
      });
    doc.fillColor('#94A3B8').fontSize(10)
      .text(`Document confidentiel · ${DATE_FR}`, 56, 720, {
        width: contentWidth,
      });

    doc.addPage();
    pageNum = 2;
    doc.y = 56;

    h1('1. Résumé exécutif');
    para('SoukExpat est une marketplace web destinée à la communauté expatriée au Maroc. Elle permet d’acheter, vendre et échanger des biens ou services via des annonces modérées, avec une mise en relation directe entre membres.');
    bullets([
      `Site en ligne : ${SITE}`,
      'Modèle : mise en relation (pas de paiement intégré)',
      'Expérience responsive + application installable (PWA)',
      'API prête pour des clients mobiles futurs',
      'Valeurs : Sécurité · Communauté · Rapidité',
    ]);

    h1('2. Vision & positionnement');
    para('Faciliter la vie des expatriés en centralisant les annonces dans un espace clair, modéré et pensé pour le contexte marocain (villes, catégories, contact rapide). Promesse produit : « Trouvez tout, partout. »');
    bullets([
      'Audience : expatriés et communauté locale intéressée',
      'Promesse : simplicité, confiance et proximité',
      'Différenciation : modération + messagerie + WhatsApp',
    ]);

    h1('3. Problème & solution');
    h2('Problème');
    bullets([
      'Annonces dispersées sur réseaux sociaux et groupes',
      'Manque de confiance et de contrôle qualité',
      'Contact peu structuré entre acheteurs et vendeurs',
    ]);
    h2('Solution');
    bullets([
      'Plateforme unique dédiée aux expat au Maroc',
      'Validation des annonces avant publication',
      'Contact via messagerie interne et/ou WhatsApp',
      'Filtres par catégorie et ville',
    ]);

    h1('4. Utilisateurs cibles');
    bullets([
      'Acheteurs : recherchent un bien/service localement',
      'Vendeurs : publient et gèrent leurs annonces (même compte)',
      'Éditeurs / admins : animent et modèrent la plateforme',
      'Partenaires : visibilité auprès d’une audience expatriée',
    ]);

    h1('5. Parcours principaux');
    h2('Acheteur');
    bullets([
      'Découverte (accueil, recherche, filtres)',
      'Consultation de la fiche annonce et du profil vendeur',
      'Contact (message SoukExpat et/ou WhatsApp)',
      'Conclusion hors plateforme (remise / paiement en personne)',
    ]);
    h2('Vendeur');
    bullets([
      'Inscription et profil (WhatsApp optionnel)',
      'Création d’annonce avec photos (prix en MAD)',
      'Passage en modération (pending → approved / rejected)',
      'Échanges et suivi dans « Mes annonces »',
    ]);

    h1('6. Fonctionnalités produit');
    bullets([
      'Annonces (photos, prix MAD, catégories, villes)',
      'Messagerie (texte, photo, position)',
      'WhatsApp (profil, annonce, contact site)',
      'Profil vendeur public',
      'PWA installable desktop / mobile / tablette',
      'API REST /api/v1 sécurisée par JWT',
      'Pages institutionnelles (À propos, Comment ça marche, FAQ, Contact, Blog, Légal)',
    ]);

    h1('7. Confiance & responsabilité');
    para('La plateforme affiche clairement ses limites aux utilisateurs : elle ne traite pas les paiements ni les litiges. Son rôle est exclusivement de mettre les membres en contact.');
    bullets([
      'Popup de sécurité avant contact (ne pas payer avant réception)',
      'Modération humaine des annonces',
      'Aucune responsabilité sur les ventes entre particuliers',
      'Purge des messages de discussion après environ 30 jours',
    ]);

    h1('8. Administration & mesure');
    bullets([
      'Dashboard éditeur / administrateur',
      'Approbation / refus / suspension des annonces',
      'Catégories, villes, slider d’accueil, contenus du site',
      'Gestion des contacts et supervision des discussions',
      'Analytics avec exports Excel, Word et PDF',
      'Gestion des utilisateurs (rôle admin)',
    ]);

    h1('9. Architecture technique (vue partenaires)');
    bullets([
      'Symfony 8 / PHP 8.4, Twig, Bootstrap',
      'MySQL / MariaDB',
      'Sécurité applicative (sessions web + JWT API)',
      'Déploiement HTTPS sur soukexpat.com',
      'Extensibilité via API pour apps natives ou partenaires',
    ]);

    h1('10. Opportunités de partenariat');
    para('Sans modèle de paiement intégré aujourd’hui, SoukExpat ouvre des collaborations autour de la visibilité et de la distribution auprès des expatriés.');
    bullets([
      'Sponsoring / emplacements d’accueil (slider, campagnes)',
      'Opérations co-brandées',
      'Distribution digitale (web, PWA, apps via API)',
      'Partenariats sectoriels (immobilier, mobilité, services, lifestyle)',
      'Reporting d’activité pour suivre l’impact',
    ]);

    h1('11. Pourquoi collaborer maintenant ?');
    bullets([
      'Produit déjà en ligne et utilisable',
      'Positionnement de niche clair',
      'Expérience mobile-first + installable',
      'Socle technique moderne et évolutif',
      'Message de confiance transparent pour la communauté',
    ]);

    h1('12. Prochaines étapes');
    bullets([
      'Visite guidée de soukexpat.com',
      'Démonstration des parcours acheteur / vendeur / admin',
      'Atelier de définition du partenariat',
      'Feuille de route et indicateurs de succès',
    ]);

    ensureSpace(100);
    doc.moveDown(1);
    doc.fillColor(`#${COLORS.navy}`).font('Helvetica-Bold').fontSize(14)
      .text('Contact', { width: contentWidth });
    doc.moveDown(0.3);
    doc.fillColor(`#${COLORS.dark}`).font('Helvetica').fontSize(11)
      .text(
        `Site : ${SITE}\nEmail : ${CONTACT_EMAIL}\nContact : formulaire et WhatsApp disponibles sur la plateforme\nSoukExpat — Le Marché Mondial des Expatriés`,
        { width: contentWidth, lineGap: 3 }
      );

    drawFooter();
    doc.end();
    stream.on('finish', () => resolve(out));
    stream.on('error', reject);
  });
}

(async () => {
  try {
    const pptxPath = await buildPptx();
    const pdfPath = await buildPdf();
    console.log('OK PPTX:', pptxPath);
    console.log('OK PDF :', pdfPath);
  } catch (err) {
    console.error(err);
    process.exit(1);
  }
})();
