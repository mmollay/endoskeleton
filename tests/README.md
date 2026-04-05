# Endoskeleton Preset QA

WCAG AA contrast gate for all 9 presets. Runs before every deploy.

## Run

```bash
# Against production
node tests/preset-qa.mjs

# Against local dev
BASE_URL=http://localhost:8080 node tests/preset-qa.mjs

# Or via npm
npm run qa
```

## What it does

For each preset (corporate, editorial, minimal, tech, creative, outdoor, playful, elegant):

1. Navigates to `$BASE_URL/?<preset-params>&_cb=<timestamp>` (cache-buster)
2. Takes a 1440x900 screenshot -> `tests/screenshots/<preset>-<ts>.png`
3. Reads computed styles from body text, hero h1, nav links, card titles
4. Computes WCAG contrast ratio (sRGB relative luminance)
5. Checks against WCAG AA thresholds:
   - Normal text: >= 4.5:1
   - Large text (>=24px, or >=18.66px bold): >= 3:1

## On FAIL

- Exit code `1` (blocks deploy)
- Console output shows preset, element, measured vs required ratio
- Full report written to `tests/qa-report.md`

## On PASS

- Exit code `0`
- Report still generated for record-keeping
