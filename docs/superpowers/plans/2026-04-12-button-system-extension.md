# Button System Extension — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the Endoskeleton button system with Lucide Icons, 5 size tiers, 3 semantic color variants per palette, and loading/disabled/success states.

**Architecture:** All new features live in base.css (sizes, colors, states, icon styling) and the 24 color palette files (semantic colors). Lucide JS loads via CDN. Button modules (buttons/*.css) remain untouched — they control shape/style, base.css controls sizes/colors/states.

**Tech Stack:** CSS, Lucide JS (CDN), Playwright for testing

**Priority Order:** Icons → Sizes → Colors → States → Test page → Deploy

---

### Task 1: Lucide JS CDN + Icon CSS

**Files:**
- Modify: `base.css` (append icon rules after btn-block)
- Modify: `konfigurator.html` (add CDN script tag)
- Modify: `shared.js` (add lucide init)

- [ ] **Step 1: Add icon CSS to base.css**

Append after the existing `.btn-block` rules (around line 1345):

```css
/* ─── Button Icons ───────────────────────────────────────────────────── */
.btn svg {
  width: 1em;
  height: 1em;
  flex-shrink: 0;
}

.btn-icon-right {
  flex-direction: row-reverse;
}

.btn-icon-only {
  padding: 0;
  width: 2.75rem;
  height: 2.75rem;
}
.btn-icon-only svg {
  width: 1.25em;
  height: 1.25em;
}
.btn-icon-only.btn-xs { width: 1.75rem; height: 1.75rem; }
.btn-icon-only.btn-sm { width: 2.15rem; height: 2.15rem; }
.btn-icon-only.btn-lg { width: 3.25rem; height: 3.25rem; }
.btn-icon-only.btn-xl { width: 3.75rem; height: 3.75rem; }
```

- [ ] **Step 2: Add Lucide CDN to konfigurator.html**

Add before the closing `</body>` tag, BEFORE the existing scripts:

```html
<script src="https://unpkg.com/lucide@0.460.0/dist/umd/lucide.min.js"></script>
```

- [ ] **Step 3: Add lucide init to shared.js**

At the end of the DOMContentLoaded handler in shared.js, add:

```javascript
/* Lucide Icons — render all data-lucide elements */
if (window.lucide) {
  lucide.createIcons();
}
```

- [ ] **Step 4: Add lucide re-init after dynamic content in konfigurator.html**

In konfigurator.html, find the `switchHero` function and add `if (window.lucide) lucide.createIcons();` at the end. Same for `applyPreset_config`.

- [ ] **Step 5: Commit**

```bash
git add base.css konfigurator.html shared.js
git commit -m "feat: lucide icons integration for buttons"
```

---

### Task 2: Button Sizes (btn-xs, btn-xl)

**Files:**
- Modify: `base.css` (update size modifiers section)

- [ ] **Step 1: Add btn-xs and btn-xl to base.css**

Find the existing `.btn-sm` and `.btn-lg` rules and extend:

```css
.btn-xs {
  padding: 0.35rem 0.75rem;
  font-size: calc(var(--fs-xs) * 0.9);
  min-height: 1.75rem;
}

.btn-sm {
  padding: 0.55rem 1.2rem;
  font-size: var(--fs-xs);
  min-height: 2.15rem;
}

.btn-lg {
  padding: 1rem 2.8rem;
  font-size: var(--fs-base);
  min-height: 3.25rem;
}

.btn-xl {
  padding: 1.25rem 3.5rem;
  font-size: calc(var(--fs-lg) * 0.9);
  min-height: 3.75rem;
  font-weight: 700;
}
```

- [ ] **Step 2: Commit**

```bash
git add base.css
git commit -m "feat: btn-xs and btn-xl size variants"
```

---

### Task 3: Semantic Color Variables in All 24 Palettes

**Files:**
- Modify: all 24 files in `colors/*.css`

- [ ] **Step 1: Add semantic colors to each palette**

Append these variables to each palette's `:root` block. Colors handpicked for harmony.

| Palette | --btn-success | --btn-success-dark | --btn-danger | --btn-danger-dark | --btn-warning | --btn-warning-dark |
|---------|--------------|-------------------|-------------|------------------|--------------|-------------------|
| blue | #1a8a5a | #126842 | #a83a3a | #7a2828 | #a87a1a | #7a5a12 |
| brown | #4a7a42 | #365c30 | #7a3030 | #5c2222 | #7a6a30 | #5c4e22 |
| charcoal | #4a7a5f | #365c46 | #8f5a5a | #6d4242 | #8f7a4a | #6d5c36 |
| coral | #5faa7a | #428a5c | #c04a4a | #963636 | #c0963a | #967228 |
| cyan | #06a870 | #048a5a | #d44a4a | #a83636 | #d4960a | #a87408 |
| electric | #22c55e | #16a34a | #ef4444 | #dc2626 | #f59e0b | #d97706 |
| forest | #3a7a5a | #2a5c42 | #7a3a3a | #5c2a2a | #7a6a2a | #5c4e1e |
| gold | #4a8a42 | #366830 | #a83a2a | #7a2a1e | #b89a2a | #8a7420 |
| gray | #3a6a4a | #2a4e36 | #6a3a3a | #4e2a2a | #6a5a3a | #4e422a |
| green | #3d7a3d | #2d5c2d | #8b3a3a | #6d2c2c | #8b6d3a | #6d552c |
| lavender | #4a8a5c | #366844 | #bf4a4a | #963636 | #bf8a3a | #96682a |
| lime | #2aaa6a | #1e8a52 | #c54a4a | #a83636 | #c5961e | #a87816 |
| mint | #3a9a6a | #2a7a52 | #b44a4a | #8a3636 | #b4882a | #8a681e |
| navy | #1b5c3a | #124228 | #5c2a2a | #421e1e | #5c4a1b | #423612 |
| neon-pink | #22c55e | #16a34a | #ef4444 | #dc2626 | #f59e0b | #d97706 |
| olive | #4a7f4a | #365c36 | #7f3b3b | #5c2a2a | #7f6b2a | #5c4e1e |
| orange | #3a8a4a | #2a6836 | #c43a2a | #961e18 | #c4960a | #967208 |
| pink | #3a8a5a | #2a6842 | #c43838 | #962828 | #c48a3a | #96682a |
| red | #2a8a4a | #1e6836 | #b04040 | #862e2e | #b08a1c | #866814 |
| slate | #3a6a4a | #2a4e36 | #683a3a | #4e2a2a | #685a3a | #4e422a |
| survival | #3d8a4a | #2d6836 | #8a3a3a | #682a2a | #8a7a2d | #685a20 |
| teal | #1a7a4a | #125c36 | #7a3a3a | #5c2a2a | #7a6a1e | #5c4e16 |
| violet | #2ea06a | #1e7a4e | #a03a3a | #7a2a2a | #a07a2e | #7a5c20 |
| wine | #3a7242 | #2a5430 | #723030 | #542222 | #72602f | #544622 |

- [ ] **Step 2: Commit**

```bash
git add colors/
git commit -m "feat: semantic button colors (success/danger/warning) for all 24 palettes"
```

---

### Task 4: Color Variant CSS Classes in base.css

**Files:**
- Modify: `base.css`

- [ ] **Step 1: Add semantic color button classes**

Insert after btn-ghost rules, before hero contrast section:

```css
/* ─── Semantic Button Colors ─────────────────────────────────────────── */
.btn-success { background: var(--btn-success); color: #fff; border-color: var(--btn-success); }
.btn-success:hover { background: var(--btn-success-dark); border-color: var(--btn-success-dark); }

.btn-danger { background: var(--btn-danger); color: #fff; border-color: var(--btn-danger); }
.btn-danger:hover { background: var(--btn-danger-dark); border-color: var(--btn-danger-dark); }

.btn-warning { background: var(--btn-warning); color: #fff; border-color: var(--btn-warning); }
.btn-warning:hover { background: var(--btn-warning-dark); border-color: var(--btn-warning-dark); }

/* Outline variants */
.btn-outline.btn-success,
.btn-ghost.btn-success {
  background: transparent; color: var(--btn-success); border: 2px solid var(--btn-success);
}
.btn-outline.btn-success:hover,
.btn-ghost.btn-success:hover { background: var(--btn-success); color: #fff; }

.btn-outline.btn-danger,
.btn-ghost.btn-danger {
  background: transparent; color: var(--btn-danger); border: 2px solid var(--btn-danger);
}
.btn-outline.btn-danger:hover,
.btn-ghost.btn-danger:hover { background: var(--btn-danger); color: #fff; }

.btn-outline.btn-warning,
.btn-ghost.btn-warning {
  background: transparent; color: var(--btn-warning); border: 2px solid var(--btn-warning);
}
.btn-outline.btn-warning:hover,
.btn-ghost.btn-warning:hover { background: var(--btn-warning); color: #fff; }
```

- [ ] **Step 2: Commit**

```bash
git add base.css
git commit -m "feat: btn-success, btn-danger, btn-warning CSS classes"
```

---

### Task 5: Button States (Loading, Disabled, Success)

**Files:**
- Modify: `base.css`
- Modify: `shared.js`

- [ ] **Step 1: Add state CSS to base.css**

```css
/* ─── Button States ──────────────────────────────────────────────────── */

/* Disabled */
.btn:disabled,
.btn.btn-disabled {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
  transform: none !important;
  box-shadow: none !important;
}

/* Loading spinner */
.btn-loading {
  position: relative;
  color: transparent !important;
  pointer-events: none;
}
.btn-loading * { visibility: hidden; }
.btn-loading::after {
  content: '';
  position: absolute;
  width: 1.1em;
  height: 1.1em;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: btn-spin 0.6s linear infinite;
}
.btn-ghost.btn-loading::after,
.btn-outline.btn-loading::after {
  border-color: rgba(var(--primary-rgb), 0.2);
  border-top-color: var(--primary);
}
@keyframes btn-spin { to { transform: rotate(360deg); } }

/* Success flash */
.btn-success-flash {
  background: var(--btn-success) !important;
  color: #fff !important;
  border-color: var(--btn-success) !important;
  pointer-events: none;
  transition: background 0.3s, color 0.3s;
}

/* Focus visible */
.btn:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}
```

- [ ] **Step 2: Add btnSuccess helper to shared.js**

Add a helper that temporarily shows a check icon on a button, then reverts:

```javascript
/* Button success flash — shows check, then reverts */
window.btnSuccess = function(btn, ms) {
  var origContent = btn.cloneNode(true);
  btn.classList.add('btn-success-flash');
  // Replace content with check SVG
  while (btn.firstChild) btn.removeChild(btn.firstChild);
  var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('width', '1em');
  svg.setAttribute('height', '1em');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '2.5');
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');
  var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
  path.setAttribute('d', 'M20 6L9 17l-5-5');
  svg.appendChild(path);
  btn.appendChild(svg);
  setTimeout(function() {
    btn.classList.remove('btn-success-flash');
    while (btn.firstChild) btn.removeChild(btn.firstChild);
    while (origContent.firstChild) btn.appendChild(origContent.firstChild);
    if (window.lucide) lucide.createIcons();
  }, ms || 2000);
};
```

- [ ] **Step 3: Commit**

```bash
git add base.css shared.js
git commit -m "feat: button states (loading, disabled, success flash, focus-visible)"
```

---

### Task 6: Update button-test.html

**Files:**
- Rewrite: `button-test.html`

- [ ] **Step 1: Rewrite button-test.html with all features**

Complete rewrite to include:
1. Palette dropdown (switches colors/*.css dynamically)
2. Per-module cards with Primary + Ghost on light + dark
3. Sizes section: xs/sm/default/lg/xl side by side
4. Colors section: Primary + Success + Danger + Warning (filled + outline)
5. Icons section: Buttons with sample Lucide icons
6. States section: Normal + Loading + Disabled + Success
7. Lucide CDN loaded, createIcons() called after render

The palette switcher changes `<link id="color-css">` href.

- [ ] **Step 2: Commit**

```bash
git add button-test.html
git commit -m "feat: comprehensive button test page with palette switcher"
```

---

### Task 7: Konfigurator Icon Picker

**Files:**
- Modify: `konfigurator.html`

- [ ] **Step 1: Add icon dropdown in sidebar**

After the button-grid section, add a select dropdown with 17 common icons (arrow-right, chevron-right, phone, mail, calendar, download, external-link, heart, star, check, search, user, home, map-pin, shield, zap, globe).

- [ ] **Step 2: Add change handler**

On change, insert/remove `<i data-lucide="...">` elements into all hero CTA buttons and call `lucide.createIcons()`.

- [ ] **Step 3: Commit**

```bash
git add konfigurator.html
git commit -m "feat: icon picker in konfigurator sidebar"
```

---

### Task 8: Version Bump + Deploy + Verify

**Files:**
- Modify: `VERSION`, `CHANGELOG.md`, `konfigurator.html`

- [ ] **Step 1: Version bump to 3.39.0**

Update VERSION file, all version references in konfigurator.html, CHANGELOG.md.

- [ ] **Step 2: Commit and deploy**

```bash
git add -A
git commit -m "feat: v3.39.0 — button system extension"
bash scripts/deploy.sh
```

- [ ] **Step 3: Verify with Playwright**

1. button-test.html: all features render correctly
2. Palette switcher: colors change harmoniously
3. konfigurator.html: icon picker works
4. Visual check: sizes, states, colors

---

## File Change Summary

| File | Changes |
|------|---------|
| `base.css` | Icon CSS, btn-xs/xl, semantic colors, states, focus-visible |
| `shared.js` | lucide.createIcons(), btnSuccess() helper |
| `konfigurator.html` | Lucide CDN, icon picker, lucide re-init, version |
| `colors/*.css` (24) | --btn-success/danger/warning + dark variants |
| `button-test.html` | Full rewrite with palette switcher |
| `VERSION` | 3.38.2 → 3.39.0 |
| `CHANGELOG.md` | v3.39.0 entry |
