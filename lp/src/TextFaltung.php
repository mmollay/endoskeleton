<?php
/**
 * Kopie von panel app/Support/TextFaltung.php (rc1084) — beide müssen gleich bleiben: gleiche Tabellen, gleiche Schritte, gleiche Reihenfolge.
 * Wer hier etwas ändert, ändert es dort mit (und umgekehrt); tests/faltung.test.php prüft die Eigenschaften, die für beide gelten.
 * Der Baukasten hat keine Abhängigkeit zum Panel: Diese Datei steht für sich, reines PHP mit mbstring und PCRE, ohne Composer und ohne `intl`.
 *
 * ABWEICHUNG vom Panel (Baukasten 3.54.1, Befunde der unabhängigen Prüfung — im Panel noch nicht): (1) lesartenMit() entscheidet „mehrdeutig?“ auch am Text nach den Schritten schriftformen … sternchen
 * (Zalgo-Haufen mit Umlautmarke, vor dem ein Nullbreiten- oder Verwechslungszeichen steht); (2) das Satzende in satzstrang() kennt schließende Zeichen und „…“; (3) lesartenOhnePunkte() ist öffentlich.
 * Bis das Panel dieselben Änderungen hat, sagen die beiden Kopien auf diesen Eingaben nicht dasselbe — der Baukasten ist dann strenger bei (1) und milder bei (2). Mit dem Panel zugleich übernehmen.
 *
 * Die Faltung eines Texts für den Vergleich mit der Sperrliste der Landingpages (Wörter, die auf einer Seite nicht stehen dürfen).
 *
 * Anlass (rc1084): Die Sperrliste ließ sich durch Schreibweisen umgehen, die der Leser für dasselbe Wort hält — Umlaut in NFD (a + kombinierender Punkt), kyrillisches „а“
 * statt „a“, Vollbreit-Zeichen („ｇarantiert“). Auf dem Panel-Server gibt es keine Erweiterung `intl` (keine Klasse Normalizer); sie zu aktivieren wäre eine Änderung des Servers, und der Baukasten setzt sie ebenso wenig voraus. Diese Klasse
 * erledigt das in reinem PHP mit mbstring und PCRE, in kleinen Schritten, jeder mit genau einer Aufgabe.
 *
 * Was „dasselbe“ heißt (die drei Quellen der Gleichheit, jede im Test an einer Liste aus der Unicode-Datenbank geprüft, tests/fixtures/sperrliste-*.json):
 *  1 Kompatibilität (NFKC): Jedes Zeichen, das Unicode selbst zu EINEM ASCII-Buchstaben oder EINER Ziffer zerlegt — Vollbreit, mathematische und eingekreiste Schriften, hoch- und
 *    tiefgestellte Buchstaben, römische Ziffern, Letterlike Symbols (ℂ ℓ ℕ …) —, wird dieser Buchstabe. Vollbreit macht mbstring, die großen Blöcke rechnet schriftformen(),
 *    den Rest nennt KOMPAT.
 *  2 Kanonische Zerlegung (NFC = NFD): Ein Buchstabe mit Akzent oder Diaerese ist gleich dem Grundbuchstaben + kombinierendem Zeichen. Ohne `Normalizer` steht die Zuordnung in AKZENTE
 *    (für lateinische, griechische und kyrillische Buchstaben, erzeugt aus der Zerlegungstabelle von Unicode 13); dazu fällt jedes kombinierende Zeichen weg.
 *  3 Verwechslung: Zeichen anderer Schriften, die genauso aussehen wie ein lateinischer Buchstabe (VERWECHSLUNG). Kein Zeichen davon ist ein deutscher Buchstabe.
 *
 * Was falten() macht (Reihenfolge ist Absicht; Text und Eintrag der Liste gehen durch dieselbe Faltung, also zählt nur, dass beide gleich enden):
 *   1 schriftformen      mathematische Buchstaben und Ziffern (𝗴𝗮𝗿𝗮…, auch Griechisch), eingekreiste/geklammerte/quadratische Buchstaben (ⓖⓐⓡ…) und „Outlined Latin“ → Buchstabe, nach Rechnung
 *   2 verwechslungen     KOMPAT, VERWECHSLUNG und VORAB: Zeichen → lateinischer Buchstabe oder Ziffer. Vor Schritt 3, damit Zeichen, die PCRE (Unicode 14) noch nicht kennt und als
 *                        „nicht zugewiesen“ löschen würde, vorher zum Buchstaben werden
 *   3 unsichtbares       alles, was man nicht sieht und mitten in ein Wort setzen kann: Formatzeichen (\p{Cf}), nicht zugewiesene Zeichen (\p{Cn}), Steuerzeichen, Variantenzeichen,
 *                        Hangul-Füller, Braille-Leerzeichen
 *   4 vollbreit          Vollbreit-Formen U+FF01–FF5E → ASCII (mb_convert_kana 'a'); Striche U+2010–2015, U+2212 → „-“, Prozentzeichen U+FE6A/U+066A → „%“
 *   5 sternchen          jedes „*“ fällt weg: „**“ ist der Fettdruck der Seite, „*“ (kursiv) setzt Markdown auch mitten ins Wort — die Seite zeigt das Wort ohne
 *   6 marken             a/o/u + (Marken) + Umlautmarke (¨ U+0308, kleines e U+0364, Doppelakut U+030B; auch ein nachgestelltes ¨ U+00A8) → ä/ö/ü (NFD → NFC), danach fallen alle übrigen
 *                        kombinierenden Zeichen weg (Zalgo, Durchstreichen, Akzente in NFD)
 *   7 leerraum           jeder Leerraum (auch U+00A0, U+3000, Tabulator, Zeilenumbruch) → ein Leerzeichen
 *   8 punkte             ein Leerzeichen vor/nach einem Zeichen, das weder Buchstabe noch Ziffer ist, fällt weg („100 % sicher“ = „100% sicher“)
 *   9 reihen             drei oder mehr EINZELNE Buchstaben hintereinander, getrennt durch je 1–3 Zeichen, die kein Buchstabe und keine Ziffer sind (g a r a n t …, g.a.r…, g - a - r …),
 *                        werden zu einem Wort — nach Schritt 8, damit „g - a - r“ (drei Zeichen Abstand) nur noch „g-a-r“ ist
 *  10 klein              Kleinschreibung samt Auffalten (MB_CASE_FOLD: ß → ss, ſ → s, ﬁ → fi)
 *  11 akzente            AKZENTE: ä/ö/ü/æ/œ → ae/oe/ue (so schreibt man Umlaute ohne Umlautzeichen), alle anderen Buchstaben mit Akzent, Haken oder Strich → der Buchstabe ohne
 *  12 marken, 13 verwechslungen   noch einmal: das Auffalten kann selbst ein kombinierendes Zeichen oder ein griechisches Zeichen erzeugen (ΐ → ι + Zeichen)
 *
 * Lesarten: Zwei Dinge lassen sich auf zwei Arten lesen, und dann gelten beide (lesarten()): ein Hangul-Füller oder Braille-Leerzeichen mitten im Text (Lücke oder nichts?) und ein
 * a/o/u mit einem Haufen Marken, unter denen eine Umlautmarke ist (Umlaut oder Zierrat im Zalgo-Text? — ein einzelnes ¨ ist ein Umlaut). Dazu die Lesart OHNE Zusammenziehen der
 * Buchstabenreihen, wenn der Text eine Reihe hatte. Ein Eintrag hat ebenso zwei Fassungen, mit und ohne Zusammenziehen (eintraege(): „u. a.“ aus einzelnen Buchstaben soll auch neben
 * anderen Einzelbuchstaben gefunden werden). Für Wendungen gibt es den Satzstrang (satzstrang(), je Lesart).
 *
 * Unicode-Stand: Die Tabellen stammen aus Unicode 13 (Python), die Fixtures zusätzlich aus Unicode 17 für die Zeichen ab 14 (Node/ICU, sperrliste-kompat.json „neuer“); PCRE auf dem Server kennt
 * Unicode 14. Was später dazukommt und noch kein Buchstabe ist, löscht Schritt 3 als „nicht zugewiesen“ — ein Zeichen ab Unicode 15, das wie ein Buchstabe aussieht und hier nicht
 * genannt ist (außer Outlined Latin), lässt den Buchstaben verschwinden.
 *
 * Was bewusst NICHT passiert, und warum (festgehalten in tests/faltung.test.php, FA06):
 *  - Leetspeak (g4rantiert, h3ilung): Ziffern stehen in Sperrwörtern („100 % sicher“) und in gewöhnlichem Text; „1 → i/l“ und „0 → o“ machten aus Zahlen Buchstaben und aus Wörtern
 *    Fehlalarme. Für den Leser ist „g4rantiert“ außerdem ein anderes Wort als „garantiert“; die Gleichheit, die diese Klasse herstellt, ist die des AUSSEHENS und der Rechtschreibung.
 *  - der Umlaut ohne Punkte (Krauter): Blüten ≠ bluten, schön ≠ schon, Lösung ≠ Losung. Wer ä als ae schreibt, schreibt dasselbe Wort; wer den Punkt weglässt, schreibt ein anderes.
 *  - Kapitälchen (ɢᴀʀᴀɴᴛɪᴇʀᴛ), Armenisch, Cherokee, Klammer-Großbuchstaben 🄐, Regionalzeichen 🇬 u. a.: Die Verwechslungstabelle ist eine kleine Auswahl der Zeichen, die in gängigen
 *    Schriften nicht von einem lateinischen Buchstaben zu unterscheiden sind — nicht die Verwechslungsliste des Unicode-Konsortiums (UTS #39, mehrere tausend Einträge).
 *  - ein Buchstabe vertauscht, ergänzt oder weggelassen, umgekehrt geschriebene Wörter: andere Wörter.
 *
 * Grenzen und Fehlverhalten: Ein Text über MAX_BYTES, ungültiges UTF-8, ein Richtungs-Steuerzeichen (U+202A–202E, U+2066–2069: sie kehren die Anzeige um, „U+202E treitnarag“ liest sich
 * garantiert; das Panel weist sie schon bei der Eingabe ab, der Baukasten nicht) oder ein Regex-Schritt, der scheitert (PCRE-Grenzen), liefert null — „nicht prüfbar“. Der Aufrufer
 * (Endo\Lp\Sperrliste) behandelt das als gesperrt, nie als frei. Alle Schritte brauchen Zeit linear zur Länge des Texts (kein verschachteltes Rückwärtssuchen: die Wiederholungen
 * in Schritt 9 sind besitzergreifend). Nach 64 KB (so groß ist im Panel die ganze Seite höchstens; im Baukasten ist es die Obergrenze für jedes einzelne Textfeld) wird nicht gekappt, sondern abgelehnt: Ein
 * gekapptes Ende wäre eine Umgehung.
 */

