<?php
/**
 * „Profil übernehmen": kopiert ein Kundenprofil in den Ordner einer Landingpage (profil/). Nur so erreicht eine
 * Profiländerung eine Seite — jeder Bau liest ausschließlich die Kopie und ist damit reproduzierbar.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class ProfilKopie
{
    /** @return list<Befund> sperrende Befunde → nichts kopiert */
    public static function uebernehmen(string $seite, string $profilOrdner): array
    {
        $p = Profil::laden($profilOrdner);
        $befunde = $p->pruefen();
        if (Befund::sperrt($befunde)) {
            return $befunde;
        }
        $ziel = "$seite/profil";
        Dateien::loeschen($ziel);
        foreach (['profil.json', 'profil.css', (string) $p->daten['logo']['datei'], 'rechtliches/impressum.txt', 'rechtliches/datenschutz.txt'] as $f) {
            Dateien::kopieren("$profilOrdner/$f", "$ziel/$f");
        }
        if (is_file("$profilOrdner/bilder/bilder.json")) {
            Dateien::kopieren("$profilOrdner/bilder/bilder.json", "$ziel/bilder/bilder.json");
            foreach (array_keys($p->bilder) as $datei) {
                Dateien::kopieren("$profilOrdner/bilder/$datei", "$ziel/bilder/$datei");
            }
        }
        if (is_dir("$profilOrdner/schrift")) {
            foreach (Dateien::eintraege("$profilOrdner/schrift") as $s) {
                if (str_ends_with($s, '.woff2')) {
                    Dateien::kopieren("$profilOrdner/schrift/$s", "$ziel/schrift/$s");
                }
            }
        }
        Dateien::schreiben("$ziel/VERSION", $p->daten['version'] . "\n");
        return $befunde;
    }
}
