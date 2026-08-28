<?php
declare(strict_types=1);

/*
 * ---------------------------------------------------------------------------
 *  Configuration du site — c'est le seul fichier à ajuster après un déploiement
 * ---------------------------------------------------------------------------
 */

// Nom affiché du site
const SITE_NOM = 'Programmes 2027';
const SITE_BASELINE = 'Les mesures proposées par les candidats à l\'élection présidentielle, sans commentaire.';

// Nom de domaine final (sans slash final). Sert aux balises Open Graph et au sitemap.
const SITE_URL = 'https://www.quelestleprogramme.fr';

// Chemin d'installation depuis la racine du domaine.
// '' si le site est à la racine, '/programmes2027' s'il est dans un sous-dossier.
const BASE_PATH = '';

/*
 * Mode brouillon : affiche les avertissements internes (mesures non sourcées,
 * photos manquantes, mentions légales incomplètes).
 *
 * Il s'active TOUT SEUL en local — il n'y a qu'un seul config.php versionné,
 * et on ne veut pas avoir à basculer un interrupteur avant chaque déploiement.
 * En local tu vois tes avertissements, en ligne le site est propre.
 *
 * FORCER_BROUILLON = true force l'affichage partout, y compris en production.
 * Le workflow de déploiement refuse de publier tant que cette constante est à true.
 */
const FORCER_BROUILLON = false;

// Dates clés du scrutin (affichées dans le bandeau et la méthodologie)
const DATE_PARRAINAGES = '12 mars 2027';
const DATE_TOUR_1 = '18 avril 2027';
const DATE_TOUR_2 = '2 mai 2027';

// Date de dernière mise à jour éditoriale du contenu (à modifier à la main)
const DATE_MAJ = '26 août 2026';

// Mentions légales — à compléter avant toute mise en ligne (obligation LCEN)
const EDITEUR_NOM = 'Programmes2027';
const EDITEUR_STATUT = 'T.I';
const EDITEUR_EMAIL = 'contact@quelestleprogramme.fr';
const EDITEUR_ADRESSE = 'France';
const DIRECTEUR_PUBLICATION = 'T.I';
const HEBERGEUR = 'IONOS SARL — 7 place de la Gare, 57200 Sarreguemines — 0970 808 911';

// Racine des données
define('DATA_DIR', dirname(__DIR__) . '/data');