declare(strict_types=1);

namespace Endo\Lp;

final class TextFaltung
{
    /** Längster Text in Bytes, der gefaltet wird — länger ist „nicht prüfbar“. Eine ganze Seite ist im Panel höchstens 64 KB groß, ein einzelnes Textfeld der Seite ist viel kürzer. */
    public const MAX_BYTES = 65536;

    /** Lesart: Hangul-Füller und Braille-Leerzeichen sind Lücken, ein U+0308 ist kein Umlaut. */
    private const ALT = 1;
    /** Lesart: Buchstabenreihen bleiben, wie sie sind. */
    private const OHNE_REIHEN = 2;
    /** Für den Satzstrang: Leerzeichen um Satzzeichen bleiben stehen, damit man ein Satzende erkennt. */
    private const OHNE_PUNKTE = 4;
    /** Marken, die ein Umlaut-Punktpaar ersetzen oder sein können: ¨ darüber, kleines e darüber (alte Schreibung des ü), Doppelakut (ő ű sehen wie ö ü aus). */
    private const UMLAUTMARKE = '\x{0308}\x{0364}\x{030B}';

    /** Ein Buchstabe: jeder Buchstabe außer den Modifikatorbuchstaben (Lm: Tatweel ـ, Dehnstrich ー, Apostroph ʼ, Dreieckspunkte ː …), die wie Trennzeichen mitten ins Wort passen. */
    private const B = '\p{Ll}\p{Lu}\p{Lt}\p{Lo}';
    private const UNSICHTBAR = '~[\p{Cf}\p{Cn}\x{00AD}\x{034F}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180B}-\x{180E}\x{2800}\x{3164}\x{FE00}-\x{FE0F}\x{FFA0}\x{E0100}-\x{E01EF}]|(?![\t\n\x0B\x0C\r\x{85}])\p{Cc}~u';
    private const REIHE = '~(?<![' . self::B . '\p{N}])[' . self::B . '](?![' . self::B . '\p{N}])(?:[^' . self::B . '\p{N}]{1,3}+[' . self::B . '](?![' . self::B . '\p{N}])){2,}+~u';
    private const PUNKTE = '~ ?([^' . self::B . '\p{N}\s]) ?~u';
    private const NICHT_BUCHSTABE = '~[^' . self::B . '\p{N}]+~u';
    /**
     * Was einen Text mehrdeutig macht (Lesart ALT): ein Füller (Lücke oder nichts?) und ein a/o/u mit einem Haufen Marken (zwei oder mehr), unter denen eine Umlautmarke ist (Umlaut oder
     * Zierrat im Zalgo-Text?). Ein einzelnes ¨ darüber ist ein Umlaut, kein Zierrat — NFD sperrt also nicht anders als NFC.
     */
    private const MEHRDEUTIG = '~[\x{115F}\x{1160}\x{2800}\x{3164}\x{FFA0}]|[aouAOU](?=[\p{Mn}\p{Me}]*[' . self::UMLAUTMARKE . '])[\p{Mn}\p{Me}]{2,}~u';

    /** Die 58 griechischen Zeichen jeder mathematischen Schrift U+1D6A8–1D7C9 in ihrer Reihenfolge (aus NFKD). */
    private const MATHE_GRIECHISCH = "\u{0391}\u{0392}\u{0393}\u{0394}\u{0395}\u{0396}\u{0397}\u{0398}\u{0399}\u{039A}\u{039B}\u{039C}\u{039D}\u{039E}\u{039F}\u{03A0}\u{03A1}\u{0398}\u{03A3}\u{03A4}\u{03A5}\u{03A6}\u{03A7}\u{03A8}\u{03A9}\u{2207}\u{03B1}\u{03B2}\u{03B3}\u{03B4}\u{03B5}\u{03B6}\u{03B7}\u{03B8}\u{03B9}\u{03BA}\u{03BB}\u{03BC}\u{03BD}\u{03BE}\u{03BF}\u{03C0}\u{03C1}\u{03C2}\u{03C3}\u{03C4}\u{03C5}\u{03C6}\u{03C7}\u{03C8}\u{03C9}\u{2202}\u{03B5}\u{03B8}\u{03BA}\u{03C6}\u{03C1}\u{03C0}";

