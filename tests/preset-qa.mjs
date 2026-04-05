#!/usr/bin/env node
/**
 * Preset QA - WCAG AA Contrast Gate
 * Tests all 8 endoskeleton presets. Fails (exit 1) if any preset breaks WCAG AA.
 * Usage: node tests/preset-qa.mjs
 * Env: BASE_URL (default: https://skeleton.ssi.at)
 */
import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import { PNG } from 'pngjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const BASE_URL = process.env.BASE_URL || 'https://skeleton.ssi.at';
const SCREENSHOT_DIR = join(__dirname, 'screenshots');
const REPORT_PATH = join(__dirname, 'qa-report.md');
const TIMESTAMP = new Date().toISOString().replace(/[:.]/g, '-');

mkdirSync(SCREENSHOT_DIR, { recursive: true });

const PRESETS = {
  corporate:  'layout=modern&hero=split&color=blue&charakter=neutral&spacing=normal&animation=subtle&font=contrast&buttons=outline-elegant&nav=sticky&navStyle=light&navLayout=standard&width=default&footer=dark&theme=light',
  editorial:  'layout=magazine&hero=banner&color=wine&charakter=neutral&spacing=normal&animation=subtle&font=editorial&buttons=outline&nav=sticky&navStyle=light&navLayout=centered&width=wide&footer=dark&theme=light',
  minimal:    'layout=modern&hero=minimal&color=gray&charakter=elegant&spacing=compact&animation=none&font=modern&buttons=ghost&nav=sticky&navStyle=light&navLayout=minimal&width=default&footer=minimal&theme=light',
  tech:       'layout=modern&hero=fullscreen&color=cyan&charakter=kantig&spacing=compact&animation=dynamic&font=mono&buttons=tech&nav=transparent&navStyle=dark&navLayout=standard&width=full&footer=dark&theme=tech',
  creative:   'layout=magazine&hero=fullscreen&color=orange&charakter=markant&spacing=spacious&animation=dynamic&font=contrast&buttons=bold&nav=transparent&navStyle=dark&navLayout=club&width=wide&footer=dark&theme=dark',
  outdoor:    'layout=modern&hero=fullscreen&color=brown&charakter=markant&spacing=normal&animation=dynamic&font=survival&buttons=outline-elegant&nav=shrink&navStyle=light&navLayout=standard&width=wide&footer=dark&theme=warm',
  playful:    'layout=modern&hero=playful&color=orange&charakter=sanft&spacing=normal&animation=dynamic&font=playful&buttons=rounded&nav=sticky&navStyle=light&navLayout=standard&width=default&footer=colored&theme=sunset',
  elegant:    'layout=modern&hero=split&color=gold&charakter=elegant&spacing=spacious&animation=subtle&font=editorial&buttons=outline-elegant&nav=sticky&navStyle=light&navLayout=standard&width=default&footer=dark&theme=warm',
  survival:   'layout=modern&hero=fullscreen&color=survival&charakter=kantig&spacing=spacious&animation=dynamic&font=survival&buttons=sharp&nav=transparent&navStyle=light&navLayout=standard&width=default&footer=dark&theme=warm',
};

function parseColor(str) {
  const m = str && str.match(/rgba?\(\s*(\d+(?:\.\d+)?)\s*,?\s*(\d+(?:\.\d+)?)\s*,?\s*(\d+(?:\.\d+)?)(?:\s*[,\/]\s*(\d+(?:\.\d+)?%?))?\s*\)/);
  if (!m) return null;
  let a = 1;
  if (m[4] !== undefined) a = m[4].endsWith('%') ? parseFloat(m[4]) / 100 : +m[4];
  return { r: +m[1], g: +m[2], b: +m[3], a };
}

function relLum({ r, g, b }) {
  const chan = c => {
    const s = c / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  };
  return 0.2126 * chan(r) + 0.7152 * chan(g) + 0.0722 * chan(b);
}

function blendOver(fg, bg) {
  if (fg.a >= 1) return fg;
  const a = fg.a;
  return {
    r: fg.r * a + bg.r * (1 - a),
    g: fg.g * a + bg.g * (1 - a),
    b: fg.b * a + bg.b * (1 - a),
    a: 1,
  };
}

function contrastRatioRGB(fg, bg) {
  if (!fg || !bg) return null;
  const bgEff = bg.a < 1 ? blendOver(bg, { r: 255, g: 255, b: 255, a: 1 }) : bg;
  const fgEff = fg.a < 1 ? blendOver(fg, bgEff) : fg;
  const L1 = relLum(fgEff);
  const L2 = relLum(bgEff);
  const [light, dark] = L1 > L2 ? [L1, L2] : [L2, L1];
  return (light + 0.05) / (dark + 0.05);
}

function rgbToString({ r, g, b, a }) {
  if (a !== undefined && a < 1) return `rgba(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)}, ${a.toFixed(2)})`;
  return `rgb(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)})`;
}

