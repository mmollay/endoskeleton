<?php
declare(strict_types=1);

final class RecommendEngine
{
    /* ── Branche → Preset-Scores ──────────────────────────────────────────
       Jede Branche mappt auf Presets mit Gewichten (0.0-1.0).
       Zusaetzlich: empfohlene Detail-Overrides pro Branche. */
    private const BRANCH_MAP = [
        // Originale
        "gastronomie" => ["outdoor" => 0.8, "elegant" => 0.7, "softcraft" => 0.6, "corporate" => 0.4],
        "tourismus"   => ["outdoor" => 0.8, "cinematic" => 0.75, "elegant" => 0.7, "softcraft" => 0.5],
        "natur"       => ["softcraft" => 0.8, "outdoor" => 0.75, "playful" => 0.6, "elegant" => 0.5],
        "handwerk"    => ["outdoor" => 0.8, "survival" => 0.7, "corporate" => 0.6, "softcraft" => 0.4],
        "gesundheit"  => ["softcraft" => 0.85, "elegant" => 0.75, "minimal" => 0.6, "playful" => 0.5],
        "kreativ"     => ["creative" => 0.85, "playful" => 0.7, "startup" => 0.65, "editorial" => 0.5],
        "corporate"   => ["corporate" => 0.9, "minimal" => 0.7, "tech" => 0.6, "elegant" => 0.4],
        "tech"        => ["tech" => 0.9, "startup" => 0.8, "minimal" => 0.6, "creative" => 0.4],
        "bildung"     => ["playful" => 0.75, "softcraft" => 0.7, "corporate" => 0.6, "minimal" => 0.5],
        "sport"       => ["survival" => 0.85, "tech" => 0.7, "creative" => 0.6, "outdoor" => 0.55],
        "mode"        => ["elegant" => 0.9, "minimal" => 0.75, "creative" => 0.6, "editorial" => 0.5],
        "recht"       => ["corporate" => 0.9, "minimal" => 0.75, "elegant" => 0.6],
        "immobilien"  => ["corporate" => 0.8, "elegant" => 0.75, "minimal" => 0.65, "cinematic" => 0.5],
        // Neu hinzugefuegt
        "tierdienstleistung" => ["softcraft" => 0.85, "playful" => 0.7, "outdoor" => 0.65, "elegant" => 0.4],
        "tier"        => ["softcraft" => 0.85, "playful" => 0.7, "outdoor" => 0.65, "elegant" => 0.4],
        "veterinär"   => ["softcraft" => 0.8, "minimal" => 0.7, "corporate" => 0.6],
        "soziales"    => ["softcraft" => 0.85, "playful" => 0.7, "elegant" => 0.5, "corporate" => 0.4],
        "verein"      => ["softcraft" => 0.8, "playful" => 0.75, "outdoor" => 0.5, "corporate" => 0.4],
        "fotografie"  => ["cinematic" => 0.9, "elegant" => 0.8, "minimal" => 0.7, "creative" => 0.5],
        "medien"      => ["editorial" => 0.9, "creative" => 0.75, "tech" => 0.6, "startup" => 0.5],
        "beratung"    => ["corporate" => 0.85, "minimal" => 0.7, "elegant" => 0.65, "softcraft" => 0.4],
        "finanzen"    => ["corporate" => 0.9, "minimal" => 0.75, "elegant" => 0.6],
        "versicherung"=> ["corporate" => 0.85, "minimal" => 0.7, "tech" => 0.5],
        "agentur"     => ["creative" => 0.85, "startup" => 0.8, "tech" => 0.6, "minimal" => 0.5],
        "events"      => ["cinematic" => 0.85, "creative" => 0.75, "elegant" => 0.7, "playful" => 0.5],
        "hochzeit"    => ["elegant" => 0.95, "softcraft" => 0.7, "cinematic" => 0.65],
        "beauty"      => ["elegant" => 0.9, "minimal" => 0.75, "softcraft" => 0.6],
        "wellness"    => ["softcraft" => 0.9, "elegant" => 0.8, "minimal" => 0.5],
        "restaurant"  => ["outdoor" => 0.8, "elegant" => 0.75, "softcraft" => 0.6, "cinematic" => 0.4],
        "hotel"       => ["elegant" => 0.85, "cinematic" => 0.8, "outdoor" => 0.6, "softcraft" => 0.5],
        "arzt"        => ["minimal" => 0.85, "softcraft" => 0.75, "corporate" => 0.6],
        "therapie"    => ["softcraft" => 0.9, "playful" => 0.65, "minimal" => 0.6],
        "yoga"        => ["softcraft" => 0.9, "elegant" => 0.7, "minimal" => 0.6],
        "musik"       => ["creative" => 0.85, "cinematic" => 0.8, "editorial" => 0.6],
        "kunst"       => ["creative" => 0.9, "editorial" => 0.75, "minimal" => 0.7, "cinematic" => 0.5],
        "bau"         => ["survival" => 0.8, "outdoor" => 0.75, "corporate" => 0.6],
        "landwirtschaft" => ["outdoor" => 0.85, "softcraft" => 0.75, "survival" => 0.6],
        "energie"     => ["tech" => 0.85, "corporate" => 0.7, "startup" => 0.6, "minimal" => 0.5],
        "logistik"    => ["corporate" => 0.8, "tech" => 0.7, "minimal" => 0.6],
        "automotive"  => ["tech" => 0.85, "cinematic" => 0.75, "corporate" => 0.6, "survival" => 0.5],
        "lebensmittel"=> ["softcraft" => 0.8, "outdoor" => 0.7, "playful" => 0.6, "elegant" => 0.5],
        "wein"        => ["elegant" => 0.9, "editorial" => 0.7, "softcraft" => 0.6, "cinematic" => 0.5],
        "dienstleistung" => ["corporate" => 0.75, "softcraft" => 0.7, "minimal" => 0.65],
    ];