    /**
     * Lateinischer Kleinbuchstabe ← Zeichen, die in gängigen Schriften genauso aussehen. Gründe je Gruppe:
     *  - Kyrillisch und Griechisch: die klassischen Verwechsler; Groß- und Kleinbuchstaben stehen einzeln da, weil der Schritt VOR der Kleinschreibung läuft (Ν sieht aus wie N, ν wie v).
     *  - ɑ ɡ ı İ ȷ 𝚤 𝚥 ŉ ẚ: lateinische Sonderformen ohne deutschen Gebrauch (Alpha, Schreibschrift-g, punktloses i und j, i mit Punkt; ŉ und ẚ falten sonst zu einem Buchstaben
     *    + Modifikatorapostroph, der wie ein Trenner wirkte).
     * Auswahl nach dem Gesichtspunkt „nicht zu unterscheiden“; Zeichen, die nur ähnlich aussehen (ь, в, и, п, т, μ, β …), fehlen mit Absicht. Kein Zeichen der Tabelle ist ein deutscher
     * Buchstabe (a–z, ä, ö, ü, ß) — das prüft FA09. Die Zeichen stehen als Nummern da, damit ein Blick in die Datei sie nicht mit lateinischen verwechselt.
     */
    private const VERWECHSLUNG = [
        'a' => "\u{0430}\u{0410}\u{03B1}\u{0391}\u{0251}\u{1E9A}",                       // а А (kyrillisch) α Α (griechisch) ɑ ẚ
        'b' => "\u{0412}\u{0392}",                                                       // В Β
        'c' => "\u{0441}\u{0421}\u{03F2}\u{03F9}",                                       // с С ϲ Ϲ (lunares Sigma)
        'd' => "\u{0501}\u{0500}",                                                       // ԁ Ԁ
        'e' => "\u{0435}\u{0415}\u{0395}",                                               // е Е Ε
        'g' => "\u{0261}",                                                               // ɡ
        'h' => "\u{041D}\u{04BB}\u{04BA}\u{0397}",                                       // Н һ Һ Η
        'i' => "\u{0456}\u{0406}\u{0399}\u{03B9}\u{0131}\u{0130}\u{1D6A4}",              // і І Ι ι ı İ 𝚤
        'j' => "\u{0458}\u{0408}\u{03F3}\u{0237}\u{1D6A5}",                              // ј Ј ϳ ȷ 𝚥
        'k' => "\u{043A}\u{041A}\u{039A}\u{03BA}",                                       // к К Κ κ
        'l' => "\u{04CF}",                                                               // ӏ (Palotschka)
        'm' => "\u{041C}\u{039C}",                                                       // М Μ
        'n' => "\u{039D}\u{0149}",                                                       // Ν ŉ
        'o' => "\u{043E}\u{041E}\u{03BF}\u{039F}",                                       // о О ο Ο
        'p' => "\u{0440}\u{0420}\u{03C1}\u{03A1}",                                       // р Р ρ Ρ
        'q' => "\u{051B}\u{051A}",                                                       // ԛ Ԛ
        's' => "\u{0455}\u{0405}",                                                       // ѕ Ѕ
        't' => "\u{0422}\u{03A4}",                                                       // Т Τ
        'u' => "\u{03C5}",                                                               // υ
        'v' => "\u{03BD}",                                                               // ν
        'w' => "\u{051D}\u{051C}",                                                       // ԝ Ԝ
        'x' => "\u{0445}\u{0425}\u{03C7}\u{03A7}",                                       // х Х χ Χ
        'y' => "\u{0443}\u{0423}\u{03A5}",                                               // у У Υ
        'z' => "\u{0396}",                                                               // Ζ
    ];

