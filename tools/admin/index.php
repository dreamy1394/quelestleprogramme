<?php
declare(strict_types=1);

/**
 * Back-office de validation — Programmes 2027
 * ---------------------------------------------------------------------------
 * OUTIL LOCAL. Ce fichier n'est jamais déployé en production (voir .gitignore
 * du déploiement et le workflow deploy.yml). Il écrit dans data/candidats/,
 * et git reste la source de vérité : on valide ici, on commit, on pousse,
 * la CI déploie.
 *
 * Lancement, depuis la racine du projet :
 *     php -S localhost:8000 -t .
 * puis http://localhost:8000/tools/admin/
 */

const RACINE = __DIR__ . '/../..';

/* ---------------------------------------------------------------- Verrou */

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
    http_response_code(403);
    exit('Back-office accessible uniquement en local. Ce fichier ne doit pas se trouver sur le serveur de production.');
}

session_start();
if (empty($_SESSION['jeton'])) {
    $_SESSION['jeton'] = bin2hex(random_bytes(16));
}
$jeton = $_SESSION['jeton'];

require_once RACINE . '/inc/data.php';

/* ---------------------------------------------------------------- Helpers */

function ecrire_candidat(string $slug, array $donnees): bool
{
    $chemin = RACINE . '/data/candidats/' . basename($slug) . '.json';
    $json = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false && file_put_contents($chemin, $json . "\n") !== false;
}

function lire_candidat_brut(string $slug): ?array
{
    $chemin = RACINE . '/data/candidats/' . basename($slug) . '.json';
    if (!is_file($chemin)) {
        return null;
    }
    $d = json_decode((string) file_get_contents($chemin), true);
    return is_array($d) ? $d : null;
}

function inbox(): array
{
    $items = [];
    foreach (glob(RACINE . '/data/_inbox/*.json') ?: [] as $fichier) {
        $d = json_decode((string) file_get_contents($fichier), true);
        if (is_array($d)) {
            $d['_fichier'] = basename($fichier);
            $items[] = $d;
        }
    }
    return $items;
}

function mesures_sans_source(): array
{
    $liste = [];
    foreach (charger_candidats() as $slug => $candidat) {
        foreach ($candidat['mesures'] ?? [] as $i => $mesure) {
            if (empty($mesure['source']['url'])) {
                $liste[] = ['slug' => $slug, 'nom' => $candidat['nom'], 'index' => $i, 'mesure' => $mesure];
            }
        }
    }
    return $liste;
}

function message(string $texte, string $type = 'ok'): void
{
    $_SESSION['message'] = ['texte' => $texte, 'type' => $type];
}

function rediriger(string $onglet = 'sources'): never
{
    header('Location: ?onglet=' . urlencode($onglet));
    exit;
}

