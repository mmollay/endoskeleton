/* SSI Endoskeleton 3.64.0 — Kamera-Vorführung des Bausteins „bewegung“ (Variante live). Wird erst nach dem Klick auf „Kamera starten“ geladen
   (lp/bewegung.js importiert es). Erkennt den Körper mit MediaPipe Pose Landmarker (Apache-2.0), Modell und WASM liegen im Seitenordner unter
   modell/ (scripts/modell-holen.sh) — kein CDN. Das Kamerabild wird nur auf eine Leinwand gezeichnet: nichts wird hochgeladen, nichts im
   Browser gespeichert (kein Cookie, kein Web-Speicher), das <video> hängt nie im Dokument. Aufgenommen werden nur Körperpunkte (Format
   „bewegung v1“, docs/bewegung-format.md), Augen und Mund nie. Ohne Kamera, Erlaubnis, WebGL oder WASM: Hinweis, die Beispielbewegung läuft weiter. */
const WURZEL = new URL('../modell/', import.meta.url).href;
const FPS = 30;
const MAX_BILDER = 300;                                    // 10 s bei 30 Bildern je Sekunde
const zustaende = new WeakMap();
let erkenner = null;

const runden = (v, n) => Math.round(v * 10 ** n) / 10 ** n;
const klemme = (v) => Math.min(1, Math.max(0, v));

/** Modell und WASM vom eigenen Server laden (einmal je Seitenaufruf); Grafikkarte zuerst, sonst Prozessor. */
function laden() {
  erkenner ||= (async () => {
    const { FilesetResolver, PoseLandmarker } = await import(WURZEL + 'vision_bundle.js');
    const dateien = await FilesetResolver.forVisionTasks(WURZEL + 'wasm');
    const optionen = (delegate) => ({ baseOptions: { modelAssetPath: WURZEL + 'pose_landmarker_lite.task', delegate }, runningMode: 'VIDEO', numPoses: 1 });
    try {
      return await PoseLandmarker.createFromOptions(dateien, optionen('GPU'));
    } catch (e) {
      return await PoseLandmarker.createFromOptions(dateien, optionen('CPU'));
    }
  })().catch((e) => { erkenner = null; throw e; });
  return erkenner;
}

/** Ein Bild des Formats: x, y, Sichtbarkeit je Punkt; Gesichtspunkte und unsichtbare Punkte sind 0, 0, 0. */
function bild(pose, gesicht) {
  const aus = [];
  for (let i = 0; i < 33; i++) {
    const p = pose && gesicht.indexOf(i) < 0 ? pose[i] : null;
    const s = p ? runden(klemme(p.visibility || 0), 3) : 0;
    aus.push(...(s ? [runden(klemme(p.x), 3), runden(klemme(p.y), 3), s] : [0, 0, 0]));
  }
  return aus;
}

/** Die Datei wie lp/musterbuch/bew/beispiel-winken.bewegung: Kopf, Punktnamen, ein Bild je Zeile. */
function alsText(d) {
  return '{"v":1,"name":' + JSON.stringify(d.name) + ',"quelle":"eigene-aufnahme","verhaeltnis":' + d.verhaeltnis + ',"fps":' + FPS + ',"dim":2,"dauer_ms":' + d.dauer_ms
    + ',\n"punkte":' + JSON.stringify(d.punkte) + ',\n"rahmen":[\n' + d.rahmen.map((r) => '[' + r.join(',') + ']').join(',\n') + '\n]}\n';
}

function herunterladen(text) {
  const t = new Date(), z = (n) => String(n).padStart(2, '0');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([text], { type: 'application/json' }));
  a.download = 'aufnahme-' + t.getFullYear() + z(t.getMonth() + 1) + z(t.getDate()) + '-' + z(t.getHours()) + z(t.getMinutes()) + '.bewegung';
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 4000);
}

function umText(knopf, an) {
  knopf.textContent = knopf.getAttribute(an ? 'data-an' : 'data-aus');
}

