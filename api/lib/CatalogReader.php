<?php
declare(strict_types=1);

final class CatalogReader
{
    private string $root;
    private array $config;

    public function __construct()
    {
        $this->root = dirname(__DIR__, 2); // endoskeleton/
        $configFile = $this->root . '/config.json';
        $this->config = json_decode(file_get_contents($configFile), true);
    }

    public function getVersion(): string
    {
        $versionFile = $this->root . '/VERSION';
        return file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'unknown';
    }

    public function getPresets(): array
    {
        // Parse PRESETS from demo.html <script> block
        $demo = file_get_contents($this->root . '/demo.html');
        if (!preg_match('/var PRESETS\s*=\s*\{(.+?)\};\s*\n\s*var defaults/s', $demo, $m)) {
            return [];
        }
        // Convert JS object to JSON: add quotes around keys
        $js = '{' . $m[1] . '}';
        $js = preg_replace('/([a-zA-Z_]\w*)\s*:/', '"$1":', $js);
        $js = str_replace("'", '"', $js);
        return json_decode($js, true) ?? [];
    }

    public function getParameters(): array
    {
        $params = [];

        $dirMap = [
            'color'     => ['dir' => 'colors',      'path' => 'colors/{value}.css'],
            'font'      => ['dir' => 'fonts',        'path' => 'fonts/{value}.css'],
            'buttons'   => ['dir' => 'buttons',      'path' => 'buttons/{value}.css'],
            'charakter' => ['dir' => 'softness',     'path' => 'softness/{value}.css'],
            'layout'    => ['dir' => 'layouts',       'path' => 'layouts/{value}.css'],
            'hero'      => ['dir' => 'heroes',        'path' => 'heroes/{value}.css'],
            'spacing'   => ['dir' => 'spacing',       'path' => 'spacing/{value}.css'],
            'animation' => ['dir' => 'animation',     'path' => 'animation/{value}.css'],
            'width'     => ['dir' => 'widths',         'path' => 'widths/{value}.css'],
            'navLayout' => ['dir' => 'navlayouts',    'path' => 'navlayouts/{value}.css'],
        ];

        foreach ($dirMap as $param => $info) {
            $dir = $this->root . '/' . $info['dir'];
            $options = [];
            if (is_dir($dir)) {
                foreach (glob($dir . '/*.css') as $file) {
                    $options[] = basename($file, '.css');
                }
                sort($options);
            }
            $params[$param] = [
                'options' => $options,
                'path'    => $info['path'],
                'count'   => count($options),
            ];
        }

        // Non-file parameters
        $params['navBehavior'] = ['options' => ['solid', 'sticky', 'transparent', 'shrink']];
        $params['navStyle']    = ['options' => ['light', 'dark']];
        $params['footerStyle'] = ['options' => ['dark', 'minimal', 'colored']];
        $params['theme']       = ['options' => ['light', 'dark', 'warm', 'tech', 'pastel', 'forest']];
        $params['navCase']     = ['options' => ['normal', 'upper']];

        // Add hints from config.json
        $options = $this->config['_options'] ?? [];
        if (isset($options['_charakter_hints'])) {
            $params['charakter']['hints'] = $options['_charakter_hints'];
        }
        if (isset($options['_font_hints'])) {
            $params['font']['hints'] = $options['_font_hints'];
        }

        return $params;
    }

    public function getCatalog(): array
    {
        return [
            'version'    => $this->getVersion(),
            'base_url'   => 'https://skeleton.ssi.at',
            'presets'    => $this->getPresets(),
            'parameters' => $this->getParameters(),
        ];
    }

    public function getPresetDetail(string $name): ?array
    {
        $presets = $this->getPresets();
        if (!isset($presets[$name])) {
            return null;
        }

        $config = $presets[$name];
        $cssUrls = [];
        $cssMap = [
            'color'     => 'colors',
            'font'      => 'fonts',
            'buttons'   => 'buttons',
            'charakter' => 'softness',
            'layout'    => 'layouts',
            'hero'      => 'heroes',
            'spacing'   => 'spacing',
            'animation' => 'animation',
            'width'     => 'widths',
            'navLayout' => 'navlayouts',
        ];

        foreach ($cssMap as $param => $dir) {
            if (isset($config[$param])) {
                $file = $this->root . '/' . $dir . '/' . $config[$param] . '.css';
                if (file_exists($file)) {
                    $cssUrls[] = 'https://skeleton.ssi.at/' . $dir . '/' . $config[$param] . '.css';
                }
            }
        }

        return [
            'name'        => $name,
            'parameters'  => $config,
            'css_urls'    => $cssUrls,
            'preview_url' => 'https://skeleton.ssi.at/demo.html#preset=' . $name,
        ];
    }
}