/* ----------------------------------------------------------------- Actions */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($jeton, (string) ($_POST['jeton'] ?? ''))) {
        message('Jeton de session invalide. Rechargez la page.', 'erreur');
        rediriger();
    }

    $action = (string) ($_POST['action'] ?? '');

    /* --- Rattacher une source à une mesure existante --- */
    if ($action === 'sourcer') {
        $slug = (string) $_POST['slug'];
        $index = (int) $_POST['index'];
        $candidat = lire_candidat_brut($slug);

        if ($candidat === null || !isset($candidat['mesures'][$index])) {
            message('Mesure introuvable — le fichier a peut-être changé depuis l\'affichage.', 'erreur');
            rediriger();
        }
        if (($candidat['mesures'][$index]['titre'] ?? '') !== (string) $_POST['titre_controle']) {
            message('La mesure ciblée ne correspond plus. Rechargez la page avant de recommencer.', 'erreur');
            rediriger();
        }
        if (trim((string) $_POST['url']) === '') {
            message('Une source sans URL n\'est pas une source.', 'erreur');
            rediriger();
        }

        $candidat['mesures'][$index]['source'] = [
            'libelle' => trim((string) $_POST['libelle']) ?: 'Source officielle',
            'url' => trim((string) $_POST['url']),
            'date' => trim((string) $_POST['date']),
        ];
        if (trim((string) ($_POST['citation'] ?? '')) !== '') {
            $candidat['mesures'][$index]['citation'] = trim((string) $_POST['citation']);
        }

        ecrire_candidat($slug, $candidat)
            ? message('Source enregistrée. Pensez à committer.')
            : message('Écriture impossible : vérifiez les droits sur data/candidats/.', 'erreur');
        rediriger('sources');
    }

    /* --- Ajouter une mesure à la main --- */
    if ($action === 'ajouter') {
        $slug = (string) $_POST['slug'];
        $candidat = lire_candidat_brut($slug);

        if ($candidat === null) {
            message('Candidat inconnu.', 'erreur');
            rediriger('ajout');
        }
        $titre = trim((string) $_POST['titre']);
        $url = trim((string) $_POST['url']);
        $citation = trim((string) $_POST['citation']);

        if ($titre === '' || $url === '' || $citation === '') {
            message('Titre, URL de la source et citation verbatim sont obligatoires. Une mesure sans verbatim ne peut pas être vérifiée.', 'erreur');
            rediriger('ajout');
        }

        $mesure = [
            'theme' => (string) $_POST['theme'],
            'titre' => $titre,
            'detail' => trim((string) $_POST['detail']),
            'citation' => $citation,
            'source' => [
                'libelle' => trim((string) $_POST['libelle']) ?: 'Source officielle',
                'url' => $url,
                'date' => trim((string) $_POST['date']) ?: date('Y-m-d'),
            ],
        ];
        if (trim((string) $_POST['chiffre']) !== '') {
            $mesure['chiffre'] = trim((string) $_POST['chiffre']);
        }

        $candidat['mesures'][] = $mesure;
        ecrire_candidat($slug, $candidat)
            ? message('Mesure ajoutée à ' . $candidat['nom'] . '.')
            : message('Écriture impossible.', 'erreur');
        rediriger('ajout');
    }

    /* --- Valider ou rejeter une proposition de la file d'attente --- */
    if ($action === 'valider_inbox' || $action === 'rejeter_inbox') {
        $fichier = RACINE . '/data/_inbox/' . basename((string) $_POST['fichier']);
        if (!is_file($fichier)) {
            message('Proposition introuvable.', 'erreur');
            rediriger('file');
        }

        if ($action === 'rejeter_inbox') {
            @mkdir(RACINE . '/data/_inbox/rejetees', 0775, true);
            rename($fichier, RACINE . '/data/_inbox/rejetees/' . basename($fichier));
            message('Proposition rejetée et archivée.');
            rediriger('file');
        }

        $proposition = json_decode((string) file_get_contents($fichier), true) ?: [];
        $candidat = lire_candidat_brut((string) ($proposition['candidat'] ?? ''));
        if ($candidat === null) {
            message('Le candidat visé par cette proposition n\'existe pas.', 'erreur');
            rediriger('file');
        }
        if (empty($proposition['citation']) || empty($proposition['source']['url'])) {
            message('Proposition refusée automatiquement : pas de citation verbatim ou pas d\'URL.', 'erreur');
            rediriger('file');
        }

        $candidat['mesures'][] = [
            'theme' => (string) ($_POST['theme'] ?? $proposition['theme']),
            'titre' => trim((string) $_POST['titre']),
            'detail' => trim((string) $_POST['detail']),
            'citation' => $proposition['citation'],
            'source' => $proposition['source'],
        ];
        if (trim((string) ($_POST['chiffre'] ?? '')) !== '') {
            $candidat['mesures'][count($candidat['mesures']) - 1]['chiffre'] = trim((string) $_POST['chiffre']);
        }

        if (ecrire_candidat((string) $proposition['candidat'], $candidat)) {
            @mkdir(RACINE . '/data/_inbox/validees', 0775, true);
            rename($fichier, RACINE . '/data/_inbox/validees/' . basename($fichier));
            message('Mesure validée et publiée dans le JSON du candidat.');
        } else {
            message('Écriture impossible.', 'erreur');
        }
        rediriger('file');
    }
}

