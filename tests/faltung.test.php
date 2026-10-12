<?php
/**
 * Sperrliste der Landingpages, Teil 1: die Faltung (Endo\Lp\TextFaltung) und die Suche (Endo\Lp\Sperrliste) — php8.2 tests/run.php faltung
 *
 * Anlass (Baukasten 3.54.0): Der Prüfer verglich die Sperrliste und die Grenzwörter des Marken-Profils nur nach „** weg, weiche Trennzeichen weg, Leerraum zusammen“. Er ließ sich durch
 * Schreibweisen umgehen, die der Leser für dasselbe Wort hält — kyrillisches „а“ statt „a“, Vollbreit, mathematische Schriften, Umlaut in NFD, Buchstabenreihen („g a r a n t …“). Das Panel
 * hat dasselbe Problem in rc1084 gelöst (App\Support\TextFaltung); der Baukasten trägt eine eigene Kopie, ohne Abhängigkeit zum Panel. Die Prüfungen hier sind die des Panels
 * (tests/LandingpageSperrlisteTest.php dort), soweit sie für die Kopie gelten; was nur das Panel kennt (Gespräch, Schnittstelle, Bilder) fehlt. Wo der Prüfer angeschlossen ist,
 * steht in tests/pruefen-sperrliste.test.php.
 *
 *   FA00 die Bausteine sind da: TextFaltung, Sperrliste, die Obergrenze; Pruefer::normal ist weg (keine zweite Normalisierung)
 *   FA01 jede genannte Umgehung wird erkannt: NFD, kyrillisch/griechisch, Vollbreit, Schriftformen, Ligaturen, vorkomponierte Akzentbuchstaben, kombinierende Zeichen, Unsichtbares,
 *        Striche und Prozentzeichen-Varianten, Einträge mit Richtungszeichen
 *   FA02 Abstände: g a r a n t i e r t, g.a.r.a.n.t …; lange Einzelwörter mit einem Trenner mitten im Wort; Wendungen mit gespreizten Wörtern; Füller statt Leerzeichen
 *   FA03 ß/ss und ä/ae, ö/oe, ü/ue gelten als gleich; der Umlaut ohne Punkte (Krauter) NICHT
 *   FA04 FEHLALARM: gewöhnlicher deutscher Text bleibt frei
 *   FA05 die Teilwort-Logik der bisherigen Prüfung bleibt
 *   FA06 bewusst NICHT gefaltet (festgehalten, damit es nicht still anders wird) und bekannte Nebenwirkungen
 *   FA08 begrenzte Zeit und fail-closed: zu lang, ungültiges UTF-8, Richtungszeichen und jeder gescheiterte Regex-Schritt lassen NIE durch
 *   FA09 Eigenschaften: Tabellen ohne deutsche Buchstaben; NFC = NFD für alle 790 Akzentbuchstaben; 994 Kompatibilitätszeichen; Marken und Unsichtbares mitten im Wort; Lesarten; Stabilität
 *   FA10 der gewöhnliche Text der Baukasten-Dokumente wird mit eigenen Beispiellisten NICHT häufiger gesperrt als vorher
 *   FA12 Härtung (Befunde der unabhängigen Prüfung; seit Panel rc1092 in beiden Kopien): Zalgo mit Unsichtbarem davor, Satzende vor Anführungszeichen, Auslassungszeichen, lesartenOhnePunkte
 *   FA13 Härtung 2 (Panel rc1092, beide Kopien): Buchstaben mit Haken/Strich/Tilde ohne Unicode-Zerlegung (tests/fixtures/sperrliste-haken.json), dünne Leerzeichen
 *   FA11 eine Stelle: nur der Prüfer ruft die Suche auf, nur die Suche die Faltung, und niemand vergleicht selbst
 *
 * Keine Kundenlisten: Die Wörter der Listen sind erfundene Beispiele. Die Fixtures sind Auszüge aus der Unicode-Datenbank (tests/fixtures/sperrliste-*.json).
 */

declare(strict_types=1);

require_once __DIR__ . '/hilfe.php';

use Endo\Lp\Sperrliste;
use Endo\Lp\TextFaltung;

$expect = static function (bool $cond, string $msg): void {
    T::ok($cond, $msg);
};
/** Der Vergleich der Sperrliste — die eine Stelle. */
$sp = static fn (string $text, array $liste): ?string => Sperrliste::wort($text, $liste);
/** Das Verhalten bis 3.53.0 (Pruefer::normal + mb_stripos), nur als Referenz für „vorher“ und den Korpus FA10. */
$vorher = static function (string $text, array $liste): ?string {
    $normal = static fn (string $t): string => trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\x{00AD}\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', str_replace('**', '', $t))));
    $v = $normal($text);
    foreach ($liste as $w) {
        $n = $normal((string) $w);
        if ($n !== '' && mb_stripos($v, $n) !== false) {
            return (string) $w;
        }
    }
    return null;
};
/** Eine Reihe Zeichen einer Schriftform: Anfang des kleinen „a“ der Form + Buchstabe. */
$schrift = static fn (string $wort, int $anfangA): string => implode('', array_map(static fn (string $c): string => mb_chr($anfangA + ord($c) - ord('a')), str_split($wort)));
/** Erfundene Beispielliste (keine Liste eines Kunden). */
$liste = ['garantiert', 'Heilkräuter', 'hilft gegen', '100 % sicher', 'risikofrei', 'heilt', 'Wirkstoff'];
/** Wie lange eine Aufgabe braucht: [Ergebnis, Sekunden]. */
$zeit = static function (callable $f): array {
    $t = hrtime(true);
    $r = $f();
    return [$r, (hrtime(true) - $t) / 1e9];
};
$grenzeS = 3.0;

