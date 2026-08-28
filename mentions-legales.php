<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/data.php';

$titrePage = 'Mentions légales';
$descriptionPage = 'Éditeur, directeur de la publication, hébergeur et conditions d\'utilisation du site.';
$pageActive = '';

require __DIR__ . '/inc/header.php';
?>

<section class="wrap section page-texte">
  <h1>Mentions légales</h1>

  <?php if (mode_brouillon()): ?>
    <p class="alerte-brouillon bloc">
      Ces mentions contiennent des champs à compléter dans <code>inc/config.php</code>.
      L'identification de l'éditeur est une obligation légale (loi pour la confiance dans l'économie
      numérique) : le site ne doit pas être mis en ligne avant qu'elles soient renseignées.
    </p>
  <?php endif; ?>

  <h2>Éditeur du site</h2>
  <p>
    <?= e(EDITEUR_NOM) ?><br>
    <?= e(EDITEUR_STATUT) ?><br>
    <?= e(EDITEUR_ADRESSE) ?><br>
    Contact : <a href="mailto:<?= e(EDITEUR_EMAIL) ?>"><?= e(EDITEUR_EMAIL) ?></a>
  </p>

  <h2>Directeur de la publication</h2>
  <p><?= e(DIRECTEUR_PUBLICATION) ?></p>

  <h2>Hébergeur</h2>
  <p><?= e(HEBERGEUR) ?></p>

  <h2>Nature du site</h2>
  <p>
    Ce site a une vocation d'information. Il n'est affilié à aucun parti politique, aucun candidat,
    aucune institution publique. Il ne constitue pas de la propagande électorale et ne diffuse aucune
    publicité, commerciale ou politique.
  </p>

  <h2>Contenus</h2>
  <p>
    Les mesures présentées sont retranscrites à partir de déclarations et de documents rendus publics
    par les candidats ou leurs formations politiques. Elles n'engagent que leurs auteurs. L'éditeur
    s'efforce d'assurer l'exactitude des informations publiées mais ne peut garantir qu'elles sont
    exhaustives ni à jour à tout instant. Toute erreur signalée est corrigée dans les meilleurs délais.
  </p>

  <h2>Droit de réponse</h2>
  <p>
    Toute personne nommée sur ce site peut demander la rectification d'une information la concernant en
    écrivant à <a href="mailto:<?= e(EDITEUR_EMAIL) ?>"><?= e(EDITEUR_EMAIL) ?></a>.
  </p>

  <h2>Crédits photographiques</h2>
  <p>
    Les portraits utilisés sont publiés sous licence libre. L'auteur et la licence de chaque photographie
    sont indiqués sous le portrait concerné, sur la page du candidat.
  </p>

  <h2>Propriété intellectuelle</h2>
  <p>
    La structure du site, sa charte graphique et les textes rédigés par l'éditeur sont protégés par le
    droit d'auteur. Les citations de déclarations publiques relèvent du droit de citation.
  </p>

  <h2>Données personnelles</h2>
  <p>
    Ce site ne dépose aucun cookie de mesure d'audience ni de publicité et ne collecte aucune donnée
    personnelle lors de la navigation. Les messages envoyés à l'adresse de contact sont utilisés
    uniquement pour y répondre et ne sont ni cédés ni exploités à d'autres fins.
  </p>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
