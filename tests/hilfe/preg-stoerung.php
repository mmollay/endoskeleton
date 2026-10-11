<?php
/**
 * Schadensattrappe für preg_replace, preg_replace_callback und preg_match im Namensraum Endo\Lp (dort stehen TextFaltung, Sperrliste und der Prüfer) — damit ein Test beweisen kann,
 * dass jeder Regex-Schritt der Faltung, der scheitert, zu „nicht prüfbar“ führt und nie zu einem leeren Text (der nichts findet).
 *
 * Wichtig: PHP merkt sich beim ersten Aufruf einer Funktion im Namensraum, welche gemeint ist (die des Namensraums oder die globale). Die Datei muss also geladen sein, BEVOR irgendein Code
 * des Namensraums läuft — deshalb wird sie nur in einem eigenen PHP-Prozess benutzt (tests/hilfe/preg-stoerung-lauf.php), nie im Prozess von tests/run.php.
 *
 *   require __DIR__ . '/preg-stoerung.php';                                // zuerst
 *   PregStoerung::$aufzeichnen = true;  …falten…  PregStoerung::$gesehen   // die Muster, die aufgerufen wurden
 *   PregStoerung::$anzahl                                                  // wie oft jedes Muster aufgerufen wurde
 *   PregStoerung::$scheitern = $muster;                                    // dieses Muster liefert null, wie bei einem PCRE-Fehler (Rücksprung-, JIT-Grenze)
 *   PregStoerung::$verschonen = 1;                                         // … aber erst ab dem 2. Aufruf (der erste geht durch): so erreicht man auch den zweiten Aufruf desselben Musters
 *
 * Warum so: Einen PCRE-Fehler lässt sich mit pcre.backtrack_limit nur für den ersten Schritt erzwingen, der zuerst über die Grenze kommt; die anderen Schritte erreicht man so nie.
 * Ohne Einstellung (scheitern = null) reichen die Funktionen alles unverändert an PHP weiter. Gilt nur für Aufrufe ohne führenden Backslash im Namensraum Endo\Lp.
 */

declare(strict_types=1);

namespace Endo\LpTest {

    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

    final class PregStoerung
    {
        public static bool $aufzeichnen = false;
        /** @var list<string> */
        public static array $gesehen = [];
        /** @var array<string,int> */
        public static array $anzahl = [];
        public static ?string $scheitern = null;
        public static int $verschonen = 0;

        /** Soll dieser Aufruf scheitern? Zeichnet auf, wenn gewünscht. */
        public static function scheitert(string|array $muster): bool
        {
            $m = is_string($muster) ? $muster : implode('|', $muster);
            if (self::$aufzeichnen) {
                self::$anzahl[$m] = (self::$anzahl[$m] ?? 0) + 1;
                if (!in_array($m, self::$gesehen, true)) {
                    self::$gesehen[] = $m;
                }
            }
            if (self::$scheitern === null || $m !== self::$scheitern) {
                return false;
            }
            if (self::$verschonen > 0) {
                self::$verschonen--;

                return false;
            }

            return true;
        }
    }
}

namespace Endo\Lp {

    use Endo\LpTest\PregStoerung;

    function preg_replace(string|array $pattern, string|array $replacement, string|array $subject, int $limit = -1, ?int &$count = null): string|array|null
    {
        if (PregStoerung::scheitert($pattern)) {
            return null;
        }

        return \preg_replace($pattern, $replacement, $subject, $limit, $count);
    }

    function preg_match(string $pattern, string $subject, &$matches = null, int $flags = 0, int $offset = 0): int|false
    {
        if (PregStoerung::scheitert($pattern)) {
            return false;
        }

        return \preg_match($pattern, $subject, $matches, $flags, $offset);
    }

    function preg_replace_callback(string|array $pattern, callable $callback, string|array $subject, int $limit = -1, ?int &$count = null, int $flags = 0): string|array|null
    {
        if (PregStoerung::scheitert($pattern)) {
            return null;
        }

        return \preg_replace_callback($pattern, $callback, $subject, $limit, $count, $flags);
    }
}
