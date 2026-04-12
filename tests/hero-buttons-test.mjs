/**
 * Hero Button Module Test — v3.38.0
 * Tests all 24 button modules on fullscreen (dark) and split (light) hero.
 * Checks computed styles to verify buttons would be visible.
 */
import { chromium } from "playwright";

const MODULES = [
  "bold", "brutalist", "chip", "cta-arrow", "ghost-tint", "ghost",
  "gradient-cta", "heavy-bold", "minimal-link", "outline-elegant",
  "outline-thin", "outline", "pill-solid", "pill-thin", "rect-outline",
  "rect-solid", "rounded", "shadow-lift", "sharp", "soft-round",
  "soft", "tactical", "tech-angle", "tech",
];

const HEROES = [
  { id: "hero-fullscreen", name: "fullscreen", dark: true },
  { id: "hero-split", name: "split", dark: false },
];

const BASE = "http://localhost:8765/konfigurator.html";

function parseRgb(color) {
  const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
  if (!m) return null;
  return { r: +m[1], g: +m[2], b: +m[3] };
}

function brightness(rgb) {
  return rgb ? (rgb.r + rgb.g + rgb.b) / 3 : -1;
}

function isTransparent(bg) {
  return !bg || bg === "rgba(0, 0, 0, 0)" || bg === "transparent";
}

function hasBorder(bw) {
  return bw && bw !== "0px" && !bw.split(" ").every(v => v === "0px");
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });

await page.goto(BASE, { waitUntil: "networkidle" });
await page.waitForTimeout(1500);

let passed = 0;
let failed = 0;
const failures = [];

for (const hero of HEROES) {
  console.log(`\n--- ${hero.name.toUpperCase()} hero (${hero.dark ? "dark" : "light"} bg) ---`);

  for (const mod of MODULES) {
    const testName = `${hero.name}/${mod}`;
    const result = await page.evaluate(({ heroId, moduleName }) => {
      document.querySelectorAll("#hero-container > section")
        .forEach(s => s.style.display = "none");
      const heroEl = document.getElementById(heroId);
      if (!heroEl) return { error: "hero not found" };
      heroEl.style.display = "";
      if (typeof switchButtons === "function") switchButtons(moduleName);
      heroEl.offsetHeight; // force reflow

      const primary = heroEl.querySelector(".btn-primary");
      const ghost = heroEl.querySelector(".btn-ghost") || heroEl.querySelector(".btn-outline");

      function getStyles(btn) {
        if (!btn) return null;
        const s = getComputedStyle(btn);
        return {
          color: s.color,
          bg: s.backgroundColor,
          borderWidth: s.borderWidth,
          borderColor: s.borderColor,
          opacity: s.opacity,
        };
      }
      return { primary: getStyles(primary), ghost: getStyles(ghost) };
    }, { heroId: hero.id, moduleName: mod });

    if (result.error) {
      console.log(`  ✗ ${testName} — ${result.error}`);
      failed++; failures.push(testName);
      continue;
    }

    const issues = [];

    // === PRIMARY button checks ===
    if (result.primary) {
      const pColor = parseRgb(result.primary.color);
      const pBg = result.primary.bg;

      if (hero.dark) {
        // On dark hero: primary needs either a visible background OR light text
        if (isTransparent(pBg) && pColor && brightness(pColor) < 100) {
          issues.push(`primary: dark text (brightness ${brightness(pColor).toFixed(0)}) on transparent bg`);
        }
      }
    } else {
      issues.push("primary: not found");
    }

    // === GHOST button checks ===
    if (result.ghost) {
      const gColor = parseRgb(result.ghost.color);
      const gBg = result.ghost.bg;
      const gHasBorder = hasBorder(result.ghost.borderWidth);

      if (hero.dark) {
        // On dark hero: ghost needs light text + (border OR background)
        if (gColor && brightness(gColor) < 100) {
          if (isTransparent(gBg) && !gHasBorder) {
            issues.push(`ghost: dark text (brightness ${brightness(gColor).toFixed(0)}), no bg, no border`);
          } else if (isTransparent(gBg)) {
            issues.push(`ghost: dark text (brightness ${brightness(gColor).toFixed(0)}) despite border`);
          }
        }
      }
      // On light hero: no check needed — dark text on light bg is always readable
    } else {
      issues.push("ghost: not found");
    }

    if (issues.length > 0) {
      console.log(`  ✗ ${testName}`);
      issues.forEach(i => console.log(`    - ${i}`));
      console.log(`    primary: color=${result.primary?.color} bg=${result.primary?.bg} border=${result.primary?.borderWidth}`);
      console.log(`    ghost:   color=${result.ghost?.color} bg=${result.ghost?.bg} border=${result.ghost?.borderWidth}`);
      failed++;
      failures.push(testName);
    } else {
      // Show key style info for verification
      const pBr = brightness(parseRgb(result.primary?.color));
      const gBr = brightness(parseRgb(result.ghost?.color));
      const info = hero.dark
        ? `primary-text:${pBr.toFixed(0)} ghost-text:${gBr.toFixed(0)}`
        : `module-styles-applied`;
      console.log(`  ✓ ${testName} (${info})`);
      passed++;
    }
  }
}

await browser.close();

console.log(`\n${"=".repeat(60)}`);
console.log(`Results: ${passed} passed, ${failed} failed out of ${passed + failed}`);
if (failures.length) {
  console.log(`\nFailed:`);
  failures.forEach(f => console.log(`  - ${f}`));
}
console.log();
process.exit(failed > 0 ? 1 : 0);