return [
    'FA00 die Bausteine sind da, und Pruefer::normal gibt es nicht mehr' => static function () use ($expect): void {
        $expect(class_exists(TextFaltung::class), 'FA00 die Klasse Endo\\Lp\\TextFaltung ist da');
        $expect(class_exists(Sperrliste::class), 'FA00 die Klasse Endo\\Lp\\Sperrliste ist da');
        $expect(class_exists(TextFaltung::class) && method_exists(TextFaltung::class, 'falten') && method_exists(TextFaltung::class, 'lesarten') && method_exists(TextFaltung::class, 'eintraege') && method_exists(TextFaltung::class, 'strang')
            && method_exists(TextFaltung::class, 'satzstrang') && defined(TextFaltung::class . '::MAX_BYTES') && constant(TextFaltung::class . '::MAX_BYTES') >= 65536,
            'FA00 falten(), lesarten(), eintraege(), strang(), satzstrang() und die Obergrenze MAX_BYTES (mindestens 64 KB)');
        $expect(class_exists(Sperrliste::class) && method_exists(Sperrliste::class, 'wort') && method_exists(Sperrliste::class, 'treffer') && method_exists(Sperrliste::class, 'lesarten') && method_exists(Sperrliste::class, 'eintraege') && defined(Sperrliste::class . '::NICHT_PRUEFBAR')
            && (string) (new \ReflectionMethod(Sperrliste::class, 'wort'))->getReturnType() === '?string' && (new \ReflectionMethod(Sperrliste::class, 'wort'))->isStatic()
            && (new \ReflectionMethod(Sperrliste::class, 'wort'))->getNumberOfParameters() === 2,
            'FA00 Sperrliste::wort(string $text, array $liste): ?string (wie Antworten::sperrwort im Panel), treffer() und die Marke NICHT_PRUEFBAR');
        $expect(!method_exists(\Endo\Lp\Pruefer::class, 'normal'), 'FA00 Pruefer::normal gibt es nicht mehr — keine zweite Normalisierung neben der Faltung');
    },

    'FA01 jede Umgehung aus der Prüfung der Schnittstelle wird erkannt (NFD, kyrillisch/griechisch, Vollbreit, Schriftformen, Ligaturen, Unsichtbares …)' => static function () use ($expect, $sp, $vorher, $schrift, $liste): void {
    $umgehungen = [
        'NFD-Umlaut (a + U+0308)'                       => ["Das sind Heilkra\u{0308}uter", 'Heilkräuter'],
        'NFD-Umlaut, Großbuchstaben'                    => ["HEILKRA\u{0308}UTER", 'Heilkräuter'],
        'kyrillisches а (U+0430) in garantiert'         => ["Das ist g\u{0430}rantiert", 'garantiert'],
        'kyrillisch: а, е, і (U+0430/0435/0456)'        => ["g\u{0430}r\u{0430}ntI\u{0435}rt und r\u{0456}s\u{0456}kofre\u{0456}", 'garantiert'],
        'kyrillisch, Großbuchstabe А (U+0410)'          => ["Das ist G\u{0410}RANTIERT", 'garantiert'],
        'kyrillisch: о und р in „Wirkstoff“'            => ["Der Wirkst\u{043E}ff, die W\u{0456}rkstoff", 'Wirkstoff'],
        'griechisch: ο (omicron) in „Wirkstoff“'        => ["Der Wirkst\u{03BF}ff hilft", 'Wirkstoff'],
        'griechisch: ι (iota) in „risikofrei“'          => ["Das ist r\u{03B9}s\u{03B9}kofre\u{03B9}", 'risikofrei'],
        'griechisch: Κ (kappa) und Ε in „Krebs“'        => ["Gegen \u{039A}r\u{0395}bs", 'Krebs'],
        'Vollbreit: ｇ (U+FF47) am Anfang'              => ["Das ist \u{FF47}arantiert", 'garantiert'],
        'Vollbreit: ganzes Wort in Großbuchstaben'      => ["Das ist \u{FF27}\u{FF21}\u{FF32}\u{FF21}\u{FF2E}\u{FF34}\u{FF29}\u{FF25}\u{FF32}\u{FF34}", 'garantiert'],
        'Vollbreit: Ziffern und Prozentzeichen'         => ["Das ist \u{FF11}\u{FF10}\u{FF10}\u{FF05} sicher", '100 % sicher'],
        'Vollbreit: Leerzeichen U+3000'                 => ["Das hilft\u{3000}gegen Angst", 'hilft gegen'],
        'mathematisch fett serifenlos (𝗴𝗮𝗿𝗮…)'          => ['Das ist ' . $schrift('garantiert', 0x1D5EE), 'garantiert'],
        'mathematisch kursiv (𝑔𝑎𝑟𝑎…)'                   => ['Das ist ' . $schrift('garantiert', 0x1D44E), 'garantiert'],
        'mathematisch Schreibschrift/Fraktur gemischt'  => ['Das ist ' . mb_chr(0x1D4F0) . mb_chr(0x1D51E) . 'rantiert', 'garantiert'],
        'mathematisch Monospace Großbuchstaben'         => ['Das ist ' . implode('', array_map(static fn (string $c): string => mb_chr(0x1D670 + ord($c) - ord('A')), str_split('GARANTIERT'))), 'garantiert'],
        'mathematische Ziffern: fett, doppelt, serifenlos fett' => ['Das ist ' . mb_chr(0x1D7CF) . mb_chr(0x1D7CE) . mb_chr(0x1D7CE) . ' % sicher, ' . mb_chr(0x1D7D9) . mb_chr(0x1D7D8) . mb_chr(0x1D7D8) . ' % sicher, ' . mb_chr(0x1D7ED) . mb_chr(0x1D7EC) . mb_chr(0x1D7EC) . ' % sicher', '100 % sicher'],
        'mathematische Ziffer 9 in „99 % sicher“'       => ['Das ist ' . mb_chr(0x1D7D7) . mb_chr(0x1D7D7) . ' % sicher', '99 % sicher'],
        'quadratische Buchstaben (🅶🅐🆁…)'                => ['Das ist ' . implode('', array_map(static fn (string $c): string => mb_chr(0x1F130 + ord($c) - ord('a')), str_split('garantiert'))), 'garantiert'],
        'schwarz eingekreiste Buchstaben'               => ['Das ist ' . implode('', array_map(static fn (string $c): string => mb_chr(0x1F150 + ord($c) - ord('a')), str_split('garantiert'))), 'garantiert'],
        'geklammerte Buchstaben (⒢⒜⒭…)'                  => ['Das ist ' . implode('', array_map(static fn (string $c): string => mb_chr(0x249C + ord($c) - ord('a')), str_split('garantiert'))), 'garantiert'],
        'mathematisch kursives h (ℎ, U+210E) in „heilt“' => ["Das \u{210E}eilt alles", 'heilt'],
        'eingekreist (ⓖⓐⓡⓐⓝⓣⓘⓔⓡⓣ)'                      => ['Das ist ' . $schrift('garantiert', 0x24D0), 'garantiert'],
        'eingekreist, Großbuchstaben (Ⓖ…)'              => ['Das ist ' . implode('', array_map(static fn (string $c): string => mb_chr(0x24B6 + ord($c) - ord('A')), str_split('GARANTIERT'))), 'garantiert'],
        'Ligatur ﬀ (U+FB00) in „Wirkstoff“'            => ["Der Wirksto\u{FB00} hilft", 'Wirkstoff'],
        'punktloses ı (U+0131) statt i'                 => ["Das ist garant\u{0131}ert", 'garantiert'],
        'langes ſ (U+017F)'                             => ["Der Wirk\u{017F}toff", 'Wirkstoff'],
        'Akzent als NFC (á)'                            => ["Das ist g\u{00E1}rantiert", 'garantiert'],
        'Akzent als NFD (a + U+0301)'                   => ["Das ist ga\u{0301}rantiert", 'garantiert'],
        'viele kombinierende Zeichen (Zalgo)'           => ["g\u{0337}\u{0301}a\u{0336}\u{0308}r\u{0335}antiert", 'garantiert'],
        'durchgestrichen mit U+0336 über jedem Buchstaben' => ['Das ist ' . implode("\u{0336}", str_split('garantiert')) . "\u{0336}", 'garantiert'],
        'Hangul-Füller U+3164 mitten im Wort'           => ["Das ist gar\u{3164}antiert", 'garantiert'],
        'Hangul-Füller U+115F (Choseong) mitten im Wort' => ["Das ist gar\u{115F}antiert", 'garantiert'],
        'Hangul-Füller U+1160 (Jungseong) mitten im Wort' => ["Das ist gar\u{1160}antiert", 'garantiert'],
        'Hangul-Füller U+115F im kurzen Wort'           => ["Das hei\u{115F}lt alles", 'heilt'],
        'Hangul-Füller U+1160 im kurzen Wort'           => ["Das hei\u{1160}lt alles", 'heilt'],
        'Hangul-Füller U+115F statt Leerzeichen in der Wendung' => ["Das hilft\u{115F}gegen Angst", 'hilft gegen'],
        'halbbreiter Füller U+FFA0 im kurzen Wort'      => ["Das hei\u{FFA0}lt alles", 'heilt'],
        'Braille-Leerzeichen U+2800 im kurzen Wort'     => ["Das hei\u{2800}lt alles", 'heilt'],
        'Khmer-Vokal U+17B4 (unsichtbar) im Wort'       => ["Das ist gar\u{17B4}antiert", 'garantiert'],
        'Khmer-Vokal U+17B5 (unsichtbar) im kurzen Wort' => ["Das hei\u{17B5}lt alles", 'heilt'],
        'Mongolischer Trenner U+180E im Wort'           => ["Das ist gar\u{180E}antiert", 'garantiert'],
        'Variantenzeichen U+FE0F im Wort'               => ["Das ist gar\u{FE0F}antiert", 'garantiert'],
        'kombinierender Grapheme-Joiner U+034F im Wort' => ["Das ist gar\u{034F}antiert", 'garantiert'],
        'Braille-Leerzeichen U+2800 mitten im Wort'     => ["Das ist gar\u{2800}antiert", 'garantiert'],
        'Steuerzeichen NUL und BS im Wort'              => ["Das ist gar\0an\x08tiert", 'garantiert'],
        'Zeichen der Schreibrichtung (schon vorher)'    => ["Das ist gar\u{200E}antiert", 'garantiert'],
        'Kombination: vollbreit + kyrillisch + NFD + unsichtbar' => ["\u{FF27}\u{0410}\u{200B}R\u{0430}\u{0301}NT\u{0456}\u{FEFF}ERT", 'garantiert'],
        'Eintrag der Liste in NFD, Text in NFC'         => ['Das sind Heilkräuter', "Heilkra\u{0308}uter"],
        'Eintrag der Liste mit Vollbreit, Text normal'  => ['Das ist garantiert gut', "\u{FF47}arantiert"],
        // Befunde der unabhängigen Prüfung (rc1084)
        'vorkomponiertes kyrillisches ӓ (U+04D3) statt ä' => ["Heilkr\u{04D3}uter", 'Heilkräuter'],
        'vorkomponiertes ạ (a mit Punkt darunter)'      => ["gar\u{1EA1}ntiert", 'garantiert'],
        'ǎ und ǧ (Haken)'                               => ["g\u{01CE}rantiert und \u{01E7}arantiert", 'garantiert'],
        'griechisches ά (alpha + Akzent)'               => ["g\u{03AC}rantiert", 'garantiert'],
        'a + Variantenzeichen U+180B + U+0308'          => ["Heilkra\u{180B}\u{0308}uter", 'Heilkräuter'],
        'a + Variantenzeichen U+E0100 + U+0308'         => ["Heilkra\u{E0100}\u{0308}uter", 'Heilkräuter'],
        'a + Akzent + U+0308 (Stapel mit Umlaut)'       => ["Heilkra\u{0301}\u{0308}uter", 'Heilkräuter'],
        'a + Punkt darunter + U+0308'                   => ["Heilkra\u{0323}\u{0308}uter", 'Heilkräuter'],
        'Zalgo, in dem ein U+0308 auf dem a liegt'      => ["g\u{0337}a\u{0336}\u{0308}rantiert", 'garantiert'],
        'nicht zugewiesenes Zeichen U+2065 im Wort'     => ["Das he\u{2065}ilt alles", 'heilt'],
        'nicht zugewiesen U+FFF0 im Wort'               => ["Das he\u{FFF0}ilt alles", 'heilt'],
        'nicht zugewiesen U+E0080 im Wort'              => ["Das he\u{E0080}ilt alles", 'heilt'],
        'Tatweel U+0640 mitten im langen Wort'          => ["Das ist gar\u{0640}antiert gut", 'garantiert'],
        'Dehnstrich U+30FC mitten im langen Wort'       => ["gar\u{30FC}antiert", 'garantiert'],
        'Tatweel zwischen allen Buchstaben'             => ["g\u{0640}a\u{0640}r\u{0640}a\u{0640}n\u{0640}t\u{0640}i\u{0640}e\u{0640}r\u{0640}t", 'garantiert'],
        'ordinal ª statt a'                             => ["g\u{00AA}rantiert", 'garantiert'],
        'römisches ⅼ (U+217C) statt l'                  => ["Hei\u{217C}kräuter", 'Heilkräuter'],
        'römisches ⅰ (U+2170) statt i'                  => ["Insul\u{2170}n", 'Insulin'],
        'römisches ⅽ (U+217D) statt c'                  => ["\u{217D}annabis", 'Cannabis'],
        'mathematisches Griechisch (ο, U+1D6D0)'        => ["Wirkst\u{1D6D0}ff", 'Wirkstoff'],
        'eingekreiste Ziffer ① statt 1'                 => ["Das ist \u{2460}00 % sicher", '100 % sicher'],
        'hochgestellte Buchstaben (ᵍᵃʳᵃⁿᵗⁱᵉʳᵗ)'         => ['Das ist ᵍᵃʳᵃⁿᵗⁱᵉʳᵗ', 'garantiert'],
        'tiefgestellte Buchstaben und ſ'                => ['Der Wirk' . "\u{017F}" . 'toff', 'Wirkstoff'],
        'Strich U+2011 (geschützter Bindestrich)'       => ["Alles DSGVO\u{2011}konform hier", 'DSGVO-konform'],
        'Strich U+2013 (Halbgeviertstrich)'             => ["Alles DSGVO\u{2013}konform hier", 'DSGVO-konform'],
        'Prozentzeichen U+FE6A (klein)'                 => ["Das ist 100 \u{FE6A} sicher", '100 % sicher'],
        'Prozentzeichen U+066A (arabisch)'              => ["Das ist 100\u{066A} sicher", '100 % sicher'],
        'Füller U+3164 statt Leerzeichen in der Wendung' => ["Das hilft\u{3164}gegen Angst", 'hilft gegen'],
        'Braille-Leerzeichen statt Leerzeichen'         => ["Das hilft\u{2800}gegen Angst", 'hilft gegen'],
        'halbbreiter Füller U+FFA0 in der Wendung'      => ["Das hilft\u{FFA0}gegen Angst", 'hilft gegen'],
        'Füller U+3164 mitten im kurzen Wort'           => ["Das hei\u{3164}lt alles", 'heilt'],
        // zweite unabhängige Prüfung
        'U+0345 (Ypogegrammeni) mitten im Wort'         => ["Das ist ga\u{0345}rantiert", 'garantiert'],
        'U+0345 im kurzen Wort'                         => ["Das hei\u{0345}lt", 'heilt'],
        'kleines e über dem u (U+0364, alte Schreibung ü)' => ["Verfu\u{0364}gbarkeit", 'Verfügbarkeit'],
        'kleines e über dem a (U+0364)'                 => ["Heilkra\u{0364}uter", 'Heilkräuter'],
        'nachgestelltes ¨ (U+00A8) wie in PDF-Auszügen' => ["Verfu\u{00A8}gbarkeit", 'Verfügbarkeit'],
        'ű (Doppelakut) sieht wie ü aus'                => ["Verf\u{0171}gbarkeit", 'Verfügbarkeit'],
        'ő (Doppelakut) sieht wie ö aus'                => ["H\u{0151}ren", 'Hören'],
        'Umkreisung U+20DD zwischen u und ¨'            => ["Verfu\u{20DD}\u{0308}gbarkeit", 'Verfügbarkeit'],
        'Einheitenquadrat ㎏ statt kg'                  => ["Nimm 10 \u{338F} in 10 Tagen ab", '10 kg in 10 Tagen'],
        'Einheitenquadrat ㎜ in „Immunsystem“'         => ["I\u{339C}unsystem stärken", 'Immunsystem'],
        'Digraph ǌ (nj) statt nj'                       => ["Ina\u{01CC}ektion", 'Inanjektion'],
        'römisches Ⅵ (U+2165, „VI“) in „Viagra“'        => ["\u{2165}agra", 'Viagra'],
        'römisches Ⅴ (U+2164) statt V'                  => ["\u{2164}iagra", 'Viagra'],
        'eingekreiste ⑩ statt 10'                       => ["\u{2469} Jahre Garantie", '10 Jahre Garantie'],
        'Klammerziffer ⑴ statt (1)'                     => ["Punkt \u{2474} gilt", 'Punkt (1) gilt'],
        '™ statt tm'                                    => ["Das Produk\u{2122}", 'Produktm'],
        'Zalgo mit kleinem e (U+0364) auf dem a'        => ["g\u{0337}a\u{0364}\u{0301}rantiert", 'garantiert'],
        'Zalgo mit Doppelakut (U+030B) auf dem a'       => ["g\u{0337}a\u{030B}\u{0301}rantiert", 'garantiert'],
        // dritte unabhängige Prüfung
        'Zeichen ab Unicode 14: ꟳ (U+A7F3, F) in „Wirkstoff“' => ["Wirksto\u{A7F3}\u{A7F3}", 'Wirkstoff'],
        'Zeichen ab Unicode 14: ꟳ in „risikofrei“'        => ["risiko\u{A7F3}rei", 'risikofrei'],
        'Zeichen ab Unicode 14: ꟲ (C) in „sicher“'        => ["Das ist 100 % si\u{A7F2}her", '100 % sicher'],
        'Unicode 16 (PCRE kennt es nicht): Outlined Latin A' => ["g\u{1CCD6}rantiert", 'garantiert'],
        'Zalgo mit ¨ auf dem a, Wendung mit Bindestrich' => ["Durchfa\u{301}\u{308}ll-verschwunden", 'Durchfall verschwunden'],
        'Zalgo mit ¨ auf dem a, Wendung mit Klammer'    => ["Durchfa\u{301}\u{308}ll (verschwunden)", 'Durchfall verschwunden'],
        'Zalgo mit ¨ auf dem o, Wendung mit Bindestrich' => ["o\u{301}\u{308}hne-Risiko", 'ohne Risiko'],
        'kurzer Eintrag mit Strich, Text mit U+2011'    => ["Das Wi\u{2011}Fi hier", 'Wi-Fi'],
        'kurzer Eintrag mit Strich, Text mit U+2013'    => ["Das Wi\u{2013}Fi hier", 'Wi-Fi'],
        'kurzer Eintrag mit Prozent, Text mit U+FE6A'   => ["Nur 5 \u{FE6A} Rabatt", '5 % Rabatt'],
        'kurzer Eintrag mit Prozent, Text mit U+066A'   => ["Nur 5\u{066A} Rabatt", '5 % Rabatt'],
        'Eintrag mit Richtungszeichen am Ende (Copy & Paste)' => ['Das ist garantiert gut', "garantiert\u{202C}"],
        'Eintrag mit Richtungsisolat um das Wort'       => ['Das ist garantiert gut', "\u{2066}garantiert\u{2069}"],
    ];
    $schlecht = [];
    foreach ($umgehungen as $was => [$text, $wort]) {
        // streng: allein in der Liste kommt genau dieser Eintrag zurück; in der Liste der Marke wenigstens irgendein Treffer, wenn der Eintrag dort steht
        if ($sp($text, [$wort]) !== $wort || (in_array($wort, $liste, true) && $sp($text, $liste) === null)) {
            $schlecht[] = $was;
        }
    }
    $expect($schlecht === [], 'FA01 jede Umgehung wird erkannt (' . count($umgehungen) . ' Schreibweisen) — nicht erkannt: ' . implode('; ', $schlecht));
    foreach (['NFD' => ["Heilkra\u{0308}uter", 'Heilkräuter'], 'kyrillisch' => ["g\u{0430}rantiert", 'garantiert'], 'Vollbreit' => ["\u{FF47}arantiert", 'garantiert']] as $was => [$text, $wort]) {
        $expect($sp($text, $liste) === $wort && $vorher($text, $liste) === null, "FA01 $was (genannt in der Prüfung): jetzt erkannt, vorher nicht");
    }
    },

    'FA02 Abstände zwischen den Buchstaben und mitten im langen Wort, gespreizte Wendungen, Füller statt Leerzeichen, Leerraum um Zeichen' => static function () use ($expect, $sp, $vorher, $liste): void {
    $abstaende = [
        'Leerzeichen zwischen allen Buchstaben'     => ['Das ist g a r a n t i e r t gut', 'garantiert'],
        'viele Leerzeichen zwischen den Buchstaben' => ['Das ist g   a   r   a   n   t   i   e   r   t gut', 'garantiert'],
        'Punkte zwischen den Buchstaben'            => ['Das ist g.a.r.a.n.t.i.e.r.t gut', 'garantiert'],
        'Bindestriche zwischen den Buchstaben'      => ['Das ist g-a-r-a-n-t-i-e-r-t gut', 'garantiert'],
        'Unterstriche zwischen den Buchstaben'      => ['Das ist g_a_r_a_n_t_i_e_r_t gut', 'garantiert'],
        'Sternchen mit Leerzeichen dazwischen'      => ['Das ist g * a * r * a * n * t * i * e * r * t gut', 'garantiert'],
        'Großbuchstaben, Schrägstriche'             => ['Das ist G/A/R/A/N/T/I/E/R/T gut', 'garantiert'],
        'zwei Zeichen zwischen den Buchstaben'      => ['Das ist g..a..r..a..n..t..i..e..r..t gut', 'garantiert'],
        'drei Zeichen zwischen den Buchstaben'      => ['Das ist g.-.a.-.r.-.a.-.n.-.t.-.i.-.e.-.r.-.t gut', 'garantiert'],
        'ein Wort mit Umlaut, Buchstabe für Buchstabe' => ['Das sind H e i l k r ä u t e r', 'Heilkräuter'],
        'kurzes Wort, Buchstabe für Buchstabe'      => ['Das h e i l t alles', 'heilt'],
        'ein Leerzeichen mitten im langen Wort'     => ['Das ist gar antiert gut', 'garantiert'],
        'ein Bindestrich mitten im langen Wort'     => ['Das ist ga-rantiert gut', 'garantiert'],
        'ein Punkt mitten im langen Wort'           => ['Das ist garan.tiert gut', 'garantiert'],
        'ein Unterstrich mitten im langen Wort'     => ['Das ist gar_antiert gut', 'garantiert'],
        'Leerzeichen im Wort „risikofrei“'          => ['Das ist risiko frei', 'risikofrei'],
        'Leerraum aus Tabulator und Zeilenumbruch'  => ["Das hilft\t\n gegen Angst", 'hilft gegen'],
        'geschütztes Leerzeichen U+00A0'            => ["Das hilft\u{00A0}gegen Angst", 'hilft gegen'],
        'Leerraum um das Prozentzeichen weg'        => ['Das ist 100% sicher', '100 % sicher'],
        'Leerraum um das Prozentzeichen mehr'       => ['Das ist 100   %   sicher', '100 % sicher'],
        'Leerzeichen vor dem Prozentzeichen weg'    => ['Das ist 100 %sicher', '100 % sicher'],
        'Wendung, beide Wörter gespreizt, Wortlücke breiter' => ['Das h i l f t   g e g e n Angst', 'hilft gegen'],
        'Wendung, alle Buchstaben gleich weit auseinander' => ['Das h i l f t g e g e n Angst', 'hilft gegen'],
        'Wendung, gespreizt mit Punkten'            => ['Das h.i.l.f.t g.e.g.e.n Angst', 'hilft gegen'],
        'Wendung „ohne Risiko“, gespreizt, Großbuchstaben' => ['Das O H N E   R I S I K O Angst', 'ohne Risiko'],
        'Wendung mit Ziffern, gespreizt'            => ['Das 100 % s i c h e r', '100 % sicher'],
        'Wendung mit gespreizten Ziffern und Buchstaben' => ['Das 1 0 0 % s i c h e r', '100 % sicher'],
        'Klammer-Großbuchstaben (🄖🄐🄡… = (G)(A)(R)…)' => ['Das ist ' . implode('', array_map(static fn (string $c): string => mb_chr(0x1F110 + ord($c) - ord('A')), str_split('GARANTIERT'))), 'garantiert'],
        'Wendung, ein langes Wort mitten geteilt'   => ['Nach Tag drei: Durch fall verschwunden.', 'Durchfall verschwunden'],
        'Wendung, zwei Wörter geteilt'              => ['Das ist absolut si cher gut', 'absolut sicher'],
        'Wendung mit Unterstrich statt Leerzeichen' => ['Das hilft_gegen Angst', 'hilft gegen'],
        'Wendung mit senkrechtem Strich'            => ['Das hilft|gegen Angst', 'hilft gegen'],
        'Wendung mit senkrechtem Strich und Leerzeichen' => ['Das hilft | gegen Angst', 'hilft gegen'],
        'Wendung mit Vollbreit-Strich ｜'            => ["Das hilft \u{FF5C} gegen Angst", 'hilft gegen'],
        'Wendung mit Prozent und senkrechtem Strich' => ['Nur 100 %|sicher', '100 % sicher'],
        'Wendung mit Strich im Eintrag (DSGVO-konform)' => ['Wir sind DSGVO|konform', 'DSGVO-konform'],
        'Wendung mit Leerzeichen vor dem Komma (kein Satzende)' => ['Das hilft , gegen Angst', 'hilft gegen'],
        'Wendung mit Zeilenumbruch mitten im Wort'  => ["Das hil\nft gegen Angst", 'hilft gegen'],
        'Wendung, langes Wort mit Zeilenumbruch geteilt' => ["Durch\nfall verschwunden", 'Durchfall verschwunden'],
        'Wendung mit Bindestrich statt Leerzeichen' => ['Das hilft-gegen Angst', 'hilft gegen'],
        'Wendung mit Komma im Wort (keine Satzgrenze, kein Leerzeichen)' => ['Das hil,ft gegen Angst', 'hilft gegen'],
        'Eintrag aus drei Einzelbuchstaben, der im Text Teil eines Worts ist' => ['Das gilt i. d. Regel auch', 'i. d. R'],
        'Eintrag aus drei Einzelbuchstaben im Satz'  => ['Plan ab c d', 'b c d'],
        'Eintrag aus einzelnen Buchstaben neben anderen Einzelbuchstaben' => ['Das gilt z. B. u. a. für Kinder', 'u. a.'],
        'Eintrag aus einzelnen Buchstaben (z. B.)'  => ['Das gilt z. B. u. a. für Kinder', 'z. B.'],
    ];
    $schlecht = [];
    foreach ($abstaende as $was => [$text, $wort]) {
        if ($sp($text, [$wort]) !== $wort || (in_array($wort, $liste, true) && $sp($text, $liste) === null)) {
            $schlecht[] = $was;
        }
    }
    $expect($schlecht === [], 'FA02 Abstände zwischen den Buchstaben und mitten im langen Wort werden erkannt (' . count($abstaende) . ' Fälle) — nicht erkannt: ' . implode('; ', $schlecht));
    $expect($sp('Das ist 100% sicher', $liste) === '100 % sicher' && $vorher('Das ist 100% sicher', $liste) === null, 'FA02 „100% sicher“ passierte vorher (Leerzeichen um das Zeichen), jetzt nicht');
    },

    'FA03 ß = ss und ä/ö/ü = ae/oe/ue gelten als gleich, der Umlaut ohne Punkte nicht' => static function () use ($expect, $sp): void {
    $gleich = [
        ['Gewißheit', 'Gewissheit'], ['STRASSE', 'Straße'], ['Straße', 'strasse'], ['Heilkraeuter', 'Heilkräuter'], ['HEILKRAEUTER', 'Heilkräuter'],
        ['Mädchen', 'Maedchen'], ['Größe', 'Groesse'], ['Grösse', 'Größe'], ['Müll', 'Muell'], ['Übung', 'UEBUNG'], ['Büro', "bu\u{0308}ro"],
    ];
    $schlecht = [];
    foreach ($gleich as [$a, $b]) {
        if ($sp("Hier steht $a im Text", [$b]) === null || $sp("Hier steht $b im Text", [$a]) === null) {
            $schlecht[] = "$a/$b";
        }
    }
    $expect($schlecht === [], 'FA03 ß = ss und ä/ö/ü = ae/oe/ue gelten als dieselbe Schreibweise (in beide Richtungen, auch in NFD) — verschieden: ' . implode('; ', $schlecht));
    $expect($sp('Hier steht Heilkrauter im Text', ['Heilkräuter']) === null && $sp('Hier steht Mutter im Text', ['Mütter']) === null,
        'FA03 der Umlaut ohne Punkte (Krauter, Mutter) wird NICHT zum Umlaut: Blüten/bluten, schön/schon, Lösung/Losung bleiben verschieden (FA04)');
    },

    'FA04 Fehlalarm: gewöhnlicher deutscher Text bleibt frei' => static function () use ($expect, $sp, $liste): void {
    $frei = [
        'Kräuter (ohne „Heil“) bei der Liste mit Heilkräuter'  => ['Frische Kräuter aus dem Garten, Kräutertee und Kräuterhaus', ['Heilkräuter']],
        'Garantie / Garantien bei garantiert'                  => ['Die Garantie läuft zwei Jahre, Garantien gibt es nicht, Garantiegeber fehlen', ['garantiert']],
        'Blüten (ü) bei „bluten“'                              => ['Im Mai stehen die Blüten in Weiß', ['bluten']],
        'Bluten bei „Blüten“'                                  => ['Wunden bluten oft stark', ['Blüten']],
        'schön bei „schon“'                                    => ['Es ist schön hier', ['schon']],
        'Lösung bei „Losung“'                                  => ['Die Lösung ist einfach', ['Losung']],
        'Mütter bei „Mutter“ (kein Teilwort)'                  => ['Die Mütter und Väter kamen', ['Mutter']],
        'zwei Wörter ergeben zufällig „Heilung“ (7 Zeichen)'   => ['Das Haus blieb heil. Ungefähr zwei Wochen später stand es noch', ['Heilung']],
        'zwei Wörter ergeben zufällig „heilt“'                 => ['Ich eilte nach Hause', ['heilt']],
        'Abkürzungen z. B., d. h., u. a., i. d. R.'            => ['Das gilt z. B. für Kinder, d. h. für alle, u. a. m. und i. d. R. auch für Erwachsene.', $liste],
        'Aufzählung Option A, B, C, D'                         => ['Du wählst Option A, B, C oder D.', $liste],
        'russischer Text'                                      => ['Привет, мир! Это обычный русский текст о хоре и роке.', $liste],
        'griechischer Text'                                    => ['Ελληνικά: α-Linolensäure, β-Carotin und Ω-3', $liste],
        'japanischer Text'                                     => ['日本語のテキストです。ｶﾀｶﾅも大丈夫。', $liste],
        'Emoji'                                                => ["Ein Vortrag \u{1F389} mit Überraschung \u{2728}", $liste],
        'Pangramm mit allen Umlauten'                          => ['Falsches Üben von Xylophonmusik quält jeden größeren Zwerg', $liste],
        'Zahlen und Prozente'                                  => ['Es sind 100 Plätze, 50 % Rabatt und 3 Tage Zeit', $liste],
        'Wendung mit Prozent: „100 Sicherheitsfunktionen“'      => ['Wir prüfen 100 Sicherheitsfunktionen täglich.', ['100 % sicher']],
        'Wendung mit Prozent: „100 € sicher“'                    => ['Bestellungen ab 100 € sicher versenden', ['100 % sicher']],
        'Wendung mit Prozent: „100 sicheren Plätzen“'            => ['Mit 100 sicheren Plätzen', ['100 % sicher']],
        'Wendung über ein Satzende, mit Buchstabenreihe (u. a. m.) im Text' => ["Das hilft.\nGegen Kälte hilft Wärme. Mu\u{0308}digkeit. Ca. u. a. m.", ['hilft gegen']],
        'Wendung über ein Satzende, mit Buchstabenreihe im Text' => ['Er kam herein. Minuten später ging er, i. d. R. spät.', ['in Minuten']],
        'Wendung über Doppelpunkt und Komma'                   => ['Das hilft: gegen alles, das hilft, gegen nichts', ['hilft gegen']],
        'NFD-Umlaut: „Lösung“ (NFD) ist nicht „Losung“'        => ["Die Lo\u{0308}sung ist einfach", ['Losung']],
        'NFD-Umlaut: „schön“ (NFD) ist nicht „schon“'          => ["Es ist scho\u{0308}n hier", ['schon']],
        'leerer Text'                                          => ['', $liste],
        'nur Leerraum'                                         => ["  \n\t ", $liste],
    ];
    $schlecht = [];
    foreach ($frei as $was => [$text, $l]) {
        $r = $sp($text, $l);
        if ($r !== null) {
            $schlecht[] = "$was → „{$r}“";
        }
    }
    $expect($schlecht === [], 'FA04 normaler deutscher Text wird nicht gesperrt (' . count($frei) . ' Fälle) — zu Unrecht: ' . implode('; ', $schlecht));
    $expect($sp('Die Garantie läuft zwei Jahre', ['Garantie']) === 'Garantie' && $sp('Die Garantie läuft zwei Jahre', ['garantiert']) === null,
        'FA04 „Garantie“ ist gesperrt, wenn die Liste „Garantie“ enthält — und frei, wenn nur „garantiert“ darauf steht (die Liste entscheidet, nicht die Faltung)');
    $expect($sp('Der Kräutertee ist heil', ['Heilkräuter']) === null && $sp('Heilkräutertee', ['Heilkräuter']) === 'Heilkräuter', 'FA04 Heilkräuter gesperrt (auch als Teilwort), Kräuter frei');
    $expect($sp('Café und Cafe', ['cafe']) === 'cafe' && $sp('Résumé', ['resume']) === 'resume', 'FA04 Akzente zählen nicht (é = e): so entscheidet die Faltung, damit „garántiert“ nicht durchgeht');
    $zwei = $sp('Das hilft. Gegen alles', ['hilft gegen']);
    $expect($zwei === null, 'FA04 eine Wendung mit Leerzeichen wird nicht über einen Satzgrenze hinweg gefunden („hilft. Gegen“) — nur Einzelwörter ab 8 Buchstaben werden auseinandergezogen gesucht');
    },

    'FA05 die Teilwort-Logik der bisherigen Prüfung bleibt (Teilwort, Groß/Klein, **, Leerraum, Reihenfolge, Original-Wort, leere Einträge)' => static function () use ($expect, $sp, $vorher): void {
    $expect($sp('Das ist ungarantiertes Glück', ['garantiert']) === 'garantiert' && $sp('GARANTIERT!', ['garantiert']) === 'garantiert' && $sp('garantiert', ['GARANTIERT']) === 'GARANTIERT',
        'FA05 Teilwort (auch mitten im Wort), Groß/Klein egal — wie bisher; zurück kommt der Eintrag der Liste, so wie er dasteht');
    $expect($sp('Das ist **garantiert** gut', ['garantiert']) === 'garantiert' && $sp('gar**antiert', ['garantiert']) === 'garantiert', 'FA05 ** (Fettdruck) fällt weg, auch mitten im Wort');
    $expect($sp('Das   hilft   gegen', ['hilft gegen']) === 'hilft gegen' && $sp("Das hilft\ngegen", ['hilft  gegen']) === 'hilft  gegen', 'FA05 Leerraum wird zusammengefasst, bei Text und Eintrag');
    $expect($sp('Das ist Risikofreiheit und garantiert', ['risikofrei', 'garantiert']) === 'risikofrei' && $sp('garantiert und risikofrei', ['risikofrei', 'garantiert']) === 'risikofrei',
        'FA05 es kommt der erste Eintrag der LISTE zurück, der im Text steht (nicht der erste im Text)');
    $expect($sp('Irgendein Text', ['', '  ', "\u{200B}", "x\u{FEFF}y", 'text']) === 'text' && $sp('Irgendein Text', []) === null && $sp('', ['text']) === null,
        'FA05 leere Einträge und Einträge aus Unsichtbarem zählen nicht; leere Liste und leerer Text sperren nichts');
    $expect($sp("gar\u{200B}antiert", ["gar\u{00AD}antiert"]) === "gar\u{00AD}antiert", 'FA05 Unsichtbares im Eintrag der Liste spielt keine Rolle (der Eintrag wird genauso gefaltet)');
    $expect($vorher('Wunder-Kräuter', ['Heilkräuter']) === null && $sp('Wunder-Kräuter', ['Heilkräuter']) === null, 'FA05 ein Bindestrich zwischen ZWEI Wörtern macht keine Zusammenschreibung („Wunder-Kräuter“ ≠ „Heilkräuter“)');
        $expect(TextFaltung::falten("  Ein   Text\n\nmit **Fett** \u{200B}") === 'ein text mit fett', 'FA05 falten(): klein, ohne **, ohne Unsichtbares, Leerraum zusammengefasst, getrimmt — ' . json_encode(TextFaltung::falten("  Ein   Text\n\nmit **Fett** \u{200B}")));
    },

    'FA06 bewusst nicht gefaltet (Leetspeak, Umlaut ohne Punkte, Kapitälchen …) und dokumentierte Nebenwirkungen' => static function () use ($expect, $sp): void {
    $bleibtOffen = [
        'Leetspeak: g4rantiert'                      => ['Das ist g4rantiert', ['garantiert']],
        'Leetspeak: h3ilkräuter'                     => ['Das sind h3ilkräuter', ['Heilkräuter']],
        'Leetspeak: 1 statt i'                       => ['Das ist r1s1kofre1', ['risikofrei']],
        'Umlaut ohne Punkte: Heilkrauter'            => ['Das sind Heilkrauter', ['Heilkräuter']],
        'Kapitälchen: ɢᴀʀᴀɴᴛɪᴇʀᴛ'                    => ['Das ist ɢᴀʀᴀɴᴛɪᴇʀᴛ', ['garantiert']],
        'Regionalzeichen 🇬🇦🇷…'                       => ['Das ist ' . implode('', array_map(static fn (string $c): string => mb_chr(0x1F1E6 + ord($c) - ord('A')), str_split('GARANTIERT'))), ['garantiert']],
        'vier Zeichen zwischen den Buchstaben eines kurzen Worts' => ['Das h----e----i----l----t alles', ['heilt']],
        'Apostroph mitten im kurzen Wort'            => ["Das he\u{02BC}ilt alles", ['heilt']],
        'Bindestrich mitten im kurzen Wort'          => ['Das hei-lt alles', ['heilt']],
        'ein Buchstabe vertauscht oder ergänzt'      => ['Das ist garrantiert', ['garantiert']],
        'umgekehrt geschrieben'                      => ['Das ist treitnarag', ['garantiert']],
    ];
    $gesperrt = [];
    foreach ($bleibtOffen as $was => [$text, $l]) {
        if ($sp($text, $l) !== null) {
            $gesperrt[] = $was;
        }
    }
    $expect($gesperrt === [], 'FA06 bewusst nicht gefaltet bleibt offen (Leetspeak ist ein anderes Wort für den Leser; Ziffern gehören zu Zahlen; Kapitälchen, Klammer-Großbuchstaben u. a. stehen als Lücke im Bericht) — plötzlich gesperrt: ' . implode('; ', $gesperrt));
    // Bekannte Nebenwirkungen, hier festgehalten, damit sie nicht still anders werden (Befunde F1/F2 der unabhängigen Prüfung): ß = ss und ö = oe treffen andere Wörter, die Wortnähe des Strangs
    $nebenwirkungen = [
        'Maße ↔ Masse (ß = ss)'                      => ['Die Masse des Körpers', ['Maße']],
        'Buße ↔ Busse'                               => ['Der Busse fährt', ['Buße']],
        'Öl ↔ „oel“ in fotoelektrisch (ö = oe)'      => ['Die fotoelektrische Wirkung', ['Öl']],
        'Risiko + frei: zwei Wörter ergeben den Eintrag' => ['Das Risiko frei zu entscheiden bleibt bei dir', ['risikofrei']],
        'rechts + sicheren: zwei Wörter ergeben den Eintrag' => ['Auf der rechts sicheren Seite', ['rechtssicher']],
    ];
    $nichtGesperrt = [];
    foreach ($nebenwirkungen as $was => [$text, $l]) {
        if ($sp($text, $l) === null) {
            $nichtGesperrt[] = $was;
        }
    }
    $expect($nichtGesperrt === [], 'FA06 bekannte Nebenwirkungen bleiben, wie sie dokumentiert sind (ein Eintrag mit ß/ö trifft auch die ss/oe-Wörter; ein langes Wort wird auch aus zwei Nachbarwörtern gefunden) — nicht mehr gesperrt: ' . implode('; ', $nichtGesperrt));
    },

    'FA08 begrenzte Zeit und fail-closed: zu lang, ungültiges UTF-8, Richtungszeichen, gescheiterter Regex-Schritt lassen NIE durch; Pathologie endet in Sekunden' => static function () use ($expect, $sp, $zeit, $grenzeS): void {
        $max = (int) constant(TextFaltung::class . '::MAX_BYTES');
        // über der Obergrenze: ohne Faltung abgelehnt (null), und sperrwort sperrt (fail-closed), statt „nichts gefunden“ zu sagen
        [$r, $t] = $zeit(static fn () => TextFaltung::falten(str_repeat("\u{0301}", 500000)));
        $expect($r === null && $t < 0.5, sprintf('FA08 1 MB kombinierende Zeichen: falten() lehnt sofort ab (null) — %.3f s', $t));
        [$r, $t] = $zeit(static fn () => Sperrliste::wort(str_repeat('a', 1000000) . ' garantiert', ['garantiert']));
        $expect($r !== null && $t < 0.5, sprintf('FA08 1 MB Text mit dem Sperrwort am Ende: gesperrt (nie durchgelassen), nicht in %.3f s verarbeitet — Antwort „%s“', $t, (string) $r));
        [$r, $t] = $zeit(static fn () => Sperrliste::wort(str_repeat("\u{0301}", 500000), ['garantiert']));
        $expect($r !== null && $t < 0.5, 'FA08 1 MB kombinierende Zeichen: gesperrt statt durchgelassen');
        $expect(Sperrliste::wort(str_repeat('a', 2000000), []) === null, 'FA08 mit leerer Liste gibt es nichts zu sperren, auch bei sehr langem Text (und nichts wird gerechnet)');
        $kurzVorGrenze = str_repeat('x ', (int) ($max / 2) - 20) . 'garantiert';
        $expect(strlen($kurzVorGrenze) < $max && Sperrliste::wort($kurzVorGrenze, ['garantiert']) === 'garantiert', 'FA08 knapp unter der Obergrenze wird noch vollständig geprüft: das Sperrwort ganz am Ende wird gefunden (kein stilles Kappen)');
        $expect(Sperrliste::wort(str_repeat('x', $max + 1) . ' garantiert', ['garantiert']) !== null, 'FA08 knapp über der Obergrenze: gesperrt (nicht gekappt — ein gekapptes Ende wäre eine Umgehung)');

        $muster = [
            'nur kombinierende Zeichen'                  => str_repeat("\u{0301}", (int) ($max / 2) - 4),
            'ein Buchstabe, dann Zalgo'                  => 'a' . str_repeat("\u{0301}\u{0308}\u{0337}", (int) ($max / 6) - 4),
            'abwechselnd a und Zeichen'                  => str_repeat("a\u{0301}", (int) ($max / 3) - 4),
            'Buchstaben mit Leerzeichen (eine Reihe)'    => str_repeat('a ', (int) ($max / 2) - 4),
            'Buchstaben mit Punkten (eine Reihe)'        => str_repeat('a.', (int) ($max / 2) - 4),
            'Buchstaben mit drei Zeichen Abstand'        => str_repeat('a - ', (int) ($max / 4) - 4),
            'Buchstaben, Reihen aus je zwei'             => str_repeat('a b, ', (int) ($max / 5) - 4),
            'Reihen aus drei Buchstaben, immer wieder'   => str_repeat('a b c, ', (int) ($max / 7) - 4),
            'nur Leerzeichen'                            => str_repeat(' ', $max - 4),
            'nur Zeilenumbrüche und Tabulatoren'         => str_repeat("\n\t", (int) ($max / 2) - 4),
            'nur Nullbreitenzeichen'                     => str_repeat("\u{200B}", (int) ($max / 3) - 4),
            'nur Sternchen'                              => str_repeat('*', $max - 4),
            'nur Vollbreit-Buchstaben'                   => str_repeat("\u{FF27}", (int) ($max / 3) - 4),
            'nur kyrillische а'                          => str_repeat("\u{0430}", (int) ($max / 2) - 4),
            'nur Umlaute'                                => str_repeat('ä', (int) ($max / 2) - 4),
            'nur Schriftformen (4-Byte-Zeichen)'         => str_repeat(mb_chr(0x1D5EE), (int) ($max / 4) - 4),
            'nur Ziffern'                                => str_repeat('1', $max - 4),
            'nur Satzzeichen'                            => str_repeat('.', $max - 4),
            'ein Wort aus einem Buchstaben, 8-mal Leerraum' => str_repeat('a        ', (int) ($max / 9) - 4),
            'Wort, Leerzeichen, Zeichen im Wechsel'      => str_repeat("garantier\u{0301} ", (int) ($max / 13) - 4),
        ];
        $langsam = [];
        $verloren = [];
        foreach ($muster as $was => $text) {
            $text = mb_strcut($text, 0, $max - 100);   // knapp unter der Obergrenze: Die Faltung läuft wirklich, sie wird nicht schon vorher abgewiesen
            [$r, $t] = $zeit(static fn () => Sperrliste::wort($text . ' nichts', ['garantiert', 'Heilkräuter', 'hilft gegen']));
            if ($t >= $grenzeS) {
                $langsam[] = sprintf('%s (%.2f s)', $was, $t);
            }
            if ($r !== null) {
                $verloren[] = sprintf('%s (gesperrt: „%s“)', $was, $r);   // ohne Sperrwort im Text darf nichts gesperrt werden — sonst wurde nur die Obergrenze getroffen
            }
        }
        $expect($langsam === [] && $verloren === [], 'FA08 ' . count($muster) . ' pathologische Eingaben knapp unter der Obergrenze enden jede in unter ' . $grenzeS . ' s und ohne Sperrwort bleiben sie frei — zu langsam: ' . implode('; ', $langsam) . ' — zu Unrecht gesperrt: ' . implode('; ', $verloren));
        $gefunden = [];
        foreach (['nur kombinierende Zeichen', 'Buchstaben mit Leerzeichen (eine Reihe)', 'nur Leerzeichen', 'Wort, Leerzeichen, Zeichen im Wechsel'] as $was) {
            if (Sperrliste::wort(mb_strcut($muster[$was], 0, $max - 200) . " g a r a n t i e r t\n", ['garantiert']) !== 'garantiert') {
                $gefunden[] = $was;
            }
        }
        $expect($gefunden === [], 'FA08 auch nach langem Vorlauf wird das Sperrwort am Ende noch gefunden — nicht gefunden hinter: ' . implode('; ', $gefunden));

        // ungültiges UTF-8: nicht prüfbar, also gesperrt
        $expect(TextFaltung::falten("gar\xC3\x28antiert") === null && Sperrliste::wort("gar\xC3\x28antiert", ['garantiert']) !== null && Sperrliste::wort("Hallo \xFF", []) === null,
            'FA08 ungültiges UTF-8 ist nicht prüfbar: falten() gibt null, sperrwort() sperrt (fail-closed); ohne Liste nichts');
        // ein Regex-Schritt, der scheitert (hier: JIT aus und Rücksprunggrenze winzig), darf nie „kein Treffer“ heißen
        $jitVorher = ini_get('pcre.jit');
        $grenzeVorher = ini_get('pcre.backtrack_limit');
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '5');
        $text = str_repeat('a b c d e f ', 40) . 'Das ist garantiert gut';
        $r1 = TextFaltung::falten($text);
        $r2 = Sperrliste::wort($text, ['garantiert']);
        ini_set('pcre.jit', (string) $jitVorher);
        ini_set('pcre.backtrack_limit', (string) $grenzeVorher);
        $expect($r1 === null && $r2 !== null, 'FA08 scheitert ein Regex-Schritt (preg_* gibt null), ist der Text „nicht prüfbar“ und wird gesperrt — statt als leerer Text durchzugehen');
        $expect(TextFaltung::falten('Das ist garantiert gut') === 'das ist garantiert gut', 'FA08 danach (Einstellungen zurück) faltet alles wieder normal');

        // Richtungs-Steuerzeichen kehren die Anzeige um: nicht prüfbar, also gesperrt (die Eingabe der Wege weist sie ohnehin ab)
        $schlecht = [];
        foreach (["\u{202E}treitnarag", "Das ist \u{202D}garantiert\u{202C}", "a\u{2066}b", "x\u{2067}y\u{2069}", "\u{202A}Text\u{202C}"] as $umkehr) {
            if (TextFaltung::falten($umkehr) !== null || Sperrliste::wort($umkehr, ['garantiert']) === null) {
                $schlecht[] = json_encode($umkehr);
            }
        }
        $expect($schlecht === [] && TextFaltung::falten("gar\u{200E}antiert\u{200F}") === 'garantiert' && Sperrliste::wort("Das ist gar\u{200F}antiert", ['garantiert']) === 'garantiert',
            'FA08 Richtungs-Steuerzeichen LRE/RLE/PDF/LRO/RLO und LRI/RLI/FSI/PDI machen den Text unprüfbar (gesperrt); die harmlosen Marken LRM/RLM nicht — falsch: ' . implode(' ', $schlecht));
    },

    'FA09 Eigenschaften: Tabellen, NFC = NFD für alle 790 Akzentbuchstaben, 994 Kompatibilitätszeichen, Marken und Unsichtbares mitten im Wort, Lesarten, Satzstrang, Stabilität' => static function () use ($expect, $sp): void {
        $unsinn = [];
        $eintraege = 0;
        $tabellen = [];
        foreach (['VERWECHSLUNG', 'KOMPAT', 'VORAB', 'AKZENTE', 'HAKEN'] as $name) {
            try {
                $tabellen[$name] = (new \ReflectionClassConstant(TextFaltung::class, $name))->getValue();
            } catch (\ReflectionException) {
                $tabellen[$name] = [];   // eine fehlende Tabelle ist hier leer; dass sie da sein muss, sagt FA13
            }
            foreach ($tabellen[$name] as $ersatz => $zeichen) {
                foreach (mb_str_split($zeichen) as $z) {
                    $eintraege++;
                    $deutsch1 = preg_match('/^[A-Za-zÄÖÜäöüß]$/u', $z) === 1;
                    // Kein Zeichen einer Tabelle ist ein deutscher Buchstabe (in AKZENTE allein ä, ö, ü selbst — die Umschrift)
                    if (mb_strlen($z) !== 1 || ($deutsch1 && !($name === 'AKZENTE' && in_array($z, ['ä', 'ö', 'ü'], true)))) {
                        $unsinn[] = "$name $ersatz ← U+" . dechex(mb_ord($z));
                    }
                    if ($name === 'VERWECHSLUNG' && preg_match('/^[a-z]$/', (string) $ersatz) !== 1) {
                        $unsinn[] = "$name: Ersatz $ersatz ist kein einzelner Kleinbuchstabe";
                    }
                    if ($name === 'KOMPAT' && preg_match('~^[a-z0-9().,/]+$~', (string) $ersatz) !== 1) {
                        $unsinn[] = "$name: Ersatz $ersatz besteht nicht aus Kleinbuchstaben, Ziffern und ().,/";
                    }
                    // jedes Zeichen wird zum Ersatz (zwischen zwei Silben „xy“, damit keine Buchstabenreihe aus einzelnen Buchstaben daraus wird)
                    if (TextFaltung::falten("xy{$z}xy") !== "xy{$ersatz}xy") {
                        $unsinn[] = "$name U+" . dechex(mb_ord($z)) . ' → ' . json_encode(TextFaltung::falten("xy{$z}xy"), JSON_UNESCAPED_UNICODE) . " statt $ersatz";
                    }
                }
            }
        }
        $expect($eintraege >= 900 && $unsinn === [], "FA09 die fünf Tabellen ($eintraege Zeichen): kein Zeichen ist ein deutscher Buchstabe (a–z, ä, ö, ü, ß; in AKZENTE nur ä/ö/ü als Umschrift), und jedes wird zu seinem Ersatz — falsch: " . implode('; ', array_slice($unsinn, 0, 8)));
        $deutsch = 'abcdefghijklmnopqrstuvwxyzäöüß';
        $verbogen = [];
        foreach (mb_str_split($deutsch) as $b) {
            foreach ([$b, mb_strtoupper($b)] as $form) {
                if (TextFaltung::falten("x{$form}x") !== 'x' . ($b === 'ä' ? 'ae' : ($b === 'ö' ? 'oe' : ($b === 'ü' ? 'ue' : ($b === 'ß' ? 'ss' : $b)))) . 'x') {
                    $verbogen[] = $form;
                }
            }
        }
        $expect($verbogen === [], 'FA09 jeder deutsche Buchstabe (klein und groß) wird nur klein, ä/ö/ü zu ae/oe/ue und ß zu ss — sonst nichts — verändert: ' . implode(' ', $verbogen));

        $nfd = json_decode((string) file_get_contents(__DIR__ . '/fixtures/sperrliste-nfd.json'), true);
        $verschieden = [];
        foreach ((array) ($nfd['paare'] ?? []) as [$nfc, $zerlegt]) {
            // einzeln und mitten in einem Wort, klein und GROSS (das Fixture hält beide Fälle)
            if (TextFaltung::falten($nfc) !== TextFaltung::falten($zerlegt) || TextFaltung::falten("W{$nfc}rter") !== TextFaltung::falten("W{$zerlegt}rter")) {
                $verschieden[] = sprintf('U+%04X', mb_ord($nfc));
            }
        }
        $expect(count((array) ($nfd['paare'] ?? [])) === 790 && $verschieden === [], 'FA09 NFC = NFD: jeder der ' . count((array) ($nfd['paare'] ?? [])) . ' lateinischen, griechischen und kyrillischen Buchstaben mit Akzent (Unicode ' . ($nfd['unicode'] ?? '?') . ') faltet gleich wie Grundbuchstabe + kombinierende Zeichen — verschieden: ' . implode(' ', array_slice($verschieden, 0, 10)));
        $kompat = json_decode((string) file_get_contents(__DIR__ . '/fixtures/sperrliste-kompat.json'), true);
        $falsch = [];
        foreach ((array) ($kompat['paare'] ?? []) as [$cp, $ascii]) {
            if (TextFaltung::falten(mb_chr($cp)) !== strtolower($ascii)) {
                $falsch[] = sprintf('U+%04X', $cp);
            }
        }
        $expect(count((array) ($kompat['paare'] ?? [])) === 994 && $falsch === [], 'FA09 Kompatibilität: jedes der ' . count((array) ($kompat['paare'] ?? [])) . ' Zeichen, das Unicode (NFKD) zu einem einzigen ASCII-Buchstaben oder einer Ziffer macht, wird dieser — falsch: ' . implode(' ', array_slice($falsch, 0, 10)));
        $falsch = [];
        foreach ((array) ($kompat['griechisch'] ?? []) as [$cp, $griechisch]) {
            if (TextFaltung::falten(mb_chr($cp)) !== TextFaltung::falten($griechisch)) {
                $falsch[] = sprintf('U+%04X', $cp);
            }
        }
        $expect(count((array) ($kompat['griechisch'] ?? [])) === 290 && $falsch === [], 'FA09 die mathematischen griechischen Buchstaben (U+1D6A8–1D7CB) falten wie die gewöhnlichen — falsch: ' . implode(' ', array_slice($falsch, 0, 10)));

        $falsch = [];
        foreach ((array) ($kompat['mehrere'] ?? []) as [$cp, $ascii]) {
            if (TextFaltung::falten(mb_chr($cp)) !== TextFaltung::falten($ascii)) {
                $falsch[] = sprintf('U+%04X', $cp);
            }
        }
        $expect(count((array) ($kompat['mehrere'] ?? [])) === 265 && $falsch === [], 'FA09 Kompatibilität, mehrere Zeichen: jedes der ' . count((array) ($kompat['mehrere'] ?? [])) . ' Zeichen, das Unicode (NFKD) in mehrere ASCII-Zeichen zerlegt (㎏ kg, ™ TM, ⑩ 10, ⑴ (1), Ⅻ XII, ǆ dz …), faltet wie diese — falsch: ' . implode(' ', array_slice($falsch, 0, 10)));
        $falsch = [];
        foreach ((array) ($kompat['neuer'] ?? []) as [$cp, $ascii]) {
            if (TextFaltung::falten(mb_chr($cp)) !== TextFaltung::falten($ascii) || TextFaltung::falten('gar' . mb_chr($cp) . 'antiert') === 'garantiert' && strtolower($ascii) !== 'a') {
                $falsch[] = sprintf('U+%04X', $cp);
            }
        }
        $expect(count((array) ($kompat['neuer'] ?? [])) === 41 && $falsch === [], 'FA09 Zeichen ab Unicode 14 (' . count((array) ($kompat['neuer'] ?? [])) . ', Stand Unicode 17 aus Node/ICU; PCRE auf dem Server kennt 14): ꟲ ꟳ ꟴ 𐞥 und Outlined Latin falten wie ihre ASCII-Zerlegung — falsch: ' . implode(' ', array_slice($falsch, 0, 10)));

        $striche = [];
        foreach (["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2015}", "\u{2212}", "\u{FE58}", "\u{FE63}", "\u{FF0D}"] as $strich) {
            if (Sperrliste::wort("Das Wi{$strich}Fi hier", ['Wi-Fi']) !== 'Wi-Fi') {
                $striche[] = sprintf('U+%04X', mb_ord($strich));
            }
        }
        $expect($striche === [], 'FA09 alle Strich-Varianten (U+2010–2015, 2212, FE58, FE63, FF0D) sind für einen kurzen Eintrag mit Bindestrich ein Bindestrich — nicht erkannt: ' . implode(' ', $striche));

        // Jede Marke und jedes unsichtbare Zeichen mitten im Wort ändert nichts (Befund der zweiten Prüfung: U+0345 machte aus „ga\u{0345}rantiert“ „gairantiert“)
        $verraeter = [];
        $neuerCp = array_column((array) ($kompat['neuer'] ?? []), 0);   // ab Unicode 14: Buchstaben, die für PCRE „nicht zugewiesen“ sind und hier nicht verschwinden, sondern zum Buchstaben werden
        for ($cp = 0x80; $cp < 0x110000; $cp++) {
            if (in_array($cp, $neuerCp, true)) {
                continue;
            }
            if ($cp >= 0xD800 && $cp <= 0xDFFF) {
                continue;
            }
            $c = mb_chr($cp);
            if (($cp >= 0x202A && $cp <= 0x202E) || ($cp >= 0x2066 && $cp <= 0x2069)) {
                continue;   // die Richtungs-Steuerzeichen machen den Text bewusst unprüfbar (FA08)
            }
            if (preg_match('/^[\p{Mn}\p{Me}\p{Cf}]$/u', $c) !== 1 && !in_array($cp, [0x115F, 0x1160, 0x2800, 0x3164, 0xFFA0], true) && ($cp % 29 !== 0 || preg_match('/^\p{Cn}$/u', $c) !== 1)) {
                continue;
            }
            foreach (["gar{$c}antiert", "{$c}garantiert{$c}", "hei{$c}lt"] as $i => $t) {
                if (TextFaltung::falten($t) !== ($i === 2 ? 'heilt' : 'garantiert')) {
                    $verraeter[] = sprintf('U+%04X', $cp);
                    break;
                }
            }
        }
        $expect($verraeter === [], 'FA09 jedes kombinierende Zeichen (Mn, Me), jedes Formatzeichen (Cf) und jedes nicht zugewiesene (jedes 29.) mitten in „garantiert“ und „heilt“ ändert das Wort nicht — ändert es: ' . implode(' ', array_slice($verraeter, 0, 10)));

        // Lesarten: ein Text hat mehrere, wenn ein Zeichen zwei Lesarten hat oder eine Buchstabenreihe zusammengezogen wurde
        $l1 = TextFaltung::lesarten('Heilkräuter');
        $l2 = TextFaltung::lesarten('h i l f t   g e g e n');
        $l3 = TextFaltung::lesarten("Heilkra\u{0308}uter");
        $l4 = TextFaltung::lesarten("Das hilft\u{3164}gegen");
        $l5 = TextFaltung::lesarten("g\u{0337}a\u{0336}\u{0308}rantiert");
        $expect($l1 === ['heilkraeuter'] && count((array) $l2) === 2 && in_array('hilftgegen', (array) $l2, true) && $l3 === ['heilkraeuter'] && $l5 === ['gaerantiert', 'garantiert']
            && $l4 === ['das hilftgegen', 'das hilft gegen'] && TextFaltung::lesarten(str_repeat('a', TextFaltung::MAX_BYTES + 1)) === null,
            'FA09 lesarten(): ein gewöhnliches Wort hat eine, ein sauberer NFD-Umlaut ebenso (NFD = NFC), gespreizter Text zwei (mit und ohne Zusammenziehen), Zalgo mit ¨ und Füller haben zwei Lesarten, zu lang ist null — ' . json_encode([$l1, $l2, $l3, $l4, $l5], JSON_UNESCAPED_UNICODE));
        $sz = [TextFaltung::satzstrang('Das hilft. Gegen alles'), TextFaltung::satzstrang('Das hilft gegen alles'), TextFaltung::satzstrang('Durch fall, verschwunden'), TextFaltung::satzstrang("Zeile eins\nZeile zwei"),
            TextFaltung::satzstrang('Das hilft , gegen'), TextFaltung::satzstrang('Nur 100 % sicher'), TextFaltung::satzstrang("Durchfa\u{301}\u{308}ll-verschwunden")];
        $expect(!str_contains((string) $sz[0][0], 'hilftgegen') && str_contains((string) $sz[1][0], 'hilftgegen') && !str_contains((string) $sz[2][0], 'durchfallverschwunden') && str_contains((string) $sz[3][0], 'einszeile')
            && str_contains((string) $sz[4][0], 'hilftgegen') && str_contains((string) $sz[5][0], '100%sicher') && count($sz[6]) === 2 && str_contains($sz[6][1], 'durchfallverschwunden') && !str_contains($sz[0][0], '|')
            && TextFaltung::satzstrang(str_repeat('a', TextFaltung::MAX_BYTES + 1)) === null,
            'FA09 satzstrang(): ein Satzende (. ! ? : ; , an das Wort geklebt, vor Leerzeichen oder am Ende) trennt, ein Komma nach Leerzeichen und ein Zeilenumbruch nicht; % bleibt; Zalgo hat zwei Lesarten — ' . json_encode($sz, JSON_UNESCAPED_UNICODE));
        $expect(TextFaltung::eintraege("garantiert\u{202C}") === ['garantiert'] && TextFaltung::eintraege("\u{2066}Heilkräuter\u{2069}") === ['heilkraeuter'] && TextFaltung::eintraege("Heil\xC3\x28") === null
            && TextFaltung::eintraege('i. d. R') === ['idr', 'i.d.r'] && TextFaltung::eintraege('hilft gegen') === ['hilft gegen'],
            'FA09 eintraege(): Richtungszeichen im Eintrag der Liste fallen weg (Copy & Paste), ungültiges UTF-8 bleibt null, ein Eintrag aus Einzelbuchstaben hat zwei Fassungen (mit und ohne Zusammenziehen)');

        mt_srand(20261011);
        $pool = array_merge(mb_str_split('abcdefghijklmnopqrstuvwxyzABCXYZäöüßÄÖÜ0123456789 .-_*/,;:%!?()'), ['ΐ', 'ǰ', 'ẖ', 'ŉ', 'ẚ', "\u{FF0A}", "\u{0345}", 'ᾳ', 'ΰ', 'Ά', 'ǆ', "\u{2800}", "\u{FFA0}", "\u{2065}", "\u{0640}", 'ª', 'ⅼ', '①', 'ᵍ', 'ℏ', "\u{0301}", "\u{0308}", "\u{0337}", "\u{200B}", "\u{00AD}", "\u{FF27}", "\u{FF41}", "\u{0430}", "\u{0435}", "\u{03BF}", "\u{0131}",
            "\u{017F}", "\u{FB01}", 'ℎ', 'ⓐ', mb_chr(0x1D5EE), mb_chr(0x1D41A), "\u{3000}", "\u{00A0}", "\t", "\n", "\u{3164}", '日', 'я', 'é', 'ñ', 'ø', 'æ', 'œ', "İ", '**']);
        $instabil = [];
        for ($i = 0; $i < 400; $i++) {
            $t = '';
            for ($k = mt_rand(1, 40); $k > 0; $k--) {
                $t .= $pool[mt_rand(0, count($pool) - 1)];
            }
            $einmal = TextFaltung::falten($t);
            if ($einmal === null || TextFaltung::falten($einmal) !== $einmal) {
                $instabil[] = json_encode($t, JSON_UNESCAPED_UNICODE);
                if (count($instabil) >= 3) {
                    break;
                }
            }
        }
        $expect($instabil === [], 'FA09 die Faltung ist stabil: zweimal gefaltet ist gleich einmal gefaltet (400 Zufallstexte aus 60 Zeichen) — Gegenbeispiele: ' . implode(' ', $instabil));
        $expect(TextFaltung::falten('ΐ') === 'i' && TextFaltung::falten('ǰ') === 'j' && TextFaltung::falten('İ') === 'i' && TextFaltung::falten('Ǆ') !== null,
            'FA09 das Auffalten erzeugt kein Zeichen, das erst ein weiterer Lauf wegnimmt (ΐ, ǰ, İ: Zeichen + kombinierende Zeichen) — ' . json_encode([TextFaltung::falten('ΐ'), TextFaltung::falten('ǰ'), TextFaltung::falten('İ')], JSON_UNESCAPED_UNICODE));
        // jeder einzelne Codepunkt von U+0020 bis U+2FFF (Lateinisch, Griechisch, Kyrillisch, Symbole, eingekreiste Zeichen …) und in Schritten darüber: einzeln, im Wort und in einer Reihe stabil
        $instabil = [];
        for ($cp = 0x20; $cp < 0x110000; $cp += $cp < 0x3000 ? 1 : 7) {
            if ($cp >= 0xD800 && $cp <= 0xDFFF) {
                continue;
            }
            $c = mb_chr($cp);
            foreach ([$c, "a{$c}b", "g {$c} a"] as $t) {
                $f = TextFaltung::falten($t);
                if ($f !== null && TextFaltung::falten($f) !== $f) {
                    $instabil[] = sprintf('U+%04X', $cp);
                    break;
                }
            }
        }
        $expect($instabil === [], 'FA09 die Faltung ist stabil für jeden Codepunkt bis U+2FFF und jeden siebten darüber (einzeln, im Wort, in einer Reihe) — instabil: ' . implode(' ', array_slice($instabil, 0, 10)));
        // die Reihenfolge der Schritte 8 (punkte) und 9 (reihen) ist wesentlich: „a, _ e%j“ hat vier Zeichen zwischen a und e, erst ohne die Leerzeichen sind es zwei
        $reihenfolge = [TextFaltung::falten('a, _ e%j'), TextFaltung::falten("tæjΐœ%a,\u{3000}_\u{0308}\té%ǰ;\u{3164}3")];
        $expect($reihenfolge[0] === 'aej' && TextFaltung::falten((string) $reihenfolge[0]) === $reihenfolge[0] && TextFaltung::falten((string) $reihenfolge[1]) === $reihenfolge[1],
            'FA09 Leerzeichen um Zeichen fallen weg, BEVOR Buchstabenreihen zusammengezogen werden (sonst wäre die Faltung nicht stabil) — ' . json_encode($reihenfolge, JSON_UNESCAPED_UNICODE));
        $mitStrang = TextFaltung::strang('das ist g.a-r antiert, 100 %!');
        $expect($mitStrang === 'dasistgarantiert100', 'FA09 strang(): nur Buchstaben und Ziffern — ' . json_encode($mitStrang));
        $abk = (string) TextFaltung::falten('Das gilt z. B. für Kinder, d. h. für alle, u. a. m. und i. d. R. auch.');
        $expect(str_contains($abk, 'z.b.') && str_contains($abk, 'd.h.') && !str_contains($abk, 'zb') && str_contains($abk, ',uam.') && str_contains($abk, ' idr.'),
            'FA09 Abkürzungen mit zwei Buchstaben (z. B., d. h.) bleiben Abkürzungen; erst ab DREI einzelnen Buchstaben in Folge wird zusammengezogen (u. a. m. → uam) — ' . json_encode($abk, JSON_UNESCAPED_UNICODE));
    },

    'FA08 jeder Regex-Schritt der Faltung, der scheitert, macht den Text „nicht prüfbar“ — in der Suche und in der ganzen Prüfung einer Seite (Schadensattrappe im eigenen Prozess)' => static function () use ($expect): void {
        // Der Text trägt Ziffern, Buchstabenreihe, Schriftform, Umlaut, U+0308 und einen Füller (alle Zweige der Faltung), aber kein Sperrwort.
        $probe = 'Das ist g a r a n t und ä, ' . mb_chr(0x1D5EE) . ', ⓖ, İ, a' . "\u{0308}" . ' und ' . "\u{3164}";
        $seite = H::seite([], []);
        $seite['bausteine'][0]['text'] = $probe;
        $ordner = H::ordner($seite, null, H::profilMitListen(['garantiert', 'Durchfall verschwunden'], ['heilt']));
        // Eine Seite mit Preis: Dort läuft auch der Regex für „kostenlos“ (Texte ohne dieses Wort)
        $t = Endo\Lp\Katalog::laden()->baustein('termin')['beispiel'];
        $t['preis'] = 25;
        $mitPreis = H::seite([$t]);
        $mitPreis['meta']['beschreibung'] = 'Online-Abend mit Fragerunde.';
        $mitPreis['bausteine'][0]['dachzeile'] = 'Online-Abend';
        $mitPreis['bausteine'][0]['knopf']['text'] = 'Jetzt anmelden';
        $mitPreis['bausteine'][2]['titel'] = 'Jetzt anmelden';
        $ordnerPreis = H::ordner($mitPreis, null, H::profilMitListen([], []));
        $lauf = H::wurzel() . '/tests/hilfe/preg-stoerung-lauf.php';
        $p = proc_open([PHP_BINARY, $lauf, $ordner, $ordnerPreis], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $rohre);
        $aus = '';
        $fehler = '';
        if (is_resource($p)) {
            $aus = (string) stream_get_contents($rohre[1]);
            $fehler = (string) stream_get_contents($rohre[2]);
            proc_close($p);
        }
        $r = json_decode($aus, true);
        if (!is_array($r)) {
            $expect(false, 'FA08 der Kindprozess lieferte kein JSON: ' . substr($aus . ' ' . $fehler, 0, 600));
            return;
        }
        $expect($r['ohne'] === [null, null] && $r['gesehen'] >= 13 && $r['versuche'] >= 50 && $r['nichtGesperrt'] === [],
            'FA08 ' . $r['gesehen'] . ' Regex-Muster der Faltung, der Lesarten, des Eintrags, des Strangs und des Satzstrangs, ' . $r['versuche'] . ' Aufrufe: scheitert JEDER einzeln, ist der Text gesperrt (nie leer und frei) — nicht gesperrt: ' . implode(' | ', array_slice($r['nichtGesperrt'], 0, 5)));
        $expect($r['pruefer']['kontrolle'] === [] && $r['pruefer']['versuche'] >= 20 && $r['pruefer']['nichtGemeldet'] === [],
            'FA08 Prüfer: ohne Störung kein Befund „nicht prüfbar“; mit Störung ' . $r['pruefer']['versuche'] . ' Aufrufe, jedes Mal ein sperrender Befund — nicht gemeldet: ' . implode(' | ', array_slice($r['pruefer']['nichtGemeldet'], 0, 5)) . ' — Kontrolle: ' . implode(' | ', $r['pruefer']['kontrolle']));
        $expect($r['preis']['aufgerufen'] === true && $r['preis']['kontrolle'] === [] && $r['preis']['nichtGemeldet'] === [],
            'FA08 Preis-Prüfung: Der Regex für „kostenlos“ wird bei einem Termin mit Preis aufgerufen, und scheitert er, sperrt der Prüfer (kein stilles „kein Treffer“) — Antwort: ' . json_encode($r['preis'], JSON_UNESCAPED_UNICODE));
    },

    'FA08 steht die interne Kodierung nicht auf UTF-8, ist jeder Text „nicht prüfbar“ — die Faltung würde sonst still falsch rechnen' => static function () use ($expect): void {
        $code = 'require ' . var_export(H::wurzel() . '/lp/autoload.php', true) . '; use Endo\\Lp\\Sperrliste as S; '
            . 'echo json_encode([mb_internal_encoding(), S::wort("\u{FF47}arantiert", ["garantiert"]), S::wort("g\u{0430}rantiert", ["garantiert"]), S::treffer("Heilkra\u{0308}uter", ["Heilkräuter"]), S::lesarten("Hallo"), S::eintraege("garantiert"), S::wort("Hallo", []), S::wort("Hallo", ["garantiert"])], JSON_UNESCAPED_UNICODE);';
        $lauf = static function (string $zeichensatz) use ($code): ?array {
            $p = proc_open([PHP_BINARY, '-d', 'default_charset=' . $zeichensatz, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $rohre);
            $aus = '';
            if (is_resource($p)) {
                $aus = (string) stream_get_contents($rohre[1]);
                stream_get_contents($rohre[2]);
                proc_close($p);
            }
            $j = json_decode($aus, true);
            return is_array($j) ? $j : null;
        };
        $utf8 = $lauf('UTF-8');
        $expect($utf8 === ['UTF-8', 'garantiert', 'garantiert', ['Heilkräuter'], ['hallo'], ['garantiert'], null, null], 'FA08 mit UTF-8 (Normalfall) wird gefaltet wie sonst — ' . json_encode($utf8, JSON_UNESCAPED_UNICODE));
        $latin = $lauf('ISO-8859-1');
        $expect($latin === ['ISO-8859-1', Sperrliste::NICHT_PRUEFBAR, Sperrliste::NICHT_PRUEFBAR, null, null, null, null, Sperrliste::NICHT_PRUEFBAR],
            'FA08 mit default_charset ISO-8859-1 ist jeder Text und jeder Eintrag „nicht prüfbar“ (gesperrt), nie still „frei“; ohne Liste gibt es weiter nichts zu sperren — ' . json_encode($latin, JSON_UNESCAPED_UNICODE));
    },

    'FA08 ein Eintrag der Liste, der sich nicht prüfen lässt (über 64 KB), sperrt jeden Text; treffer() sagt null, nie eine leere Liste' => static function () use ($expect, $sp): void {
        $riesig = str_repeat('x', TextFaltung::MAX_BYTES + 10);
        $expect($sp('Ein ganz gewöhnlicher Satz', ['garantiert', $riesig]) === Sperrliste::NICHT_PRUEFBAR && $sp('Ein ganz gewöhnlicher Satz', ['garantiert']) === null,
            'FA08 ein Eintrag über 64 KB sperrt jeden Text (nie stilles Überspringen); ohne ihn bleibt der Satz frei');
        $expect(Sperrliste::treffer('Ein ganz gewöhnlicher Satz', ['garantiert', $riesig]) === null && Sperrliste::treffer("gar\xC3\x28antiert", ['garantiert']) === null
            && Sperrliste::treffer('Ein ganz gewöhnlicher Satz', ['garantiert']) === [] && Sperrliste::treffer('Das ist garantiert gut', ['garantiert', 'gut', 'nein']) === ['garantiert', 'gut'],
            'FA08 treffer(): null = nicht prüfbar (Eintrag zu lang, Text kein UTF-8), [] = nichts gefunden, sonst ALLE getroffenen Einträge der Liste in ihrer Reihenfolge');
    },


    'FA12 Härtung (Befunde der unabhängigen Prüfung, seit Panel rc1092 in beiden Kopien): Zalgo mit Unsichtbarem davor, Satzende vor Anführungszeichen und Auslassungszeichen' => static function () use ($expect, $sp): void {
        // B2: Ob ein Text mehrdeutig ist (Umlaut oder Zalgo?), entscheidet sich an dem Text, der verglichen wird — nicht am Rohtext
        $zalgo = [
            'a + ¨ + Akzent (schon vorher erkannt)'      => "ga\u{0308}\u{0301}rantiert",
            'Nullbreitenzeichen zwischen a und Marken'   => "ga\u{200B}\u{0308}\u{0301}rantiert",
            'Nullbreitenzeichen zwischen den Marken'     => "ga\u{0308}\u{200B}\u{0301}rantiert",
            'weiches Trennzeichen vor den Marken'        => "ga\u{00AD}\u{0308}\u{0301}rantiert",
            'Sternchen vor den Marken'                   => "ga*\u{0308}\u{0301}rantiert",
            'vollbreites a + Marken'                     => "g\u{FF41}\u{0308}\u{0301}rantiert",
            'kyrillisches а + Marken'                    => "g\u{0430}\u{0308}\u{0301}rantiert",
            'mathematisches a + Marken'                  => "g\u{1D5BA}\u{0308}\u{0301}rantiert",
        ];
        $schlecht = [];
        foreach ($zalgo as $was => $text) {
            if ($sp("Das ist $text gut", ['garantiert']) !== 'garantiert') {
                $schlecht[] = $was;
            }
        }
        $expect($schlecht === [], 'FA12 Zalgo-Haufen mit Umlautmarke wird auch gefunden, wenn Unsichtbares oder ein Verwechslungszeichen davor steht — nicht erkannt: ' . implode('; ', $schlecht));
        $expect($sp("Heilkra\u{0308}uter", ['Heilkräuter']) === 'Heilkräuter' && $sp("Blu\u{0308}ten", ['bluten']) === null && $sp("Blu\u{200B}\u{0308}ten", ['bluten']) === null && $sp("Lo\u{0308}sung", ['Losung']) === null,
            'FA12 ein einzelnes ¨ bleibt ein Umlaut (NFD = NFC), auch mit Unsichtbarem davor: Blüten ≠ bluten, Lösung ≠ Losung');
        // B3: Ein Satzende vor einem schließenden Zeichen oder das Auslassungszeichen trennt eine Wendung
        $satzende = [
            ["Sie sagt: \u{201E}Das hilft.\u{201C} Gegen die Angst gibt es Wege.", 'hilft gegen'],
            ['Das hilft.) Gegen die Angst', 'hilft gegen'],
            ["Was hilft?\u{AB} Gegen Angst hilft Mut.", 'hilft gegen'],
            ["Das hilft\u{2026}  Gegen Angst", 'hilft gegen'],
            ["Das ist 100 %.\u{201C} Sicher ist nichts.", '100 % sicher'],
            ["Durchfall.\u{201C} Verschwunden war er nie.", 'Durchfall verschwunden'],
            ['Das hilft... Gegen Angst', 'hilft gegen'],
        ];
        $zuUnrecht = [];
        foreach ($satzende as [$text, $wort]) {
            if ($sp($text, [$wort]) !== null) {
                $zuUnrecht[] = $text;
            }
        }
        $expect($zuUnrecht === [], 'FA12 Satzende vor schließendem Zeichen (Zitat, Klammer) und Auslassungszeichen trennen die Wendung — zu Unrecht gesperrt: ' . implode(' | ', $zuUnrecht));
        $bleibt = [["Das hilft gegen Angst", 'hilft gegen'], ["Das hilft\u{3000}gegen Angst", 'hilft gegen'], ['Das hilft , gegen Angst', 'hilft gegen'], ['Das hilft .. gegen Angst', 'hilft gegen'], ['Das hilft ... gegen Angst', 'hilft gegen'], ['Das hilft ,. gegen Angst', 'hilft gegen'], ["Das hilft \u{2026} gegen Angst", 'hilft gegen'], ["Das hilft .\u{2026}. gegen Angst", 'hilft gegen'], ['Nach Tag drei: Durch fall verschwunden.', 'Durchfall verschwunden'], ['Das ist 100 % sicher', '100 % sicher']];
        $verloren = [];
        foreach ($bleibt as [$text, $wort]) {
            if ($sp($text, [$wort]) !== $wort) {
                $verloren[] = $text;
            }
        }
        $expect($verloren === [], 'FA12 Wendungen ohne Satzende werden weiter gefunden (auch mit Satzzeichen nach einem Leerzeichen: „hilft .. gegen“ ist kein Satzende) — nicht mehr gefunden: ' . implode(' | ', $verloren));
        // B4: lesartenOhnePunkte lässt Leerzeichen um Satzzeichen stehen
        $expect(TextFaltung::lesartenOhnePunkte('Kosten – frei wählbar') === ['kosten - frei waehlbar'] && TextFaltung::lesarten('Kosten – frei wählbar') === ['kosten-frei waehlbar'] && TextFaltung::lesartenOhnePunkte(str_repeat('a', TextFaltung::MAX_BYTES + 1)) === null,
            'FA12 lesartenOhnePunkte(): „Kosten – frei“ bleibt mit Leerzeichen (lesarten() zieht es zusammen); zu lang ist null — ' . json_encode([TextFaltung::lesartenOhnePunkte('Kosten – frei wählbar'), TextFaltung::lesarten('Kosten – frei wählbar')], JSON_UNESCAPED_UNICODE));
    },

    'FA13 Härtung 2 (Panel rc1092): Buchstaben mit Haken/Strich/Tilde ohne Unicode-Zerlegung und dünne Leerzeichen' => static function () use ($expect, $sp, $liste): void {
        // B5: Buchstaben mit Haken, Strich, Tilde, Schwanz …, die Unicode NICHT zerlegt (ƒ ɠ ɍ ɨ ƭ …): der Leser liest den Grundbuchstaben (Fixture aus der Unicode-Datenbank, Tabelle HAKEN)
        $haken = json_decode((string) file_get_contents(__DIR__ . '/fixtures/sperrliste-haken.json'), true);
        $falsch = [];
        foreach ((array) ($haken['paare'] ?? []) as [$cp, $grund]) {
            if (TextFaltung::falten('xy' . mb_chr($cp) . 'xy') !== 'xy' . $grund . 'xy') {
                $falsch[] = sprintf('U+%04X', $cp);
            }
        }
        $expect(count((array) ($haken['paare'] ?? [])) === 216 && $falsch === [], 'FA13 (B5) jeder der ' . count((array) ($haken['paare'] ?? [])) . ' lateinischen Buchstaben „X mit Haken/Strich/Tilde/…“ ohne Zerlegung (Unicode ' . ($haken['unicode'] ?? '?') . ') faltet zum Grundbuchstaben — falsch: ' . implode(' ', array_slice($falsch, 0, 12)) . (count($falsch) > 12 ? ' … (' . count($falsch) . ')' : ''));
        $tabellen = [];
        foreach (['VERWECHSLUNG', 'KOMPAT', 'VORAB', 'AKZENTE', 'HAKEN'] as $name) {
            try {
                $tabellen[$name] = (new \ReflectionClassConstant(TextFaltung::class, $name))->getValue();
            } catch (\ReflectionException) {
                $tabellen[$name] = [];
            }
        }
        $andere = [];
        foreach (['VERWECHSLUNG', 'KOMPAT', 'VORAB', 'AKZENTE'] as $name) {
            foreach ($tabellen[$name] as $zeichen) {
                foreach (mb_str_split($zeichen) as $z) {
                    $andere[$z] = true;
                }
            }
        }
        $doppelt = [];
        $deutsch = [];
        $hakenZeichen = 0;
        foreach ($tabellen['HAKEN'] as $ersatz => $zeichen) {
            foreach (mb_str_split($zeichen) as $z) {
                $hakenZeichen++;
                if (isset($andere[$z])) {
                    $doppelt[] = sprintf('U+%04X', mb_ord($z));
                }
                if (preg_match('/^[A-Za-zÄÖÜäöüß]$/u', $z) === 1 || preg_match('/^[a-z]$/', (string) $ersatz) !== 1 || TextFaltung::falten("xy{$z}xy") !== "xy{$ersatz}xy") {
                    $deutsch[] = sprintf('U+%04X', mb_ord($z));
                }
            }
        }
        $expect($hakenZeichen >= 200 && $doppelt === [] && $deutsch === [], "FA13 (B5) die Tabelle HAKEN ist da ($hakenZeichen Zeichen), überschneidet sich mit keiner anderen Tabelle, kein Zeichen ist ein deutscher Buchstabe und jedes wird zu seinem Ersatz — doppelt: " . implode(' ', $doppelt) . '; falsch: ' . implode(' ', array_slice($deutsch, 0, 8)));
        $beispiele = [
            'f mit Haken ×2 in „Wirkstoff“'          => ["Der Wirksto\u{0192}\u{0192} hilft", 'Wirkstoff'],
            'g mit Haken (klein) in „garantiert“'    => ["Das ist \u{0260}arantiert", 'garantiert'],
            'G mit Haken (groß) in „GARANTIERT“'     => ["Das ist \u{0193}ARANTIERT", 'garantiert'],
            't mit Haken in „garantiert“'            => ["Das ist garan\u{01AD}iert", 'garantiert'],
            'r mit Strich in „risikofrei“'           => ["Das ist \u{024D}isikofrei", 'risikofrei'],
            'i mit Strich in der Wendung'            => ["Das h\u{0268}lft gegen Angst", 'hilft gegen'],
            'h mit Haken in „heilt“'                 => ["Das \u{0266}eilt alles", 'heilt'],
            'O mit Tilde (Ɵ) in „Wirkstoff“'         => ["Wirkst\u{019F}ff", 'Wirkstoff'],
            'l mit Tilde in „Heilkräuter“'           => ["Hei\u{026B}kräuter", 'Heilkräuter'],
            'z mit Haken ×2 in „zertifiziert“'       => ["Das ist \u{0225}ertifi\u{0225}iert", 'zertifiziert'],
            'c mit Locke in „Cannabis“'              => ["Das \u{0255}annabis", 'Cannabis'],
        ];
        $schlecht = [];
        foreach ($beispiele as $was => [$text, $wort]) {
            if ($sp($text, [$wort]) !== $wort) {
                $schlecht[] = $was;
            }
        }
        $expect($schlecht === [], 'FA13 (B5) ein Sperrwort mit Buchstaben mit Haken/Strich/Tilde wird erkannt (' . count($beispiele) . ' Fälle) — nicht erkannt: ' . implode('; ', $schlecht));
        $schlecht = [];
        foreach (['Soren' => 'Søren', 'Lodz' => 'Łódź', 'Dorde' => 'Đorđe', 'Hamrun' => 'Ħamrun'] as $wort => $text) {
            if ($sp("Das ist $text aus dem Norden", [$wort]) !== $wort) {
                $schlecht[] = $text;
            }
        }
        $expect($schlecht === [] && $sp('Das ist schön', ['schon']) === null, 'FA13 (B5) die Buchstaben, die vorher schon galten (ø ł đ ħ), gelten weiter; der Umlaut ohne Punkte bleibt ein anderes Wort — ' . implode(' ', $schlecht));

        // Dünne Leerzeichen (Schmal- und Haarspatium): zwischen Buchstaben so schmal, dass der Leser KEINE Lücke sieht — Lücke oder nichts? gilt wie beim Hangul-Füller in beiden Lesarten
        $duenn = [
            'Haarspatium U+200A in „heilt“'          => ["Das hei\u{200A}lt alles", 'heilt'],
            'Schmalspatium U+2009 in „heilt“'        => ["Das hei\u{2009}lt alles", 'heilt'],
            'Haarspatium in „Wirkstoff“'             => ["Der Wirk\u{200A}stoff hilft", 'Wirkstoff'],
        ];
        $schlecht = [];
        foreach ($duenn as $was => [$text, $wort]) {
            if ($sp($text, [$wort]) !== $wort) {
                $schlecht[] = $was;
            }
        }
        $expect($schlecht === [], 'FA13 dünne Leerzeichen mitten in einem kurzen Wort trennen es nicht — nicht erkannt: ' . implode('; ', $schlecht));
        $expect(TextFaltung::lesarten("hei\u{2009}lt") === ['hei lt', 'heilt'] && TextFaltung::lesarten("hei\u{200A}lt") === ['hei lt', 'heilt'] && TextFaltung::lesarten("100\u{2009}% sicher") === ['100%sicher'] && TextFaltung::lesarten('hei lt') === ['hei lt'],
            'FA13 dünne Leerzeichen: zwei Lesarten (Lücke oder nichts), außer wo es auf dasselbe hinausläuft; ein gewöhnliches Leerzeichen bleibt eine Lücke — ' . json_encode([TextFaltung::lesarten("hei\u{2009}lt"), TextFaltung::lesarten("100\u{2009}% sicher"), TextFaltung::lesarten('hei lt')], JSON_UNESCAPED_UNICODE));
        $bleibt = [["Das hilft\u{2009}gegen Angst", 'hilft gegen'], ["Das ist 100\u{2009}% sicher", '100 % sicher'], ["Das hilft\u{200A}gegen Angst", 'hilft gegen']];
        $verloren = [];
        foreach ($bleibt as [$text, $wort]) {
            if ($sp($text, [$wort]) !== $wort) {
                $verloren[] = $text;
            }
        }
        $expect($verloren === [], 'FA13 Wendungen mit dünnem Leerzeichen als Wortabstand werden weiter gefunden — nicht mehr gefunden: ' . implode(' | ', $verloren));
        $frei = ["Das gilt z.\u{2009}B. für Kinder, d.\u{2009}h. für alle", "Das kostet 5\u{2009}€ oder 12\u{2009}kg Gepäck", "Wir\u{2009}haben\u{2009}Zeit und\u{200A}Ruhe", "Mo\u{2009}–\u{2009}Fr 9\u{2009}–\u{2009}17\u{2009}Uhr"];
        $zuUnrecht = [];
        foreach ($frei as $text) {
            if ($sp($text, $liste) !== null) {
                $zuUnrecht[] = $text;
            }
        }
        $expect($zuUnrecht === [], 'FA13 gewöhnlicher Text mit dünnen Leerzeichen (z. B., 5 €, Zeitangaben) bleibt frei — zu Unrecht gesperrt: ' . implode(' | ', $zuUnrecht));
        $expect($sp("Ich\u{2009}eilte nach Hause", ['heilt']) === 'heilt' && $sp('Ich eilte nach Hause', ['heilt']) === null,
            'FA13 bekannte Nebenwirkung, festgehalten: ein dünnes Leerzeichen zwischen zwei Wörtern lässt einen KURZEN Eintrag über die Wortgrenze treffen („Ich eilte“ → „heilt“); mit gewöhnlichem Leerzeichen nicht');
    },

    'FA10 der gewöhnliche Text der Baukasten-Dokumente und -Vorlagen wird mit eigenen Beispiellisten nicht häufiger gesperrt als vorher' => static function () use ($expect, $sp, $vorher): void {
        $listen = [   // erfundene Beispiele (keine Kundenlisten): Heilversprechen, Sicherheitsversprechen, Wendungen
            'heil'   => ['garantiert', 'Wunderheilung', 'heilt', 'hilft gegen', 'Wirkstoff', 'Nebenwirkungen', 'Krankheit', 'Therapie', 'schmerzfrei'],
            'sicher' => ['risikofrei', 'ohne Risiko', '100 % sicher', 'absolut sicher', 'rechtssicher', 'Verfügbarkeit', 'in Minuten', 'unfehlbar'],
        ];
        $korpus = [];
        // CHANGELOG.md gehört nicht dazu: Es nennt die Umgehungsschreibweisen selbst als Beispiele
        $dateien = array_merge(glob(H::wurzel() . '/docs/superpowers/*/*.md') ?: [], [H::wurzel() . '/CLAUDE.md', H::wurzel() . '/lp/SKILL.md', H::wurzel() . '/lp/werkstatt.md']);
        foreach ($dateien as $datei) {
            foreach (preg_split('/\R/u', (string) file_get_contents($datei)) ?: [] as $zeile) {
                if (mb_strlen($zeile) >= 20 && mb_strlen($zeile) <= 2000) {
                    $korpus[] = $zeile;
                }
            }
        }
        foreach (array_merge(glob(H::wurzel() . '/lp/musterbuch/*.json') ?: [], glob(H::wurzel() . '/lp/vorlagen/*.json') ?: [], [H::wurzel() . '/lp/bausteine.json']) as $datei) {
            $json = json_decode((string) file_get_contents($datei), true) ?? [];
            array_walk_recursive($json, static function ($w) use (&$korpus): void {
                if (is_string($w) && mb_strlen($w) >= 12 && mb_strlen($w) <= 2000) {
                    $korpus[] = $w;
                }
            });
        }
        $korpus = array_values(array_unique($korpus));
        $neu = [];
        $verloren = [];
        $vor = 0;
        $nach = 0;
        foreach ($korpus as $zeile) {
            foreach ($listen as $marke => $l) {
                $a = $vorher($zeile, $l);
                $b = $sp($zeile, $l);
                $vor += $a !== null ? 1 : 0;
                $nach += $b !== null ? 1 : 0;
                if ($b !== null && $a === null) {
                    $neu[] = $marke . ':' . $b . ' in „' . mb_substr($zeile, 0, 90) . '“';
                }
                if ($b === null && $a !== null) {
                    $verloren[] = $marke . ':' . $a . ' in „' . mb_substr($zeile, 0, 90) . '“';
                }
            }
        }
        $expect(count($korpus) > 300 && $neu === [] && $verloren === [], 'FA10 Korpus: ' . count($korpus) . ' Zeilen und Texte × 2 Beispiellisten — gesperrt vorher ' . $vor . ', jetzt ' . $nach . '; NEU gesperrt (Fehlalarm?): ' . implode(' || ', array_slice($neu, 0, 5)) . ' — VORHER gesperrt, jetzt frei (Lücke?): ' . implode(' || ', array_slice($verloren, 0, 5)));
    },

    'FA11 eine Stelle: nur der Prüfer ruft die Sperrliste auf, nur die Sperrliste die Faltung, und niemand vergleicht selbst mit mb_stripos' => static function () use ($expect): void {
        $nutzer = [];
        $faltungsnutzer = [];
        $vergleicher = [];
        foreach (glob(H::wurzel() . '/lp/src/*.php') ?: [] as $datei) {
            $q = (string) file_get_contents($datei);
            $kurz = basename($datei);
            if (str_contains($q, 'TextFaltung::') && !in_array($kurz, ['TextFaltung.php', 'Sperrliste.php'], true)) {
                $faltungsnutzer[] = $kurz;
            }
            if (str_contains($q, 'Sperrliste::') && !in_array($kurz, ['TextFaltung.php', 'Sperrliste.php'], true)) {
                $nutzer[] = $kurz;
            }
            if ($kurz !== 'Sperrliste.php' && preg_match('/mb_stripos\([^;]*(sperr|liste|wort|grenz)/iu', $q) === 1) {
                $vergleicher[] = $kurz;
            }
        }
        $expect($nutzer === ['Pruefer.php'], 'FA11 nur Pruefer.php ruft die Sperrliste auf — ' . implode(', ', $nutzer));
        $expect($faltungsnutzer === [], 'FA11 die Faltung (TextFaltung) ruft nur die Sperrliste auf, auch der Prüfer nicht — ' . implode(', ', $faltungsnutzer));
        $expect($vergleicher === [], 'FA11 kein anderer Teil des Baukastens vergleicht selbst mit der Sperrliste (mb_stripos) — ' . implode(', ', $vergleicher));
        $q = (string) file_get_contents(H::wurzel() . '/lp/src/Pruefer.php');
        $expect(substr_count($q, 'Sperrliste::treffer(') >= 2 && !str_contains($q, 'self::normal('), 'FA11 der Prüfer sucht Sperrliste und Grenzwörter über Sperrliste::treffer und kennt kein normal() mehr');
    },
];