function requiredRatio(fontSizePx, fontWeight) {
  const weight = parseInt(fontWeight, 10) || 400;
  if (fontSizePx >= 24) return 3.0;
  if (fontSizePx >= 18.66 && weight >= 700) return 3.0;
  return 4.5;
}

// Pixel-sample background by hiding the element's text color, then screenshotting its bbox.
async function sampleBgPixel(page, element) {
  // Ensure element is in viewport so the screenshot clip lands on real pixels.
  try { await element.scrollIntoViewIfNeeded({ timeout: 2000 }); } catch {}
  const box = await element.boundingBox();
  if (!box || box.width < 2 || box.height < 2) return null;
  const vp = page.viewportSize() || { width: 1440, height: 900 };
  // Clamp to viewport bounds
  const x0 = Math.max(0, Math.min(vp.width - 2, Math.floor(box.x)));
  const y0 = Math.max(0, Math.min(vp.height - 2, Math.floor(box.y)));
  const x1 = Math.max(x0 + 2, Math.min(vp.width, Math.floor(box.x + box.width)));
  const y1 = Math.max(y0 + 2, Math.min(vp.height, Math.floor(box.y + box.height)));
  const clampedW = x1 - x0;
  const clampedH = y1 - y0;
  if (clampedW < 2 || clampedH < 2) return null;

  // Hide fg text while keeping layout intact
  await element.evaluate((el) => {
    el.dataset.qaOrigColor = el.style.color;
    el.dataset.qaOrigTextShadow = el.style.textShadow;
    el.style.color = 'transparent';
    el.style.textShadow = 'none';
  });

  const clip = {
    x: x0,
    y: y0,
    width: Math.min(clampedW, 800),
    height: Math.min(clampedH, 300),
  };
  const buf = await page.screenshot({ clip, type: 'png' });

  await element.evaluate((el) => {
    el.style.color = el.dataset.qaOrigColor || '';
    el.style.textShadow = el.dataset.qaOrigTextShadow || '';
    delete el.dataset.qaOrigColor;
    delete el.dataset.qaOrigTextShadow;
  });

  const png = PNG.sync.read(buf);
  const { width, height, data } = png;
  let r = 0, g = 0, b = 0, n = 0;
  const stepX = Math.max(1, Math.floor(width / 8));
  const stepY = Math.max(1, Math.floor(height / 8));
  for (let y = Math.floor(stepY / 2); y < height; y += stepY) {
    for (let x = Math.floor(stepX / 2); x < width; x += stepX) {
      const idx = (y * width + x) * 4;
      r += data[idx];
      g += data[idx + 1];
      b += data[idx + 2];
      n++;
    }
  }
  if (n === 0) return null;
  return { r: r / n, g: g / n, b: b / n, a: 1 };
}

// DOM walk collecting alpha-aware backgrounds; detects background-image on the way.
async function domBgWalk(element) {
  return await element.evaluate((el) => {
    const parseRGBA = (s) => {
      const m = s && s.match(/rgba?\(\s*(\d+(?:\.\d+)?)\s*,?\s*(\d+(?:\.\d+)?)\s*,?\s*(\d+(?:\.\d+)?)(?:\s*[,\/]\s*(\d+(?:\.\d+)?%?))?\s*\)/);
      if (!m) return null;
      let a = 1;
      if (m[4] !== undefined) a = m[4].endsWith('%') ? parseFloat(m[4]) / 100 : +m[4];
      return { r: +m[1], g: +m[2], b: +m[3], a };
    };
    const stack = [];
    let cur = el;
    let hasImage = false;
    while (cur) {
      const cs = getComputedStyle(cur);
      if (cs.backgroundImage && cs.backgroundImage !== 'none') {
        hasImage = true;
        break;
      }
      const bg = parseRGBA(cs.backgroundColor);
      if (bg && bg.a > 0) {
        stack.push(bg);
        if (bg.a >= 1) break;
      }
      cur = cur.parentElement;
    }
    return { stack, hasImage };
  });
}

function composeStack(stack) {
  let base = { r: 255, g: 255, b: 255, a: 1 };
  for (let i = stack.length - 1; i >= 0; i--) {
    base = blendOver(stack[i], base);
  }
  return base;
}

const results = [];
let failures = 0;

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  userAgent: 'endoskeleton-qa-bot/1.0 (Playwright)',
});

const SELECTORS = [
  { label: 'body-text',  sel: 'main p, article p, section p, body p' },
  { label: 'hero-h1',    sel: '.hero h1, [class*="hero"] h1, header h1, h1' },
  { label: 'nav-link',   sel: 'nav a, header nav a, [class*="nav"] a' },
  { label: 'card-title', sel: '.card h2, .card h3, [class*="card"] h2, [class*="card"] h3, article h2, article h3' },
];