/* ---------------------------------------------------------------- Affichage */

$onglet = (string) ($_GET['onglet'] ?? 'sources');
$notification = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

$candidats = charger_candidats();
$themes = charger_themes();
$sansSource = mesures_sans_source();
$file = inbox();
$rapport = @file_get_contents(RACINE . '/data/_veille/rapport.md') ?: '';

$onglets = [
    'sources' => 'À sourcer (' . count($sansSource) . ')',
    'file' => 'File d\'attente (' . count($file) . ')',
    'ajout' => 'Ajouter une mesure',
    'veille' => 'Dernier rapport de veille',
];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Back-office — Programmes 2027</title>
<link rel="stylesheet" href="/assets/css/style.css">
<style>
  body { background: var(--papier-2); }
  .admin-tete { background: var(--encre); color: #fff; padding: 1rem 0; }
  .admin-tete .wrap { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
  .admin-tete h1 { font-size: 1.1rem; color: #fff; }
  .admin-tete p { margin: 0; font-size: .78rem; opacity: .7; }
  .admin-onglets { display: flex; gap: .4rem; flex-wrap: wrap; margin: 1.5rem 0; }
  .admin-onglets a { text-decoration: none; font-size: .85rem; font-weight: 500; padding: .45rem .9rem;
    border-radius: 999px; border: 1px solid var(--filet); background: var(--papier); color: var(--encre-douce); }
  .admin-onglets a.actif { background: var(--encre); color: var(--papier); border-color: var(--encre); }
  .fiche { background: var(--papier); border: 1px solid var(--filet); border-radius: 10px;
    padding: 1.1rem 1.25rem; margin-bottom: 1rem; }
  .fiche-tete { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; align-items: baseline;
    margin-bottom: .5rem; }
  .fiche-tete strong { font-family: var(--serif); font-size: 1.05rem; }
  .fiche-qui { font-size: .78rem; color: var(--encre-tenue); text-transform: uppercase; letter-spacing: .05em; }
  .fiche p.detail { margin: 0 0 .9rem; font-size: .9rem; color: var(--encre-douce); }
  .champs { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); align-items: end; }
  .champ { display: flex; flex-direction: column; gap: .2rem; }
  .champ.large { grid-column: 1 / -1; }
  .champ label { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--encre-tenue); }
  input, select, textarea { font: inherit; font-size: .88rem; padding: .5rem .65rem; border-radius: 6px;
    border: 1px solid var(--filet-fort); background: var(--papier); color: var(--encre); width: 100%; }
  textarea { min-height: 68px; resize: vertical; }
  button { font: inherit; font-size: .85rem; font-weight: 600; cursor: pointer; padding: .55rem 1.1rem;
    border-radius: 999px; border: 1px solid var(--encre); background: var(--encre); color: var(--papier); }
  button.secondaire { background: transparent; color: var(--encre); border-color: var(--filet-fort); }
  .actions { display: flex; gap: .5rem; margin-top: .9rem; flex-wrap: wrap; }
  .notif { padding: .8rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: .9rem;
    border-left: 3px solid #2f7d4f; background: color-mix(in srgb, #2f7d4f 10%, transparent); }
  .notif.erreur { border-left-color: #c0392b; background: color-mix(in srgb, #c0392b 10%, transparent); }
  .citation { font-size: .85rem; font-style: italic; color: var(--encre-douce);
    border-left: 3px solid var(--filet-fort); padding: .4rem .8rem; margin: 0 0 .9rem; }
  .vide { color: var(--encre-tenue); font-style: italic; }
  .rapport { background: var(--papier); border: 1px solid var(--filet); border-radius: 10px;
    padding: 1.5rem; white-space: pre-wrap; font-size: .85rem; line-height: 1.6; overflow-x: auto; }
  .rappel { font-size: .82rem; color: var(--encre-tenue); border: 1px dashed var(--filet-fort);
    border-radius: 8px; padding: .8rem 1rem; margin-bottom: 1.5rem; }
</style>
</head>
<body>

<div class="admin-tete">
  <div class="wrap">
    <div>
      <h1>Back-office — Programmes 2027</h1>
      <p>Outil local. Les validations écrivent dans <code>data/candidats/</code> : commitez après chaque session.</p>
    </div>
    <p><?= count($candidats) ?> candidats · <?= total_mesures() ?> mesures · <?= count($sansSource) ?> sans source</p>
  </div>
</div>

<div class="wrap" style="padding-bottom:4rem">

  <nav class="admin-onglets">
    <?php foreach ($onglets as $cle => $libelle): ?>
      <a href="?onglet=<?= $cle ?>" class="<?= $onglet === $cle ? 'actif' : '' ?>"><?= e($libelle) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($notification): ?>
    <div class="notif <?= $notification['type'] === 'erreur' ? 'erreur' : '' ?>"><?= e($notification['texte']) ?></div>
  <?php endif; ?>

  <?php /* ------------------------------------------------ Onglet : à sourcer */ ?>
  <?php if ($onglet === 'sources'): ?>
    <div class="rappel">
      Chaque mesure listée ici est en ligne sans source vérifiable. C'est la dette la plus coûteuse du projet :
      tant qu'elle existe, la page « Méthodologie » promet quelque chose que le site ne tient pas.
      Traitez-les par lots de cinq, en remontant systématiquement au document officiel — jamais à un article de presse.
    </div>

    <?php if ($sansSource === []): ?>
      <p class="vide">Toutes les mesures publiées sont sourcées. Le site ne signale plus aucune dette de sourçage.</p>
    <?php else: ?>
      <?php foreach ($sansSource as $entree): $m = $entree['mesure']; ?>
        <form class="fiche" method="post">
          <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
          <input type="hidden" name="action" value="sourcer">
          <input type="hidden" name="slug" value="<?= e($entree['slug']) ?>">
          <input type="hidden" name="index" value="<?= (int) $entree['index'] ?>">
          <input type="hidden" name="titre_controle" value="<?= e($m['titre'] ?? '') ?>">

          <div class="fiche-tete">
            <strong><?= e($m['titre'] ?? '') ?></strong>
            <span class="fiche-qui"><?= e($entree['nom']) ?> · <?= e($themes[$m['theme']]['libelle'] ?? $m['theme']) ?></span>
          </div>
          <p class="detail"><?= e($m['detail'] ?? '') ?></p>

          <div class="champs">
            <div class="champ large">
              <label for="url-<?= e($entree['slug']) ?>-<?= (int) $entree['index'] ?>">URL du document officiel</label>
              <input type="url" name="url" id="url-<?= e($entree['slug']) ?>-<?= (int) $entree['index'] ?>" placeholder="https://…" required>
            </div>
            <div class="champ">
              <label>Intitulé de la source</label>
              <input type="text" name="libelle" placeholder="Programme officiel, p. 12">
            </div>
            <div class="champ">
              <label>Date de la source</label>
              <input type="date" name="date">
            </div>
            <div class="champ large">
              <label>Citation verbatim (recommandé)</label>
              <textarea name="citation" placeholder="La phrase exacte du document, copiée telle quelle."></textarea>
            </div>
          </div>
          <div class="actions"><button type="submit">Enregistrer la source</button></div>
        </form>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* -------------------------------------------- Onglet : file d'attente */ ?>
  <?php if ($onglet === 'file'): ?>
    <div class="rappel">
      Propositions déposées dans <code>data/_inbox/</code> par la chaîne d'extraction automatique.
      Rien n'entre sur le site sans passer par ici. Une proposition sans citation verbatim ou sans URL
      est refusée automatiquement — relisez toujours la citation avant de valider, c'est le seul garde-fou
      contre une reformulation qui change le sens.
    </div>

    <?php if ($file === []): ?>
      <p class="vide">File vide. Elle se remplira quand la chaîne d'extraction sera branchée.</p>
    <?php else: ?>
      <?php foreach ($file as $p): ?>
        <form class="fiche" method="post">
          <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
          <input type="hidden" name="fichier" value="<?= e($p['_fichier']) ?>">

          <div class="fiche-tete">
            <strong>Proposition</strong>
            <span class="fiche-qui"><?= e($candidats[$p['candidat']]['nom'] ?? $p['candidat']) ?></span>
          </div>

          <?php if (!empty($p['citation'])): ?>
            <p class="citation">« <?= e($p['citation']) ?> »</p>
          <?php endif; ?>
          <?php if (!empty($p['source']['url'])): ?>
            <p class="detail">Source : <a href="<?= e($p['source']['url']) ?>" target="_blank" rel="noopener"><?= e($p['source']['libelle'] ?? $p['source']['url']) ?></a></p>
          <?php endif; ?>

          <div class="champs">
            <div class="champ">
              <label>Thème</label>
              <select name="theme">
                <?php foreach ($themes as $id => $t): ?>
                  <option value="<?= e($id) ?>"<?= ($p['theme'] ?? '') === $id ? ' selected' : '' ?>><?= e($t['libelle']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="champ">
              <label>Chiffre clé</label>
              <input type="text" name="chiffre" value="<?= e($p['chiffre'] ?? '') ?>">
            </div>
            <div class="champ large">
              <label>Titre de la mesure</label>
              <input type="text" name="titre" value="<?= e($p['titre'] ?? '') ?>" required>
            </div>
            <div class="champ large">
              <label>Description</label>
              <textarea name="detail"><?= e($p['detail'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="actions">
            <button type="submit" name="action" value="valider_inbox">Valider et publier</button>
            <button type="submit" name="action" value="rejeter_inbox" class="secondaire">Rejeter</button>
          </div>
        </form>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* ------------------------------------------------- Onglet : ajout manuel */ ?>
  <?php if ($onglet === 'ajout'): ?>
    <div class="rappel">
      Saisie directe, pour les mesures que vous lisez vous-même dans un document officiel.
      Les trois champs obligatoires — titre, URL, verbatim — sont les mêmes que ceux exigés de la
      chaîne automatique. Pas de régime de faveur pour l'humain.
    </div>

    <form class="fiche" method="post">
      <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
      <input type="hidden" name="action" value="ajouter">
      <div class="champs">
        <div class="champ">
          <label>Candidat</label>
          <select name="slug" required>
            <?php foreach ($candidats as $slug => $c): ?>
              <option value="<?= e($slug) ?>"><?= e($c['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="champ">
          <label>Thème</label>
          <select name="theme" required>
            <?php foreach ($themes as $id => $t): ?>
              <option value="<?= e($id) ?>"><?= e($t['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="champ">
          <label>Chiffre clé (facultatif)</label>
          <input type="text" name="chiffre" placeholder="+500 lits">
        </div>
        <div class="champ large">
          <label>Titre de la mesure</label>
          <input type="text" name="titre" required>
        </div>
        <div class="champ large">
          <label>Description neutre</label>
          <textarea name="detail" placeholder="Une à deux phrases, au plus près de l'annonce, sans jugement."></textarea>
        </div>
        <div class="champ large">
          <label>Citation verbatim du document</label>
          <textarea name="citation" required placeholder="La phrase exacte, copiée du document officiel."></textarea>
        </div>
        <div class="champ large">
          <label>URL de la source</label>
          <input type="url" name="url" required placeholder="https://…">
        </div>
        <div class="champ">
          <label>Intitulé de la source</label>
          <input type="text" name="libelle" placeholder="Programme officiel, p. 12">
        </div>
        <div class="champ">
          <label>Date de la source</label>
          <input type="date" name="date" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="actions"><button type="submit">Ajouter la mesure</button></div>
    </form>
  <?php endif; ?>

  <?php /* --------------------------------------------------- Onglet : veille */ ?>
  <?php if ($onglet === 'veille'): ?>
    <?php if ($rapport === ''): ?>
      <p class="vide">Aucun rapport. Lancez <code>php tools/veille.php --init</code> pour référencer les sources,
      puis <code>php tools/veille.php</code> aux passages suivants.</p>
    <?php else: ?>
      <div class="rapport"><?= e($rapport) ?></div>
    <?php endif; ?>
  <?php endif; ?>

</div>
</body>
</html>