    /* ── Detail-Overrides pro Branche ─────────────────────────────────── */
    private const BRANCH_OVERRIDES = [
        "tierdienstleistung" => ["buttons" => "soft-round",  "font" => "rounded-friendly", "charakter" => "sanft",  "hero" => "split",  "spacing" => "spacious"],
        "tier"        => ["buttons" => "soft-round",  "font" => "rounded-friendly", "charakter" => "sanft",  "hero" => "split",  "spacing" => "spacious"],
        "gastronomie" => ["buttons" => "soft",         "font" => "classic-serif",     "charakter" => "neutral", "hero" => "veil",   "spacing" => "spacious"],
        "hotel"       => ["buttons" => "outline-elegant", "font" => "luxury-thin",    "charakter" => "elegant", "hero" => "fullscreen", "spacing" => "spacious"],
        "hochzeit"    => ["buttons" => "outline-elegant", "font" => "luxury-thin",    "charakter" => "elegant", "hero" => "fullscreen", "spacing" => "spacious"],
        "beauty"      => ["buttons" => "pill-thin",    "font" => "elegant",           "charakter" => "elegant", "hero" => "split",  "spacing" => "normal"],
        "wellness"    => ["buttons" => "soft-round",  "font" => "rounded-friendly",  "charakter" => "sanft",  "hero" => "veil",   "spacing" => "spacious"],
        "sport"       => ["buttons" => "rect-solid",  "font" => "brand-bold",         "charakter" => "kantig", "hero" => "fullscreen", "spacing" => "compact"],
        "tech"        => ["buttons" => "tech",         "font" => "mono",               "charakter" => "markant", "hero" => "minimal", "spacing" => "compact"],
        "kreativ"     => ["buttons" => "gradient-cta", "font" => "playful",           "charakter" => "markant", "hero" => "split",  "spacing" => "normal"],
        "agentur"     => ["buttons" => "cta-arrow",   "font" => "modern-sans",        "charakter" => "markant", "hero" => "split",  "spacing" => "normal"],
        "recht"       => ["buttons" => "rect-outline", "font" => "swiss-precision",   "charakter" => "markant", "hero" => "minimal", "spacing" => "normal"],
        "finanzen"    => ["buttons" => "rect-outline", "font" => "swiss-precision",   "charakter" => "markant", "hero" => "minimal", "spacing" => "normal"],
        "corporate"   => ["buttons" => "rect-outline", "font" => "swiss-precision",   "charakter" => "neutral", "hero" => "split",  "spacing" => "normal"],
        "fotografie"  => ["buttons" => "ghost",        "font" => "modern",             "charakter" => "elegant", "hero" => "fullscreen", "spacing" => "spacious"],
        "musik"       => ["buttons" => "brutalist",    "font" => "display-serif",     "charakter" => "markant", "hero" => "fullscreen", "spacing" => "spacious"],
        "arzt"        => ["buttons" => "soft",         "font" => "humanist",           "charakter" => "sanft",  "hero" => "split",  "spacing" => "normal"],
        "therapie"    => ["buttons" => "soft-round",  "font" => "rounded-friendly",  "charakter" => "sanft",  "hero" => "split",  "spacing" => "spacious"],
        "bildung"     => ["buttons" => "rounded",      "font" => "rounded",            "charakter" => "neutral", "hero" => "banner", "spacing" => "normal"],
        "medien"      => ["buttons" => "cta-arrow",   "font" => "editorial",          "charakter" => "neutral", "hero" => "banner", "spacing" => "normal"],
    ];

