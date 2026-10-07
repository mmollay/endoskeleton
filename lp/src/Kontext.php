<?php
/** Hilfen für die Vorlagen unter lp/bausteine/: Maskieren, Abschnitt öffnen, Bilder, Knöpfe, JSON-LD, Anrede. */

declare(strict_types=1);

namespace Endo\Lp;

final class Kontext
{
    /** @var array<string, string> Zielpfad im Seitenordner (es/bilder/…) => Quelldatei */
    public array $kopien = [];

    public function __construct(public readonly Profil $profil, public readonly array $meta, private readonly string $seite)
    {
    }

    public function e(string $s): string
    {
        return Text::e($s);
    }

    public function inline(string $s): string
    {
        return Text::inline($s);
    }

    public function absaetze(?string $s, string $klasse = ''): string
    {
        return Text::absaetze((string) $s, $klasse);
    }

    /** Text je nach Anrede des Profils. */
    public function du(string $du, string $sie): string
    {
        return ($this->profil->daten['anrede'] ?? 'du') === 'Sie' ? $sie : $du;
    }

    public function id(int $nr, string $name): string
    {
        return "lp-a$nr-$name";
    }

    /** <section> eines Bausteins mit Klassen, optionalem Anker und Bezug auf seine Überschrift. */
    public function oeffnen(array $b, int $nr): string
    {
        $typ = $b['typ'];
        $anker = isset($b['anker']) ? ' id="' . $this->e($b['anker']) . '"' : '';
        return sprintf('<section class="lp-block lp-%s lp-%s--%s"%s aria-labelledby="%s">', $typ, $typ, $this->e($b['variante']), $anker, $this->id($nr, 'titel'));
    }

    public function titel(int $nr, string $text): string
    {
        return '<h2 class="lp-titel" id="' . $this->id($nr, 'titel') . '">' . $this->e($text) . '</h2>';
    }

    /**
     * <img> mit Maßen aus der Datei. bestand/<name> wird nach es/bilder/ kopiert, img/<name> bleibt, wo es ist.
     * $stumm: das Bild steht in einem beschrifteten Bedienelement und bekommt kein eigenes alt.
     */
    public function bild(array $bild, string $klasse, bool $wichtig = false, bool $stumm = false): string
    {
        $datei = (string) $bild['datei'];
        $name = substr($datei, strpos($datei, '/') + 1);
        if (str_starts_with($datei, 'bestand/')) {
            $quelle = "$this->seite/profil/bilder/$name";
            $src = "es/bilder/$name";
            $this->kopien[$src] = $quelle;
            $alt = $bild['alt'] ?? (string) ($this->profil->bild($name)['alt'] ?? '');
        } else {
            $quelle = "$this->seite/img/$name";
            $src = "img/$name";
            $alt = (string) ($bild['alt'] ?? '');
        }
        if ($stumm || ($bild['dekorativ'] ?? false) === true) {
            $alt = '';
        }
        $mass = @getimagesize($quelle) ?: throw new BauFehler("Bildmaße nicht lesbar: $datei");
        $laden = $wichtig ? ' fetchpriority="high" decoding="async"' : ' loading="lazy" decoding="async"';
        return sprintf('<img class="%s" src="%s" alt="%s" width="%d" height="%d"%s>', $klasse, $this->e($src), $this->e($alt), $mass[0], $mass[1], $laden);
    }

    public function knopf(array $knopf): string
    {
        return '<a class="btn btn-primary lp-knopf" href="' . $this->e((string) $knopf['ziel']) . '">' . $this->e((string) $knopf['text']) . '</a>';
    }

    /** JSON-LD, sicher gegen </script> und & im Text. */
    public function jsonLd(array $daten): string
    {
        $json = json_encode(['@context' => 'https://schema.org'] + $daten,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    public function datum(string $zeitpunkt): string
    {
        return Text::datum($zeitpunkt, $this->profil->sprache());
    }

    public function iso(string $zeitpunkt, int $plusMinuten = 0): string
    {
        return Text::iso($zeitpunkt, $plusMinuten);
    }
}
