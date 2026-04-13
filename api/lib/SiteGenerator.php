<?php
declare(strict_types=1);

final class SiteGenerator
{
    private string $root;
    private string $outputDir;

    public function __construct()
    {
        $this->root = dirname(__DIR__, 2);
        $this->outputDir = __DIR__ . "/../data/generated";
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    public function generate(array $preset, array $content): array
    {
        $hash = substr(md5(json_encode([$preset, $content]) . microtime()), 0, 12);
        $dir = $this->outputDir . "/" . $hash;
        mkdir($dir, 0755, true);

        copy($this->root . "/base.css", $dir . "/base.css");
        $this->copyModuleCss($preset, $dir);
        $this->generateThemeCss($preset, $content, $dir);
        copy($this->root . "/shared.js", $dir . "/shared.js");

        $files = ["base.css", "theme.css", "shared.js"];
        $pages = $content["pages"] ?? [["slug" => "index", "title" => "Home", "sections" => []]];

        // Deduplicate pages by slug — first occurrence wins (index page with hero/about/services
        // must not be overwritten by a sub-page that also normalizes to slug "index")
        $seenSlugs = [];
        foreach ($pages as $page) {
            $slug = preg_replace("/[^a-z0-9-]/", "", strtolower($page["slug"] ?? "index"));
            if (isset($seenSlugs[$slug])) continue;
            $seenSlugs[$slug] = true;
            $filename = $slug . ".html";
            $html = $this->buildPage($preset, $content, $page);
            file_put_contents($dir . "/" . $filename, $html);
            $files[] = $filename;
        }

        foreach (glob($dir . "/*.css") as $f) {
            $name = basename($f);
            if (!in_array($name, $files, true)) {
                $files[] = $name;
            }
        }

        $htaccess = "<IfModule mod_headers.c>\n  Header set Cache-Control \"no-cache\"\n</IfModule>";
        file_put_contents($dir . "/.htaccess", $htaccess);

        $this->createZip($dir, $hash);

        return [
            "hash"         => $hash,
            "files"        => $files,
            "preview_url"  => "https://skeleton.ssi.at/api/data/generated/" . $hash . "/index.html",
            "download_url" => "https://skeleton.ssi.at/api/data/generated/" . $hash . "/site.zip",
            "expires_in"   => 3600,
        ];
    }

    private function copyModuleCss(array $preset, string $dir): void
    {
        $modules = [
            "color" => "colors", "layout" => "layouts", "hero" => "heroes",
            "buttons" => "buttons", "charakter" => "softness", "spacing" => "spacing",
            "animation" => "animation", "width" => "widths", "navLayout" => "navlayouts",
            "font" => "fonts",
        ];

        foreach (array_unique(array_values($modules)) as $subdir) {
            if (!is_dir($dir . "/" . $subdir)) {
                mkdir($dir . "/" . $subdir, 0755, true);
            }
        }

        foreach ($modules as $param => $subdir) {
            $value = $preset[$param] ?? null;
            if ($value === null) continue;
            $src = $this->root . "/" . $subdir . "/" . $value . ".css";
            if (file_exists($src)) {
                copy($src, $dir . "/" . $subdir . "/" . $value . ".css");
            }
        }
    }

    private function adjustBrightness(string $hex, int $amount): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + $amount));
        $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + $amount));
        $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + $amount));
        return sprintf('#%02x%02x%02x', (int)$r, (int)$g, (int)$b);
    }

    private function generateThemeCss(array $preset, array $content, string $dir): void
    {
        $theme = $preset["theme"] ?? "light";
        $colors = $content["colors"] ?? [];
        $primary = $colors["primary"] ?? "#3b82f6";
        $accent = $colors["accent"] ?? $primary;
        $bg = $colors["bg"] ?? ($theme === "dark" ? "#0f172a" : "#ffffff");

        $primaryLight = $this->adjustBrightness($primary, 30);
        $primaryDark = $this->adjustBrightness($primary, -30);

        $css = "/* Endoskeleton Generated Theme — from Scanner data */\n";
        $css .= ":root {\n";
        $css .= "  --theme: {$theme};\n";
        $css .= "  --color-primary: {$primary};\n";
        $css .= "  --color-primary-light: {$primaryLight};\n";
        $css .= "  --color-primary-dark: {$primaryDark};\n";
        $css .= "  --color-accent: {$accent};\n";

        if (in_array($theme, ["light", "warm", "pastel"], true)) {
            $css .= "  --color-bg: {$bg};\n";
            $css .= "  --color-text: #1e293b;\n";
            $css .= "  --color-text-muted: #64748b;\n";
            $css .= "  --color-surface: #f8fafc;\n";
            $css .= "  --color-border: #e2e8f0;\n";
        } else {
            $css .= "  --color-bg: {$bg};\n";
            $css .= "  --color-text: #e2e8f0;\n";
            $css .= "  --color-text-muted: #94a3b8;\n";
            $css .= "  --color-surface: #1e293b;\n";
            $css .= "  --color-border: #334155;\n";
        }
        $css .= "}\n\n";

        if (in_array($theme, ["light", "warm", "pastel"], true)) {
            $css .= "[data-theme=\"dark\"] {\n";
            $css .= "  --color-bg: #0f172a;\n";
            $css .= "  --color-text: #e2e8f0;\n";
            $css .= "  --color-text-muted: #94a3b8;\n";
            $css .= "  --color-surface: #1e293b;\n";
            $css .= "  --color-border: #334155;\n";
            $css .= "}\n";
        } else {
            $css .= "[data-theme=\"light\"] {\n";
            $css .= "  --color-bg: #ffffff;\n";
            $css .= "  --color-text: #1e293b;\n";
            $css .= "  --color-text-muted: #64748b;\n";
            $css .= "  --color-surface: #f8fafc;\n";
            $css .= "  --color-border: #e2e8f0;\n";
            $css .= "}\n";
        }

        file_put_contents($dir . "/theme.css", $css);
    }

    private function buildSiteConfigScript(array $preset, array $content): string
    {
        $company = addslashes($content["company"] ?? "Website");
        $logo = addslashes($content["logo_url"] ?? "img/logo.png");

        $skipNav = ["impressum", "datenschutz", "disclaimer", "kontakt"];
        $navPages = [];
        foreach ($content["pages"] ?? [] as $p) {
            $slug = addslashes($p["slug"] ?? "index");
            if (in_array($slug, $skipNav, true)) continue;
            $title = addslashes($p["title"] ?? "Seite");
            $navPages[] = ["slug" => $slug, "title" => $title];
        }
        $navItems = [];
        if (count($navPages) <= 5) {
            foreach ($navPages as $p) {
                $navItems[] = "      {label: \"{$p['title']}\", href: \"{$p['slug']}.html\"}";
            }
        } else {
            $main = array_slice($navPages, 0, 4);
            $more = array_slice($navPages, 4);
            foreach ($main as $p) {
                $navItems[] = "      {label: \"{$p['title']}\", href: \"{$p['slug']}.html\"}";
            }
            $ch = [];
            foreach ($more as $p) {
                $ch[] = "{label: \"{$p['title']}\", href: \"{$p['slug']}.html\"}";
            }
            $navItems[] = "      {label: \"Mehr\", href: \"#\", children: [" . implode(", ", $ch) . "]}";
        }
        // Kontakt always last
        $hasKontakt = false;
        foreach ($content["pages"] ?? [] as $p) {
            if (($p["slug"] ?? "") === "kontakt") { $hasKontakt = true; break; }
        }
        if ($hasKontakt) {
            $navItems[] = "      {label: \"Kontakt\", href: \"kontakt.html\"}";
        }
        $navJs = implode(",\n", $navItems);

        $contact = $content["contact"] ?? [];
        $email = addslashes($contact["email"] ?? "");
        $phone = addslashes($contact["phone"] ?? "");
        $address = addslashes($contact["address"] ?? "");
        $contactName = addslashes($contact["name"] ?? $content["company"] ?? "");

        $social = $content["social_media"] ?? [];
        $socialItems = [];
        foreach ($social as $name => $url) {
            $n = addslashes($name);
            $u = addslashes($url);
            $socialItems[] = "{$n}: \"{$u}\"";
        }
        $socialJs = implode(", ", $socialItems);

        $footer = $content["footer"] ?? [];
        $tagline = addslashes($footer["tagline"] ?? "");

        return <<<SCRIPT
<script>
  window.SITE_CONFIG = {
    name: "{$company}",
    logo: "{$logo}",
    nav: [
{$navJs}
    ],
    contact: {
      name: "{$contactName}",
      email: "{$email}",
      phone: "{$phone}",
      address: "{$address}"
    },
    social: {{$socialJs}},
    footer: {
      tagline: "{$tagline}",
      credit: true
    }
  };
</script>
SCRIPT;
    }

    /**
     * Render repeating blocks like {{#SERVICES}}...{{/SERVICES}}
     */
    private function renderRepeatingBlocks(string $html, array $sections, array $page): string
    {
        $defaultIcon = "";

        // Services repeating block
        if (preg_match('/\{\{#SERVICES\}\}(.*?)\{\{\/SERVICES\}\}/s', $html, $m)) {
            $tpl = $m[1];
            $rendered = "";
            foreach ($sections as $sec) {
                if (($sec["type"] ?? "") === "services" && !empty($sec["items"])) {
                    foreach ($sec["items"] as $item) {
                        $block = $tpl;
                        $block = str_replace("{{SERVICE_TITLE}}", $item["title"] ?? "", $block);
                        $block = str_replace("{{SERVICE_TEXT}}", $item["text"] ?? "", $block);
                        $block = str_replace("{{SERVICE_ICON_PATH}}", $item["icon_path"] ?? $defaultIcon, $block);
                        // Remove empty card-icon div
                        if (empty($item["icon_path"] ?? "")) {
                            $block = preg_replace('#<div class="card-icon">.*?</div>#s', '', $block);
                        }
                        $rendered .= $block;
                    }
                }
            }
            $html = str_replace($m[0], $rendered, $html);
        }

        // Stats repeating block
        if (preg_match('/\{\{#STATS\}\}(.*?)\{\{\/STATS\}\}/s', $html, $m)) {
            $tpl = $m[1];
            $rendered = "";
            foreach ($sections as $sec) {
                if (($sec["type"] ?? "") === "stats" && !empty($sec["items"])) {
                    foreach ($sec["items"] as $item) {
                        $block = $tpl;
                        $block = str_replace("{{STAT_VALUE}}", $item["value"] ?? "", $block);
                        $block = str_replace("{{STAT_LABEL}}", $item["label"] ?? "", $block);
                        $rendered .= $block;
                    }
                }
            }
            $html = str_replace($m[0], $rendered, $html);
        }

        // Features repeating block (subpage template, page-level features)
        if (preg_match('/\{\{#FEATURES\}\}(.*?)\{\{\/FEATURES\}\}/s', $html, $m)) {
            $tpl = $m[1];
            $rendered = "";
            $features = $page["features"] ?? [];
            if (!empty($features)) {
                foreach ($features as $item) {
                    $block = $tpl;
                    $block = str_replace("{{FEATURE_TITLE}}", $item["title"] ?? "", $block);
                    $block = str_replace("{{FEATURE_TEXT}}", $item["text"] ?? "", $block);
                    $block = str_replace("{{FEATURE_ICON_PATH}}", $item["icon_path"] ?? $defaultIcon, $block);
                    if (empty($item["icon_path"] ?? "")) {
                        $block = preg_replace('#<div class="card-icon">.*?</div>#s', '', $block);
                    }
                    $rendered .= $block;
                }
            }
            $html = str_replace($m[0], $rendered, $html);
        }

        return $html;
    }

    private function buildPage(array $preset, array $content, array $page): string
    {
        $slug = preg_replace("/[^a-z0-9-]/", "", strtolower($page["slug"] ?? "index"));

        // Template routing
        $templateMap = [
            "index"       => "pages/index.html",
            "kontakt"     => "pages/kontakt.html",
            "impressum"   => "pages/impressum.html",
            "datenschutz" => "pages/datenschutz.html",
        ];
        $templateFile = $templateMap[$slug] ?? "pages/subpage.html";
        $template = file_get_contents($this->root . "/" . $templateFile);

        $replacements = [
            "{{SITE_NAME}}"        => $content["company"] ?? "Website",
            "{{SITE_URL}}"         => "",
            "{{PAGE_TITLE}}"       => $page["title"] ?? "Home",
            "{{PAGE_DESCRIPTION}}" => $content["description"] ?? "",
            "{{PAGE_FILE}}"        => $slug . ".html",
            "{{LAYOUT}}"          => $preset["layout"] ?? "modern",
            "{{HERO}}"            => $preset["hero"] ?? "fullscreen",
            "{{HERO_TYPE}}"       => $preset["hero"] ?? "fullscreen",
            "{{BUTTONS}}"         => $preset["buttons"] ?? "rounded",
            "{{CHARAKTER}}"       => $preset["charakter"] ?? "neutral",
            "{{SPACING}}"         => $preset["spacing"] ?? "normal",
            "{{ANIMATION}}"       => $preset["animation"] ?? "subtle",
            "{{WIDTH}}"           => $preset["width"] ?? "default",
            "{{FONT}}"            => "Inter:wght@300;400;500;600;700",
            "{{FONT_MODULE}}"     => $preset["font"] ?? "modern",
            "{{COLOR_MODULE}}"    => $preset["color"] ?? "blue",
            "{{MODULES}}"         => "",
            "{{MODULES_LIST}}"    => "",
            "{{CURRENT_DATE}}"    => date("d.m.Y"),
        ];

        $sections = $page["sections"] ?? [];
        foreach ($sections as $section) {
            $type = $section["type"] ?? "";

            if ($type === "hero") {
                $replacements["{{HERO_EYEBROW}}"]  = $section["eyebrow"] ?? "";
                $replacements["{{HERO_TITLE}}"]    = $section["headline"] ?? $page["title"] ?? "";
                $replacements["{{HERO_SUBTITLE}}"] = $section["subline"] ?? "";
                $replacements["{{HERO_IMAGE}}"]    = $section["image"] ?? "";
                $replacements["{{CTA_PRIMARY_TEXT}}"]  = $section["cta"] ?? "";
                $replacements["{{CTA_PRIMARY_HREF}}"]  = $section["cta_href"] ?? "#kontakt";
                $replacements["{{CTA_SECONDARY_TEXT}}"] = $section["cta2"] ?? "";
                $replacements["{{CTA_SECONDARY_HREF}}"] = $section["cta2_href"] ?? "#";
            }

            if ($type === "about") {
                $replacements["{{ABOUT_TAG}}"]       = $section["tag"] ?? "";
                $replacements["{{ABOUT_TITLE}}"]     = $section["title"] ?? "";
                $replacements["{{ABOUT_TEXT}}"]       = $section["text"] ?? "";
                $replacements["{{ABOUT_CTA}}"]       = $section["cta"] ?? "";
                $replacements["{{ABOUT_LINK}}"]      = $section["cta_href"] ?? "#";
                $replacements["{{ABOUT_IMAGE}}"]     = $section["image"] ?? "";
                $replacements["{{ABOUT_IMAGE_ALT}}"] = $section["image_alt"] ?? "";
            }

            if ($type === "services") {
                $replacements["{{SERVICES_TAG}}"]   = $section["tag"] ?? "";
                $replacements["{{SERVICES_TITLE}}"] = $section["title"] ?? "";
                $replacements["{{SERVICES_LEAD}}"]  = $section["lead"] ?? "";
            }

            if ($type === "cta") {
                $replacements["{{CTA_SECTION_TITLE}}"]  = $section["title"] ?? "";
                $replacements["{{CTA_SECTION_TEXT}}"]    = $section["text"] ?? "";
                $replacements["{{CTA_SECTION_BUTTON}}"]  = $section["cta"] ?? "";
                $replacements["{{CTA_SECTION_HREF}}"]    = $section["cta_href"] ?? "#";
            }

            if ($type === "testimonial") {
                $replacements["{{TESTIMONIAL_TAG}}"]    = $section["tag"] ?? "";
                $replacements["{{TESTIMONIAL_TITLE}}"]  = $section["title"] ?? "";
                $replacements["{{TESTIMONIAL_QUOTE}}"]  = $section["quote"] ?? "";
                $replacements["{{TESTIMONIAL_AUTHOR}}"] = $section["author"] ?? "";
            }
        }

        // Contact replacements
        $contact = $content["contact"] ?? [];
        $replacements["{{CONTACT_NAME}}"]    = $contact["name"] ?? $content["company"] ?? "";
        $replacements["{{CONTACT_EMAIL}}"]   = $contact["email"] ?? "";
        $replacements["{{CONTACT_PHONE}}"]   = $contact["phone"] ?? "";
        $replacements["{{CONTACT_ADDRESS}}"] = $contact["address"] ?? "";
        $replacements["{{CONTACT_PHONE_RAW}}"] = preg_replace("/[^0-9+]/", "", $contact["phone"] ?? "");
        $replacements["{{FOOTER_TAGLINE}}"] = $content["footer"]["tagline"] ?? "";
        $replacements["{{SOCIAL_LINKS}}"]   = "";

        // Nav items (grouped when > 6 pages)
        $skipNav = ["impressum", "datenschutz", "disclaimer", "kontakt"];
        $navPages = [];
        foreach ($content["pages"] ?? [] as $p) {
            $s = $p["slug"] ?? "index";
            if (in_array($s, $skipNav, true)) continue;
            $navPages[] = ["slug" => $s, "title" => $p["title"] ?? "Seite"];
        }
        $navItems = [];
        if (count($navPages) <= 5) {
            foreach ($navPages as $p) {
                $navItems[] = '{ label: "' . addslashes($p["title"]) . '", href: "' . $p["slug"] . '.html" }';
            }
        } else {
            $mainPages = array_slice($navPages, 0, 4);
            $morePages = array_slice($navPages, 4);
            foreach ($mainPages as $p) {
                $navItems[] = '{ label: "' . addslashes($p["title"]) . '", href: "' . $p["slug"] . '.html" }';
            }
            $children = [];
            foreach ($morePages as $p) {
                $children[] = '{ label: "' . addslashes($p["title"]) . '", href: "' . $p["slug"] . '.html" }';
            }
            $navItems[] = '{ label: "Mehr", href: "#", children: [' . implode(", ", $children) . '] }';
        }
        // Kontakt always as last visible nav item
        $hasKontakt = false;
        foreach ($content["pages"] ?? [] as $p) {
            if (($p["slug"] ?? "") === "kontakt") { $hasKontakt = true; break; }
        }
        if ($hasKontakt) {
            $navItems[] = '{ label: "Kontakt", href: "kontakt.html" }';
        }
        $replacements["{{NAV_ITEMS}}"] = implode(",\n        ", $navItems);

        // Scanner images
        $images = $content["images"] ?? [];
        if (!empty($images) && empty($replacements["{{HERO_IMAGE}}"])) {
            $replacements["{{HERO_IMAGE}}"] = $images[0]["url"] ?? "";
        }
        if (isset($images[1]) && empty($replacements["{{ABOUT_IMAGE}}"])) {
            $replacements["{{ABOUT_IMAGE}}"] = $images[1]["url"] ?? "";
            $replacements["{{ABOUT_IMAGE_ALT}}"] = $images[1]["alt"] ?? "";
        }
        if (!empty($content["logo_url"])) {
            $replacements["{{LOGO_URL}}"] = $content["logo_url"];
        }

        // Company / Impressum replacements
        $companyData = $content["company_data"] ?? $content["impressum"] ?? [];
        $replacements["{{COMPANY_NAME}}"]             = $companyData["name"] ?? $content["company"] ?? "";
        $replacements["{{COMPANY_ADDRESS_STREET}}"]   = $companyData["address_street"] ?? "";
        $replacements["{{COMPANY_ADDRESS_CITY}}"]     = $companyData["address_city"] ?? "";
        $replacements["{{COMPANY_BUSINESS}}"]         = $companyData["business"] ?? "";
        $replacements["{{COMPANY_UID}}"]              = $companyData["uid"] ?? "";
        $replacements["{{COMPANY_TRADE_REGULATION}}"] = $companyData["trade_regulation"] ?? "";
        $replacements["{{COMPANY_AUTHORITY}}"]        = $companyData["authority"] ?? "";
        $replacements["{{COMPANY_MEMBERSHIP}}"]       = $companyData["membership"] ?? "";

        // Opening hours
        $replacements["{{OPENING_HOURS}}"] = $companyData["opening_hours"] ?? $content["opening_hours"] ?? "";

        // Hosting (datenschutz)
        $hosting = $content["hosting"] ?? [];
        $replacements["{{HOSTING_PROVIDER}}"]      = $hosting["provider"] ?? "";
        $replacements["{{HOSTING_PROVIDER_FULL}}"]  = $hosting["provider_full"] ?? "";
        $replacements["{{HOSTING_PRIVACY_URL}}"]    = $hosting["privacy_url"] ?? "";

        // Subpage-specific replacements
        $replacements["{{PAGE_TAG}}"]  = $page["hero_eyebrow"] ?? $page["tag"] ?? "";
        $replacements["{{PAGE_LEAD}}"] = $page["hero_subtitle"] ?? $page["lead"] ?? "";

        // PAGE_CONTENT: built from text sections
        $pageContentParts = [];
        foreach ($sections as $sec) {
            if (($sec["type"] ?? "") === "text") {
                $text = $sec["content"] ?? $sec["text"] ?? "";
                if ($text !== "") {
                    // Split by double newlines into paragraphs, wrap each in <p>
                    $paragraphs = preg_split("/\n\s*\n/", $text);
                    foreach ($paragraphs as $para) {
                        $para = trim($para);
                        if ($para !== "") {
                            $pageContentParts[] = "<p>" . htmlspecialchars($para) . "</p>";
                        }
                    }
                }
            }
        }
        $replacements["{{PAGE_CONTENT}}"] = implode("\n      ", $pageContentParts);

        // Subpage CTA (page-level fields)
        $replacements["{{CTA_TITLE}}"]  = $page["cta_title"] ?? $replacements["{{CTA_SECTION_TITLE}}"] ?? "";
        $replacements["{{CTA_TEXT}}"]   = $page["cta_text"] ?? $replacements["{{CTA_SECTION_TEXT}}"] ?? "";
        $replacements["{{CTA_HREF}}"]   = $page["cta_href"] ?? $replacements["{{CTA_SECTION_HREF}}"] ?? "#kontakt";
        $replacements["{{CTA_BUTTON}}"] = $page["cta_button"] ?? $replacements["{{CTA_SECTION_BUTTON}}"] ?? "";

        // Features (page-level)
        $replacements["{{FEATURES_TAG}}"]   = $page["features_tag"] ?? "";
        $replacements["{{FEATURES_TITLE}}"] = $page["features_title"] ?? "";

        // Apply all simple replacements
        $html = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Render repeating blocks BEFORE cleanup
        $html = $this->renderRepeatingBlocks($html, $sections, $page);

        // Cleanup remaining unfilled placeholders
        $html = preg_replace("/\\{\\{[A-Z_]+\\}\\}/", "", $html);
        $html = preg_replace("/\\{\\{#\\w+\\}\\}.*?\\{\\{\\/\\w+\\}\\}/s", "", $html);

        // Remove broken images (empty src) and empty background-image divs
        $html = preg_replace('/<img[^>]*src\s*=\s*["\']["\'][^>]*\/?>/i', '', $html);
        $html = preg_replace('/background-image:\s*url\([\'"]?[\'"]?\)\s*;?/', '', $html);

        // Remove empty content elements left after placeholder cleanup
        // NOTE: excludes div (often CSS-styled containers like hero-bg) and section
        $html = preg_replace('/<(h[1-6]|p|span|a|li|figcaption|cite)[^>]*>\s*<\/\1>/i', '', $html);
        // Second pass for nested empties (e.g. <a><span></span></a>)
        $html = preg_replace('/<(h[1-6]|p|span|a|li|figcaption|cite)[^>]*>\s*<\/\1>/i', '', $html);

        // Replace template SITE_CONFIG block with generated one
        $configScript = $this->buildSiteConfigScript($preset, $content);
        $html = preg_replace(
            '/  <script>\s*window\.SITE_CONFIG\s*=\s*\{.*?\};\s*<\/script>/s',
            $configScript,
            $html
        );

        return $html;
    }

    private function createZip(string $dir, string $hash): void
    {
        $zipFile = $dir . "/site.zip";
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === "site.zip") continue;
            $relativePath = substr($file->getPathname(), strlen($dir) + 1);
            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }

        $zip->close();
    }

    public static function cleanup(): void
    {
        $dir = __DIR__ . "/../data/generated";
        if (!is_dir($dir)) return;
        foreach (glob($dir . "/*", GLOB_ONLYDIR) as $subdir) {
            if (filemtime($subdir) < time() - 3600) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($subdir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $f) {
                    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
                }
                rmdir($subdir);
            }
        }
    }
}
