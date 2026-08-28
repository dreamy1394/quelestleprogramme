<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* ---------------------------------------------------------------------------
 * Couche d'accès aux données
 * Aucune base de données : tout vit dans /data. Pour modifier le site,
 * on modifie un JSON — jamais le PHP.
 * ------------------------------------------------------------------------ */

/**
 * Le mode brouillon s'active automatiquement en local (serveur PHP intégré,
 * localhost, .local, .test) et reste éteint en production, sauf si
 * FORCER_BROUILLON le réclame explicitement.
 */
function mode_brouillon(): bool
{
    static $actif = null;
    if ($actif !== null) {
        return $actif;
    }
    if (FORCER_BROUILLON) {
        return $actif = true;
    }

    // Le nom d'hôte fait foi dès qu'il existe : c'est le seul critère
    // observable depuis un navigateur, donc le seul qui soit testable.
    $hote = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($hote !== '') {
        $hote = explode(':', $hote)[0];
        return $actif = in_array($hote, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($hote, '.local')
            || str_ends_with($hote, '.test');
    }

    // Hors requête HTTP (scripts en ligne de commande), on est forcément
    // en environnement de travail.
    return $actif = true;
}

function lire_json(string $chemin): array
{
    if (!is_file($chemin)) {
        return [];
    }
    $contenu = file_get_contents($chemin);
    if ($contenu === false) {
        return [];
    }
    $donnees = json_decode($contenu, true);

    if (!is_array($donnees)) {
        // Un JSON mal formé ne doit pas casser le site en production.
        if (mode_brouillon()) {
            trigger_error('JSON invalide : ' . $chemin . ' — ' . json_last_error_msg(), E_USER_WARNING);
        }
        return [];
    }
    return $donnees;
}

/** Référentiel des thèmes, dans l'ordre du fichier themes.json. */
function charger_themes(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $brut = lire_json(DATA_DIR . '/themes.json');
    $cache = [];
    foreach ($brut['themes'] ?? [] as $theme) {
        $cache[$theme['id']] = $theme;
    }
    return $cache;
}

function theme(string $id): ?array
{
    return charger_themes()[$id] ?? null;
}

/** Retire les accents pour obtenir un tri alphabétique correct en français. */
function cle_tri(string $texte): string
{
    $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $texte);
    return mb_strtolower($translit !== false ? $translit : $texte);
}

/**
 * Tous les candidats, triés par nom de famille.
 * Le tri alphabétique est un choix éditorial : aucun classement par sondage,
 * par parti ou par ordre d'annonce n'est utilisé sur le site.
 */
function charger_candidats(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $candidats = [];
    foreach (glob(DATA_DIR . '/candidats/*.json') ?: [] as $fichier) {
        $c = lire_json($fichier);
        if (!empty($c['slug'])) {
            $candidats[$c['slug']] = $c;
        }
    }

    uasort($candidats, static fn(array $a, array $b) =>
        cle_tri($a['nom_famille'] ?? $a['nom']) <=> cle_tri($b['nom_famille'] ?? $b['nom'])
    );

    $cache = $candidats;
    return $cache;
}

function charger_candidat(string $slug): ?array
{
    return charger_candidats()[$slug] ?? null;
}

/** Regroupe les mesures d'un candidat par thème, dans l'ordre du référentiel. */
function mesures_par_theme(array $candidat): array
{
    $groupes = [];
    foreach (array_keys(charger_themes()) as $idTheme) {
        $groupes[$idTheme] = [];
    }
    foreach ($candidat['mesures'] ?? [] as $mesure) {
        $id = $mesure['theme'] ?? null;
        if ($id !== null && array_key_exists($id, $groupes)) {
            $groupes[$id][] = $mesure;
        }
    }
    return array_filter($groupes, static fn(array $m) => $m !== []);
}

/** Nombre total de mesures recensées, tous candidats confondus. */
function total_mesures(): int
{
    $total = 0;
    foreach (charger_candidats() as $c) {
        $total += count($c['mesures'] ?? []);
    }
    return $total;
}

/** Nombre de mesures dépourvues de source vérifiable. */
function total_mesures_non_sourcees(): int
{
    $total = 0;
    foreach (charger_candidats() as $c) {
        foreach ($c['mesures'] ?? [] as $m) {
            if (empty($m['source']['url'])) {
                $total++;
            }
        }
    }
    return $total;
}

/** Matrice thème → candidat → mesures, pour la page comparateur. */
function matrice_comparative(): array
{
    $matrice = [];
    foreach (charger_themes() as $idTheme => $theme) {
        $ligne = [];
        $auMoinsUne = false;
        foreach (charger_candidats() as $slug => $candidat) {
            $mesures = array_values(array_filter(
                $candidat['mesures'] ?? [],
                static fn(array $m) => ($m['theme'] ?? null) === $idTheme
            ));
            if ($mesures !== []) {
                $auMoinsUne = true;
            }
            $ligne[$slug] = $mesures;
        }
        if ($auMoinsUne) {
            $matrice[$idTheme] = $ligne;
        }
    }
    return $matrice;
}

/* ------------------------------ Présentation ---------------------------- */

function e(?string $texte): string
{
    return htmlspecialchars((string) $texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $chemin = ''): string
{
    return BASE_PATH . '/' . ltrim($chemin, '/');
}

function libelle_statut(?string $statut): array
{
    return match ($statut) {
        'declaree' => ['Candidature déclarée', 'declaree'],
        'pressentie' => ['Candidature pressentie', 'pressentie'],
        'retiree' => ['Candidature retirée', 'retiree'],
        default => ['Statut non renseigné', 'inconnu'],
    };
}

/** Initiales, utilisées comme portrait de repli tant qu'aucune photo n'est fournie. */
function initiales(string $nom): string
{
    $mots = preg_split('/[\s\-]+/u', trim($nom)) ?: [];
    $lettres = '';
    foreach ($mots as $mot) {
        if ($mot !== '' && mb_strlen($lettres) < 2) {
            $lettres .= mb_strtoupper(mb_substr($mot, 0, 1));
        }
    }
    return $lettres;
}
