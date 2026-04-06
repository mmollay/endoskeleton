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

    private function generateThemeCss(array $preset, array $content, string $dir): void
    {
        $theme = $preset["theme"] ?? "light";
        $css = "/* Endoskeleton Generated Theme */\n:root {\n";
        $css .= "  --theme: $theme;\n";
        $css .= "}\n";
        file_put_contents($dir . "/theme.css", $css);
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

        $html = str_replace(array_keys($replacements), array_values($replacements), $template);
        $html = preg_replace("/\\{\\{[A-Z_]+\\}\\}/", "", $html);
        $html = preg_replace("/\\{\\{#\\w+\\}\\}.*?\\{\\{\\/\\w+\\}\\}/s", "", $html);

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