    /**
     * Ersatz ← Zeichen, die Unicode selbst (NFKD, nicht schon durch die kanonische Zerlegung in AKZENTE) in ASCII-Buchstaben/Ziffern zerlegt: ein Zeichen (ᵃ ⅼ ① ℂ) oder mehrere
     * (㎏ → kg, ™ → tm, ⑩ → 10, ⑴ → (1), Ⅻ → xii, ǆ → dz), ohne die Blöcke, die schriftformen() rechnet, und ohne Vollbreit (mb_convert_kana). Erzeugt aus Unicode 13 (Python unicodedata;
     * tests/fixtures/sperrliste-kompat.json hält die ganze Liste, der Test FA09 prüft jedes Zeichen). Der Ersatz ist der Schlüssel, klein geschrieben (die Faltung macht ohnehin klein).
     */
    private const KOMPAT = [
        '0' => "\u{2070}\u{2080}\u{24EA}\u{1FBF0}",   // ⁰ ₀ ⓪ 🯰
        '1' => "\u{00B9}\u{2081}\u{2460}\u{1FBF1}",   // ¹ ₁ ① 🯱
        '2' => "\u{00B2}\u{2082}\u{2461}\u{1FBF2}",   // ² ₂ ② 🯲
        '3' => "\u{00B3}\u{2083}\u{2462}\u{1FBF3}",   // ³ ₃ ③ 🯳
        '4' => "\u{2074}\u{2084}\u{2463}\u{1FBF4}",   // ⁴ ₄ ④ 🯴
        '5' => "\u{2075}\u{2085}\u{2464}\u{1FBF5}",   // ⁵ ₅ ⑤ 🯵
        '6' => "\u{2076}\u{2086}\u{2465}\u{1FBF6}",   // ⁶ ₆ ⑥ 🯶
        '7' => "\u{2077}\u{2087}\u{2466}\u{1FBF7}",   // ⁷ ₇ ⑦ 🯷
        '8' => "\u{2078}\u{2088}\u{2467}\u{1FBF8}",   // ⁸ ₈ ⑧ 🯸
        '9' => "\u{2079}\u{2089}\u{2468}\u{1FBF9}",   // ⁹ ₉ ⑨ 🯹
        'a' => "\u{00AA}\u{1D2C}\u{1D43}\u{2090}",   // ª ᴬ ᵃ ₐ
        'b' => "\u{1D2E}\u{1D47}\u{212C}",   // ᴮ ᵇ ℬ
        'c' => "\u{1D9C}\u{2102}\u{212D}\u{216D}\u{217D}\u{1F12B}\u{A7F2}",   // ᶜ ℂ ℭ Ⅽ ⅽ 🄫
        'd' => "\u{1D30}\u{1D48}\u{2145}\u{2146}\u{216E}\u{217E}",   // ᴰ ᵈ ⅅ ⅆ Ⅾ ⅾ
        'e' => "\u{1D31}\u{1D49}\u{2091}\u{212F}\u{2130}\u{2147}",   // ᴱ ᵉ ₑ ℯ ℰ ⅇ
        'f' => "\u{1DA0}\u{2131}\u{A7F3}",   // ᶠ ℱ
        'g' => "\u{1D33}\u{1D4D}\u{210A}",   // ᴳ ᵍ ℊ
        'h' => "\u{02B0}\u{1D34}\u{2095}\u{210B}\u{210C}\u{210D}\u{210E}",   // ʰ ᴴ ₕ ℋ ℌ ℍ ℎ
        'i' => "\u{1D35}\u{1D62}\u{2071}\u{2110}\u{2111}\u{2139}\u{2148}\u{2160}\u{2170}",   // ᴵ ᵢ ⁱ ℐ ℑ ℹ ⅈ Ⅰ ⅰ
        'j' => "\u{02B2}\u{1D36}\u{2149}\u{2C7C}",   // ʲ ᴶ ⅉ ⱼ
        'k' => "\u{1D37}\u{1D4F}\u{2096}",   // ᴷ ᵏ ₖ
        'l' => "\u{02E1}\u{1D38}\u{2097}\u{2112}\u{2113}\u{216C}\u{217C}",   // ˡ ᴸ ₗ ℒ ℓ Ⅼ ⅼ
        'm' => "\u{1D39}\u{1D50}\u{2098}\u{2133}\u{216F}\u{217F}",   // ᴹ ᵐ ₘ ℳ Ⅿ ⅿ
        'n' => "\u{1D3A}\u{207F}\u{2099}\u{2115}",   // ᴺ ⁿ ₙ ℕ
        'o' => "\u{00BA}\u{1D3C}\u{1D52}\u{2092}\u{2134}",   // º ᴼ ᵒ ₒ ℴ
        'p' => "\u{1D3E}\u{1D56}\u{209A}\u{2119}",   // ᴾ ᵖ ₚ ℙ
        'q' => "\u{211A}\u{A7F4}\u{107A5}",   // ℚ
        'r' => "\u{02B3}\u{1D3F}\u{1D63}\u{211B}\u{211C}\u{211D}\u{1F12C}",   // ʳ ᴿ ᵣ ℛ ℜ ℝ 🄬
        's' => "\u{017F}\u{02E2}\u{1E9B}\u{209B}\u{A7F1}",   // ſ ˢ ẛ ₛ
        't' => "\u{1D40}\u{1D57}\u{209C}",   // ᵀ ᵗ ₜ
        'u' => "\u{1D41}\u{1D58}\u{1D64}",   // ᵁ ᵘ ᵤ
        'v' => "\u{1D5B}\u{1D65}\u{2164}\u{2174}\u{2C7D}",   // ᵛ ᵥ Ⅴ ⅴ ⱽ
        'w' => "\u{02B7}\u{1D42}",   // ʷ ᵂ
        'x' => "\u{02E3}\u{2093}\u{2169}\u{2179}",   // ˣ ₓ Ⅹ ⅹ
        'y' => "\u{02B8}",   // ʸ
        'z' => "\u{1DBB}\u{2124}\u{2128}",   // ᶻ ℤ ℨ
        '0,' => "\u{1F101}",   // 🄁
        '0.' => "\u{1F100}",   // 🄀
        '1,' => "\u{1F102}",   // 🄂
        '1.' => "\u{2488}",   // ⒈
        '10' => "\u{2469}",   // ⑩
        '11' => "\u{246A}",   // ⑪
        '12' => "\u{246B}",   // ⑫
        '13' => "\u{246C}",   // ⑬
        '14' => "\u{246D}",   // ⑭
        '15' => "\u{246E}",   // ⑮
        '16' => "\u{246F}",   // ⑯
        '17' => "\u{2470}",   // ⑰
        '18' => "\u{2471}",   // ⑱
        '19' => "\u{2472}",   // ⑲
        '2,' => "\u{1F103}",   // 🄃
        '2.' => "\u{2489}",   // ⒉
        '20' => "\u{2473}",   // ⑳
        '21' => "\u{3251}",   // ㉑
        '22' => "\u{3252}",   // ㉒
        '23' => "\u{3253}",   // ㉓
        '24' => "\u{3254}",   // ㉔
        '25' => "\u{3255}",   // ㉕
        '26' => "\u{3256}",   // ㉖
        '27' => "\u{3257}",   // ㉗
        '28' => "\u{3258}",   // ㉘
        '29' => "\u{3259}",   // ㉙
        '3,' => "\u{1F104}",   // 🄄
        '3.' => "\u{248A}",   // ⒊
        '30' => "\u{325A}",   // ㉚
        '31' => "\u{325B}",   // ㉛
        '32' => "\u{325C}",   // ㉜
        '33' => "\u{325D}",   // ㉝
        '34' => "\u{325E}",   // ㉞
        '35' => "\u{325F}",   // ㉟
        '36' => "\u{32B1}",   // ㊱
        '37' => "\u{32B2}",   // ㊲
        '38' => "\u{32B3}",   // ㊳
        '39' => "\u{32B4}",   // ㊴
        '4,' => "\u{1F105}",   // 🄅
        '4.' => "\u{248B}",   // ⒋
        '40' => "\u{32B5}",   // ㊵
        '41' => "\u{32B6}",   // ㊶
        '42' => "\u{32B7}",   // ㊷
        '43' => "\u{32B8}",   // ㊸
        '44' => "\u{32B9}",   // ㊹
        '45' => "\u{32BA}",   // ㊺
        '46' => "\u{32BB}",   // ㊻
        '47' => "\u{32BC}",   // ㊼
        '48' => "\u{32BD}",   // ㊽
        '49' => "\u{32BE}",   // ㊾
        '5,' => "\u{1F106}",   // 🄆
        '5.' => "\u{248C}",   // ⒌
        '50' => "\u{32BF}",   // ㊿
        '6,' => "\u{1F107}",   // 🄇
        '6.' => "\u{248D}",   // ⒍
        '7,' => "\u{1F108}",   // 🄈
        '7.' => "\u{248E}",   // ⒎
        '8,' => "\u{1F109}",   // 🄉
        '8.' => "\u{248F}",   // ⒏
        '9,' => "\u{1F10A}",   // 🄊
        '9.' => "\u{2490}",   // ⒐
        'au' => "\u{3373}",   // ㍳
        'bq' => "\u{33C3}",   // ㏃
        'cc' => "\u{33C4}",   // ㏄
        'cd' => "\u{33C5}\u{1F12D}",   // ㏅ 🄭
        'cm' => "\u{339D}",   // ㎝
        'da' => "\u{3372}",   // ㍲
        'db' => "\u{33C8}",   // ㏈
        'dj' => "\u{1F190}",   // 🆐
        'dl' => "\u{3397}",   // ㎗
        'dm' => "\u{3377}",   // ㍷
        'dz' => "\u{01C4}\u{01C5}\u{01C6}\u{01F1}\u{01F2}\u{01F3}",   // Ǆ ǅ ǆ Ǳ ǲ ǳ
        'ev' => "\u{32CE}",   // ㋎
        'ff' => "\u{FB00}",   // ﬀ
        'fi' => "\u{FB01}",   // ﬁ
        'fl' => "\u{FB02}",   // ﬂ
        'fm' => "\u{3399}",   // ㎙
        'gb' => "\u{3387}",   // ㎇
        'gy' => "\u{33C9}",   // ㏉
        'ha' => "\u{33CA}",   // ㏊
        'hg' => "\u{32CC}",   // ㋌
        'hp' => "\u{33CB}",   // ㏋
        'hz' => "\u{3390}",   // ㎐
        'ii' => "\u{2161}\u{2171}",   // Ⅱ ⅱ
        'ij' => "\u{0132}\u{0133}",   // Ĳ ĳ
        'in' => "\u{33CC}",   // ㏌
        'iu' => "\u{337A}",   // ㍺
        'iv' => "\u{2163}\u{2173}",   // Ⅳ ⅳ
        'ix' => "\u{2168}\u{2178}",   // Ⅸ ⅸ
        'ka' => "\u{3384}",   // ㎄
        'kb' => "\u{3385}",   // ㎅
        'kg' => "\u{338F}",   // ㎏
        'kk' => "\u{33CD}",   // ㏍
        'kl' => "\u{3398}",   // ㎘
        'km' => "\u{339E}\u{33CE}",   // ㎞ ㏎
        'kt' => "\u{33CF}",   // ㏏
        'kv' => "\u{33B8}",   // ㎸
        'kw' => "\u{33BE}",   // ㎾
        'lj' => "\u{01C7}\u{01C8}\u{01C9}",   // Ǉ ǈ ǉ
        'lm' => "\u{33D0}",   // ㏐
        'ln' => "\u{33D1}",   // ㏑
        'lx' => "\u{33D3}",   // ㏓
        'm2' => "\u{33A1}",   // ㎡
        'm3' => "\u{33A5}",   // ㎥
        'ma' => "\u{3383}",   // ㎃
        'mb' => "\u{3386}\u{33D4}",   // ㎆ ㏔
        'mg' => "\u{338E}",   // ㎎
        'ml' => "\u{3396}",   // ㎖
        'mm' => "\u{339C}",   // ㎜
        'ms' => "\u{33B3}",   // ㎳
        'mv' => "\u{33B7}\u{33B9}",   // ㎷ ㎹
        'mw' => "\u{33BD}\u{33BF}",   // ㎽ ㎿
        'na' => "\u{3381}",   // ㎁
        'nf' => "\u{338B}",   // ㎋
        'nj' => "\u{01CA}\u{01CB}\u{01CC}",   // Ǌ ǋ ǌ
        'nm' => "\u{339A}",   // ㎚
        'no' => "\u{2116}",   // №
        'ns' => "\u{33B1}",   // ㎱
        'nv' => "\u{33B5}",   // ㎵
        'nw' => "\u{33BB}",   // ㎻
        'ov' => "\u{3375}",   // ㍵
        'pa' => "\u{3380}\u{33A9}",   // ㎀ ㎩
        'pc' => "\u{3376}",   // ㍶
        'pf' => "\u{338A}",   // ㎊
        'ph' => "\u{33D7}",   // ㏗
        'pr' => "\u{33DA}",   // ㏚
        'ps' => "\u{33B0}",   // ㎰
        'pv' => "\u{33B4}",   // ㎴
        'pw' => "\u{33BA}",   // ㎺
        'rs' => "\u{20A8}",   // ₨
        'sm' => "\u{2120}",   // ℠
        'sr' => "\u{33DB}",   // ㏛
        'st' => "\u{FB05}\u{FB06}",   // ﬅ ﬆ
        'sv' => "\u{33DC}",   // ㏜
        'tm' => "\u{2122}",   // ™
        'vi' => "\u{2165}\u{2175}",   // Ⅵ ⅵ
        'wb' => "\u{33DD}",   // ㏝
        'wz' => "\u{1F12E}",   // 🄮
        'xi' => "\u{216A}\u{217A}",   // Ⅺ ⅺ
        '(1)' => "\u{2474}",   // ⑴
        '(2)' => "\u{2475}",   // ⑵
        '(3)' => "\u{2476}",   // ⑶
        '(4)' => "\u{2477}",   // ⑷
        '(5)' => "\u{2478}",   // ⑸
        '(6)' => "\u{2479}",   // ⑹
        '(7)' => "\u{247A}",   // ⑺
        '(8)' => "\u{247B}",   // ⑻
        '(9)' => "\u{247C}",   // ⑼
        '(a)' => "\u{1F110}",   // 🄐
        '(b)' => "\u{1F111}",   // 🄑
        '(c)' => "\u{1F112}",   // 🄒
        '(d)' => "\u{1F113}",   // 🄓
        '(e)' => "\u{1F114}",   // 🄔
        '(f)' => "\u{1F115}",   // 🄕
        '(g)' => "\u{1F116}",   // 🄖
        '(h)' => "\u{1F117}",   // 🄗
        '(i)' => "\u{1F118}",   // 🄘
        '(j)' => "\u{1F119}",   // 🄙
        '(k)' => "\u{1F11A}",   // 🄚
        '(l)' => "\u{1F11B}",   // 🄛
        '(m)' => "\u{1F11C}",   // 🄜
        '(n)' => "\u{1F11D}",   // 🄝
        '(o)' => "\u{1F11E}",   // 🄞
        '(p)' => "\u{1F11F}",   // 🄟
        '(q)' => "\u{1F120}",   // 🄠
        '(r)' => "\u{1F121}",   // 🄡
        '(s)' => "\u{1F122}",   // 🄢
        '(t)' => "\u{1F123}",   // 🄣
        '(u)' => "\u{1F124}",   // 🄤
        '(v)' => "\u{1F125}",   // 🄥
        '(w)' => "\u{1F126}",   // 🄦
        '(x)' => "\u{1F127}",   // 🄧
        '(y)' => "\u{1F128}",   // 🄨
        '(z)' => "\u{1F129}",   // 🄩
        '10.' => "\u{2491}",   // ⒑
        '11.' => "\u{2492}",   // ⒒
        '12.' => "\u{2493}",   // ⒓
        '13.' => "\u{2494}",   // ⒔
        '14.' => "\u{2495}",   // ⒕
        '15.' => "\u{2496}",   // ⒖
        '16.' => "\u{2497}",   // ⒗
        '17.' => "\u{2498}",   // ⒘
        '18.' => "\u{2499}",   // ⒙
        '19.' => "\u{249A}",   // ⒚
        '20.' => "\u{249B}",   // ⒛
        'a/c' => "\u{2100}",   // ℀
        'a/s' => "\u{2101}",   // ℁
        'bar' => "\u{3374}",   // ㍴
        'c/o' => "\u{2105}",   // ℅
        'c/u' => "\u{2106}",   // ℆
        'cal' => "\u{3388}",   // ㎈
        'cm2' => "\u{33A0}",   // ㎠
        'cm3' => "\u{33A4}",   // ㎤
        'co.' => "\u{33C7}",   // ㏇
        'dm2' => "\u{3378}",   // ㍸
        'dm3' => "\u{3379}",   // ㍹
        'erg' => "\u{32CD}",   // ㋍
        'fax' => "\u{213B}",   // ℻
        'ffi' => "\u{FB03}",   // ﬃ
        'ffl' => "\u{FB04}",   // ﬄ
        'gal' => "\u{33FF}",   // ㏿
        'ghz' => "\u{3393}",   // ㎓
        'gpa' => "\u{33AC}",   // ㎬
        'hpa' => "\u{3371}",   // ㍱
        'iii' => "\u{2162}\u{2172}",   // Ⅲ ⅲ
        'khz' => "\u{3391}",   // ㎑
        'km2' => "\u{33A2}",   // ㎢
        'km3' => "\u{33A6}",   // ㎦
        'kpa' => "\u{33AA}",   // ㎪
        'log' => "\u{33D2}",   // ㏒
        'ltd' => "\u{32CF}",   // ㋏
        'mhz' => "\u{3392}",   // ㎒
        'mil' => "\u{33D5}",   // ㏕
        'mm2' => "\u{339F}",   // ㎟
        'mm3' => "\u{33A3}",   // ㎣
        'mol' => "\u{33D6}",   // ㏖
        'mpa' => "\u{33AB}",   // ㎫
        'ppm' => "\u{33D9}",   // ㏙
        'pte' => "\u{3250}",   // ㉐
        'rad' => "\u{33AD}",   // ㎭
        'tel' => "\u{2121}",   // ℡
        'thz' => "\u{3394}",   // ㎔
        'vii' => "\u{2166}\u{2176}",   // Ⅶ ⅶ
        'xii' => "\u{216B}\u{217B}",   // Ⅻ ⅻ
        '(10)' => "\u{247D}",   // ⑽
        '(11)' => "\u{247E}",   // ⑾
        '(12)' => "\u{247F}",   // ⑿
        '(13)' => "\u{2480}",   // ⒀
        '(14)' => "\u{2481}",   // ⒁
        '(15)' => "\u{2482}",   // ⒂
        '(16)' => "\u{2483}",   // ⒃
        '(17)' => "\u{2484}",   // ⒄
        '(18)' => "\u{2485}",   // ⒅
        '(19)' => "\u{2486}",   // ⒆
        '(20)' => "\u{2487}",   // ⒇
        'a.m.' => "\u{33C2}",   // ㏂
        'kcal' => "\u{3389}",   // ㎉
        'p.m.' => "\u{33D8}",   // ㏘
        'viii' => "\u{2167}\u{2177}",   // Ⅷ ⅷ
    ];