for (const [name, params] of Object.entries(PRESETS)) {
  const cacheBuster = `_cb=${Date.now()}`;
  const url = `${BASE_URL}/?${cacheBuster}#${params}`;
  const presetResult = { name, url, checks: [], error: null, screenshot: null };

  const page = await context.newPage();
  page.setDefaultTimeout(30000);

  try {
    const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
    if (!response || !response.ok()) {
      throw new Error(`HTTP ${response ? response.status() : 'no-response'}`);
    }
    await page.waitForTimeout(800);

    const shotPath = join(SCREENSHOT_DIR, `${name}-${TIMESTAMP}.png`);
    await page.screenshot({ path: shotPath, fullPage: false });
    presetResult.screenshot = shotPath;

    for (const { label, sel } of SELECTORS) {
      const el = await page.$(sel);
      if (!el) continue;
      const isVisible = await el.isVisible().catch(() => false);
      if (!isVisible) { await el.dispose(); continue; }

      const meta = await el.evaluate((node) => {
        const s = getComputedStyle(node);
        return {
          color: s.color,
          fontSize: parseFloat(s.fontSize),
          fontWeight: s.fontWeight,
          text: (node.textContent || '').trim().slice(0, 60),
        };
      });

      // Primary approach: pixel-sample (robust against images, gradients, overlays, ::before, absolute children).
      // Fallback to DOM-walk if sampling fails.
      let bgRGB = await sampleBgPixel(page, el);
      let method = 'pixel-sample';
      if (!bgRGB) {
        const walk = await domBgWalk(el);
        if (walk.stack.length > 0) {
          bgRGB = composeStack(walk.stack);
          method = 'dom-walk';
        } else {
          bgRGB = { r: 255, g: 255, b: 255, a: 1 };
          method = 'default-white';
        }
      }

      const fgRGB = parseColor(meta.color);
      const ratio = contrastRatioRGB(fgRGB, bgRGB);
      const required = requiredRatio(meta.fontSize, meta.fontWeight);
      const pass = ratio !== null && ratio >= required;

      presetResult.checks.push({
        label,
        selector: sel,
        color: meta.color,
        background: bgRGB ? rgbToString(bgRGB) : 'n/a',
        fontSize: meta.fontSize,
        fontWeight: meta.fontWeight,
        text: meta.text,
        ratio,
        required,
        pass,
        method,
      });
      if (!pass) failures++;
      await el.dispose();
    }
  } catch (err) {
    presetResult.error = err.message;
    failures++;
  } finally {
    await page.close();
  }

  results.push(presetResult);
  const status = presetResult.error
    ? `ERROR: ${presetResult.error}`
    : presetResult.checks.every(c => c.pass)
      ? 'PASS'
      : 'FAIL';
  console.log(`[${status}] ${name}`);
  for (const c of presetResult.checks) {
    const mark = c.pass ? 'ok  ' : 'FAIL';
    console.log(`  ${mark} ${c.label.padEnd(11)} [${c.method.padEnd(12)}] ${c.color} on ${c.background}  ${c.ratio ? c.ratio.toFixed(2) : 'n/a'}:1 (need ${c.required}:1)`);
  }
}

await browser.close();

const lines = [];
lines.push(`# Preset QA Report`);
lines.push('');
lines.push(`- Generated: ${new Date().toISOString()}`);
lines.push(`- Base URL: ${BASE_URL}`);
lines.push(`- Presets tested: ${results.length}`);
lines.push(`- Total failures: ${failures}`);
lines.push('');
lines.push('## Summary');
lines.push('');
lines.push('| Preset | Status | Checks | Min Ratio |');
lines.push('|--------|--------|--------|-----------|');
for (const r of results) {
  const minRatio = r.checks.length ? Math.min(...r.checks.map(c => c.ratio || 0)) : 0;
  const okCount = r.checks.filter(c => c.pass).length;
  const status = r.error ? `ERROR` : (okCount === r.checks.length ? 'PASS' : 'FAIL');
  lines.push(`| ${r.name} | ${status} | ${okCount}/${r.checks.length} | ${minRatio.toFixed(2)}:1 |`);
}
lines.push('');
lines.push('## Details');
lines.push('');
for (const r of results) {
  lines.push(`### ${r.name}`);
  lines.push('');
  lines.push(`- URL: ${r.url}`);
  if (r.screenshot) lines.push(`- Screenshot: \`${r.screenshot.replace(/.*\/tests\//, 'tests/')}\``);
  if (r.error) {
    lines.push(`- ERROR: ${r.error}`);
    lines.push('');
    continue;
  }
  lines.push('');
  lines.push('| Element | Method | Color | Background | Size | Weight | Ratio | Required | Result |');
  lines.push('|---------|--------|-------|-----------|------|--------|-------|----------|--------|');
  for (const c of r.checks) {
    lines.push(`| ${c.label} | ${c.method} | ${c.color} | ${c.background} | ${c.fontSize}px | ${c.fontWeight} | ${c.ratio ? c.ratio.toFixed(2) : 'n/a'}:1 | ${c.required}:1 | ${c.pass ? 'PASS' : 'FAIL'} |`);
  }
  lines.push('');
}

writeFileSync(REPORT_PATH, lines.join('\n'));
console.log('');
console.log(`Report: ${REPORT_PATH}`);
console.log(`Failures: ${failures}`);
process.exit(failures > 0 ? 1 : 0);
