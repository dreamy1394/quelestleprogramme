<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/data.php';

$slug = isset($_GET['id']) ? (string) $_GET['id'] : '';
$candidat = charger_candidat($slug);

if ($candidat === null) {
    http_response_code(404);
    $titrePage = 'Candidat introuvable';
    $pageActive = '';
    require __DIR__ . '/inc/header.php';
    echo '<section class="wrap section"><h1>Cette page n\'existe pas</h1>'
       . '<p class="section-note">Le candidat demandé n\'est pas (ou plus) référencé sur le site.</p>'
       . '<p><a class="bouton bouton-primaire" href="' . url('') . '">Revenir à la liste des candidats</a></p></section>';
    require __DIR__ . '/inc/footer.php';
    exit;
}

$titrePage = $candidat['nom'] . ' — ses propositions';
$descriptionPage = 'Les mesures annoncées par ' . $candidat['nom'] . ' (' . ($candidat['parti'] ?? '') . ') pour l\'élection présidentielle de 2027, classées par thème.';
$pageActive = 'accueil';

$groupes = mesures_par_theme($candidat);
[$statutLibelle, $statutClasse] = libelle_statut($candidat['statut_candidature'] ?? null);
$accent = $candidat['couleur'] ?? '#5b6472';
$photo = $candidat['photo']['fichier'] ?? null;

$candidats = charger_candidats();
$slugs = array_keys($candidats);
$position = array_search($slug, $slugs, true);
$precedent = $position > 0 ? $slugs[$position - 1] : null;
$suivant = $position < count($slugs) - 1 ? $slugs[$position + 1] : null;

require __DIR__ . '/inc/header.php';
?>

