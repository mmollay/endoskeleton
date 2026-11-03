/* SSI Endoskeleton 3.66.0 — Kamera-Vorführung des Bausteins „bewegung“ (Variante live). Wird erst nach dem Klick auf „Kamera starten“ geladen
   (lp/bewegung.js importiert es). Erkennt den Körper mit MediaPipe Pose Landmarker (Apache-2.0), Modell und WASM liegen im Seitenordner unter
   modell/ (scripts/modell-holen.sh) — kein CDN. Das Kamerabild wird nur auf eine Leinwand gezeichnet: nichts wird hochgeladen, nichts im
   Browser gespeichert (kein Cookie, kein Web-Speicher), das <video> hängt nie im Dokument. Aufgenommen werden nur Körperpunkte (Format
   „bewegung v1“, docs/bewegung-format.md), Augen und Mund nie. Ohne Kamera, Erlaubnis, WebGL oder WASM: Hinweis, die Beispielbewegung läuft weiter.
   Für Seiten, die die Aufnahme selbst weiterverwenden: am Ende einer Aufnahme geht am <figure> das Ereignis lp-bewegung:aufnahme
   (detail.text = die fertige .bewegung-Datei), und das Attribut data-lp-modell nennt einen anderen Modellordner. Das Kit selbst sendet nichts.
   Quelle „Datei“ (Zweig lp/bewegung-video, für 3.69.0): ausVideo() wertet statt der Kamera ein gewähltes Video aus — dieselbe Erkennung, dasselbe Format;
   das Video bleibt im Browser (Objekt-URL, nie im Dokument), nur die Körperpunkte werden zur Datei. */
const STANDARD = new URL('../modell/', import.meta.url).href;
const FPS = 30;
const MAX_BILDER = 300;                                    // 10 s bei 30 Bildern je Sekunde
const ABBRUCH = new Error('abgebrochen');                  // Klick oder Tab-Wechsel, während noch geladen wird: leise zurück zum Ausgang
const zustaende = new WeakMap();
const erkenner = new Map();                                 // je Modellordner einmal geladen

const runden = (v, n) => Math.round(v * 10 ** n) / 10 ** n;
const klemme = (v) => Math.min(1, Math.max(0, v));

/** Modellordner: der Wert von data-lp-modell, wenn er ein gewöhnlicher Pfad derselben Herkunft ist (relativ zur Seite oder absolut, nur A–Z a–z 0–9 . _ ~ / -),
    sonst der Standard. Kein Schema (javascript:, data:, https:), kein Anfrageteil, kein %-Code, kein Leerraum; „//host/…“ wird wegen der Herkunft verworfen. */
export function modellordner(wert, seite, standard) {
  if (!wert || !/^[A-Za-z0-9._~\/-]+$/.test(wert)) return standard;
  try {
    const u = new URL(wert, seite);
    return u.origin === new URL(seite).origin ? u.href.replace(/\/?$/, '/') : standard;
  } catch (e) {
    return standard;
  }
}

/** Modell und WASM vom eigenen Server laden (einmal je Seitenaufruf und Ordner); Grafikkarte zuerst, sonst Prozessor. */
function laden(wurzel) {
  if (!erkenner.has(wurzel)) {
    erkenner.set(wurzel, (async () => {
      const { FilesetResolver, PoseLandmarker } = await import(wurzel + 'vision_bundle.js');
      const dateien = await FilesetResolver.forVisionTasks(wurzel + 'wasm');
      const optionen = (delegate) => ({ baseOptions: { modelAssetPath: wurzel + 'pose_landmarker_lite.task', delegate }, runningMode: 'VIDEO', numPoses: 1 });
      try {
        return await PoseLandmarker.createFromOptions(dateien, optionen('GPU'));
      } catch (e) {
        return await PoseLandmarker.createFromOptions(dateien, optionen('CPU'));
      }
    })().catch((e) => { erkenner.delete(wurzel); throw e; }));
  }
  return erkenner.get(wurzel);
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
  return '{"v":1,"name":' + JSON.stringify(d.name) + ',"quelle":' + JSON.stringify(d.quelle || 'eigene-aufnahme') + ',"verhaeltnis":' + d.verhaeltnis + ',"fps":' + FPS + ',"dim":2,"dauer_ms":' + d.dauer_ms
    + ',\n"punkte":' + JSON.stringify(d.punkte) + ',\n"rahmen":[\n' + d.rahmen.map((r) => '[' + r.join(',') + ']').join(',\n') + '\n]}\n';
}

