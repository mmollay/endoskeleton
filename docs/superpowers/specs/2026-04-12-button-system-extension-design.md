# Button System Extension — Design Spec

**Datum:** 2026-04-12
**Projekt:** SSI Endoskeleton
**Scope:** Icons, Größen, Farb-Varianten, Zustände für das Button-System

---

## 1. Lucide Icons Integration

**Ansatz:** Lucide JS Runtime via CDN. Icons werden per `data-lucide` Attribut deklariert, `lucide.createIcons()` rendert sie als inline SVG.

**CDN:** `https://unpkg.com/lucide@latest/dist/umd/lucide.min.js` (~15KB)

### Syntax

```html
<!-- Icon links (default) -->
<a class="btn btn-primary">
  <i data-lucide="arrow-right"></i> Jetzt starten
</a>

<!-- Icon rechts -->
<a class="btn btn-primary btn-icon-right">
  Weiter <i data-lucide="chevron-right"></i>
</a>

<!-- Icon-only -->
<a class="btn btn-primary btn-icon-only">
  <i data-lucide="phone"></i>
</a>
```

### CSS-Regeln (in base.css)

```css
/* Icon sizing — passt sich an Button-Größe an */
.btn svg { width: 1em; height: 1em; flex-shrink: 0; }
.btn-icon-right { flex-direction: row-reverse; }

/* Icon-only: quadratisch */
.btn-icon-only { padding: 0; width: 2.75rem; height: 2.75rem; }
.btn-icon-only svg { width: 1.25em; height: 1.25em; }
.btn-icon-only.btn-sm { width: 2rem; height: 2rem; }
.btn-icon-only.btn-lg { width: 3.25rem; height: 3.25rem; }
.btn-icon-only.btn-xs { width: 1.75rem; height: 1.75rem; }
.btn-icon-only.btn-xl { width: 3.75rem; height: 3.75rem; }
```

### JS-Initialisierung (in shared.js)

```javascript
// Am Ende von DOMContentLoaded:
if (window.lucide) lucide.createIcons();
```

Für den Konfigurator: nach jedem dynamischen Content-Update (Hero-Wechsel etc.) erneut `lucide.createIcons()` aufrufen.

### Konfigurator-Integration

Im Button-Bereich der Sidebar ein optionales Icon-Dropdown mit den 20-30 häufigsten Icons:
`arrow-right`, `chevron-right`, `phone`, `mail`, `calendar`, `download`, `external-link`, `heart`, `star`, `check`, `plus`, `search`, `settings`, `user`, `home`, `map-pin`, `clock`, `shield`, `zap`, `globe`

Der Konfigurator setzt `data-lucide` auf die Hero-Buttons und ruft `lucide.createIcons()` auf.

---

## 2. Button-Größen

**Bestehend:** `btn-sm`, `btn-lg` — werden beibehalten und ergänzt.

### Neue Klassen

| Klasse | Padding | Font-Size | Min-Height |
|--------|---------|-----------|------------|
| `btn-xs` | 0.35rem 0.75rem | var(--fs-xs) * 0.9 | 1.75rem |
| `btn-sm` | 0.55rem 1.2rem | var(--fs-xs) | 2.15rem |
| (default) | 0.85rem 2rem | var(--fs-sm) | 2.75rem |
| `btn-lg` | 1rem 2.8rem | var(--fs-base) | 3.25rem |
| `btn-xl` | 1.25rem 3.5rem | var(--fs-lg) * 0.9 | 3.75rem |

Diese Werte werden in base.css definiert. Button-Module überschreiben sie NICHT — sie beeinflussen nur Form/Stil (border-radius, shadows, transforms), nicht Größe.

---

## 3. Farb-Varianten (handpicked pro Palette)

### Neue CSS-Variablen pro Palette

Jede `colors/*.css` bekommt 6 neue Variablen:

```css
:root {
  /* Bestehend */
  --primary: #2e6b2e;
  --primary-dark: #1e4d1e;
  --primary-light: #4a9e4a;
  --primary-rgb: 46, 107, 46;
  
  /* Neu: Semantische Button-Farben */
  --btn-success: #3d7a3d;
  --btn-success-dark: #2d5c2d;
  --btn-danger: #8b3a3a;
  --btn-danger-dark: #6d2c2c;
  --btn-warning: #8b6d3a;
  --btn-warning-dark: #6d552c;
}
```

