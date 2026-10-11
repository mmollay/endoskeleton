<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Seitenrahmen jeder Landingpage und ihrer Rechtsseiten: Kopf, Logo, Sprunglink, Pflichtfußzeile. Nicht wählbar,
 * nur aus dem Profil. shared.js wird bewusst nicht geladen.
 *
 * @var Endo\Lp\Kontext $k
 * @var string $titel @var string $beschreibung @var bool $noindex @var string $inhalt
 * @var ?array{url: string, bild: ?string, breite: int, hoehe: int, alt: string} $og  Vorschaukarte beim Teilen (Endo\Lp\Vorschau), nur auf der Startseite
 * @var string $css @var string $js @var string $logo @var string $version
 */
$p = $k->profil->daten;
$f = $p['firma'];
?>
<!DOCTYPE html>
<html lang="<?= $k->e($k->profil->sprache()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $k->e($titel) ?></title>
<meta name="description" content="<?= $k->e($beschreibung) ?>">
<?php if ($noindex): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif ?>
<meta name="generator" content="SSI Endoskeleton <?= $k->e($version) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= $k->e($titel) ?>">
<meta property="og:description" content="<?= $k->e($beschreibung) ?>">
<?php if ($og !== null): ?>
<meta property="og:url" content="<?= $k->e($og['url']) ?>">
<?php if ($og['bild'] !== null): ?>
<meta property="og:image" content="<?= $k->e($og['bild']) ?>">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="<?= (int) $og['breite'] ?>">
<meta property="og:image:height" content="<?= (int) $og['hoehe'] ?>">
<?php if ($og['alt'] !== ''): ?>
<meta property="og:image:alt" content="<?= $k->e($og['alt']) ?>">
<?php endif ?>
<meta name="twitter:card" content="summary_large_image">
<?php endif ?>
<?php endif ?>
<link rel="stylesheet" href="<?= $k->e($css) ?>">
<script src="<?= $k->e($js) ?>" defer></script>
</head>
<body class="lp-seite">
<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>
<header class="lp-kopf">
<div class="lp-kopf-innen container">
<a class="lp-logo" href="index.html"><img src="<?= $k->e($logo) ?>" alt="<?= $k->e($p['logo']['alt']) ?>" width="<?= (int) $p['logo']['breite'] ?>" height="<?= (int) $p['logo']['hoehe'] ?>"></a>
</div>
</header>
<main id="inhalt" class="lp-inhalt">
<?= $inhalt ?>

</main>
<footer class="lp-fuss">
<div class="lp-fuss-innen container">
<p class="lp-fuss-firma"><?= $k->e($f['name']) ?> · <?= $k->e($f['strasse']) ?> · <?= $k->e($f['plz_ort']) ?> · <a href="mailto:<?= $k->e($f['email']) ?>"><?= $k->e($f['email']) ?></a></p>
<nav class="lp-fuss-links" aria-label="Rechtliches">
<a href="impressum.html">Impressum</a>
<a href="datenschutz.html">Datenschutz</a>
</nav>
</div>
</footer>
</body>
</html>