/** Verdrahtet eine Vorführung einmal. c kommt von lp/bewegung.js (fig, sec, cv, sp, el, melde, api, daten, still). */
function bauen(c) {
  const knopf = c.el('kamera'), auf = c.el('aufnehmen'), sichern = c.el('speichern'), wahl = c.el('bild'), steuerung = c.el('steuerung');
  const ctx = c.cv.getContext('2d'), stil = c.fig.getAttribute('data-lp-stil') || 'strich';
  let strom = null, video = null, lm = null, id = 0, aufnahme = null, text = '', laedt = false, farbe = '', grund = '', zeit = -1, zts = -1, pose = null;

  /** Die Beispielbewegung wieder zeigen (nach Stopp, Fehler oder leerer Aufnahme). */
  function beispiel() {
    c.api.format(c.cv, c.daten.verhaeltnis);
    c.sp.setze(c.daten, c.still);
  }

  /** Kamera aus (alle Spuren beenden), Bedienung zurück auf Ausgang. */
  function aus() {
    cancelAnimationFrame(id);
    id = 0;
    if (strom) strom.getTracks().forEach((t) => t.stop());
    if (video) video.srcObject = null;
    strom = video = aufnahme = null;
    umText(knopf, false);
    umText(auf, false);
    auf.hidden = wahl.parentNode.hidden = true;
    steuerung.hidden = false;
  }

  function stopp() {
    aus();
    c.melde('');
    beispiel();
  }

  /** Aufnahme beenden: Kamera aus, Bewegung prüfen und im Player vorspielen — oder ehrlich sagen, dass niemand zu sehen war. */
  function fertig() {
    const a = aufnahme, v = video.videoWidth / video.videoHeight;
    aus();
    if (!a.erkannt || a.rahmen.length < 2) {
      c.melde('leer');
      return beispiel();
    }
    const d = { name: 'Eigene Aufnahme', verhaeltnis: runden(v, 4), dauer_ms: Math.round(a.rahmen.length * 1000 / FPS), punkte: c.api.NAMEN, rahmen: a.rahmen };
    text = alsText(d);
    if (c.api.pruefen(JSON.parse(text), new Blob([text]).size)) {
      c.melde('fehler');
      return beispiel();
    }
    c.api.format(c.cv, d.verhaeltnis);
    c.sp.setze(JSON.parse(text), c.still);
    sichern.hidden = false;
    c.melde('fertig');
  }

  /** Ein Anzeigebild: erkennen (nur bei neuem Kamerabild), Kamerabild und Skelett zeichnen, bei laufender Aufnahme Bilder im 30-Hz-Raster anhängen. */
  function schritt(ts) {
    id = requestAnimationFrame(schritt);
    if (video.readyState < 2) return;
    if (video.currentTime !== zeit && ts > zts) {
      zeit = video.currentTime;
      zts = ts;
      pose = lm.detectForVideo(video, ts).landmarks[0] || null;
    }
    const w = c.cv.width, h = c.cv.height, ueber = wahl.value === 'kamera', f = bild(pose, c.api.GESICHT);
    if (ueber) {
      ctx.save();
      ctx.clearRect(0, 0, w, h);
      ctx.scale(-1, 1);
      ctx.drawImage(video, -w, 0, w, h);
      ctx.restore();
    }
    c.api.zeichne(ctx, { dim: 2, verhaeltnis: w / h, rahmen: [f] }, 0, w, h, { farbe, stil, spiegeln: true, ueber, schatten: grund });
    if (aufnahme) {
      if (pose) aufnahme.erkannt++;
      const faellig = Math.min(MAX_BILDER, Math.floor((ts - aufnahme.t0) * FPS / 1000) + 1);
      while (aufnahme.rahmen.length < faellig) aufnahme.rahmen.push(f);
      if (aufnahme.rahmen.length >= MAX_BILDER) fertig();
    }
  }

  /** Erst jetzt (nach dem Klick) Modell laden und die Kamera anfragen; jeder Fehler endet im Hinweis mit der Beispielbewegung. */
  async function start() {
    laedt = true;
    try {
      c.sp.pause();
      steuerung.hidden = sichern.hidden = true;
      c.melde('laedt');
      lm = await laden();
      if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) throw new Error('keine Kamera');
      strom = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false });
      video = document.createElement('video');
      video.muted = video.playsInline = true;
      video.srcObject = strom;
      await video.play();
      c.api.format(c.cv, video.videoWidth / video.videoHeight);
      const st = getComputedStyle(c.cv);
      farbe = st.color;
      grund = st.backgroundColor;
      zeit = zts = -1;
      pose = null;
      umText(knopf, true);
      auf.hidden = wahl.parentNode.hidden = false;
      c.melde('');
      id = requestAnimationFrame(schritt);
    } catch (e) {
      aus();
      c.melde('keinkamera');
      beispiel();
    } finally {
      laedt = false;
    }
  }

  auf.addEventListener('click', () => {
    if (!strom) return;
    if (aufnahme) return fertig();
    sichern.hidden = true;
    aufnahme = { t0: performance.now(), rahmen: [], erkannt: 0 };
    umText(auf, true);
    c.melde('aufnahme');
  });
  sichern.addEventListener('click', () => { if (text) herunterladen(text); });
  document.addEventListener('visibilitychange', () => { if (document.hidden && strom) stopp(); });
  addEventListener('pagehide', () => { if (strom) aus(); });
  return { laedt: () => laedt, laeuft: () => !!strom, start, stop: stopp };
}

export async function umschalten(c) {
  let z = zustaende.get(c.fig);
  if (!z) zustaende.set(c.fig, z = bauen(c));
  if (z.laedt()) return;
  return z.laeuft() ? z.stop() : z.start();
}