<article style="--accent: <?= e($accent) ?>">

  <header class="candidat-tete">
    <div class="wrap candidat-tete-inner">
      <div class="candidat-portrait">
        <?php if ($photo): ?>
          <img src="<?= url('assets/img/candidats/' . $photo) ?>" alt="Portrait de <?= e($candidat['nom']) ?>">
        <?php else: ?>
          <span class="portrait-initiales grand" aria-hidden="true"><?= e(initiales($candidat['nom'])) ?></span>
        <?php endif; ?>
      </div>
      <div class="candidat-identite">
        <p class="fil-ariane"><a href="<?= url('') ?>">Candidats</a> <span aria-hidden="true">›</span> <?= e($candidat['nom']) ?></p>
        <h1><?= e($candidat['nom']) ?></h1>
        <p class="candidat-parti"><?= e($candidat['parti'] ?? '') ?></p>
        <p class="candidat-badges">
          <span class="pastille pastille-<?= e($statutClasse) ?>"><?= e($statutLibelle) ?></span>
          <?php if (!empty($candidat['date_declaration'])): ?>
            <span class="pastille pastille-neutre">Déclarée le <?= e(date('j/m/Y', strtotime($candidat['date_declaration']))) ?></span>
          <?php endif; ?>
          <span class="pastille pastille-neutre"><?= count($candidat['mesures'] ?? []) ?> mesure(s) recensée(s)</span>
        </p>
      </div>
    </div>
    <?php if ($photo && !empty($candidat['photo']['credit'])): ?>
      <p class="wrap credit-photo">Photo : <?= e($candidat['photo']['credit']) ?><?= !empty($candidat['photo']['licence']) ? ' — ' . e($candidat['photo']['licence']) : '' ?></p>
    <?php elseif (mode_brouillon()): ?>
      <p class="wrap alerte-brouillon">Photo manquante : renseigner <code>photo.fichier</code> et le crédit dans <code>data/candidats/<?= e($slug) ?>.json</code>.</p>
    <?php endif; ?>
  </header>

  <section class="wrap section">
    <h2 class="titre-section">Qui est <?= e($candidat['nom']) ?> ?</h2>
    <div class="bio-grille">
      <div class="bio-resume">
        <p><?= e($candidat['biographie']['resume'] ?? 'Biographie non renseignée.') ?></p>
      </div>
      <?php if (!empty($candidat['biographie']['parcours'])): ?>
      <div class="bio-frise">
        <h3>Parcours politique</h3>
        <ol class="frise">
          <?php foreach ($candidat['biographie']['parcours'] as $etape): ?>
            <li>
              <span class="frise-annee"><?= e($etape['annee'] ?? '') ?></span>
              <span class="frise-evenement"><?= e($etape['evenement'] ?? '') ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="wrap section">
    <div class="section-tete">
      <h2 class="titre-section">Ses propositions, thème par thème</h2>
      <p class="section-note">
        Mesures retranscrites à partir de déclarations et documents publics, sans reformulation orientée
        ni évaluation. Les thèmes sur lesquels rien n'a été publié sont listés en fin de page.
      </p>
    </div>

    <?php if ($groupes === []): ?>
      <p class="vide-total">Aucune mesure publique n'a encore été recensée pour ce candidat.</p>
    <?php else: ?>

      <nav class="ancres-themes" aria-label="Thèmes abordés">
        <?php foreach (array_keys($groupes) as $idTheme): $t = theme($idTheme); ?>
          <a href="#theme-<?= e($idTheme) ?>"><?= e($t['libelle'] ?? $idTheme) ?> <span><?= count($groupes[$idTheme]) ?></span></a>
        <?php endforeach; ?>
      </nav>

      <div class="themes">
        <?php foreach ($groupes as $idTheme => $mesures): $t = theme($idTheme); ?>
          <section class="bloc-theme" id="theme-<?= e($idTheme) ?>">
            <header class="bloc-theme-tete">
              <span class="theme-icone" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="<?= e($t['icone'] ?? '') ?>"/></svg>
              </span>
              <div>
                <h3><?= e($t['libelle'] ?? $idTheme) ?></h3>
                <p><?= e($t['description'] ?? '') ?></p>
              </div>
            </header>
            <ul class="liste-mesures">
              <?php foreach ($mesures as $mesure): ?>
                <li class="mesure">
                  <h4>
                    <?= e($mesure['titre'] ?? '') ?>
                    <?php if (!empty($mesure['chiffre'])): ?>
                      <span class="mesure-chiffre"><?= e($mesure['chiffre']) ?></span>
                    <?php endif; ?>
                  </h4>
                  <?php if (!empty($mesure['detail'])): ?>
                    <p><?= e($mesure['detail']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($mesure['citation'])): ?>
                    <details class="verbatim">
                      <summary>Voir la formulation d'origine</summary>
                      <blockquote>« <?= e($mesure['citation']) ?> »</blockquote>
                    </details>
                  <?php endif; ?>
                  <?php if (!empty($mesure['source']['url'])): ?>
                    <p class="mesure-source">
                      Source :
                      <a href="<?= e($mesure['source']['url']) ?>" rel="noopener nofollow" target="_blank">
                        <?= e($mesure['source']['libelle'] ?? $mesure['source']['url']) ?>
                      </a>
                      <?= !empty($mesure['source']['date']) ? ' — ' . e($mesure['source']['date']) : '' ?>
                    </p>
                  <?php elseif (mode_brouillon()): ?>
                    <p class="alerte-brouillon">Source à renseigner.</p>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php
  $themesNonTraites = array_diff(array_keys(charger_themes()), array_keys($groupes));
  if ($themesNonTraites !== []):
  ?>
  <section class="wrap section">
    <div class="encart-silences">
      <h2>Thèmes sans mesure publiée à ce jour</h2>
      <p>
        Ces sujets n'ont fait l'objet d'aucune proposition publique identifiée de la part de ce candidat
        au <?= e(DATE_MAJ) ?>. Une absence ici ne signifie pas une absence de position : elle signifie
        qu'aucun document public exploitable n'a été trouvé.
      </p>
      <ul class="liste-silences">
        <?php foreach ($themesNonTraites as $idTheme): $t = theme($idTheme);
          $annonce = in_array($idTheme, $candidat['themes_annonces_sans_mesure'] ?? [], true); ?>
          <li<?= $annonce ? ' class="annonce"' : '' ?>>
            <?= e($t['libelle'] ?? $idTheme) ?>
            <?php if ($annonce): ?><span>thème annoncé, mesures non détaillées</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($candidat['a_suivre'])): ?>
  <section class="wrap section">
    <h2 class="titre-section">À suivre</h2>
    <ul class="liste-suivi">
      <?php foreach ($candidat['a_suivre'] as $point): ?>
        <li><?= e($point) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <nav class="wrap navigation-candidats" aria-label="Autres candidats">
    <?php if ($precedent): ?>
      <a class="nav-precedent" href="<?= url('candidat.php?id=' . rawurlencode($precedent)) ?>">
        <span>Candidat précédent</span><strong><?= e($candidats[$precedent]['nom']) ?></strong>
      </a>
    <?php else: ?><span></span><?php endif; ?>
    <?php if ($suivant): ?>
      <a class="nav-suivant" href="<?= url('candidat.php?id=' . rawurlencode($suivant)) ?>">
        <span>Candidat suivant</span><strong><?= e($candidats[$suivant]['nom']) ?></strong>
      </a>
    <?php endif; ?>
  </nav>

</article>

<?php require __DIR__ . '/inc/footer.php'; ?>
