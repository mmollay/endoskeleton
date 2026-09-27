<?php
/** Der Baustein-Katalog lp/bausteine.json: Bausteine, Felder, Varianten, Regeln, Klassen, Farbpaare. */

declare(strict_types=1);

namespace Endo\Lp;

final class Katalog
{
    /** Felder, die jeder Baustein kennt. */
    public const ALLGEMEIN = ['typ', 'variante', 'anker'];

    public function __construct(
        public readonly string $version,
        public readonly array $seite,
        public readonly array $rahmen,
        public readonly array $bausteine,
    ) {
    }

    public static function laden(?string $datei = null): self
    {
        $datei ??= dirname(__DIR__) . '/bausteine.json';
        $d = json_decode((string) file_get_contents($datei), true, 64, JSON_THROW_ON_ERROR);
        return new self($d['version'], $d['seite'], $d['rahmen'], $d['bausteine']);
    }

    public function baustein(string $typ): ?array
    {
        return $this->bausteine[$typ] ?? null;
    }

    /** @return array<string, string> Klasse => seit, für Rahmen und alle Bausteine */
    public function klassen(): array
    {
        $k = $this->rahmen['klassen'];
        foreach ($this->bausteine as $b) {
            $k += $b['klassen'];
        }
        return $k;
    }
}