    /**
     * Ersatz ← Zeichen, nach der Kleinschreibung. Die Umlaute werden zur Umschrift (ä = ae, ö = oe, ü = ue, æ = ae, œ = oe): Wer „Heilkraeuter“ schreibt, meint „Heilkräuter“, und
     * „Heilkräuter“ ist ohnehin das Wort der Liste. Alle anderen Buchstaben mit Akzent, Haken oder Strich werden der Buchstabe ohne (Akzent im Wort ändert es für den Leser nicht:
     * garántiert), und die vorkomponierten sind GLEICH ihrer Zerlegung (á = a + U+0301): erzeugt aus der Unicode-Zerlegungstabelle (Python unicodedata NFD, Unicode 13) für alle
     * lateinischen, griechischen und kyrillischen Buchstaben, dazu von Hand die Buchstaben mit Strich ohne Zerlegung (ø đ ħ ł ŧ ǥ …). tests/fixtures/sperrliste-nfd.json hält die Paare, FA09 prüft jedes.
     */
    private const AKZENTE = [
        'a' => 'àáâãåāăąǎǡǻȁȃȧάӑḁạảấầẩẫậắằẳẵặἀἁἂἃἄἅἆἇὰάᾀᾁᾂᾃᾄᾅᾆᾇᾰᾱᾲᾳᾴᾷ',
        'ae' => 'äæǟǣǽӓ',
        'b' => 'ƀḃḅḇ',
        'c' => 'çćĉċčḉ',
        'd' => 'ďđḋḍḏḑḓ',
        'e' => 'èéêëēĕėęěȅȇȩѐёӗḕḗḙḛḝẹẻẽếềểễệ',
        'f' => 'ḟ',
        'g' => 'ĝğġģǥǧǵḡ',
        'h' => 'ĥħȟḣḥḧḩḫℏ',
        'i' => 'ìíîïĩīĭįǐȉȋίϊїḭḯỉịἰἱἲἳἴἵἶἷὶίῐῑ',
        'j' => 'ĵ',
        'k' => 'ķǩќḱḳḵ',
        'l' => 'ĺļľŀłḷḹḻḽ',
        'm' => 'ḿṁṃ',
        'n' => 'ñńņňǹṅṇṉṋ',
        'o' => 'òóôõøōŏơǒǫǭǿȍȏȭȯȱόṍṑṓọỏốồổỗộớờởỡợὀὁὂὃὄὅὸό',
        'oe' => 'öőœȫӧṏ',
        'p' => 'ṕṗῥ',
        'r' => 'ŕŗřȑȓṙṛṝṟ',
        's' => 'śŝşšșṡṣṥṧṩ',
        't' => 'ţťŧțṫṭṯṱ',
        'u' => 'ùúûũūŭůųưǔȕȗύṳṵṷṹụủứừửữựὑὓὕὗὺύῠῡ',
        'ue' => 'üűǖǘǚǜΰϋṻῢΰῧ',
        'v' => 'ṽṿ',
        'w' => 'ŵẁẃẅẇẉ',
        'x' => 'ẋẍ',
        'y' => 'ýÿŷȳўӯӱӳẏỳỵỷỹ',
        'z' => 'źżžẑẓẕ',
        'ʒ' => 'ǯ',
        'ʹ' => 'ʹ',
        'ε' => 'έἐἑἒἓἔἕὲέ',
        'η' => 'ήἠἡἢἣἤἥἦἧὴήᾐᾑᾒᾓᾔᾕᾖᾗῂῃῄῇ',
        'ω' => 'ώὠὡὢὣὤὥὦὧὼώᾠᾡᾢᾣᾤᾥᾦᾧῲῳῴῷ',
        'ϒ' => 'ϓϔ',
        'г' => 'ѓ',
        'ж' => 'ӂӝ',
        'з' => 'ӟ',
        'и' => 'йѝӣӥ',
        'ч' => 'ӵ',
        'ы' => 'ӹ',
        'э' => 'ӭ',
        'ѵ' => 'ѷ',
        'ә' => 'ӛ',
        'ө' => 'ӫ',
    ];