    private const MOOD_MAP = [
        "warm"      => ["charakter" => "neutral",  "theme" => "warm",   "animation" => "subtle"],
        "modern"    => ["charakter" => "markant",   "theme" => "light",  "animation" => "subtle"],
        "dunkel"    => ["charakter" => "kantig",    "theme" => "dark",   "animation" => "dynamic"],
        "edel"      => ["charakter" => "elegant",   "theme" => "dark",   "animation" => "subtle"],
        "lebendig"  => ["charakter" => "markant",   "theme" => "pastel", "animation" => "dynamic"],
        "klassisch" => ["charakter" => "neutral",   "theme" => "warm",   "animation" => "none"],
        "serioes"   => ["charakter" => "markant",   "theme" => "light",  "animation" => "none"],
        "natuerlich"=> ["charakter" => "sanft",     "theme" => "warm",   "animation" => "subtle"],
        "minimalistisch" => ["charakter" => "elegant", "theme" => "light", "animation" => "none"],
        // Neu
        "freundlich"=> ["charakter" => "sanft",     "theme" => "warm",   "animation" => "subtle"],
        "professionell" => ["charakter" => "neutral", "theme" => "light", "animation" => "subtle"],
        "verspielt" => ["charakter" => "sanft",     "theme" => "pastel", "animation" => "dynamic"],
        "kraftvoll" => ["charakter" => "kantig",    "theme" => "dark",   "animation" => "dynamic"],
        "ruhig"     => ["charakter" => "sanft",     "theme" => "warm",   "animation" => "none"],
    ];

    private const COLOR_HUES = [
        "green" => 120, "blue" => 210, "orange" => 30, "teal" => 175,
        "red" => 0, "violet" => 270, "forest" => 140, "gold" => 45,
        "brown" => 25, "gray" => 0, "cyan" => 185, "lime" => 90,
        "pink" => 330, "electric" => 255, "neon-pink" => 320, "navy" => 225,
        "wine" => 345, "coral" => 16, "olive" => 80, "mint" => 155,
        "slate" => 210, "lavender" => 260, "charcoal" => 0, "survival" => 35,
    ];

    /* ── Keyword → Charakter-Mapping (erweitert) ───────────────────── */
    private const KEYWORD_MAP = [
        "luxus" => "elegant", "spa" => "elegant", "premium" => "elegant", "mode" => "elegant",
        "exklusiv" => "elegant", "hochwertig" => "elegant", "edel" => "elegant",
        "natur" => "sanft", "gesundheit" => "sanft", "beratung" => "sanft", "bio" => "sanft",
        "pflege" => "sanft", "hund" => "sanft", "tier" => "sanft", "kinder" => "sanft",
        "sozial" => "sanft", "hilfe" => "sanft", "therapie" => "sanft",
        "outdoor" => "kantig", "sport" => "kantig", "technik" => "kantig", "handwerk" => "kantig",
        "abenteuer" => "kantig", "kraft" => "kantig", "survival" => "kantig",
        "industrie" => "markant", "architektur" => "markant", "finanzen" => "markant",
        "business" => "markant", "startup" => "markant", "innovation" => "markant",
        "traditionell" => "neutral", "einladend" => "neutral", "gemuetlich" => "neutral",
        "familiär" => "neutral", "regional" => "neutral", "lokal" => "neutral",
    ];

