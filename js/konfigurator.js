(function (global) {
  "use strict";

  var STATES = {
    WELCOME: "welcome",
    SCANNING: "scanning",
    PREVIEW: "preview",
  };

  var _currentState = STATES.WELCOME;
  var _currentDomain = null;
  var _scanCache = {};

  // DOM element references
  var _el = {};

  // --- DOM helpers ------------------------------------------------------------

  function _cacheElements() {
    _el.welcome = document.getElementById("konfig-welcome");
    _el.scanning = document.getElementById("konfig-scanning");
    _el.preview = document.getElementById("konfig-preview");
    _el.domainInput = document.getElementById("konfig-domain-input");
    _el.domainButton = document.getElementById("konfig-domain-button");
    _el.domainBar = document.getElementById("konfig-domain-bar");
    _el.domainSelect = document.getElementById("konfig-domain-select");
    _el.domainBadge = document.getElementById("konfig-domain-badge");
    _el.addDomain = document.getElementById("konfig-add-domain");
    _el.scanProgress = document.getElementById("konfig-scan-progress");
    _el.scanDomain = document.getElementById("konfig-scan-domain");
    _el.historyList = document.getElementById("konfig-history-list");
    _el.deepenBtn = document.getElementById("konfig-deepen-btn");
    _el.generateBtn = document.getElementById("konfig-generate-btn");
    _el.presetRec = document.getElementById("konfig-preset-recommendation");
  }

  function _bindEvents() {
    if (_el.domainButton) {
      _el.domainButton.addEventListener("click", _onScanClick);
    }

    if (_el.domainInput) {
      _el.domainInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
          _onScanClick();
        }
      });
    }

    if (_el.addDomain) {
      _el.addDomain.addEventListener("click", _onAddDomain);
    }

    if (_el.domainSelect) {
      _el.domainSelect.addEventListener("change", _onDomainSwitch);
    }

    if (_el.deepenBtn) {
      _el.deepenBtn.addEventListener("click", _onDeepen);
    }

    if (_el.generateBtn) {
      _el.generateBtn.addEventListener("click", _onGenerate);
    }
  }

  // --- State machine ----------------------------------------------------------

  function _switchState(newState) {
    _currentState = newState;

    _setActive(_el.welcome, newState === STATES.WELCOME);
    _setActive(_el.scanning, newState === STATES.SCANNING);
    _setActive(_el.preview, newState === STATES.PREVIEW);

    // Domain bar only visible in SCANNING / PREVIEW
    _setActive(_el.domainBar, newState !== STATES.WELCOME);

    // Body class for layout adjustments (nav, sidebar offset)
    document.body.classList.toggle(
      "konfig-has-bar",
      newState !== STATES.WELCOME,
    );
  }

  function _setActive(el, active) {
    if (!el) {
      return;
    }
    if (active) {
      el.classList.add("active");
    } else {
      el.classList.remove("active");
    }
  }

  // --- Scan orchestration -----------------------------------------------------

  function _startScan(rawInput) {
    var domain = ScannerClient.cleanDomain(rawInput);

    if (!domain || domain.indexOf(".") === -1) {
      alert("Bitte eine gueltige Domain eingeben (z.B. beispiel.at)");
      return;
    }

    _currentDomain = domain;
    _switchState(STATES.SCANNING);

    if (_el.scanDomain) {
      _el.scanDomain.textContent = domain;
    }

    // Check cache first (async!)
    ScannerClient.checkCache(domain).then(function (cached) {
      if (cached && cached.domain) {
        _scanCache[domain] = cached;
        _transitionToPreview(cached);
        return;
      }

      // Quick scan - blocks until initial data available
      ScannerClient.quickScan(domain)
        .then(function (scanData) {
          _scanCache[domain] = scanData;
          _transitionToPreview(scanData);

          // Fire full scan in background
          ScannerClient.fullScan(domain)
            .then(function (fullData) {
              _scanCache[domain] = fullData;
              _updateBadge(fullData);
              // If full scan brings KI analysis, re-evaluate preset
              if (fullData.ki_analysis && fullData.ki_analysis.analysis) {
                _highlightRecommendedPreset(fullData);
              } else {
                ContentInjector.inject(fullData);
              }
            })
            .catch(function () {
              /* silently ignore */
            });

          // Start polling for incremental updates
          ScannerClient.startPolling(domain, function (updateData) {
            _scanCache[domain] = updateData;
            ContentInjector.inject(updateData);
            _updateBadge(updateData);
          });
        })
        .catch(function (err) {
          console.error("[Konfigurator] quickScan error:", err);
          _switchState(STATES.WELCOME);
          alert("Scan fehlgeschlagen. Bitte versuche es erneut.");
        });
    }); // end checkCache.then
  }

  // --- Preview transition ------------------------------------------------------

  /**
   * Check if scan data represents a valid, non-empty scan.
   * Invalid scans should not be saved to history.
   */
  function _isValidScan(scanData) {
    if (!scanData) return false;
    if (scanData.error) return false;
    var hasTitle = !!(scanData.title && scanData.title.trim());
    var hasWords = (scanData.total_words || 0) > 10;
    var hasPages =
      scanData.pages && scanData.pages.length > 0 && scanData.pages[0].title;
    return hasTitle || hasWords || hasPages;
  }

  function _transitionToPreview(scanData) {
    var domain = scanData.domain || _currentDomain;

    // Reject invalid/empty scans
    if (!_isValidScan(scanData)) {
      _switchState(STATES.WELCOME);
      alert(
        "Keine Daten fuer " +
          domain +
          " gefunden.\n\n" +
          "Bitte pruefe die Domain und versuche es erneut.",
      );
      return;
    }

    _switchState(STATES.PREVIEW);

    _updateDomainSelect(domain);
    _updateBadge(scanData);

    ContentInjector.captureOriginals();

    _saveToHistory(domain);
    _renderHistory();

    _highlightRecommendedPreset(scanData);
    _updateHashDomain(domain);
  }

  // --- Domain select dropdown --------------------------------------------------

  function _updateDomainSelect(activeDomain) {
    if (!_el.domainSelect) {
      return;
    }

    // Rebuild dropdown from full history
    while (_el.domainSelect.firstChild) {
      _el.domainSelect.removeChild(_el.domainSelect.firstChild);
    }

    var history = _loadHistory();

    // Ensure active domain is in the list
    if (activeDomain && history.indexOf(activeDomain) === -1) {
      history.unshift(activeDomain);
    }

    history.forEach(function (domain) {
      var opt = document.createElement("option");
      opt.value = domain;
      opt.textContent = domain;
      if (domain === activeDomain) opt.selected = true;
      _el.domainSelect.appendChild(opt);
    });
  }

  function _updateBadge(scanData) {
    if (!_el.domainBadge) {
      return;
    }
    var count = scanData && scanData.pages ? scanData.pages.length : 0;
    _el.domainBadge.textContent = count + (count === 1 ? " Seite" : " Seiten");
    _el.domainBadge.style.display = count > 0 ? "" : "none";
  }

  // --- Domain event handlers ---------------------------------------------------

  function _onScanClick() {
    var raw = _el.domainInput ? _el.domainInput.value : "";
    if (!raw.trim()) {
      return;
    }
    _startScan(raw.trim());
  }

  function _onAddDomain() {
    var raw = prompt("Weitere Domain scannen:");
    if (!raw || !raw.trim()) {
      return;
    }
    _startScan(raw.trim());
  }

  function _onDomainSwitch() {
    var domain = _el.domainSelect ? _el.domainSelect.value : "";
    if (!domain) {
      return;
    }

    // Check in-memory cache first
    if (_scanCache[domain]) {
      _currentDomain = domain;
      _highlightRecommendedPreset(_scanCache[domain]);
      _updateBadge(_scanCache[domain]);
      _updateHashDomain(domain);
      return;
    }

    // Otherwise start scan (checks API cache, then quick scan)
    _startScan(domain);
  }

  // --- History management ------------------------------------------------------

  var HISTORY_KEY = "konfig-domains";
  var HISTORY_MAX = 10;

  function _loadHistory() {
    try {
      var raw = localStorage.getItem(HISTORY_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) {
      return [];
    }
  }

  function _saveToHistory(domain) {
    if (!domain) {
      return;
    }
    var history = _loadHistory();
    history = history.filter(function (d) {
      return d !== domain;
    });
    history.unshift(domain);
    if (history.length > HISTORY_MAX) {
      history = history.slice(0, HISTORY_MAX);
    }
    try {
      localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
    } catch (e) {
      /* quota exceeded, ignore */
    }
  }

  function _renderHistory() {
    if (!_el.historyList) {
      return;
    }

    while (_el.historyList.firstChild) {
      _el.historyList.removeChild(_el.historyList.firstChild);
    }

    var history = _loadHistory();
    if (!history.length) {
      _el.historyList.parentElement.style.display = "none";
      return;
    }
    _el.historyList.parentElement.style.display = "";

    history.forEach(function (domain) {
      var wrapper = document.createElement("div");
      wrapper.className = "konfig-history-wrapper";

      var btn = document.createElement("button");
      btn.className = "konfig-history-item";
      btn.textContent = domain;
      btn.addEventListener("click", function () {
        _startScan(domain);
      });

      var delBtn = document.createElement("button");
      delBtn.className = "konfig-history-delete";
      delBtn.textContent = "×";
      delBtn.title = "Aus Verlauf entfernen";
      delBtn.addEventListener("click", function (e) {
        e.stopPropagation();
        _removeFromHistory(domain);
        _renderHistory();
      });

      wrapper.appendChild(btn);
      wrapper.appendChild(delBtn);
      _el.historyList.appendChild(wrapper);
    });
  }

  function _removeFromHistory(domain) {
    var history = _loadHistory();
    var idx = history.indexOf(domain);
    if (idx !== -1) {
      history.splice(idx, 1);
      try {
        localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
      } catch (e) {
        /* ignore */
      }
    }
  }

  // --- Deepen & Generate -------------------------------------------------------

  function _getSelectedPreset() {
    // 1. Check window.currentPreset (set by applyPreset in demo.html)
    if (window.currentPreset) return window.currentPreset;
    // 2. Check active preset card
    var card = document.querySelector(
      ".preset-card.active, .preset-card.selected",
    );
    if (card) return card.dataset.preset || card.getAttribute("data-preset");
    // 3. Check URL hash
    var hash = window.location.hash;
    var match = hash.match(/preset=([^&]+)/);
    if (match) return decodeURIComponent(match[1]);
    // 4. Default
    return "default";
  }

  function _setButtonLoading(btn, loading) {
    if (!btn) {
      return;
    }
    if (loading) {
      btn.dataset.originalText = btn.textContent;
      btn.textContent = "Wird geladen\u2026";
      btn.disabled = true;
    } else {
      btn.textContent = btn.dataset.originalText || btn.textContent;
      btn.disabled = false;
    }
  }

  function _buildGeneratePayload(format) {
    var preset = _getSelectedPreset();
    var domain = _currentDomain;
    var cached = domain ? _scanCache[domain] : null;
    var overrides = {};

    // Only include design-relevant overrides (API rejects unknown fields)
    var allowedKeys = [
      "layout",
      "hero",
      "color",
      "charakter",
      "spacing",
      "animation",
      "font",
      "buttons",
      "navStyle",
      "navLayout",
      "width",
      "footer",
      "theme",
    ];
    if (typeof getState === "function") {
      try {
        var state = getState() || {};
        for (var k = 0; k < allowedKeys.length; k++) {
          if (state[allowedKeys[k]]) {
            overrides[allowedKeys[k]] = state[allowedKeys[k]];
          }
        }
      } catch (e) {
        /* ignore */
      }
    }

    // Fallback: if overrides still empty, parse from URL hash
    if (Object.keys(overrides).length === 0) {
      var hash = window.location.hash.substring(1);
      if (hash) {
        var params = hash.split("&");
        for (var p = 0; p < params.length; p++) {
          var kv = params[p].split("=");
          if (kv.length === 2 && allowedKeys.indexOf(kv[0]) !== -1) {
            overrides[kv[0]] = decodeURIComponent(kv[1]);
          }
        }
      }
    }

    // Build content object from scan data (API requires company + pages)
    var companyName = domain;
    if (cached && cached.title) {
      companyName = cached.title.split(/[–\-|]/)[0].trim();
    }
    var content = {
      company: companyName,
      domain: domain,
      pages: [],
    };
    if (cached) {
      content.logo =
        cached.logo_url || (cached.logo && cached.logo.src) || null;
      content.colors = cached.colors || [];
      content.contact = cached.contact || {};
      content.pages = cached.pages || [];
    }

    return {
      preset: preset,
      format: format,
      domain: domain,
      overrides: overrides,
      content: content,
    };
  }

  function _onDeepen() {
    // Open full-screen preview: demo.html with current preset settings (no sidebar)
    var hash = window.location.hash;
    var previewUrl = "demo.html" + hash;
    window.open(previewUrl, "_blank");
  }

  function _onGenerate() {
    // Export current config as JSON (for /endo pipeline or Panel)
    var preset = _getSelectedPreset();
    var domain = _currentDomain;
    var cached = domain ? _scanCache[domain] : null;
    var state = typeof getState === "function" ? getState() : {};

    var config = {
      domain: domain,
      preset: preset,
      overrides: state,
      scan: cached || null,
      generated: new Date().toISOString(),
    };

    // Copy to clipboard
    var json = JSON.stringify(config, null, 2);
    navigator.clipboard
      .writeText(json)
      .then(function () {
        alert(
          "Konfiguration kopiert! (" +
            domain +
            ", Preset: " +
            preset +
            ")\n\nVerwende /endo " +
            domain +
            " oder Panel zur Generierung.",
        );
      })
      .catch(function () {
        prompt("Konfiguration (Ctrl+C zum Kopieren):", json);
      });
  }

  // --- Preset recommendation ---------------------------------------------------

  /**
   * Map KI analysis (branch + mood) to the best preset name.
   * Uses branch keywords and design_mood to find the optimal match.
   */
  function _resolvePreset(scanData) {
    var ki =
      scanData.ki_analysis && scanData.ki_analysis.analysis
        ? scanData.ki_analysis.analysis
        : {};
    var branch = (ki.branch || scanData.branch || "").toLowerCase();
    var mood = (ki.design_mood || "").toLowerCase();

    // Branch keyword → preset mapping
    var BRANCH_PRESETS = {
      gastro: "gastro",
      restaurant: "gastro",
      cafe: "gastro",
      küche: "gastro",
      catering: "gastro",
      hotel: "elegant",
      tourismus: "outdoor",
      pension: "outdoor",
      ferienwohn: "outdoor",
      arzt: "medical",
      praxis: "medical",
      therapie: "medical",
      pflege: "medical",
      gesundheit: "medical",
      wellness: "wellness",
      yoga: "wellness",
      massage: "wellness",
      spa: "wellness",
      anwalt: "kanzlei",
      kanzlei: "kanzlei",
      notar: "kanzlei",
      recht: "kanzlei",
      steuerber: "kanzlei",
      architekt: "architekt",
      bau: "architekt",
      immobil: "architekt",
      planungsbüro: "architekt",
      handwerk: "softcraft",
      tischler: "softcraft",
      installat: "softcraft",
      design: "creative",
      agentur: "creative",
      kreativ: "kreativ",
      werbung: "creative",
      foto: "portfolio",
      kunst: "portfolio",
      portfolio: "portfolio",
      software: "tech",
      "it-": "tech",
      digital: "tech",
      app: "startup",
      saas: "startup",
      startup: "startup",
      schule: "medical",
      bildung: "medical",
      kurs: "medical",
      coaching: "corporate",
      sport: "outdoor",
      fitness: "outdoor",
      verein: "outdoor",
      natur: "natur",
      bio: "natur",
      öko: "natur",
      garten: "natur",
      kinder: "kinder",
      spielgruppe: "kinder",
      kindergarten: "kinder",
      beratung: "corporate",
      consulting: "corporate",
      management: "corporate",
      versicherung: "corporate",
      mode: "boutique",
      boutique: "boutique",
      schmuck: "boutique",
      luxus: "elegant",
    };

    // Mood → preset fallback
    var MOOD_PRESETS = {
      modern: "corporate",
      minimalistisch: "minimal",
      minimal: "minimal",
      elegant: "elegant",
      klassisch: "gastro",
      verspielt: "playful",
      technisch: "tech",
      dunkel: "cinematic",
      warm: "softcraft",
      kreativ: "creative",
      professionell: "corporate",
      luxuriös: "elegant",
      dynamisch: "startup",
      clean: "minimal",
      bold: "creative",
    };

    // Try branch keywords first
    for (var keyword in BRANCH_PRESETS) {
      if (branch.indexOf(keyword) !== -1) {
        return BRANCH_PRESETS[keyword];
      }
    }

    // Try mood
    for (var m in MOOD_PRESETS) {
      if (mood.indexOf(m) !== -1) {
        return MOOD_PRESETS[m];
      }
    }

    // Default
    return "corporate";
  }

  function _highlightRecommendedPreset(scanData) {
    var presetName = _resolvePreset(scanData);

    // Remove previous highlights
    var cards = document.querySelectorAll(".preset-card");
    for (var i = 0; i < cards.length; i++) {
      cards[i].classList.remove("recommended");
    }

    // Auto-apply the preset
    if (typeof window.applyPreset === "function") {
      window.applyPreset(presetName);
    }

    // Highlight the card
    var matched = document.querySelector(
      '.preset-card[data-preset="' + presetName + '"]',
    );
    if (matched) {
      matched.classList.add("recommended");
    }

    // Show recommendation badge
    if (_el.presetRec) {
      var ki =
        scanData.ki_analysis && scanData.ki_analysis.analysis
          ? scanData.ki_analysis.analysis
          : {};
      var reason = ki.branch || scanData.branch || "";
      if (reason && reason.indexOf("Unbekannt") === -1) {
        _el.presetRec.textContent =
          "Empfohlen für " +
          reason +
          ": " +
          presetName.charAt(0).toUpperCase() +
          presetName.slice(1);
      } else {
        _el.presetRec.textContent =
          "Empfohlen: " +
          presetName.charAt(0).toUpperCase() +
          presetName.slice(1);
      }
      _el.presetRec.style.display = "";
    }

    // Re-inject scan content after preset switch (preset resets placeholders)
    ContentInjector.inject(scanData);
  }

  // --- URL hash ----------------------------------------------------------------

  function _getDomainFromHash() {
    var hash = window.location.hash;
    if (!hash || hash.length < 2) {
      return null;
    }
    try {
      var params = new URLSearchParams(hash.slice(1));
      return params.get("domain") || null;
    } catch (e) {
      return null;
    }
  }

  function _updateHashDomain(domain) {
    if (!domain) {
      return;
    }
    try {
      var hash = window.location.hash;
      var params = new URLSearchParams(hash ? hash.slice(1) : "");
      params.set("domain", domain);
      window.location.hash = params.toString();
    } catch (e) {
      /* ignore */
    }
  }

  // --- Public init -------------------------------------------------------------

  function init() {
    _cacheElements();
    _bindEvents();
    _renderHistory();

    // Hook into applyPreset — re-inject scan content after every preset switch
    var origApplyPreset = window.applyPreset;
    if (origApplyPreset) {
      window.applyPreset = function (name) {
        origApplyPreset(name);
        // Re-inject scan data after preset resets the placeholders
        if (_currentDomain && _scanCache[_currentDomain]) {
          setTimeout(function () {
            ContentInjector.inject(_scanCache[_currentDomain]);
            // Force German language
            if (typeof applyLang === "function") applyLang("de");
          }, 50);
        }
      };
    }

    // Auto-scan from URL hash
    var hashDomain = _getDomainFromHash();
    if (hashDomain) {
      if (_el.domainInput) {
        _el.domainInput.value = hashDomain;
      }
      _startScan(hashDomain);
    } else {
      _switchState(STATES.WELCOME);
    }
  }

  // --- Public API --------------------------------------------------------------

  var Konfigurator = {
    STATES: STATES,
    init: init,
  };

  global.Konfigurator = Konfigurator;
})(typeof window !== "undefined" ? window : this);

document.addEventListener("DOMContentLoaded", function () {
  Konfigurator.init();
});
