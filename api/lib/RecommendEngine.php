<?php
declare(strict_types=1);

final class RecommendEngine
{
    private const BRANCH_MAP = [
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
    ];

    private const COLOR_HUES = [
        "green" => 120, "blue" => 210, "orange" => 30, "teal" => 175,
        "red" => 0, "violet" => 270, "forest" => 140, "gold" => 45,
        "brown" => 25, "gray" => 0, "cyan" => 185, "lime" => 90,
        "pink" => 330, "electric" => 255, "neon-pink" => 320, "navy" => 225,
        "wine" => 345, "coral" => 16, "olive" => 80, "mint" => 155,
        "slate" => 210, "lavender" => 260, "charcoal" => 0, "survival" => 35,
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

        $branch = strtolower($input["branch"] ?? "");
        if ($branch !== "" && isset(self::BRANCH_MAP[$branch])) {
            foreach (self::BRANCH_MAP[$branch] as $preset => $weight) {
                if (isset($scores[$preset])) {
                    $scores[$preset]["score"] += $weight * 0.4;
                    $scores[$preset]["reasons"][] = "Branche " . $branch . " passt gut";
                }
            }
        }

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
                foreach ($moodParams as $param => $val) {
                    $scores[$name]["overrides"][$param] = $val;
                }
            }
        }

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

        $keywords = array_map("strtolower", $input["style_keywords"] ?? []);
        if (!empty($keywords)) {
            $this->matchKeywords($keywords, $scores);
        }

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

    private function matchKeywords(array $keywords, array &$scores): void
    {
        $charakterKeywords = [
            "luxus" => "elegant", "spa" => "elegant", "premium" => "elegant", "mode" => "elegant",
            "natur" => "sanft", "gesundheit" => "sanft", "beratung" => "sanft", "bio" => "sanft",
            "outdoor" => "kantig", "sport" => "kantig", "technik" => "kantig", "handwerk" => "kantig",
            "industrie" => "markant", "architektur" => "markant", "finanzen" => "markant",
            "traditionell" => "neutral", "einladend" => "neutral", "gemuetlich" => "neutral",
        ];

        foreach ($keywords as $kw) {
            foreach ($charakterKeywords as $match => $charakter) {
                if (str_contains($kw, $match)) {
                    foreach ($this->presets as $name => $config) {
                        if (isset($config["charakter"]) && $config["charakter"] === $charakter) {
                            $scores[$name]["score"] += 0.15;
                            $scores[$name]["reasons"][] = "Keyword " . $kw . " matcht";
                        }
                    }
                    break;
                }
            }
        }
    }
}
