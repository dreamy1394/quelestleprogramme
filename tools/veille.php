<?php
declare(strict_types=1);

/**
 * Radar de veille — Programmes 2027
 * ---------------------------------------------------------------------------
 * Surveille les sources officielles déclarées dans data/sources.json et signale
 * ce qui a changé depuis le dernier passage.
 *
 * Ce script ne comprend rien à ce qu'il lit. Il ne fait aucune interprétation,
 * n'appelle aucun modèle de langage et n'écrit jamais dans data/candidats/.
 * Il répond à une seule question : « y a-t-il eu du mouvement ? »
 *
 * Usage :
 *   php tools/veille.php            scan complet
 *   php tools/veille.php --haute    uniquement les sources de priorité haute
 *   php tools/veille.php --init     initialise l'état sans signaler de changement
 *
 * Codes de sortie : 0 = rien de neuf · 10 = changements détectés · 1 = erreur
 */

const RACINE = __DIR__ . '/..';
const DOSSIER_VEILLE = RACINE . '/data/_veille';

$options = array_slice($argv, 1);
$seulementHaute = in_array('--haute', $options, true);
$initialisation = in_array('--init', $options, true);

/* ------------------------------------------------------------------ Outils */

function sortir(string $message, int $code): never
{
    fwrite($code === 0 ? STDOUT : STDERR, $message . PHP_EOL);
    exit($code);
}

function log_ligne(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

/** Télécharge une URL et renvoie [corps, statut, erreur]. */
function telecharger(string $url, array $reglages): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $reglages['timeout_s'],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => $reglages['user_agent'],
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: fr-FR,fr;q=0.9'],
        CURLOPT_BUFFERSIZE => 65536,
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => static function ($ch, $dlTotal, $dlNow) use ($reglages) {
            return $dlNow > $reglages['taille_max_octets'] ? 1 : 0;
        },
    ]);

    $corps = curl_exec($ch);
    $statut = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $erreur = curl_error($ch);
    curl_close($ch);

    return [$corps === false ? '' : (string) $corps, $statut, $erreur];
}

/**
 * Extrait le texte lisible d'une page en retirant les zones structurellement
 * bruyantes (menus, pieds de page, scripts). Sans ce nettoyage, un compteur
 * de visites ou une date affichée suffirait à déclencher une fausse alerte.
 */
function extraire_texte(string $html): string
{
    if (trim($html) === '') {
        return '';
    }

    $doc = new DOMDocument();
    $ancien = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($ancien);

    $xpath = new DOMXPath($doc);
    $aRetirer = $xpath->query('//script | //style | //noscript | //svg | //iframe | //nav | //header | //footer | //form');
    if ($aRetirer !== false) {
        foreach (iterator_to_array($aRetirer) as $noeud) {
            $noeud->parentNode?->removeChild($noeud);
        }
    }

    $texte = $doc->textContent ?? '';
    $texte = preg_replace('/[ \t\x{00A0}]+/u', ' ', $texte) ?? $texte;
    $texte = preg_replace('/\n\s*\n+/u', "\n", $texte) ?? $texte;

    // Une ligne par phrase utile, les lignes trop courtes sont du mobilier de page.
    $lignes = [];
    foreach (explode("\n", $texte) as $ligne) {
        $ligne = trim($ligne);
        if (mb_strlen($ligne) >= 25) {
            $lignes[] = $ligne;
        }
    }
    return implode("\n", $lignes);
}

/** Liste les documents PDF liés depuis la page : le signal le plus fiable d'une publication de programme. */
function extraire_pdf(string $html, string $urlBase): array
{
    if (!preg_match_all('/href\s*=\s*["\']([^"\']+\.pdf[^"\']*)["\']/i', $html, $m)) {
        return [];
    }
    $base = parse_url($urlBase);
    $racine = ($base['scheme'] ?? 'https') . '://' . ($base['host'] ?? '')
        . (isset($base['port']) ? ':' . $base['port'] : '');

    $pdfs = [];
    foreach ($m[1] as $lien) {
        $lien = html_entity_decode($lien, ENT_QUOTES, 'UTF-8');
        if (str_starts_with($lien, 'http')) {
            $pdfs[] = $lien;
        } elseif (str_starts_with($lien, '/')) {
            $pdfs[] = $racine . $lien;
        } else {
            $pdfs[] = rtrim($urlBase, '/') . '/' . $lien;
        }
    }
    return array_values(array_unique($pdfs));
}

/** Différence ligne à ligne, limitée aux ajouts et suppressions. */
function comparer(array $avant, array $apres): array
{
    $ajouts = array_values(array_diff($apres, $avant));
    $retraits = array_values(array_diff($avant, $apres));
    return [$ajouts, $retraits];
}

