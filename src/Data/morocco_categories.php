<?php

declare(strict_types=1);

/**
 * Catégories marketplace pour expatriés au Maroc.
 * Chaque entrée : name, slug (unique), icon (Bootstrap Icons, sans préfixe "bi ").
 *
 * @return list<array{name: string, slug: string, icon: string}>
 */
return [
    // Maison & Habitat
    ['name' => 'Meubles salon', 'slug' => 'meubles-salon', 'icon' => 'bi-lamp-fill'],
    ['name' => 'Meubles chambre', 'slug' => 'meubles-chambre', 'icon' => 'bi-moon-stars'],
    ['name' => 'Meubles bureau', 'slug' => 'meubles-bureau', 'icon' => 'bi-laptop'],
    ['name' => 'Meubles jardin', 'slug' => 'meubles-jardin', 'icon' => 'bi-tree'],
    ['name' => 'Décoration', 'slug' => 'decoration', 'icon' => 'bi-palette'],
    ['name' => 'Luminaires', 'slug' => 'luminaires', 'icon' => 'bi-lightbulb'],
    ['name' => 'Literie & matelas', 'slug' => 'literie-matelas', 'icon' => 'bi-moon-stars-fill'],
    ['name' => 'Tapis & rideaux', 'slug' => 'tapis-rideaux', 'icon' => 'bi-grid-3x3'],
    ['name' => 'Cuisine & ustensiles', 'slug' => 'cuisine-ustensiles', 'icon' => 'bi-cup-hot'],
    ['name' => 'Arts de la table', 'slug' => 'arts-de-la-table', 'icon' => 'bi-egg-fried'],
    ['name' => 'Rangement & organisation', 'slug' => 'rangement', 'icon' => 'bi-box-seam'],
    ['name' => 'Bricolage & outils', 'slug' => 'bricolage-outils', 'icon' => 'bi-tools'],
    ['name' => 'Jardinage', 'slug' => 'jardinage', 'icon' => 'bi-flower1'],
    ['name' => 'Plantes', 'slug' => 'plantes', 'icon' => 'bi-flower2'],
    ['name' => 'Électroménager', 'slug' => 'electromenager', 'icon' => 'bi-plug'],
    ['name' => 'Gros électroménager', 'slug' => 'gros-electromenager', 'icon' => 'bi-house-gear'],
    ['name' => 'Petit électroménager', 'slug' => 'petit-electromenager', 'icon' => 'bi-cup-straw'],
    ['name' => 'Climatisation & chauffage', 'slug' => 'climatisation-chauffage', 'icon' => 'bi-thermometer-sun'],
    ['name' => 'Maison & jardin', 'slug' => 'maison-jardin', 'icon' => 'bi-house-heart'],

    // High-tech
    ['name' => 'Téléphones & smartphones', 'slug' => 'telephones-smartphones', 'icon' => 'bi-phone'],
    ['name' => 'Tablettes', 'slug' => 'tablettes', 'icon' => 'bi-tablet'],
    ['name' => 'Ordinateurs portables', 'slug' => 'ordinateurs-portables', 'icon' => 'bi-laptop'],
    ['name' => 'PC de bureau', 'slug' => 'pc-bureau', 'icon' => 'bi-pc-display'],
    ['name' => 'Accessoires informatique', 'slug' => 'accessoires-informatique', 'icon' => 'bi-keyboard'],
    ['name' => 'Écrans & moniteurs', 'slug' => 'ecrans-moniteurs', 'icon' => 'bi-display'],
    ['name' => 'TV & home cinéma', 'slug' => 'tv-home-cinema', 'icon' => 'bi-tv'],
    ['name' => 'Audio & hi-fi', 'slug' => 'audio-hifi', 'icon' => 'bi-speaker'],
    ['name' => 'Photo & caméras', 'slug' => 'photo-cameras', 'icon' => 'bi-camera'],
    ['name' => 'Consoles & jeux vidéo', 'slug' => 'consoles-jeux-video', 'icon' => 'bi-controller'],
    ['name' => 'Drones', 'slug' => 'drones', 'icon' => 'bi-airplane'],
    ['name' => 'Objets connectés', 'slug' => 'objets-connectes', 'icon' => 'bi-watch'],
    ['name' => 'Périphériques & câbles', 'slug' => 'peripheriques-cables', 'icon' => 'bi-usb-symbol'],
    ['name' => 'Imprimantes & scanners', 'slug' => 'imprimantes-scanners', 'icon' => 'bi-printer'],

    // Véhicules
    ['name' => 'Voitures', 'slug' => 'voitures', 'icon' => 'bi-car-front'],
    ['name' => 'Voiture', 'slug' => 'voiture', 'icon' => 'bi-car-front-fill'],
    ['name' => 'Motos & scooters', 'slug' => 'motos-scooters', 'icon' => 'bi-bicycle'],
    ['name' => 'Vélos', 'slug' => 'velos', 'icon' => 'bi-bicycle'],
    ['name' => 'Véhicules & accessoires', 'slug' => 'vehicules-accessoires', 'icon' => 'bi-wrench-adjustable'],
    ['name' => 'Pièces auto', 'slug' => 'pieces-auto', 'icon' => 'bi-gear'],
    ['name' => 'Accessoires auto', 'slug' => 'accessoires-auto', 'icon' => 'bi-speedometer2'],
    ['name' => 'Camions & utilitaires', 'slug' => 'camions-utilitaires', 'icon' => 'bi-truck'],
    ['name' => 'Bateaux & jet-ski', 'slug' => 'bateaux-jet-ski', 'icon' => 'bi-water'],
    ['name' => 'Caravanes & camping-cars', 'slug' => 'caravanes-camping-cars', 'icon' => 'bi-house-door'],
    ['name' => 'Location de véhicules', 'slug' => 'location-vehicules', 'icon' => 'bi-key'],

    // Immobilier
    ['name' => 'Appartements à louer', 'slug' => 'appartements-a-louer', 'icon' => 'bi-building'],
    ['name' => 'Appartements à vendre', 'slug' => 'appartements-a-vendre', 'icon' => 'bi-building-fill'],
    ['name' => 'Maisons à louer', 'slug' => 'maisons-a-louer', 'icon' => 'bi-house'],
    ['name' => 'Maisons à vendre', 'slug' => 'maisons-a-vendre', 'icon' => 'bi-house-fill'],
    ['name' => 'Villas', 'slug' => 'villas', 'icon' => 'bi-house-check'],
    ['name' => 'Studios & colocation', 'slug' => 'studios-colocation', 'icon' => 'bi-door-open'],
    ['name' => 'Bureaux & locaux', 'slug' => 'bureaux-locaux', 'icon' => 'bi-briefcase'],
    ['name' => 'Terrains', 'slug' => 'terrains', 'icon' => 'bi-map'],
    ['name' => 'Locations vacances', 'slug' => 'locations-vacances', 'icon' => 'bi-sun'],
    ['name' => 'Sous-location temporaire', 'slug' => 'sous-location-temporaire', 'icon' => 'bi-calendar-week'],
    ['name' => 'Parking & garages', 'slug' => 'parking-garages', 'icon' => 'bi-p-circle'],

    // Mode & Beauté
    ['name' => 'Vêtements femme', 'slug' => 'vetements-femme', 'icon' => 'bi-person'],
    ['name' => 'Vêtements homme', 'slug' => 'vetements-homme', 'icon' => 'bi-person-fill'],
    ['name' => 'Vêtements enfant', 'slug' => 'vetements-enfant', 'icon' => 'bi-emoji-smile'],
    ['name' => 'Chaussures', 'slug' => 'chaussures', 'icon' => 'bi-bag'],
    ['name' => 'Sacs & maroquinerie', 'slug' => 'sacs-maroquinerie', 'icon' => 'bi-handbag'],
    ['name' => 'Montres & bijoux', 'slug' => 'montres-bijoux', 'icon' => 'bi-gem'],
    ['name' => 'Accessoires mode', 'slug' => 'accessoires-mode', 'icon' => 'bi-eyeglasses'],
    ['name' => 'Beauté & cosmétiques', 'slug' => 'beaute-cosmetiques', 'icon' => 'bi-heart'],
    ['name' => 'Parfums', 'slug' => 'parfums', 'icon' => 'bi-droplet'],
    ['name' => 'Mariage & cérémonie', 'slug' => 'mariage-ceremonie', 'icon' => 'bi-hearts'],

    // Enfants & Bébé
    ['name' => 'Puériculture', 'slug' => 'puericulture', 'icon' => 'bi-balloon'],
    ['name' => 'Jouets', 'slug' => 'jouets', 'icon' => 'bi-puzzle'],
    ['name' => 'Poussettes & sièges auto', 'slug' => 'poussettes-sieges-auto', 'icon' => 'bi-cart'],
    ['name' => 'Chambres bébé', 'slug' => 'chambres-bebe', 'icon' => 'bi-moon'],
    ['name' => 'Livres enfants', 'slug' => 'livres-enfants', 'icon' => 'bi-book'],
    ['name' => 'Matériel scolaire', 'slug' => 'materiel-scolaire', 'icon' => 'bi-pencil'],

    // Sport & Loisirs
    ['name' => 'Sport & fitness', 'slug' => 'sport-fitness', 'icon' => 'bi-activity'],
    ['name' => 'Musculation', 'slug' => 'musculation', 'icon' => 'bi-lightning'],
    ['name' => 'Running & outdoor', 'slug' => 'running-outdoor', 'icon' => 'bi-person-walking'],
    ['name' => 'Sports aquatiques', 'slug' => 'sports-aquatiques', 'icon' => 'bi-water'],
    ['name' => 'Surf & kitesurf', 'slug' => 'surf-kitesurf', 'icon' => 'bi-tsunami'],
    ['name' => 'Randonnée & camping', 'slug' => 'randonnee-camping', 'icon' => 'bi-tree-fill'],
    ['name' => 'Vélo & cyclisme', 'slug' => 'velo-cyclisme', 'icon' => 'bi-bicycle'],
    ['name' => 'Golf', 'slug' => 'golf', 'icon' => 'bi-flag'],
    ['name' => 'Équitation', 'slug' => 'equitation', 'icon' => 'bi-trophy'],
    ['name' => 'Loisirs créatifs', 'slug' => 'loisirs-creatifs', 'icon' => 'bi-brush'],
    ['name' => 'Instruments de musique', 'slug' => 'instruments-musique', 'icon' => 'bi-music-note-beamed'],
    ['name' => 'Jeux de société', 'slug' => 'jeux-de-societe', 'icon' => 'bi-dice-5'],
    ['name' => 'Livres & BD', 'slug' => 'livres-bd', 'icon' => 'bi-book-half'],
    ['name' => 'Films, musique & DVD', 'slug' => 'films-musique-dvd', 'icon' => 'bi-film'],
    ['name' => 'Collection & antiquités', 'slug' => 'collection-antiquites', 'icon' => 'bi-award'],

    // Animaux
    ['name' => 'Animaux', 'slug' => 'animaux', 'icon' => 'bi-heart-fill'],
    ['name' => 'Chiens', 'slug' => 'chiens', 'icon' => 'bi-emoji-smile'],
    ['name' => 'Chats', 'slug' => 'chats', 'icon' => 'bi-emoji-heart-eyes'],
    ['name' => 'Accessoires animaux', 'slug' => 'accessoires-animaux', 'icon' => 'bi-bag-heart'],
    ['name' => 'Aquariophilie', 'slug' => 'aquariophilie', 'icon' => 'bi-water'],
    ['name' => 'Oiseaux', 'slug' => 'oiseaux', 'icon' => 'bi-twitter-x'],

    // Emploi & Services
    ['name' => 'Offres d\'emploi', 'slug' => 'offres-emploi', 'icon' => 'bi-briefcase-fill'],
    ['name' => 'Demande d\'emploi', 'slug' => 'demande-emploi', 'icon' => 'bi-person-badge'],
    ['name' => 'Stages & freelances', 'slug' => 'stages-freelances', 'icon' => 'bi-laptop'],
    ['name' => 'Cours & formations', 'slug' => 'cours-formations', 'icon' => 'bi-mortarboard'],
    ['name' => 'Cours de langues', 'slug' => 'cours-de-langues', 'icon' => 'bi-translate'],
    ['name' => 'Babysitting & nounou', 'slug' => 'babysitting-nounou', 'icon' => 'bi-people'],
    ['name' => 'Aide ménagère', 'slug' => 'aide-menagere', 'icon' => 'bi-house-check'],
    ['name' => 'Services à domicile', 'slug' => 'services-a-domicile', 'icon' => 'bi-hammer'],
    ['name' => 'Réparation & dépannage', 'slug' => 'reparation-depannage', 'icon' => 'bi-tools'],
    ['name' => 'Déménagement', 'slug' => 'demenagement', 'icon' => 'bi-box-arrow-right'],
    ['name' => 'Transport & livraison', 'slug' => 'transport-livraison', 'icon' => 'bi-truck'],
    ['name' => 'Événementiel', 'slug' => 'evenementiel', 'icon' => 'bi-calendar-event'],
    ['name' => 'Beauté & bien-être', 'slug' => 'beaute-bien-etre', 'icon' => 'bi-heart-pulse'],
    ['name' => 'Santé & paramédical', 'slug' => 'sante-paramedical', 'icon' => 'bi-heart-pulse'],
    ['name' => 'Services pro / B2B', 'slug' => 'services-pro-b2b', 'icon' => 'bi-building-gear'],

    // Vie expat / Maroc
    ['name' => 'Vie d\'expatrié', 'slug' => 'vie-dexpatrie', 'icon' => 'bi-globe2'],
    ['name' => 'Covoiturage', 'slug' => 'covoiturage', 'icon' => 'bi-car-front'],
    ['name' => 'Échanges & troc', 'slug' => 'echanges-troc', 'icon' => 'bi-arrow-left-right'],
    ['name' => 'Dons & gratuit', 'slug' => 'dons-gratuit', 'icon' => 'bi-gift'],
    ['name' => 'Événements & sorties', 'slug' => 'evenements-sorties', 'icon' => 'bi-calendar2-heart'],
    ['name' => 'Communauté & rencontres', 'slug' => 'communaute-rencontres', 'icon' => 'bi-people-fill'],
    ['name' => 'Artisanat marocain', 'slug' => 'artisanat-marocain', 'icon' => 'bi-stars'],
    ['name' => 'Produits locaux', 'slug' => 'produits-locaux', 'icon' => 'bi-basket'],
    ['name' => 'Alimentation & épicerie', 'slug' => 'alimentation-epicerie', 'icon' => 'bi-cart4'],
    ['name' => 'Voyages & billets', 'slug' => 'voyages-billets', 'icon' => 'bi-airplane-engines'],
    ['name' => 'Bagages & valises', 'slug' => 'bagages-valises', 'icon' => 'bi-suitcase-lg'],

    // Divers
    ['name' => 'Matériel pro', 'slug' => 'materiel-pro', 'icon' => 'bi-briefcase'],
    ['name' => 'Matériel médical', 'slug' => 'materiel-medical', 'icon' => 'bi-hospital'],
    ['name' => 'Sécurité & alarmes', 'slug' => 'securite-alarmes', 'icon' => 'bi-shield-lock'],
    ['name' => 'Énergie solaire', 'slug' => 'energie-solaire', 'icon' => 'bi-sun-fill'],
    ['name' => 'Divers', 'slug' => 'divers', 'icon' => 'bi-three-dots'],
    ['name' => 'Autres', 'slug' => 'autres', 'icon' => 'bi-tag'],
];
