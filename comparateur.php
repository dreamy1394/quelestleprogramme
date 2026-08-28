<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/data.php';

$titrePage = 'Comparer les programmes thème par thème';
$descriptionPage = 'Comparaison des mesures des candidats à la présidentielle 2027, thème par thème, dans un format identique pour tous.';
$pageActive = 'comparateur';

$candidats = charger_candidats();
$matrice = matrice_comparative();

require __DIR__ . '/inc/header.php';
?>

<section class="wrap section">
  <div class="section-tete">
    <p class="hero-kicker">Lecture croisée</p>
    <h1>Un thème, tous les candidats</h1>
    <p class="section-note">
      Choisissez un thème : les propositions de chaque candidat s'affichent côte à côte, dans l'ordre
      alphabétique, avec le même traitement graphique. Les cases vides sont conservées telles quelles —
      elles font partie de l'information.
    </p>
  </div>

  <div class="comparateur">
    <nav class="comparateur-onglets" aria-label="Choix du thème">
      <?php $premier = true; foreach ($matrice as $idTheme => $ligne): $t = theme($idTheme); ?>
        <button type="button" class="onglet<?= $premier ? ' actif' : '' ?>" data-theme="<?= e($idTheme) ?>"<?= $premier ? ' aria-current="true"' : '' ?>>
          <?= e($t['libelle'] ?? $idTheme) ?>
        </button>
      <?php $premier = false; endforeach; ?>
    </nav>

    <?php $premier = true; foreach ($matrice as $idTheme => $ligne): $t = theme($idTheme); ?>
      <section class="panneau<?= $premier ? ' actif' : '' ?>" data-panneau="<?= e($idTheme) ?>"<?= $premier ? '' : ' hidden' ?>>
        <h2><?= e($t['libelle'] ?? $idTheme) ?></h2>
        <p class="panneau-description"><?= e($t['description'] ?? '') ?></p>

        <div class="colonnes-comparaison">
          <?php foreach ($candidats as $slug => $candidat): $mesures = $ligne[$slug] ?? []; ?>
            <div class="colonne<?= $mesures === [] ? ' colonne-vide' : '' ?>" style="--accent: <?= e($candidat['couleur'] ?? '#5b6472') ?>">
              <h3>
                <a href="<?= url('candidat.php?id=' . rawurlencode($slug)) ?>"><?= e($candidat['nom']) ?></a>
                <small><?= e($candidat['parti_sigle'] ?? '') ?></small>
              </h3>
              <?php if ($mesures === []): ?>
                <p class="colonne-rien">Aucune mesure publiée sur ce thème à ce jour.</p>
              <?php else: ?>
                <ul>
                  <?php foreach ($mesures as $mesure): ?>
                    <li>
                      <strong><?= e($mesure['titre'] ?? '') ?></strong>
                      <?php if (!empty($mesure['chiffre'])): ?><span class="mesure-chiffre"><?= e($mesure['chiffre']) ?></span><?php endif; ?>
                      <?php if (!empty($mesure['detail'])): ?><span class="colonne-detail"><?= e($mesure['detail']) ?></span><?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php $premier = false; endforeach; ?>
  </div>
</section>

<script>
(function () {
  var onglets = document.querySelectorAll('.comparateur-onglets .onglet');
  var panneaux = document.querySelectorAll('.panneau');
  onglets.forEach(function (onglet) {
    onglet.addEventListener('click', function () {
      var cible = onglet.dataset.theme;
      onglets.forEach(function (o) { o.classList.toggle('actif', o === onglet); o.removeAttribute('aria-current'); });
      onglet.setAttribute('aria-current', 'true');
      panneaux.forEach(function (p) {
        var visible = p.dataset.panneau === cible;
        p.classList.toggle('actif', visible);
        p.hidden = !visible;
      });
    });
  });
})();
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