/* ------------------------------------------------------------- Préparation */

if (!extension_loaded('curl') || !extension_loaded('dom')) {
    sortir('Extensions PHP requises : curl et dom.', 1);
}

$configBrute = @file_get_contents(RACINE . '/data/sources.json');
if ($configBrute === false) {
    sortir('data/sources.json introuvable.', 1);
}
$config = json_decode($configBrute, true);
if (!is_array($config)) {
    sortir('data/sources.json illisible : ' . json_last_error_msg(), 1);
}

$reglages = array_merge([
    'delai_entre_requetes_ms' => 1500,
    'timeout_s' => 25,
    'user_agent' => 'Programmes2027-Veille/1.0',
    'seuil_lignes_modifiees' => 3,
    'taille_max_octets' => 3000000,
], $config['reglages'] ?? []);

@mkdir(DOSSIER_VEILLE . '/snapshots', 0775, true);
@mkdir(DOSSIER_VEILLE . '/journal', 0775, true);

$cheminEtat = DOSSIER_VEILLE . '/etat.json';
$etat = is_file($cheminEtat) ? (json_decode((string) file_get_contents($cheminEtat), true) ?: []) : [];

$maintenant = date('Y-m-d H:i');
$aujourdhui = date('Y-m-d');

$sources = $config['sources'] ?? [];
if ($seulementHaute) {
    $sources = array_filter($sources, static fn(array $s) => ($s['priorite'] ?? '') === 'haute');
}

$changements = [];
$erreurs = [];
$inchanges = 0;

/* ------------------------------------------------------------------- Scan */

log_ligne('Radar de veille — ' . $maintenant);
log_ligne(str_repeat('-', 60));

foreach ($sources as $source) {
    $id = $source['id'] ?? '';
    $url = $source['url'] ?? '';
    if ($id === '' || $url === '') {
        continue;
    }

    [$corps, $statut, $erreurCurl] = telecharger($url, $reglages);
    usleep($reglages['delai_entre_requetes_ms'] * 1000);

    if ($statut !== 200 || $corps === '') {
        $erreurs[] = [
            'source' => $source,
            'statut' => $statut,
            'message' => $erreurCurl !== '' ? $erreurCurl : 'réponse HTTP ' . $statut,
        ];
        log_ligne(sprintf('  ✕  %-26s HTTP %d %s', $id, $statut, $erreurCurl));
        continue;
    }

    $texte = extraire_texte($corps);
    $lignes = $texte === '' ? [] : explode("\n", $texte);
    $pdfs = extraire_pdf($corps, $url);

    // La liste des PDF entre dans l'empreinte : un programme mis en ligne sans
    // que le texte de la page bouge est le cas le plus intéressant, et c'est
    // exactement celui qu'une empreinte du seul texte laisserait passer.
    $empreinte = hash('sha256', $texte . "\n--documents--\n" . implode("\n", $pdfs));

    $precedent = $etat[$id] ?? null;
    $fichierSnapshot = DOSSIER_VEILLE . '/snapshots/' . $id . '.txt';

    if ($precedent === null || $initialisation) {
        file_put_contents($fichierSnapshot, $texte);
        $etat[$id] = [
            'url' => $url,
            'empreinte' => $empreinte,
            'nb_lignes' => count($lignes),
            'pdfs' => $pdfs,
            'dernier_scan' => $maintenant,
            'dernier_changement' => $maintenant,
        ];
        log_ligne(sprintf('  +  %-26s référencée (%d lignes, %d PDF)', $id, count($lignes), count($pdfs)));
        continue;
    }

    $etat[$id]['url'] = $url;
    $etat[$id]['dernier_scan'] = $maintenant;

    if ($precedent['empreinte'] === $empreinte) {
        $inchanges++;
        log_ligne(sprintf('  =  %-26s inchangée', $id));
        continue;
    }

    $lignesAvant = is_file($fichierSnapshot)
        ? explode("\n", (string) file_get_contents($fichierSnapshot))
        : [];
    [$ajouts, $retraits] = comparer($lignesAvant, $lignes);
    $nouveauxPdf = array_values(array_diff($pdfs, $precedent['pdfs'] ?? []));

    $ampleur = count($ajouts) + count($retraits);
    $significatif = $ampleur >= $reglages['seuil_lignes_modifiees'] || $nouveauxPdf !== [];

    file_put_contents($fichierSnapshot, $texte);
    $etat[$id]['empreinte'] = $empreinte;
    $etat[$id]['nb_lignes'] = count($lignes);
    $etat[$id]['pdfs'] = $pdfs;

    if (!$significatif) {
        log_ligne(sprintf('  ~  %-26s %d ligne(s) modifiée(s), sous le seuil', $id, $ampleur));
        continue;
    }

    $etat[$id]['dernier_changement'] = $maintenant;
    $changements[] = [
        'source' => $source,
        'ajouts' => array_slice($ajouts, 0, 25),
        'nb_ajouts' => count($ajouts),
        'nb_retraits' => count($retraits),
        'nouveaux_pdf' => $nouveauxPdf,
    ];
    log_ligne(sprintf('  ●  %-26s %d ajout(s), %d retrait(s)%s',
        $id, count($ajouts), count($retraits),
        $nouveauxPdf !== [] ? ', ' . count($nouveauxPdf) . ' NOUVEAU PDF' : ''
    ));
}

