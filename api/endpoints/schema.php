<?php
declare(strict_types=1);

if ($method !== "GET") {
    Response::error("Nur GET erlaubt", 405);
}

require_once __DIR__ . "/../lib/CatalogReader.php";
$catalog = new CatalogReader();
$params = $catalog->getParameters();
$version = $catalog->getVersion();

$paramSchemas = [];
foreach ($params as $name => $info) {
    $paramSchemas[$name] = [
        "type" => "string",
        "enum" => $info["options"],
    ];
    if (isset($info["hints"])) {
        $paramSchemas[$name]["description"] = "Hints: " . json_encode($info["hints"], JSON_UNESCAPED_UNICODE);
    }
}

$schema = [
    "openapi" => "3.0.3",
    "info" => [
        "title"       => "SSI Endoskeleton API",
        "description" => "Offene REST-API fuer das Endoskeleton Design-System. Katalog, Empfehlungen, Seitengenerierung.",
        "version"     => $version,
        "contact"     => ["email" => "office@ssi.at", "url" => "https://skeleton.ssi.at"],
    ],
    "servers" => [
        ["url" => "https://skeleton.ssi.at/api/v1", "description" => "Production"],
    ],
    "paths" => [
        "/catalog" => [
            "get" => [
                "summary" => "Komplettes Design-System",
                "description" => "Liefert alle verfuegbaren Presets, Parameter, Optionen und Hints.",
                "responses" => ["200" => ["description" => "Design-System Katalog"]],
            ],
        ],
        "/presets" => [
            "get" => [
                "summary" => "Alle Presets auflisten",
                "responses" => ["200" => ["description" => "Preset-Liste"]],
            ],
        ],
        "/presets/{name}" => [
            "get" => [
                "summary" => "Preset-Detail mit allen Parametern und CSS-URLs",
                "parameters" => [
                    ["name" => "name", "in" => "path", "required" => true, "schema" => ["type" => "string"]],
                ],
                "responses" => ["200" => ["description" => "Preset-Detail"], "404" => ["description" => "Nicht gefunden"]],
            ],
        ],
        "/recommend" => [
            "post" => [
                "summary" => "Design-Empfehlung basierend auf Anforderungen",
                "description" => "Analysiert Branche, Stimmung, Farben und Keywords und empfiehlt das beste Endoskeleton-Setup.",
                "requestBody" => [
                    "required" => true,
                    "content" => [
                        "application/json" => [
                            "schema" => [
                                "type" => "object",
                                "properties" => [
                                    "branch" => ["type" => "string", "description" => "Branche: gastronomie, tourismus, natur, handwerk, gesundheit, kreativ, corporate, tech, bildung, sport, mode, recht, immobilien"],
                                    "mood" => ["type" => "string", "description" => "Stimmung: warm, modern, dunkel, edel, lebendig, klassisch, serioes, natuerlich, minimalistisch"],
                                    "colors" => ["type" => "array", "items" => ["type" => "string"], "description" => "Hex-Farben der bestehenden Seite"],
                                    "target_audience" => ["type" => "string", "description" => "Zielgruppe"],
                                    "style_keywords" => ["type" => "array", "items" => ["type" => "string"], "description" => "Stil-Stichworte"],
                                ],
                            ],
                        ],
                    ],
                ],
                "responses" => ["200" => ["description" => "Top 3 Empfehlungen mit Confidence und Preview-URLs"]],
            ],
        ],
        "/generate" => [
            "post" => [
                "summary" => "Webseite generieren",
                "description" => "Erzeugt eine komplette Webseite aus Preset + Content.",
                "requestBody" => [
                    "required" => true,
                    "content" => [
                        "application/json" => [
                            "schema" => [
                                "type" => "object",
                                "properties" => [
                                    "preset" => ["type" => "string"],
                                    "overrides" => ["type" => "object", "properties" => $paramSchemas],
                                    "content" => ["type" => "object", "required" => ["company", "pages"]],
                                    "format" => ["type" => "string", "enum" => ["urls", "zip"], "default" => "urls"],
                                ],
                            ],
                        ],
                    ],
                ],
                "responses" => ["200" => ["description" => "Preview-URL, Download-URL, Dateiliste"]],
            ],
        ],
        "/preview" => [
            "get" => [
                "summary" => "Live-Vorschau",
                "description" => "Redirect zur demo.html mit den angegebenen Parametern.",
                "responses" => ["302" => ["description" => "Redirect zur Vorschau"]],
            ],
        ],
        "/feedback" => [
            "post" => [
                "summary" => "Feature-Request loggen",
                "requestBody" => [
                    "required" => true,
                    "content" => [
                        "application/json" => [
                            "schema" => [
                                "type" => "object",
                                "required" => ["type", "description"],
                                "properties" => [
                                    "type" => ["type" => "string", "enum" => ["missing_parameter", "missing_preset", "color_mismatch", "suggestion", "bug"]],
                                    "description" => ["type" => "string"],
                                    "context" => ["type" => "object"],
                                ],
                            ],
                        ],
                    ],
                ],
                "responses" => ["200" => ["description" => "Feedback geloggt mit ID"]],
            ],
            "get" => [
                "summary" => "Alle Feedbacks auflisten",
                "responses" => ["200" => ["description" => "Liste aller Feedback-Eintraege"]],
            ],
        ],
    ],
    "components" => [
        "schemas" => [
            "EndoskeletonParameters" => [
                "type" => "object",
                "properties" => $paramSchemas,
            ],
        ],
    ],
];

$etag = "\"schema-" . $version . "\"";
Response::cached($schema, $etag, 300);
