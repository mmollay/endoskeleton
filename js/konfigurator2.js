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

  function _transitionToPreview(scanData) {
    _switchState(STATES.PREVIEW);

    _updateDomainSelect(scanData.domain || _currentDomain);
    _updateBadge(scanData);

    ContentInjector.captureOriginals();

    _saveToHistory(scanData.domain || _currentDomain);
    _renderHistory();

    // Auto-select best preset based on KI analysis, then inject scan content
    _highlightRecommendedPreset(scanData);

    _updateHashDomain(scanData.domain || _currentDomain);
  }

  // --- Domain select dropdown --------------------------------------------------

  function _updateDomainSelect(domain) {
    if (!_el.domainSelect) {
      return;
    }

    var exists = false;
    for (var i = 0; i < _el.domainSelect.options.length; i++) {
      if (_el.domainSelect.options[i].value === domain) {
        exists = true;
        _el.domainSelect.selectedIndex = i;
        break;
      }
    }

    if (!exists) {
      var opt = document.createElement("option");
      opt.value = domain;
      opt.textContent = domain;
      _el.domainSelect.appendChild(opt);
      _el.domainSelect.value = domain;
    }
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

    var cached = _scanCache[domain] || ScannerClient.checkCache(domain);
    if (cached) {
      _currentDomain = domain;
      _scanCache[domain] = cached;
      ContentInjector.inject(cached);
      _updateBadge(cached);
      _updateHashDomain(domain);
    } else {
      _startScan(domain);
    }
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

    // Remove all existing children safely
    while (_el.historyList.firstChild) {
      _el.historyList.removeChild(_el.historyList.firstChild);
    }

    var history = _loadHistory();
    history.forEach(function (domain) {
      var li = document.createElement("li");
      var a = document.createElement("a");
      a.href = "#";
      a.textContent = domain;
      a.addEventListener("click", function (e) {
        e.preventDefault();
        _startScan(domain);
      });
      li.appendChild(a);
      _el.historyList.appendChild(li);
    });
  }

  // --- Deepen & Generate -------------------------------------------------------

  function _getSelectedPreset() {
    var card = document.querySelector(
      ".preset-card.active, .preset-card.selected",
    );
    return card
      ? card.dataset.preset || card.getAttribute("data-preset")
      : null;
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
    var cached = domain
      ? _scanCache[domain] || ScannerClient.checkCache(domain)
      : null;
    var overrides = {};

    if (typeof getState === "function") {
      try {
        overrides = getState() || {};
      } catch (e) {
        /* ignore */
      }
    }

    return {
      preset: preset,
      format: format,
      domain: domain,
      overrides: overrides,
      content: cached || null,
    };
  }

  function _onDeepen() {
    var btn = _el.deepenBtn;
    _setButtonLoading(btn, true);

    var payload = _buildGeneratePayload("urls");

    fetch("https://skeleton.ssi.at/api/v1/generate", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then(function (res) {
        if (!res.ok) {
          throw new Error("HTTP " + res.status);
        }
        return res.json();
      })
      .then(function (result) {
        _setButtonLoading(btn, false);
        if (result && result.url) {
          window.open(result.url, "_blank");
        } else {
          alert("Keine URL in der Antwort gefunden.");
        }
      })
      .catch(function (err) {
        console.error("[Konfigurator] deepen error:", err);
        _setButtonLoading(btn, false);
        alert("Fehler beim Erstellen der Vorschau.");
      });
  }

  function _onGenerate() {
    var btn = _el.generateBtn;
    _setButtonLoading(btn, true);

    var payload = _buildGeneratePayload("zip");

    fetch("https://skeleton.ssi.at/api/v1/generate", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then(function (res) {
        if (!res.ok) {
          throw new Error("HTTP " + res.status);
        }
        return res.json();
      })
      .then(function (result) {
        _setButtonLoading(btn, false);
        if (result && result.url) {
          window.open(result.url, "_blank");
        } else {
          alert("Kein Download-Link in der Antwort gefunden.");
        }
      })
      .catch(function (err) {
        console.error("[Konfigurator] generate error:", err);
        _setButtonLoading(btn, false);
        alert("Fehler beim Erstellen des Downloads.");
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