    private array $presets;

    public function __construct(array $presets)
    {
        $this->presets = $presets;
    }

    public function recommend(array $input): array
    {
        $scores = [];
        foreach ($this->presets as $name => $config) {
            $scores[$name] = ["score" => 0.0, "reasons" => [], "overrides" => []];
        }

        // ── 1. Branche (40%) ──────────────────────────────────────────────
        $branch = strtolower($input["branch"] ?? "");
        $branchMatched = false;

        // Exakter Match
        if ($branch !== "" && isset(self::BRANCH_MAP[$branch])) {
            $branchMatched = true;
            foreach (self::BRANCH_MAP[$branch] as $preset => $weight) {
                if (isset($scores[$preset])) {
                    $scores[$preset]["score"] += $weight * 0.4;
                    $scores[$preset]["reasons"][] = "Branche " . $branch . " passt";
                }
            }
        }

        // Fuzzy-Match: Branche als Substring in bekannten Branchen suchen
        if (!$branchMatched && $branch !== "") {
            foreach (self::BRANCH_MAP as $known => $presetMap) {
                if (str_contains($branch, $known) || str_contains($known, $branch)) {
                    $branchMatched = true;
                    foreach ($presetMap as $preset => $weight) {
                        if (isset($scores[$preset])) {
                            $scores[$preset]["score"] += $weight * 0.35;
                            $scores[$preset]["reasons"][] = "Branche aehnlich zu " . $known;
                        }
                    }
                    break;
                }
            }
        }

        // Detail-Overrides aus Branche anwenden
        $branchOverrides = [];
        if ($branch !== "" && isset(self::BRANCH_OVERRIDES[$branch])) {
            $branchOverrides = self::BRANCH_OVERRIDES[$branch];
        } elseif ($branchMatched) {
            // Fuzzy: naechste passende Overrides suchen
            foreach (self::BRANCH_OVERRIDES as $known => $ov) {
                if (str_contains($branch, $known) || str_contains($known, $branch)) {
                    $branchOverrides = $ov;
                    break;
                }
            }
        }

        if (!empty($branchOverrides)) {
            foreach ($scores as &$s) {
                $s["overrides"] = array_merge($s["overrides"], $branchOverrides);
            }
            unset($s);
        }

        // ── 2. Stimmung (25%) ─────────────────────────────────────────────
        $mood = strtolower($input["mood"] ?? "");
        if ($mood !== "" && isset(self::MOOD_MAP[$mood])) {
            $moodParams = self::MOOD_MAP[$mood];
            foreach ($this->presets as $name => $config) {
                $match = 0;
                $total = count($moodParams);
                foreach ($moodParams as $param => $val) {
                    if (isset($config[$param]) && $config[$param] === $val) {
                        $match++;
                    }
                }
                $moodScore = $total > 0 ? ($match / $total) : 0;
                $scores[$name]["score"] += $moodScore * 0.25;
                if ($moodScore > 0.5) {
                    $scores[$name]["reasons"][] = "Stimmung " . $mood . " passt";
                }
            }
            // Mood-Overrides nur wenn keine Branche-Overrides vorhanden
            if (empty($branchOverrides)) {
                foreach ($scores as &$s) {
                    $s["overrides"] = array_merge($s["overrides"], $moodParams);
                }
                unset($s);
            }
        }

        // ── 3. Farben (20%) ───────────────────────────────────────────────
        $inputColors = $input["colors"] ?? [];
        if (!empty($inputColors)) {
            $bestColor = $this->matchColor($inputColors);
            if ($bestColor !== null) {
                foreach ($this->presets as $name => $config) {
                    if (isset($config["color"]) && $config["color"] === $bestColor) {
                        $scores[$name]["score"] += 0.2;
                        $scores[$name]["reasons"][] = "Farbpalette " . $bestColor . " matcht";
                    }
                }
                foreach ($scores as &$s) {
                    $s["overrides"]["color"] = $bestColor;
                }
                unset($s);
            }
        }

        // ── 4. Keywords (15%) ─────────────────────────────────────────────
        $keywords = array_map("strtolower", $input["style_keywords"] ?? []);
        if (!empty($keywords)) {
            $this->matchKeywords($keywords, $scores);
        }

        // ── 5. Zielgruppe als Keywords verwenden ──────────────────────────
        $audience = strtolower($input["target_audience"] ?? "");
        if ($audience !== "") {
            $audienceWords = array_filter(explode(" ", preg_replace("/[^a-zäöüß ]/", "", $audience)));
            if (!empty($audienceWords)) {
                $this->matchKeywords($audienceWords, $scores, 0.1);
            }
        }

        // ── Sortieren + Top 3 ─────────────────────────────────────────────
        uasort($scores, fn($a, $b) => $b["score"] <=> $a["score"]);

        $top = array_slice($scores, 0, 3, true);
        $maxScore = max(array_column($top, "score")) ?: 1;

        $results = [];
        foreach ($top as $name => $data) {
            $overrides = [];
            foreach ($data["overrides"] as $k => $v) {
                if (!isset($this->presets[$name][$k]) || $this->presets[$name][$k] !== $v) {
                    $overrides[$k] = $v;
                }
            }
            $confidence = round($data["score"] / $maxScore, 2);
            $confidence = min(0.95, max(0.1, $confidence));

            $hashParts = ["preset=" . $name];
            foreach ($overrides as $k => $v) {
                $hashParts[] = $k . "=" . $v;
            }

            $results[] = [
                "preset"      => $name,
                "overrides"   => $overrides,
                "confidence"  => $confidence,
                "reasoning"   => implode(". ", array_unique($data["reasons"])) ?: "Standard-Empfehlung",
                "preview_url" => "https://skeleton.ssi.at/demo.html#" . implode("&", $hashParts),
            ];
        }

        return $results;
    }

