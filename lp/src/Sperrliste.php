<?php
/**
 * Die Suche in der Sperrliste und in den Grenzwörtern des Marken-Profils: welche Wörter der Liste stehen in einem Text?
 *
 * Kopie der Suche aus panel app/Services/Landingpage/Antworten.php (sperrwort(), rc1084) — beide müssen auf denselben Eingaben dasselbe sagen. Der Baukasten hat keine Abhängigkeit zum Panel:
 * Die Faltung (Endo\Lp\TextFaltung) und diese Suche stehen für sich. Der Unterschied: Das Panel braucht nur das ERSTE getroffene Wort (wort()), der Prüfer meldet jedes (treffer()), wie er es schon
 * vor 3.54.0 tat.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class Sperrliste
{
    /** Was wort() zurückgibt, wenn sich ein Text oder ein Eintrag der Liste nicht prüfen lässt (zu lang, kein gültiges UTF-8 …): ein Treffer statt „frei“. */
    public const NICHT_PRUEFBAR = 'ein nicht prüfbarer Text';

    /**
     * Ein Eintrag der Liste mit so vielen Buchstaben und Ziffern der Vergleichsform oder mehr (ä zählt als „ae“) wird auch gefunden, wenn der Text Trenner mitten im Wort hat (gar antiert,
     * ga-rantiert). Kürzere nicht: „ich eilte“ ergäbe „heilt“.
     */
    private const STRANG_AB = 8;

    /**
     * Das erste Wort der Liste, das im Text vorkommt — sonst null; lässt sich der Text oder ein Eintrag nicht prüfen, NICHT_PRUEFBAR (gesperrt, nie frei). Dieselbe Antwort wie Antworten::sperrwort im Panel.
     *
     * @param list<string> $liste
     */
    public static function wort(string $text, array $liste): ?string
    {
        $treffer = self::treffer($text, $liste, true);

        return $treffer === null ? self::NICHT_PRUEFBAR : ($treffer[0] ?? null);
    }

    /**
     * Alle Wörter der Liste, die im Text vorkommen, in der Reihenfolge der Liste, wie sie dastehen — [] wenn keines; null, wenn sich der Text oder ein Eintrag der Liste nicht prüfen lässt (TextFaltung gibt null).
     * null heißt NIE „nichts gefunden“: Wer sucht, um zu sperren, sperrt dann. Ohne Liste gibt es nichts zu sperren ([]); ein Eintrag, der gefaltet nichts übrig lässt (nur „*“, nur Unsichtbares), zählt nicht.
     *
     * Verglichen wird, was der Leser sieht: Text und Eintrag gehen durch dieselbe Faltung (Schreibweise, Groß/Klein, Unsichtbares, Verwechslungszeichen, Umlaut-Umschrift, Buchstabenreihen), dann zählt
     * jedes TEILWORT („garantiert“ steckt auch in „ungarantiertes“; der Eintrag „Heilkräuter“ trifft den Text „Kräuter“ nicht, wohl aber „Heilkräutertee“), in jeder Lesart des Texts (lesarten()) und jeder
     * Fassung des Eintrags (eintraege()). Ein Eintrag mit mindestens STRANG_AB Zeichen wird zusätzlich im Text ohne alle Zeichen zwischen den Buchstaben gesucht: ein einzelnes Wort im Strang jeder
     * Lesart (TextFaltung::strang, bei „gar.antiert“ darf auch ein Punkt mitten im Wort stehen), eine Wendung („hilft gegen“, „100 % sicher“) im Satzstrang des Texts, in dem ein Satzende trennt
     * (TextFaltung::satzstrang, je Lesart; „%“ und Symbole bleiben, sonst träfe „100 € sicher“ die Wendung „100 % sicher“) — „Das hilft. Gegen alles“ ist keine Wendung „hilft gegen“, „Durch fall verschwunden“ schon.
     *
     * @param list<string> $liste
     * @return ?list<string>
     */
    public static function treffer(string $text, array $liste, bool $nurErsten = false): ?array
    {
        if ($liste === []) {
            return [];
        }
        $lesarten = self::lesarten($text);
        if ($lesarten === null) {
            return null;
        }
        $straenge = [];
        $satzstraenge = null;
        $treffer = [];
        foreach ($liste as $wort) {
            $wort = (string) $wort;
            $fassungen = self::eintraege($wort);
            if ($fassungen === null) {
                return null;
            }
            $fassungen = array_values(array_filter($fassungen, static fn (string $w): bool => $w !== ''));
            if ($fassungen === []) {
                continue;
            }
            $gefunden = self::teilwort($lesarten, $fassungen);
            if (!$gefunden) {
                $w = $fassungen[0];
                $einWort = preg_match('~^\p{L}+$~u', $w);
                $s = $einWort === false ? null : TextFaltung::strang($w, $einWort !== 1);   // eine Wendung behält „%“ und Symbole, ein Wort nicht
                if ($s === null) {
                    return null;
                }
                if (mb_strlen($s) < self::STRANG_AB) {
                    continue;
                }
                if ($einWort === 1) {
                    foreach ($lesarten as $i => $vergleich) {
                        $straenge[$i] ??= TextFaltung::strang($vergleich);
                        if ($straenge[$i] === null) {
                            return null;
                        }
                        if (str_contains($straenge[$i], $s)) {
                            $gefunden = true;
                            break;
                        }
                    }
                } else {
                    $satzstraenge ??= TextFaltung::satzstrang($text);
                    if ($satzstraenge === null) {
                        return null;
                    }
                    foreach ($satzstraenge as $satz) {
                        if (str_contains($satz, $s)) {
                            $gefunden = true;
                            break;
                        }
                    }
                }
            }
            if ($gefunden) {
                $treffer[] = $wort;
                if ($nurErsten) {
                    break;
                }
            }
        }

        return $treffer;
    }

    /**
     * Die Lesarten eines Texts (TextFaltung::lesarten) — oder null, wenn er sich nicht prüfen lässt. Der Prüfer fragt über diese Stelle, nie die Faltung selbst.
     *
     * Zusätzlich zur Faltung (Baukasten, nicht im Panel): Die Faltung rechnet mit mbstring-Funktionen, die die INTERNE Kodierung nehmen (mb_str_split, mb_ord, mb_convert_kana …). Steht sie nicht auf UTF-8
     * (default_charset in der php.ini), zerlegt die Faltung die Tabellen byteweise und liefert STILL ein falsches Ergebnis: Kyrillisch und Vollbreit würden nicht mehr erkannt. Das darf nie „frei“ heißen.
     *
     * @param bool $ohnePunkte Leerzeichen um Satzzeichen bleiben stehen (TextFaltung::lesartenOhnePunkte)
     * @return ?list<string>
     */
    public static function lesarten(string $text, bool $ohnePunkte = false): ?array
    {
        if (!self::utf8()) {
            return null;
        }

        return $ohnePunkte ? TextFaltung::lesartenOhnePunkte($text) : TextFaltung::lesarten($text);
    }

    /**
     * Ein Eintrag der Liste in seinen Fassungen (TextFaltung::eintraege) — oder null, wenn er sich nicht prüfen lässt (zu lang, kein gültiges UTF-8, interne Kodierung nicht UTF-8).
     *
     * @return ?list<string>
     */
    public static function eintraege(string $wort): ?array
    {
        return self::utf8() ? TextFaltung::eintraege($wort) : null;
    }

    private static function utf8(): bool
    {
        return mb_internal_encoding() === 'UTF-8';
    }

    /** Steht eine Fassung des Eintrags als Teilwort in einer Lesart des Texts? */
    private static function teilwort(array $lesarten, array $fassungen): bool
    {
        foreach ($fassungen as $w) {
            foreach ($lesarten as $vergleich) {
                if (str_contains($vergleich, $w)) {
                    return true;
                }
            }
        }

        return false;
    }
}
