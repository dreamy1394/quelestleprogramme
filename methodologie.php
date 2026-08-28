<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/data.php';

$titrePage = 'Méthodologie et neutralité';
$descriptionPage = 'Comment les mesures sont sélectionnées, retranscrites et sourcées sur ce site, et quelles règles garantissent son impartialité.';
$pageActive = 'methodologie';

require __DIR__ . '/inc/header.php';
?>

<section class="wrap section page-texte">
  <p class="hero-kicker">Transparence</p>
  <h1>Méthodologie et neutralité</h1>
  <p class="chapo">
    Un site qui prétend informer sur des programmes politiques n'a de valeur que si ses règles sont
    publiques et vérifiables. Voici les nôtres.
  </p>

  <h2>1. Qui figure sur le site</h2>
  <p>
    Sont référencées les personnalités ayant déclaré leur candidature, ou dont la candidature est
    publiquement pressentie et relayée par plusieurs médias nationaux. Le statut de chaque candidature
    est affiché en clair sur la page correspondante.
  </p>
  <p>
    Aucune candidature n'est définitivement validée avant le dépôt des parrainages — 500 signatures
    d'élus issues d'au moins 30 départements — au Conseil constitutionnel, au plus tard le
    <?= e(DATE_PARRAINAGES) ?>. La liste sera mise à jour à cette date.
  </p>

  <h2>2. L'ordre d'affichage</h2>
  <p>
    Les candidats apparaissent partout dans l'ordre alphabétique de leur nom de famille. Ni les sondages,
    ni les résultats passés, ni la date de déclaration, ni l'appartenance politique n'interviennent dans
    cet ordre. Aucune intention de vote n'est publiée sur le site : elle orienterait la lecture des
    programmes.
  </p>

  <h2>3. Ce qui est retranscrit, et comment</h2>
  <ul class="liste-regles">
    <li><strong>Seules les mesures publiques.</strong> Programmes officiels, discours, interviews, propositions de loi. Aucune rumeur, aucune prêtée d'intention.</li>
    <li><strong>Retranscription, pas reformulation.</strong> La mesure est décrite dans des termes neutres, au plus près de l'annonce. Les expressions propres à un candidat sont mises entre guillemets et attribuées.</li>
    <li><strong>Aucune évaluation.</strong> Le site n'écrit jamais qu'une mesure est réaliste, coûteuse, floue ou souhaitable. Ce jugement appartient au lecteur.</li>
    <li><strong>Le même format pour tous.</strong> Même gabarit de page, même hiérarchie, même espace. Un candidat avec vingt mesures et un candidat avec deux ont la même page ; c'est le contenu qui diffère, pas le traitement.</li>
    <li><strong>Les silences sont affichés.</strong> Quand aucune proposition publique n'a été trouvée sur un thème, c'est écrit. Le vide n'est jamais comblé par une déduction.</li>
  </ul>

  <h2>4. Les sources</h2>
  <p>
    Chaque mesure doit être rattachée à une source publique consultable, datée. Une mesure sans source
    vérifiable n'a pas vocation à rester en ligne. Les sources privilégiées sont, dans cet ordre :
    le document programmatique officiel du candidat, l'enregistrement ou le verbatim d'une déclaration
    publique, puis la reprise concordante par plusieurs médias.
  </p>
  <p>
    Les programmes évoluent. Chaque page indique la date de dernière mise à jour du contenu
    (actuellement le <?= e(DATE_MAJ) ?>). Une mesure abandonnée par un candidat est retirée, pas conservée
    pour l'embarrasser.
  </p>

  <h2>5. Indépendance et financement</h2>
  <p>
    Ce site est édité à titre indépendant. Il n'est affilié à aucun parti, aucun candidat, aucune
    institution publique et aucun média. Il ne diffuse aucune publicité, ne vend aucun espace, ne reçoit
    aucun financement d'un acteur politique et ne collecte aucune donnée personnelle à des fins
    publicitaires.
  </p>

  <h2>6. Signaler une erreur</h2>
  <p>
    Une mesure mal retranscrite, une source manquante, un candidat absent, une formulation qui vous
    semble orientée : signalez-le à <a href="mailto:<?= e(EDITEUR_EMAIL) ?>"><?= e(EDITEUR_EMAIL) ?></a>.
    Les corrections factuelles sont traitées en priorité et la page mise à jour est datée.
  </p>

  <h2>7. Ce que ce site n'est pas</h2>
  <p>
    Ce n'est ni un test de positionnement politique, ni un comparateur qui vous dirait pour qui voter,
    ni un fact-checking. Il ne dit pas si une promesse est tenable ni si elle a déjà été tenue par le
    passé. Il dit ce qui a été proposé, par qui, et où le vérifier.
  </p>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