let videoUhr = 0;                                           // Zeitstempel für detectForVideo: steigen über alle Läufe einer Seite (MediaPipe verlangt das)

/** Wartet auf ein Ereignis des Videos (oder auf dessen Fehler). */
function ereignis(video, name) {
  return new Promise((ok, nein) => {
    const weg = () => { video.removeEventListener(name, gut); video.removeEventListener('error', schlecht); };
    const gut = () => { weg(); ok(); }, schlecht = () => { weg(); nein(new Error('video')); };
    video.addEventListener(name, gut);
    video.addEventListener('error', schlecht);
  });
}

/** Quelle „Datei“: wertet ein Video Bild für Bild aus statt der Kamera. video ist ein <video> mit gesetzter src (etwa eine Objekt-URL der gewählten Datei),
    das nicht im Dokument hängen muss. Ab o.start (Sekunden) werden höchstens 10 s im Raster von 30 Bildern je Sekunde angesprungen und erkannt (eine Person).
    o: api (LpBewegung: NAMEN, GESICHT, pruefen), wurzel (Modellordner wie data-lp-modell; sonst ../modell/ neben dem Skript), name, quelle (Format, Standard
    eigene-aufnahme), fortschritt(fertig, gesamt), signal (AbortSignal: bricht zwischen zwei Bildern ab, Fehler 'abgebrochen').
    Ergebnis: { text, bilder, erkannt, von, bis } — text ist die fertige Datei (wie beim Kamera-Download) oder null, wenn in weniger als zwei Bildern jemand zu
    sehen war; erkannt = Bilder mit Person; von/bis in Sekunden. Wirft 'kurz' (ab start bleibt kein Bild), 'video' (nicht abspielbar) oder 'fehler:<Kennung>'. */