    /**
     * Wie AKZENTE, aber VOR der Kleinschreibung (Schritt 2): Buchstaben mit Akzent, deren Grundbuchstabe als GROSSbuchstabe einen lateinischen vortäuscht (Έ = Ε + Akzent, Ε sieht aus
     * wie E; das kleine ε nicht) oder deren Auffalten sie in mehrere Zeichen zerlegt (ΰ). Nach der Kleinschreibung gingen sie verloren — und NFC = NFD gälte nicht. Erzeugt wie AKZENTE.
     */
    private const VORAB = [
        'a' => 'ᾀᾁᾂᾃᾄᾅᾆᾇᾈᾉᾊᾋᾌᾍᾎᾏᾲᾳᾴᾷᾼ',
        'e' => 'ΈἘἙἚἛἜἝῈΈ',
        'h' => 'ΉἨἩἪἫἬἭἮἯᾘᾙᾚᾛᾜᾝᾞᾟῊΉῌ',
        'ue' => 'ΰῢΰῧ',
        'y' => 'ΎΫὙὛὝὟῨῩῪΎ',
        'η' => 'ᾐᾑᾒᾓᾔᾕᾖᾗῂῃῄῇ',
        'ω' => 'ᾠᾡᾢᾣᾤᾥᾦᾧᾨᾩᾪᾫᾬᾭᾮᾯῲῳῴῷῼ',
    ];

    /** Die Schritte in ihrer Reihenfolge (Namen der Methoden unten). */
    private const SCHRITTE = ['schriftformen', 'verwechslungen', 'unsichtbares', 'vollbreit', 'sternchen', 'marken', 'leerraum', 'punkte', 'reihen', 'klein', 'akzente', 'marken', 'verwechslungen'];

    /** @var ?array<string,string> */
    private static ?array $verwechslung = null;
    /** @var ?array<string,string> */
    private static ?array $akzente = null;

    /**
     * Der Text in der Form, in der er mit der Sperrliste verglichen wird — oder null, wenn er sich nicht prüfen lässt (zu lang, kein gültiges UTF-8, Richtungs-Steuerzeichen, ein
     * Regex-Schritt ist gescheitert). null heißt NIE „nichts gefunden“: Wer faltet, um zu sperren, sperrt dann.
     */
    public static function falten(string $text): ?string
    {
        return self::lauf($text, 0);
    }

    /**
     * Die Lesarten eines Textes (siehe oben): die erste ist falten(), dazu ggf. die zweite Lesart des Füllers/U+0308 und die ohne Zusammenziehen der Buchstabenreihen. Gleiche fallen weg.
     * null, wenn eine davon nicht prüfbar ist.
     *
     * @return ?list<string>
     */
    public static function lesarten(string $text): ?array
    {
        return self::lesartenMit($text, 0);
    }

