<?php
declare(strict_types=1);
require_once __DIR__ . '/data.php';

$titrePage = $titrePage ?? SITE_NOM;
$descriptionPage = $descriptionPage ?? SITE_BASELINE;
$pageActive = $pageActive ?? '';
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titrePage) ?><?= $titrePage === SITE_NOM ? '' : ' — ' . SITE_NOM ?></title>
<meta name="description" content="<?= e($descriptionPage) ?>">
<meta property="og:title" content="<?= e($titrePage) ?>">
<meta property="og:description" content="<?= e($descriptionPage) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="fr_FR">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=1">
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body>
<a class="skip" href="#contenu">Aller au contenu</a>

<header class="site-header">
  <div class="wrap header-inner">
    <a class="marque" href="<?= url('') ?>">
      <span class="marque-cocarde" aria-hidden="true"></span>
      <span class="marque-texte">
        <strong>Programmes<span>2027</span></strong>
        <small>Information électorale non partisane</small>
      </span>
    </a>
    <nav class="nav-principale" aria-label="Navigation principale">
      <a href="<?= url('') ?>"<?= $pageActive === 'accueil' ? ' aria-current="page"' : '' ?>>Candidats</a>
      <a href="<?= url('comparateur.php') ?>"<?= $pageActive === 'comparateur' ? ' aria-current="page"' : '' ?>>Comparer</a>
      <a href="<?= url('methodologie.php') ?>"<?= $pageActive === 'methodologie' ? ' aria-current="page"' : '' ?>>Méthodologie</a>
    </nav>
  </div>
</header>

<?php if (mode_brouillon()): ?>
<div class="bandeau-brouillon" role="status">
  <div class="wrap">
    <strong>Mode brouillon</strong> — visible uniquement en local.
    <?= total_mesures_non_sourcees() ?> mesure(s) sur <?= total_mesures() ?> n'ont pas encore de source vérifiable.
    Ce bandeau n'apparaît jamais sur le site en ligne.
  </div>
</div>
<?php endif; ?>

<main id="contenu">
