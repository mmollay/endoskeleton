<?php
/**
 * „Profil übernehmen“: kopiert ein Kundenprofil in den Ordner einer Landingpage (profil/). Nur so erreicht eine
 * Profiländerung eine Seite — jeder Bau liest ausschließlich die Kopie und ist damit reproduzierbar.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class ProfilKopie
{
    /**
     * Kopiert zuerst in einen Zwischenordner und tauscht erst danach — scheitert das Kopieren (Verweis, fehlende
     * Datei), bleibt die bisherige Kopie vollständig erhalten.
     * @return list<Befund> sperrende Befunde → nichts kopiert
     */
    public static function uebernehmen(string $seite, string $profilOrdner): array
    {
        $echt = realpath($profilOrdner);
        $seiteEcht = realpath($seite);
        if ($echt === false || $seiteEcht === false || str_starts_with($echt . '/', $seiteEcht . '/')) {
            throw new \RuntimeException("Das Profil muss außerhalb der Seite liegen: $profilOrdner");
        }
        $p = Profil::laden($profilOrdner);
        $befunde = $p->pruefen();
        if (Befund::sperrt($befunde)) {
            return $befunde;
        }
        $neu = "$seite/.profil-neu";
        Dateien::loeschen($neu);
        try {
            foreach (['profil.json', 'profil.css', (string) $p->daten['logo']['datei'], 'rechtliches/impressum.txt', 'rechtliches/datenschutz.txt'] as $f) {
                Dateien::kopieren("$profilOrdner/$f", "$neu/$f");
            }
            if (is_file("$profilOrdner/bilder/bilder.json")) {
                Dateien::kopieren("$profilOrdner/bilder/bilder.json", "$neu/bilder/bilder.json");
                foreach (array_keys($p->bilder) as $datei) {
                    Dateien::kopieren("$profilOrdner/bilder/$datei", "$neu/bilder/$datei");
                }
            }
            if (is_dir("$profilOrdner/schrift")) {
                foreach (Dateien::eintraege("$profilOrdner/schrift") as $s) {
                    if (str_ends_with($s, '.woff2')) {
                        Dateien::kopieren("$profilOrdner/schrift/$s", "$neu/schrift/$s");
                    }
                }
            }
            Dateien::schreiben("$neu/VERSION", $p->daten['version'] . "\n");
        } catch (\Throwable $e) {
            Dateien::loeschen($neu);
            throw $e;
        }
        Dateien::loeschen("$seite/profil");
        rename($neu, "$seite/profil");
        return $befunde;
    }
}