    /**
     * Die Lesarten wie lesarten(), aber ohne den Schritt „Leerzeichen um Satzzeichen fallen weg“ (Lesart OHNE_PUNKTE, wie sie der Satzstrang braucht): „Kosten – frei wählbar“ bleibt „kosten - frei wählbar“
     * statt „kosten-frei wählbar“. Für Regex-Suchen, die einen Bindestrich ohne Leerzeichen von einem mit Leerzeichen unterscheiden müssen (Pruefer::preis). null, wenn der Text nicht prüfbar ist.
     *
     * @return ?list<string>
     */
    public static function lesartenOhnePunkte(string $text): ?array
    {
        return self::lesartenMit($text, self::OHNE_PUNKTE);
    }

    /**
     * Ein Eintrag der Sperrliste in der Vergleichsform — in zwei Fassungen: mit und ohne Zusammenziehen von Buchstabenreihen (ein Eintrag wie „i. d. R“ aus einzelnen Buchstaben
     * soll auch dort gefunden werden, wo der Text nicht dieselbe Reihe bildet), ohne Doppelte. Richtungs-Steuerzeichen sind im Eintrag nur Formatierung (Copy & Paste), nicht Anzeige:
     * Sie fallen weg, der Eintrag gilt weiter. null, wenn er sich nicht falten lässt.
     *
     * @return ?list<string>
     */
    public static function eintraege(string $wort): ?array
    {
        $ohne = preg_replace('~[\x{202A}-\x{202E}\x{2066}-\x{2069}]+~u', '', $wort);
        $mit = $ohne === null ? null : self::lauf($ohne, 0);
        $ohneReihen = $ohne === null ? null : self::lauf($ohne, self::OHNE_REIHEN);
        if ($mit === null || $ohneReihen === null) {
            return null;
        }

        return $mit === $ohneReihen ? [$mit] : [$mit, $ohneReihen];
    }

    /**
     * Der Strang für Wendungen („hilft gegen“, „Durchfall verschwunden“, „100 % sicher“) in jeder Lesart: wie strang() über den ganzen Text, aber ein Satzende trennt — „Das hilft.
     * Gegen alles“ ist keine Wendung „hilft gegen“. Satzende heißt: . ! ? : ; , an das Wort geklebt (nicht nach einem Leerzeichen: „hilft , gegen“ ist gesperrte Spreizung, kein Satz)
     * und vor einem Leerzeichen oder am Ende. Ein Zeilenumbruch trennt nicht (wie in der gewöhnlichen Faltung ein Leerzeichen). Als Trenner dient NUL, das Schritt 3 aus jedem Text
     * entfernt: Er kann im Text nicht vorkommen. „%“, „‰“, „°“, „§“ und Währungszeichen (€ $) bleiben stehen (strang($x, true)), sonst träfe „100 € sicher“ die Wendung „100 % sicher“.
     * null, wenn der Text nicht prüfbar ist.
     *
     * @return ?list<string>
     */
    public static function satzstrang(string $text): ?array
    {
        $saetze = self::lesartenMit($text, self::OHNE_PUNKTE);
        if ($saetze === null) {
            return null;
        }
        foreach ($saetze as $i => $t) {
            $t = preg_replace('~(?<![ .!?:;,\x{2026}])[.!?:;,\x{2026}]+[\p{Pe}\p{Pf}\p{Pi}"\']*(?= |$)~u', "\0", $t);
            $t = $t === null ? null : self::strang($t, true, true);
            if ($t === null) {
                return null;
            }
            $saetze[$i] = $t;
        }

        return $saetze;
    }

    /**
     * Der gefaltete Text nur aus Buchstaben und Ziffern: alles dazwischen (Leerzeichen, Punkte, Striche …) ist weg. Damit findet man ein langes Wort, das jemand mit einem Trenner
     * mitten im Wort geschrieben hat („gar antiert“). $symbole: „%“, „‰“, „°“, „§“ und Währungszeichen bleiben (für Wendungen mit Inhalt wie „100 % sicher“; Zeichen wie | + = ~ nicht: Sie sind Trenner); $marke: auch NUL (der Satzende-Trenner).
     * null, wenn der Regex scheitert — wie falten().
     */
    public static function strang(string $gefaltet, bool $symbole = false, bool $marke = false): ?string
    {
        return preg_replace('~[^' . self::B . '\p{N}' . ($symbole ? '%‰°§\p{Sc}' : '') . ($marke ? '\x00' : '') . ']+~u', '', $gefaltet);
    }

    /**
     * Die Lesarten mit zusätzlichen Optionen für jeden Lauf ($zusatz: 0 oder OHNE_PUNKTE für den Satzstrang). Die erste ist der gewöhnliche Lauf, dazu ggf. die ohne Zusammenziehen der
     * Buchstabenreihen (wenn der Text eine Reihe hatte) und die zweite Lesart des Füllers/der Umlautmarken. Gleiche fallen weg; null, wenn eine nicht prüfbar ist.
     *
     * @return ?list<string>
     */
    private static function lesartenMit(string $text, int $zusatz): ?array
    {
        $gespreizt = false;
        $erste = self::lauf($text, $zusatz, $gespreizt);
        if ($erste === null) {
            return null;
        }
        $weitere = $gespreizt ? [self::OHNE_REIHEN] : [];
        // Ob ein Text mehrdeutig ist, entscheidet sich an dem Text, der verglichen wird: nach den Schritten, die Verwechslungszeichen, Unsichtbares, Vollbreit und Sterne wegräumen
        // (sonst trennt ein U+200B zwischen a und den Marken den Zalgo-Haufen im Rohtext, den die Faltung danach zu einem Umlaut zusammensetzt).
        $vor = $text;
        foreach (['schriftformen', 'verwechslungen', 'unsichtbares', 'vollbreit', 'sternchen'] as $schritt) {
            $vor = self::$schritt($vor);
            if ($vor === null) {
                return null;
            }
        }
        $mehrdeutig = preg_match(self::MEHRDEUTIG, $text);   // im Rohtext: ein Füller (Schritt „unsichtbares“ löscht ihn) …
        if ($mehrdeutig === 0) {
            $mehrdeutig = preg_match(self::MEHRDEUTIG, $vor);   // … und im vorbereiteten Text: ein Zalgo-Haufen, der erst nach dem Wegräumen von Unsichtbarem zusammensteht
        }
        if ($mehrdeutig === false) {
            return null;
        }
        if ($mehrdeutig === 1) {
            $weitere[] = self::ALT;
        }
        $liste = [$erste];
        foreach ($weitere as $lesart) {
            $t = self::lauf($text, $zusatz | $lesart);
            if ($t === null) {
                return null;
            }
            if (!in_array($t, $liste, true)) {
                $liste[] = $t;
            }
        }

        return $liste;
    }