file_put_contents($cheminEtat, json_encode($etat, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

/* ----------------------------------------------------------------- Rapport */

$r = [];
$r[] = '# Veille — ' . $maintenant;
$r[] = '';
$r[] = sprintf('%d source(s) scannée(s) · **%d changement(s)** · %d inchangée(s) · %d erreur(s)',
    count($sources), count($changements), $inchanges, count($erreurs));
$r[] = '';

if ($changements !== []) {
    $r[] = '## Ce qui a bougé';
    $r[] = '';
    foreach ($changements as $c) {
        $s = $c['source'];
        $r[] = '### ' . $s['libelle'];
        $r[] = '';
        $r[] = sprintf('- Candidat concerné : `%s`', $s['candidat'] ?? '—');
        $r[] = sprintf('- Source : <%s>', $s['url']);
        $r[] = sprintf('- Ampleur : %d ajout(s), %d retrait(s)', $c['nb_ajouts'], $c['nb_retraits']);

        if ($c['nouveaux_pdf'] !== []) {
            $r[] = '- **Nouveaux documents PDF — à ouvrir en priorité :**';
            foreach ($c['nouveaux_pdf'] as $pdf) {
                $r[] = '  - <' . $pdf . '>';
            }
        }
        $r[] = '';

        if ($c['ajouts'] !== []) {
            $r[] = '<details><summary>Texte ajouté sur la page</summary>';
            $r[] = '';
            $r[] = '```';
            foreach ($c['ajouts'] as $ligne) {
                $r[] = mb_substr($ligne, 0, 300);
            }
            if ($c['nb_ajouts'] > 25) {
                $r[] = '… (' . ($c['nb_ajouts'] - 25) . ' ligne(s) supplémentaire(s))';
            }
            $r[] = '```';
            $r[] = '';
            $r[] = '</details>';
            $r[] = '';
        }
    }
    $r[] = '---';
    $r[] = '';
    $r[] = '> Ce rapport signale un mouvement, il ne qualifie rien. Une mesure n\'entre sur le site';
    $r[] = '> qu\'après lecture de la source officielle et validation manuelle dans le back-office.';
    $r[] = '';
} else {
    $r[] = 'Aucun mouvement significatif sur les sources surveillées.';
    $r[] = '';
}

if ($erreurs !== []) {
    $r[] = '## Sources injoignables';
    $r[] = '';
    foreach ($erreurs as $e) {
        $verifie = ($e['source']['verifie'] ?? false) === true;
        $r[] = sprintf('- **%s** (<%s>) — %s%s',
            $e['source']['libelle'],
            $e['source']['url'],
            $e['message'],
            $verifie ? '' : ' — *URL jamais confirmée, à vérifier à la main dans `data/sources.json`*'
        );
    }
    $r[] = '';
}

$rapport = implode("\n", $r) . "\n";
file_put_contents(DOSSIER_VEILLE . '/rapport.md', $rapport);
if ($changements !== []) {
    file_put_contents(DOSSIER_VEILLE . '/journal/' . $aujourdhui . '.md', $rapport);
}

// Résumé dans l'interface GitHub Actions
if (($resume = getenv('GITHUB_STEP_SUMMARY')) !== false && $resume !== '') {
    file_put_contents($resume, $rapport, FILE_APPEND);
}
if (($sortieGh = getenv('GITHUB_OUTPUT')) !== false && $sortieGh !== '') {
    file_put_contents($sortieGh, 'changements=' . count($changements) . "\n", FILE_APPEND);
}

log_ligne(str_repeat('-', 60));
log_ligne(sprintf('%d changement(s), %d erreur(s). Rapport : data/_veille/rapport.md', count($changements), count($erreurs)));

exit($changements !== [] ? 10 : 0);