export async function ausVideo(video, o) {
  const api = o.api, signal = o.signal;
  const halt = () => { if (signal && signal.aborted) throw ABBRUCH; };
  const lm = await laden(o.wurzel ? modellordner(o.wurzel, document.baseURI, STANDARD) : STANDARD);
  halt();
  if (video.readyState < 2) await ereignis(video, 'loadeddata');
  halt();
  const von = Math.max(0, Number(o.start) || 0), dauer = video.duration;
  const n = Math.min(MAX_BILDER, Math.floor(((Number.isFinite(dauer) ? dauer : 0) - von) * FPS + 1e-6));
  if (!(n >= 2)) throw new Error('kurz');
  const rahmen = [], basis = Math.max(videoUhr + 1, performance.now());
  let erkannt = 0;
  for (let i = 0; i < n; i++) {
    halt();
    const t = von + i / FPS;
    if (Math.abs(video.currentTime - t) > 1e-4) {
      const gesucht = ereignis(video, 'seeked');
      video.currentTime = t;
      await gesucht;
    }
    halt();
    videoUhr = basis + i * 1000 / FPS;
    const pose = lm.detectForVideo(video, videoUhr).landmarks[0] || null;
    if (pose) erkannt++;
    rahmen.push(bild(pose, api.GESICHT));
    if (o.fortschritt) o.fortschritt(i + 1, n);
  }
  const bis = runden(von + n / FPS, 3);
  if (erkannt < 2) return { text: null, bilder: n, erkannt, von, bis };
  const text = alsText({ name: o.name || 'Aus Video', quelle: o.quelle, verhaeltnis: runden(video.videoWidth / video.videoHeight, 4), dauer_ms: Math.round(n * 1000 / FPS), punkte: api.NAMEN, rahmen });
  const kennung = api.pruefen(JSON.parse(text), new Blob([text]).size);
  if (kennung) throw new Error('fehler:' + kennung);
  return { text, bilder: n, erkannt, von, bis };
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
  const ctx = c.cv.getContext('2d'), stil = c.fig.getAttribute('data-lp-stil');
  let strom = null, video = null, lm = null, id = 0, aufnahme = null, text = '', laedt = false, abbruch = false, farbe = '', grund = '', zeit = -1, zts = -1, pose = null;

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

  /** Kamera aus, Hinweis (leer = keiner), Beispielbewegung zurück — für Stopp, Abbruch und jeden Fehler. */
  function zurueck(hinweis) {
    aus();
    c.melde(hinweis);
    beispiel();
  }

  const stopp = () => zurueck('');

  /** Aufnahme beenden: Kamera aus, Bewegung prüfen und im Player vorspielen — oder ehrlich sagen, dass niemand zu sehen war. */
  function fertig() {
    try {
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
      c.fig.dispatchEvent(new CustomEvent('lp-bewegung:aufnahme', { bubbles: true, detail: { text } }));   // für Seiten, die die Aufnahme selbst übernehmen; ohne Fänger geschieht nichts
    } catch (e) {
      zurueck('keinkamera');
    }
  }

  /** Ein Anzeigebild: erkennen (nur bei neuem Kamerabild), Kamerabild und Skelett zeichnen, bei laufender Aufnahme Bilder im 30-Hz-Raster anhängen. Wirft die Erkennung, ist die Kamera aus (keine Fehlerschleife). */
  function schritt(ts) {
    id = requestAnimationFrame(schritt);
    try {
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
    } catch (e) {
      zurueck('keinkamera');
    }
  }

  /** Nach jedem await: wurde inzwischen abgebrochen (Klick) oder der Tab verlassen, wird nichts mehr eingeschaltet. */
  function weiter() {
    if (abbruch || document.hidden) throw ABBRUCH;
  }

  /** Erst jetzt (nach dem Klick) Modell laden und die Kamera anfragen; jeder Fehler endet im Hinweis mit der Beispielbewegung, ein Abbruch ohne Hinweis. */
  async function start() {
    laedt = true;
    abbruch = false;
    try {
      c.sp.pause();
      steuerung.hidden = sichern.hidden = true;
      umText(knopf, true);                                 // „Kamera ausschalten“ gilt auch während des Ladens: ein Klick bricht ab
      c.melde('laedt');
      lm = await laden(modellordner(c.fig.getAttribute('data-lp-modell'), document.baseURI, STANDARD));
      weiter();
      if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) throw new Error('keine Kamera');
      strom = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false });
      weiter();                                            // strom ist gesetzt: im Abbruch beendet aus() die Spuren
      video = document.createElement('video');
      video.muted = video.playsInline = true;
      video.srcObject = strom;
      await video.play();
      weiter();
      c.api.format(c.cv, video.videoWidth / video.videoHeight);
      const st = getComputedStyle(c.cv);
      farbe = st.color;
      grund = st.backgroundColor;
      zeit = zts = -1;
      pose = null;
      auf.hidden = wahl.parentNode.hidden = false;
      c.melde('');
      id = requestAnimationFrame(schritt);
    } catch (e) {
      zurueck(e === ABBRUCH ? '' : 'keinkamera');
    } finally {
      laedt = false;
    }
  }

  /** Tab verlassen oder Seite schließen: eine laufende Kamera geht aus, ein noch ladender Start bricht beim nächsten Schritt ab. */
  function verlassen(ende) {
    if (laedt) abbruch = true;
    if (strom) ende();
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
  document.addEventListener('visibilitychange', () => { if (document.hidden) verlassen(stopp); });
  addEventListener('pagehide', () => verlassen(aus));
  return { laedt: () => laedt, laeuft: () => !!strom, start, stop: stopp, abbrechen: () => { abbruch = true; stopp(); } };
}

export async function umschalten(c) {
  let z = zustaende.get(c.fig);
  if (!z) zustaende.set(c.fig, z = bauen(c));
  if (z.laedt()) return z.abbrechen();
  return z.laeuft() ? z.stop() : z.start();
}
