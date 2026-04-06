/* SSI Endoskeleton — SVG Hero Decoration Generator v1.0
   Generiert organische SVG-Dekorationen für Hero-Bereiche.
   Rein client-seitig, seed-basiert für Reproduzierbarkeit. */

(function () {
  "use strict";

  // ─── Simplex-like Noise (mini) ───
  function seededRandom(seed) {
    var x = Math.sin(seed * 127.1 + 311.7) * 43758.5453;
    return x - Math.floor(x);
  }

  function noise1D(x, seed) {
    var i = Math.floor(x);
    var f = x - i;
    f = f * f * (3 - 2 * f); // smoothstep
    var a = seededRandom(i + seed * 1000);
    var b = seededRandom(i + 1 + seed * 1000);
    return a + (b - a) * f;
  }

  function fbm(x, seed, octaves) {
    var val = 0,
      amp = 1,
      freq = 1,
      max = 0;
    for (var o = 0; o < (octaves || 4); o++) {
      val += noise1D(x * freq, seed + o * 17) * amp;
      max += amp;
      amp *= 0.5;
      freq *= 2;
    }
    return val / max;
  }

  // ─── Color helpers ───
  function hexToRgb(hex) {
    hex = hex.replace("#", "");
    return {
      r: parseInt(hex.substring(0, 2), 16),
      g: parseInt(hex.substring(2, 4), 16),
      b: parseInt(hex.substring(4, 6), 16),
    };
  }

  function rgbStr(r, g, b, a) {
    return "rgba(" + r + "," + g + "," + b + "," + (a || 1) + ")";
  }

  // ─── SVG wrapper ───
  function svgWrap(w, h, content) {
    return (
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' +
      w +
      " " +
      h +
      '" preserveAspectRatio="none">' +
      content +
      "</svg>"
    );
  }

  function encodeSVG(svgStr) {
    return "data:image/svg+xml," + encodeURIComponent(svgStr);
  }

  // ═══ GENERATORS ═══

  // 1. Wellen — 3 layered sine+noise curves
  function generateWaves(seed, color, w, h) {
    var c = hexToRgb(color);
    var layers = "";
    var opacities = [0.15, 0.25, 0.45];
    for (var L = 0; L < 3; L++) {
      var points = "M0," + h;
      var baseY = h * (0.35 + L * 0.2);
      for (var x = 0; x <= w; x += 8) {
        var n = fbm(x * 0.003 + L * 3, seed + L * 7, 4);
        var y = baseY + (n - 0.5) * h * 0.35;
        points += " L" + x + "," + y.toFixed(1);
      }
      points += " L" + w + "," + h + " Z";
      layers +=
        '<path d="' +
        points +
        '" fill="' +
        rgbStr(c.r, c.g, c.b, opacities[L]) +
        '"/>';
    }
    return svgWrap(w, h, layers);
  }

  // 2. Berge — jagged mountain silhouettes
  function generateMountains(seed, color, w, h) {
    var c = hexToRgb(color);
    var layers = "";
    var opacities = [0.12, 0.22, 0.4];
    for (var L = 0; L < 3; L++) {
      var points = "M0," + h;
      var baseY = h * (0.25 + L * 0.2);
      for (var x = 0; x <= w; x += 4) {
        var n = fbm(x * 0.002 + L * 5, seed + L * 13, 5);
        var sharp = Math.pow(n, 1.5);
        var y = baseY + (1 - sharp) * h * 0.45;
        points += " L" + x + "," + y.toFixed(1);
      }
      points += " L" + w + "," + h + " Z";
      layers +=
        '<path d="' +
        points +
        '" fill="' +
        rgbStr(c.r, c.g, c.b, opacities[L]) +
        '"/>';
    }
    return svgWrap(w, h, layers);
  }

  // 3. Blobs — organic floating shapes
  function generateBlobs(seed, color, w, h) {
    var c = hexToRgb(color);
    var shapes = "";
    var count = 4 + Math.floor(seededRandom(seed) * 3);
    for (var i = 0; i < count; i++) {
      var cx = seededRandom(seed + i * 3) * w;
      var cy = h * 0.3 + seededRandom(seed + i * 7) * h * 0.5;
      var r = 40 + seededRandom(seed + i * 11) * 80;
      var points = [];
      for (var a = 0; a < Math.PI * 2; a += 0.3) {
        var nr = r * (0.7 + fbm(a * 2, seed + i * 19, 3) * 0.6);
        points.push(
          (cx + Math.cos(a) * nr).toFixed(1) +
            "," +
            (cy + Math.sin(a) * nr).toFixed(1),
        );
      }
      var opacity = 0.08 + seededRandom(seed + i * 23) * 0.2;
      shapes +=
        '<polygon points="' +
        points.join(" ") +
        '" fill="' +
        rgbStr(c.r, c.g, c.b, opacity) +
        '"/>';
    }
    // Bottom fill
    shapes +=
      '<path d="M0,' +
      h * 0.8 +
      " Q" +
      w * 0.3 +
      "," +
      h * 0.65 +
      " " +
      w * 0.5 +
      "," +
      h * 0.75 +
      " T" +
      w +
      "," +
      h * 0.7 +
      " L" +
      w +
      "," +
      h +
      " L0," +
      h +
      ' Z" fill="' +
      rgbStr(c.r, c.g, c.b, 0.15) +
      '"/>';
    return svgWrap(w, h, shapes);
  }

  // 4. Geometrisch — triangulated mesh
  function generateGeometric(seed, color, w, h) {
    var c = hexToRgb(color);
    var shapes = "";
    var cols = 12,
      rows = 4;
    var cw = w / cols,
      ch = h / rows;
    for (var row = 0; row < rows; row++) {
      for (var col = 0; col < cols; col++) {
        var x = col * cw;
        var y = h * 0.4 + row * ch * 0.8;
        var jx = (seededRandom(seed + col * 7 + row * 31) - 0.5) * cw * 0.6;
        var jy = (seededRandom(seed + col * 13 + row * 41) - 0.5) * ch * 0.6;
        var opacity = 0.05 + seededRandom(seed + col + row * cols) * 0.2;
        if (y + jy > h * 0.35) {
          shapes +=
            '<polygon points="' +
            (x + jx).toFixed(0) +
            "," +
            (y + jy).toFixed(0) +
            " " +
            (x + cw + jx * 0.5).toFixed(0) +
            "," +
            (y - ch * 0.3 + jy).toFixed(0) +
            " " +
            (x + cw * 0.5).toFixed(0) +
            "," +
            (y + ch * 0.7).toFixed(0) +
            '" fill="' +
            rgbStr(c.r, c.g, c.b, opacity) +
            '"/>';
        }
      }
    }
    // Bottom gradient fill
    shapes +=
      '<rect x="0" y="' +
      h * 0.7 +
      '" width="' +
      w +
      '" height="' +
      h * 0.3 +
      '" fill="' +
      rgbStr(c.r, c.g, c.b, 0.08) +
      '"/>';
    return svgWrap(w, h, shapes);
  }

  // 5. Wolken — soft bezier cloud shapes
  function generateClouds(seed, color, w, h) {
    var c = hexToRgb(color);
    var shapes = "";
    var count = 3 + Math.floor(seededRandom(seed) * 3);
    for (var i = 0; i < count; i++) {
      var cx = seededRandom(seed + i * 5) * w;
      var cy = h * 0.4 + seededRandom(seed + i * 9) * h * 0.3;
      var rw = 80 + seededRandom(seed + i * 15) * 120;
      var rh = 30 + seededRandom(seed + i * 21) * 40;
      var opacity = 0.1 + seededRandom(seed + i * 27) * 0.15;
      shapes +=
        '<ellipse cx="' +
        cx.toFixed(0) +
        '" cy="' +
        cy.toFixed(0) +
        '" rx="' +
        rw.toFixed(0) +
        '" ry="' +
        rh.toFixed(0) +
        '" fill="' +
        rgbStr(c.r, c.g, c.b, opacity) +
        '"/>';
      // Sub-bumps
      for (var b = 0; b < 3; b++) {
        var bx = cx + (seededRandom(seed + i * 33 + b) - 0.5) * rw;
        var by = cy - rh * 0.4 - seededRandom(seed + i * 37 + b) * rh * 0.5;
        var br = rh * 0.5 + seededRandom(seed + i * 43 + b) * rh * 0.4;
        shapes +=
          '<circle cx="' +
          bx.toFixed(0) +
          '" cy="' +
          by.toFixed(0) +
          '" r="' +
          br.toFixed(0) +
          '" fill="' +
          rgbStr(c.r, c.g, c.b, opacity * 0.8) +
          '"/>';
      }
    }
    // Soft bottom
    shapes +=
      '<path d="M0,' +
      h * 0.85 +
      " Q" +
      w * 0.25 +
      "," +
      h * 0.75 +
      " " +
      w * 0.5 +
      "," +
      h * 0.82 +
      " T" +
      w +
      "," +
      h * 0.78 +
      " L" +
      w +
      "," +
      h +
      " L0," +
      h +
      ' Z" fill="' +
      rgbStr(c.r, c.g, c.b, 0.12) +
      '"/>';
    return svgWrap(w, h, shapes);
  }

  // 6. Minimal — single elegant curve
  function generateMinimal(seed, color, w, h) {
    var c = hexToRgb(color);
    var cp1x = w * (0.2 + seededRandom(seed) * 0.2);
    var cp1y = h * (0.4 + seededRandom(seed + 1) * 0.3);
    var cp2x = w * (0.6 + seededRandom(seed + 2) * 0.2);
    var cp2y = h * (0.5 + seededRandom(seed + 3) * 0.3);
    var path =
      "M0," +
      h +
      " L0," +
      (h * 0.85).toFixed(0) +
      " C" +
      cp1x.toFixed(0) +
      "," +
      cp1y.toFixed(0) +
      " " +
      cp2x.toFixed(0) +
      "," +
      cp2y.toFixed(0) +
      " " +
      w +
      "," +
      (h * 0.8).toFixed(0) +
      " L" +
      w +
      "," +
      h +
      " Z";
    return svgWrap(
      w,
      h,
      '<path d="' +
        path +
        '" fill="' +
        rgbStr(c.r, c.g, c.b, 0.08) +
        '"/>' +
        '<path d="' +
        path +
        '" fill="none" stroke="' +
        rgbStr(c.r, c.g, c.b, 0.2) +
        '" stroke-width="1.5"/>',
    );
  }

  // ═══ PUBLIC API ═══
  var GENERATORS = {
    waves: generateWaves,
    mountains: generateMountains,
    blobs: generateBlobs,
    geometric: generateGeometric,
    clouds: generateClouds,
    minimal: generateMinimal,
  };

  window.SVGHeroGenerator = {
    styles: Object.keys(GENERATORS),

    generate: function (style, seed, primaryHex) {
      var gen = GENERATORS[style];
      if (!gen) return null;
      seed = seed || Math.floor(Math.random() * 99999);
      primaryHex = primaryHex || "#2e6b2e";
      return {
        svg: gen(seed, primaryHex, 1440, 250),
        dataUri: encodeSVG(gen(seed, primaryHex, 1440, 250)),
        seed: seed,
        style: style,
      };
    },

    apply: function (style, seed, primaryHex) {
      var result = this.generate(style, seed, primaryHex);
      if (!result) return null;
      // Apply to all hero decorations
      var decos = document.querySelectorAll(".hero-decoration");
      decos.forEach(function (el) {
        el.style.backgroundImage = 'url("' + result.dataUri + '")';
        el.style.backgroundSize = "cover";
        el.style.backgroundPosition = "bottom";
      });
      window.currentSVGDecoration = result;
      return result;
    },

    animate: function (enable) {
      var decos = document.querySelectorAll('.hero-decoration');
      decos.forEach(function (el) {
        if (enable) {
          el.style.animation = 'decoFloat 12s ease-in-out infinite alternate';
        } else {
          el.style.animation = '';
        }
      });
      if (enable && !document.getElementById('deco-anim-style')) {
        var style = document.createElement('style');
        style.id = 'deco-anim-style';
        style.textContent = '@keyframes decoFloat { 0% { transform: translateX(0) scaleX(1); } 50% { transform: translateX(-8px) scaleX(1.01); } 100% { transform: translateX(8px) scaleX(0.99); } }';
        document.head.appendChild(style);
      }
    },

    clear: function () {
      var decos = document.querySelectorAll(".hero-decoration");
      decos.forEach(function (el) {
        el.style.backgroundImage = "";
      });
      window.currentSVGDecoration = null;
    },

    randomSeed: function () {
      return Math.floor(Math.random() * 99999);
    },
  };
})();