    /** Ein Lauf durch alle Schritte. $gespreizt: ob Schritt 9 etwas zusammengezogen hat. */
    private static function lauf(string $text, int $lesart, bool &$gespreizt = false): ?string
    {
        if (strlen($text) > self::MAX_BYTES || !mb_check_encoding($text, 'UTF-8') || preg_match('~[\x{202A}-\x{202E}\x{2066}-\x{2069}]~u', $text) !== 0) {
            return null;   // die Richtungs-Steuerzeichen (LRE … RLO, LRI … PDI) kehren die Anzeige um: Die Reihenfolge, die der Leser sieht, ist nicht die im Text
        }
        $t = ($lesart & self::ALT) !== 0 ? strtr($text, ["\u{115F}" => ' ', "\u{1160}" => ' ', "\u{2800}" => ' ', "\u{3164}" => ' ', "\u{FFA0}" => ' ', "\u{0308}" => '', "\u{0364}" => '', "\u{030B}" => '']) : $text;
        foreach (self::SCHRITTE as $schritt) {
            if (($schritt === 'reihen' && ($lesart & self::OHNE_REIHEN) !== 0) || ($schritt === 'punkte' && ($lesart & self::OHNE_PUNKTE) !== 0)) {
                continue;
            }
            $neu = self::$schritt($t);
            if ($neu === null) {
                return null;
            }
            if ($schritt === 'reihen') {
                $gespreizt = $neu !== $t;
            }
            $t = $neu;
        }

        return trim($t);
    }

    // ── die Schritte ───────────────────────────────────────────────────────────────────────────────

    /** 1: Formatzeichen, nicht zugewiesene Zeichen, Variantenzeichen, Füller und Steuerzeichen (außer Leerraum) raus. */
    private static function unsichtbares(string $t): ?string
    {
        return preg_replace(self::UNSICHTBAR, '', $t);
    }

    /** 2: Vollbreit → ASCII, gleich aussehende Striche und Prozentzeichen. */
    private static function vollbreit(string $t): ?string
    {
        $t = mb_convert_kana($t, 'a');

        return strtr($t, ["\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2013}" => '-', "\u{2014}" => '-', "\u{2015}" => '-', "\u{2212}" => '-', "\u{FE58}" => '-', "\u{FE63}" => '-',
            "\u{FE6A}" => '%', "\u{066A}" => '%']);
    }

    /** 3: „*“ raus (Fettdruck und Kursiv der Seite; bis rc1083 nur „**“ — Sterne, die erst nach dem Entfernen von Unsichtbarem zusammenstehen, blieben). */
    private static function sternchen(string $t): ?string
    {
        return str_replace('*', '', $t);
    }

    /** 4: mathematische und umrahmte Buchstaben → Buchstabe, nach Rechnung statt nach Tabelle. */
    private static function schriftformen(string $t): ?string
    {
        return preg_replace_callback('~[\x{1D400}-\x{1D6A3}\x{1D6A8}-\x{1D7C9}\x{1D7CE}-\x{1D7FF}\x{249C}-\x{24E9}\x{1F130}-\x{1F189}\x{1CCD6}-\x{1CCF9}]~u', static function (array $m): string {
            $z = mb_ord($m[0]);
            if ($z >= 0x1F130) {                       // quadratisch / eingekreist schwarz / quadratisch schwarz: je 26 Buchstaben im Abstand 32, dazwischen Lücken
                $i = ($z - 0x1F130) % 32;
                return $i < 26 ? chr(97 + $i) : $m[0];
            }
            if ($z >= 0x1D7CE) {                       // Ziffern: fett, doppelt, serifenlos, serifenlos fett, Festbreite — je zehn
                return chr(48 + ($z - 0x1D7CE) % 10);
            }
            if ($z >= 0x1D6A8) {                       // Griechisch: fünf Schriften zu je 58 Zeichen — das griechische Zeichen, die Verwechslung macht daraus den lateinischen Buchstaben
                return mb_substr(self::MATHE_GRIECHISCH, ($z - 0x1D6A8) % 58, 1);
            }
            if ($z >= 0x1D400) {                       // 13 Schriften zu je 26 Groß- und 26 Kleinbuchstaben
                $i = ($z - 0x1D400) % 52;
                return chr($i < 26 ? 65 + $i : 97 + $i - 26);
            }
            if ($z >= 0x1CCD6) {                       // Outlined Latin (Unicode 16, PCRE kennt es noch nicht): 26 Buchstaben, dann 10 Ziffern
                $i = $z - 0x1CCD6;
                return $i < 26 ? chr(97 + $i) : chr(48 + $i - 26);
            }
            return chr(97 + ($z - 0x249C) % 26);       // geklammert, eingekreist groß, eingekreist klein: dreimal 26 in einer Reihe
        }, $t);
    }

    /** 5 und 13: Zeichen aus KOMPAT, VERWECHSLUNG und VORAB → Buchstabe oder Ziffer. */
    private static function verwechslungen(string $t): ?string
    {
        return strtr($t, self::$verwechslung ??= self::karte(self::VERWECHSLUNG) + self::karte(self::KOMPAT) + self::karte(self::VORAB));
    }

    /** 6 und 12: a/o/u + beliebige Marken + eine Umlautmarke (¨, kleines e, Doppelakut; oder ein nachgestelltes ¨ U+00A8 wie in PDF-Auszügen) → ä/ö/ü; dann alle übrigen kombinierenden Zeichen (Mn, Me) raus. */
    private static function marken(string $t): ?string
    {
        $t = preg_replace_callback('~([aouAOU])(?:(?![' . self::UMLAUTMARKE . '])[\p{Mn}\p{Me}])*+(?:[' . self::UMLAUTMARKE . ']|\x{00A8})~u',
            static fn (array $m): string => ['a' => 'ä', 'o' => 'ö', 'u' => 'ü', 'A' => 'Ä', 'O' => 'Ö', 'U' => 'Ü'][$m[1]], $t);

        return $t === null ? null : preg_replace('~[\p{Mn}\p{Me}]+~u', '', $t);
    }

    /** 7: Leerraum zu einem Leerzeichen, getrimmt. */
    private static function leerraum(string $t): ?string
    {
        $r = preg_replace('~[\s\p{Z}]+~u', ' ', $t);

        return $r === null ? null : trim($r);
    }

    /** 8: Leerzeichen vor und nach einem Zeichen, das weder Buchstabe noch Ziffer noch Leerraum ist, fallen weg (der Leerraum ist hier schon ein einzelnes Leerzeichen). */
    private static function punkte(string $t): ?string
    {
        return preg_replace(self::PUNKTE, '$1', $t);
    }

    /** 9: drei oder mehr einzelne Buchstaben, getrennt durch je 1–3 Zeichen ohne Buchstaben und Ziffern, werden ein Wort. Besitzergreifend: kein Zurückrechnen, linear. */
    private static function reihen(string $t): ?string
    {
        try {
            return preg_replace_callback(self::REIHE, static function (array $m): string {
                $wort = preg_replace('~[^' . self::B . ']+~u', '', $m[0]);
                if ($wort === null) {
                    throw new \RuntimeException('preg_replace');   // nie „leerer Text“ daraus machen: Der Wurf bricht preg_replace_callback ab, und reihen() gibt null
                }

                return $wort;
            }, $t);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /** 10: Kleinschreibung mit Auffalten. */
    private static function klein(string $t): ?string
    {
        return mb_convert_case($t, MB_CASE_FOLD, 'UTF-8');
    }

    /** 11: Umlaute zur Umschrift, Akzente und Striche weg. */
    private static function akzente(string $t): ?string
    {
        return strtr($t, self::$akzente ??= self::karte(self::AKZENTE));
    }

    /** @param array<int|string,string> $tabelle Ersatz => Zeichen (mehrere in einem String) @return array<string,string> Zeichen => Ersatz */
    private static function karte(array $tabelle): array
    {
        $karte = [];
        foreach ($tabelle as $ersatz => $zeichen) {
            foreach (mb_str_split($zeichen) as $z) {
                $karte[$z] = (string) $ersatz;
            }
        }

        return $karte;
    }
}