    private function matchColor(array $hexColors): ?string
    {
        $inputHue = $this->averageHue($hexColors);
        if ($inputHue === null) return null;

        $bestColor = null;
        $bestDist = PHP_FLOAT_MAX;

        foreach (self::COLOR_HUES as $name => $hue) {
            $dist = min(abs($inputHue - $hue), 360 - abs($inputHue - $hue));
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $bestColor = $name;
            }
        }

        return $bestColor;
    }

    private function averageHue(array $hexColors): ?float
    {
        $hues = [];
        foreach ($hexColors as $hex) {
            $hex = ltrim($hex, "#");
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if (strlen($hex) !== 6) continue;

            $r = hexdec(substr($hex, 0, 2)) / 255;
            $g = hexdec(substr($hex, 2, 2)) / 255;
            $b = hexdec(substr($hex, 4, 2)) / 255;

            $max = max($r, $g, $b);
            $min = min($r, $g, $b);
            $delta = $max - $min;

            if ($delta < 0.01) continue;

            if ($max === $r) $h = 60 * fmod(($g - $b) / $delta, 6);
            elseif ($max === $g) $h = 60 * (($b - $r) / $delta + 2);
            else $h = 60 * (($r - $g) / $delta + 4);

            if ($h < 0) $h += 360;
            $hues[] = $h;
        }

        if (empty($hues)) return null;
        return array_sum($hues) / count($hues);
    }

    private function matchKeywords(array $keywords, array &$scores, float $weight = 0.15): void
    {
        foreach ($keywords as $kw) {
            foreach (self::KEYWORD_MAP as $match => $charakter) {
                if (str_contains($kw, $match)) {
                    foreach ($this->presets as $name => $config) {
                        if (isset($config["charakter"]) && $config["charakter"] === $charakter) {
                            $scores[$name]["score"] += $weight;
                            $scores[$name]["reasons"][] = "Keyword " . $kw;
                        }
                    }
                    break;
                }
            }
        }
    }
}