**Harmonielehre:** Die Farben werden pro Palette manuell gewählt, so dass:
- Sättigung und Helligkeit zur Primary-Farbe passen
- Danger warm-rot ist (nicht grell), Warning warm-orange/gold
- Success grün-gerichtet ist, aber sich von Primary unterscheidet wenn Primary bereits grün ist
- Dunkle Paletten (charcoal, navy, slate) bekommen gesättigtere Varianten

### CSS-Klassen (in base.css)

```css
.btn-success { background: var(--btn-success); color: #fff; }
.btn-success:hover { background: var(--btn-success-dark); }

.btn-danger { background: var(--btn-danger); color: #fff; }
.btn-danger:hover { background: var(--btn-danger-dark); }

.btn-warning { background: var(--btn-warning); color: #fff; }
.btn-warning:hover { background: var(--btn-warning-dark); }
```

Outline/Ghost-Varianten:
```css
.btn-success.btn-outline { background: transparent; color: var(--btn-success); border-color: var(--btn-success); }
.btn-danger.btn-outline { background: transparent; color: var(--btn-danger); border-color: var(--btn-danger); }
.btn-warning.btn-outline { background: transparent; color: var(--btn-warning); border-color: var(--btn-warning); }
```

### 24 Paletten

Alle 24 Paletten müssen manuell mit passenden Success/Danger/Warning Farben versehen werden:
blue, brown, charcoal, coral, cyan, electric, forest, gold, gray, green, lavender, lime, mint, navy, neon-pink, olive, orange, pink, red, slate, survival, teal, violet, wine

---

## 4. Button-Zustände

### Loading State

```html
<button class="btn btn-primary btn-loading" disabled>
  Wird gesendet...
</button>
```

```css
.btn-loading { position: relative; color: transparent !important; pointer-events: none; }
.btn-loading::after {
  content: '';
  position: absolute;
  width: 1em; height: 1em;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: btn-spin 0.6s linear infinite;
}
@keyframes btn-spin { to { transform: rotate(360deg); } }

/* Ghost/Outline loading: dunklerer Spinner */
.btn-ghost.btn-loading::after,
.btn-outline.btn-loading::after {
  border-color: rgba(var(--primary-rgb), 0.2);
  border-top-color: var(--primary);
}
```

### Disabled State

```css
.btn:disabled, .btn.btn-disabled {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
  transform: none !important;
  box-shadow: none !important;
}
```

### Success State (temporär nach Aktion)

```css
.btn-success-state {
  background: var(--btn-success) !important;
  color: #fff !important;
  pointer-events: none;
}
```

JS-Helper in shared.js:
```javascript
function btnSuccess(btn, duration) {
  btn.classList.add('btn-success-state');
  setTimeout(function() { btn.classList.remove('btn-success-state'); }, duration || 2000);
}
```

---

## 5. Testseite (button-test.html)

Die Testseite wird erweitert um:
- **Farbpaletten-Dropdown** oben — wechselt zwischen allen 24 Paletten
- **Pro Modul-Karte:** Primary + Ghost + Success + Danger auf hell und dunkel
- **Größen-Sektion:** xs bis xl nebeneinander
- **Icon-Sektion:** Buttons mit verschiedenen Icons
- **Zustands-Sektion:** Loading, Disabled, Success-State

---

## 6. Dateien die geändert werden

| Datei | Änderung |
|-------|----------|
| `base.css` | btn-xs, btn-xl, Icon-Styling, Farb-Varianten, Zustände |
| `shared.js` | Lucide Init, btnSuccess() Helper |
| `konfigurator.html` | Lucide CDN Script, Icon-Picker, Größen-Picker |
| `colors/*.css` (24 Dateien) | --btn-success, --btn-danger, --btn-warning + dark Varianten |
| `button-test.html` | Palette-Switcher, alle neuen Features anzeigen |

Button-Module (`buttons/*.css`) bleiben **unverändert**.
