<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/data.php';

$titrePage = SITE_NOM;
$descriptionPage = 'Les mesures proposées par les candidats à l\'élection présidentielle française de 2027, classées par thème et présentées sans commentaire éditorial.';
$pageActive = 'accueil';

$candidats = charger_candidats();

require __DIR__ . '/inc/header.php';
?>

<section class="hero">
  <div class="wrap hero-inner">
    <p class="hero-kicker">Élection présidentielle française · 18 avril et 2 mai 2027</p>
    <h1>Ce que chaque candidat propose, sans filtre et sans commentaire.</h1>
    <p class="hero-chapo">
      Ce site recense les mesures annoncées publiquement par les candidats déclarés ou pressentis,
      classées par thème. Aucun classement, aucune note, aucun avis : les programmes sont présentés
      dans le même format pour tous, dans l'ordre alphabétique, à vous de les comparer.
    </p>
    <div class="hero-actions">
      <a class="bouton bouton-primaire" href="#candidats">Voir les candidats</a>
      <a class="bouton bouton-secondaire" href="<?= url('comparateur.php') ?>">Comparer par thème</a>
    </div>
  </div>
</section>

<section class="bandeau-chiffres">
  <div class="wrap chiffres-grid">
    <div><strong><?= count($candidats) ?></strong><span>candidats suivis</span></div>
    <div><strong><?= total_mesures() ?></strong><span>mesures recensées</span></div>
    <div><strong><?= count(matrice_comparative()) ?></strong><span>thèmes documentés</span></div>
    <div><strong><?= e(DATE_MAJ) ?></strong><span>dernière mise à jour</span></div>
  </div>
</section>

<section class="wrap section" id="candidats">
  <div class="section-tete">
    <h2>Les candidats</h2>
    <p class="section-note">
      Classement alphabétique par nom de famille. Aucune candidature n'est définitivement validée :
      les parrainages doivent être déposés au Conseil constitutionnel au plus tard le <?= e(DATE_PARRAINAGES) ?>.
    </p>
  </div>

  <ul class="grille-candidats">
    <?php foreach ($candidats as $slug => $candidat):
        [$statutLibelle, $statutClasse] = libelle_statut($candidat['statut_candidature'] ?? null);
        $nbMesures = count($candidat['mesures'] ?? []);
        $photo = $candidat['photo']['fichier'] ?? null;
    ?>
    <li>
      <a class="carte-candidat" href="<?= url('candidat.php?id=' . rawurlencode($slug)) ?>"
         style="--accent: <?= e($candidat['couleur'] ?? '#5b6472') ?>">
        <span class="carte-portrait">
          <?php if ($photo): ?>
            <img src="<?= url('assets/img/candidats/' . $photo) ?>" alt="Portrait de <?= e($candidat['nom']) ?>" loading="lazy">
          <?php else: ?>
            <span class="portrait-initiales" aria-hidden="true"><?= e(initiales($candidat['nom'])) ?></span>
          <?php endif; ?>
        </span>
        <span class="carte-corps">
          <span class="carte-nom"><?= e($candidat['nom']) ?></span>
          <span class="carte-parti"><?= e($candidat['parti'] ?? '') ?></span>
          <span class="carte-meta">
            <span class="pastille pastille-<?= e($statutClasse) ?>"><?= e($statutLibelle) ?></span>
            <span class="carte-compte"><?= $nbMesures ?> mesure<?= $nbMesures > 1 ? 's' : '' ?></span>
          </span>
        </span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="wrap section">
  <div class="encart-neutralite">
    <h2>Comment ce site reste neutre</h2>
    <div class="encart-colonnes">
      <div>
        <h3>Même format pour tous</h3>
        <p>Chaque candidat dispose de la même page, de la même structure et du même espace. Aucune mise en avant.</p>
      </div>
      <div>
        <h3>Aucun commentaire</h3>
        <p>Les mesures sont retranscrites, pas analysées. Pas de « c'est réaliste », pas de « c'est flou ».</p>
      </div>
      <div>
        <h3>Le vide est signalé</h3>
        <p>Quand un candidat n'a rien publié sur un thème, c'est écrit noir sur blanc plutôt que comblé.</p>
      </div>
      <div>
        <h3>Aucun sondage</h3>
        <p>Les intentions de vote n'apparaissent nulle part : elles influenceraient la perception des programmes.</p>
      </div>
    </div>
    <p class="encart-lien"><a href="<?= url('methodologie.php') ?>">Lire la méthodologie complète</a></p>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
