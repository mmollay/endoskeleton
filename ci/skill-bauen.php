<?php
/** Erzeugt lp/SKILL.md (aus lp/skill-kopf.md + Katalog) und lp/werkstatt.md.   php ci/skill-bauen.php */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../lp/autoload.php';

use Endo\Lp\Katalog;
use Endo\Lp\Skill;

$lp = dirname(__DIR__) . '/lp';
$k = Katalog::laden();
file_put_contents("$lp/SKILL.md", Skill::skill($k, (string) file_get_contents("$lp/skill-kopf.md")));
file_put_contents("$lp/werkstatt.md", Skill::werkstatt($k));
echo "lp/SKILL.md und lp/werkstatt.md geschrieben\n";
