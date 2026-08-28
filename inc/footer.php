</main>

<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <p class="footer-marque"><strong>Programmes<span>2027</span></strong></p>
      <p class="footer-note">
        Site d'information indépendant. Aucun lien avec un parti, un candidat ou une institution publique.
        Contenu mis à jour le <?= e(DATE_MAJ) ?>.
      </p>
    </div>
    <div>
      <h2>Le site</h2>
      <ul>
        <li><a href="<?= url('') ?>">Les candidats</a></li>
        <li><a href="<?= url('comparateur.php') ?>">Comparer les programmes</a></li>
        <li><a href="<?= url('methodologie.php') ?>">Méthodologie et neutralité</a></li>
        <li><a href="<?= url('mentions-legales.php') ?>">Mentions légales</a></li>
      </ul>
    </div>
    <div>
      <h2>Calendrier</h2>
      <ul class="footer-dates">
        <li><span>Dépôt des parrainages</span><strong><?= e(DATE_PARRAINAGES) ?></strong></li>
        <li><span>Premier tour</span><strong><?= e(DATE_TOUR_1) ?></strong></li>
        <li><span>Second tour</span><strong><?= e(DATE_TOUR_2) ?></strong></li>
      </ul>
    </div>
  </div>
  <div class="wrap footer-bas">
    <p>
      Les mesures présentées sont retranscrites à partir de déclarations et de documents publics.
      Elles n'engagent que leurs auteurs et ne constituent ni un soutien, ni une critique.
      <a href="<?= url('methodologie.php') ?>">Signaler une erreur ou une mesure manquante</a>.
    </p>
  </div>
</footer>
</body>
</html>
