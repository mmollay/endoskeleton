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

        foreach ($pages as $page) {
            $slug = preg_replace("/[^a-z0-9-]/", "", strtolower($page["slug"] ?? "index"));
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

        $navItems = [];
        foreach ($content["pages"] ?? [] as $p) {
            $slug = addslashes($p["slug"] ?? "index");
            $title = addslashes($p["title"] ?? "Seite");
            $navItems[] = "      {label: \"{$title}\", href: \"{$slug}.html\"}";
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

    private function buildPage(array $preset, array $content, array $page): string
    {
        $template = file_get_contents($this->root . "/pages/index.html");

        $replacements = [
            "{{SITE_NAME}}"        => $content["company"] ?? "Website",
            "{{SITE_URL}}"         => "",
            "{{PAGE_TITLE}}"       => $page["title"] ?? "Home",
            "{{PAGE_DESCRIPTION}}" => $content["description"] ?? "",
            "{{PAGE_FILE}}"        => ($page["slug"] ?? "index") . ".html",
            "{{LAYOUT}}"          => $preset["layout"] ?? "modern",
            "{{HERO}}"            => $preset["hero"] ?? "fullscreen",
            "{{HERO_TYPE}}"       => $preset["hero"] ?? "fullscreen",
            "{{BUTTONS}}"         => $preset["buttons"] ?? "rounded",
            "{{CHARAKTER}}"       => $preset["charakter"] ?? "neutral",
            "{{SPACING}}"         => $preset["spacing"] ?? "normal",
            "{{ANIMATION}}"       => $preset["animation"] ?? "subtle",
            "{{WIDTH}}"           => $preset["width"] ?? "default",
            "{{FONT}}"            => "Inter:wght@300;400;500;600;700",
            "{{MODULES}}"         => "",
            "{{MODULES_LIST}}"    => "",
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
        }

        $contact = $content["contact"] ?? [];
        $replacements["{{CONTACT_NAME}}"]    = $contact["name"] ?? $content["company"] ?? "";
        $replacements["{{CONTACT_EMAIL}}"]   = $contact["email"] ?? "";
        $replacements["{{CONTACT_PHONE}}"]   = $contact["phone"] ?? "";
        $replacements["{{CONTACT_ADDRESS}}"] = $contact["address"] ?? "";
        $replacements["{{FOOTER_TAGLINE}}"] = $content["footer"]["tagline"] ?? "";
        $replacements["{{SOCIAL_LINKS}}"]   = "";

        $navItems = [];
        foreach ($content["pages"] ?? [] as $p) {
            $s = $p["slug"] ?? "index";
            $t = $p["title"] ?? "Seite";
            $navItems[] = "{label: \"" . addslashes($t) . "\", href: \"" . $s . ".html\"}";
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

        $html = str_replace(array_keys($replacements), array_values($replacements), $template);
        $html = preg_replace("/\\{\\{[A-Z_]+\\}\\}/", "", $html);
        $html = preg_replace("/\\{\\{#\\w+\\}\\}.*?\\{\\{\\/\\w+\\}\\}/s", "", $html);

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
