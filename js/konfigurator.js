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

    var _refineBtn = document.getElementById("btn-ki-refine");
    if (_refineBtn) _refineBtn.addEventListener("click", _onRefine);
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

    // Immediately clear previous domain's injected content (hero images, text, colors)
    // so the old domain's data doesn't persist during the scan loading phase
    ContentInjector.reset();

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

    var _rb = document.getElementById("btn-ki-refine");
    if (_rb) _rb.disabled = false;

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
    // Open full-screen preview: konfigurator.html?fullscreen=1 with current settings (no sidebar)
    var hash = window.location.hash;
    var previewUrl = "konfigurator.html?fullscreen=1" + hash;
    window.open(previewUrl, "_blank");
  }

  function _cleanPageText(text) {
    if (!text) return "";
    var lines = text.split("\n");
    var start = 0;
    for (var i = 0; i < Math.min(lines.length, 15); i++) {
      var line = lines[i].trim();
      if (
        line.length < 30 &&
        (line === line.toUpperCase() || line.length < 3)
      ) {
        start = i + 1;
      } else {
        break;
      }
    }
    return lines.slice(start).join("\n").trim().substring(0, 5000);
  }

  function _extractServiceItems(text) {
    if (!text) return [];
    var paragraphs = text.split(/\n\n+/).filter(function (p) {
      return p.trim().length > 20;
    });
    var items = [];
    for (var i = 0; i < Math.min(paragraphs.length, 6); i++) {
      var p = paragraphs[i].trim();
      var firstLine = p.split("\n")[0].trim();
      var rest = p.split("\n").slice(1).join(" ").trim();
      items.push({
        title: firstLine.substring(0, 80),
        text: (rest || firstLine).substring(0, 300),
        icon_path: "",
      });
    }
    return items;
  }

  function _findPrimaryColor(scanData) {
    var colors = scanData.colors || [];
    for (var i = 0; i < colors.length; i++) {
      var c = (colors[i] || "").toLowerCase().trim();
      if (!c) continue;
      if (c.indexOf("#") === 0) {
        if (c === "#ffffff" || c === "#fff" || c === "#000000" || c === "#000")
          continue;
        return c;
      }
      if (c.indexOf("rgb") === 0) {
        var nums = c.match(/\d+/g);
        if (!nums || nums.length < 3) continue;
        var r = parseInt(nums[0]),
          g = parseInt(nums[1]),
          b = parseInt(nums[2]);
        if ((r > 240 && g > 240 && b > 240) || (r < 15 && g < 15 && b < 15))
          continue;
        var hex =
          "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
        return hex;
      }
    }
    return null;
  }

  function _buildContentFromScan(scanData, presetName) {
    var sel = scanData.selected || {};
    var aboutPage = sel.about || {};
    var servicesPage = sel.services || {};
    var contact = scanData.contact || {};
    var seo = scanData.seo || {};
    var heroImage = scanData.hero_image || {};
    var heroText = scanData.hero_text || {};
    var logo = scanData.logo || {};

    var rawTitle =
      (seo.title && seo.title.text) ||
      scanData.title ||
      scanData.domain ||
      "Website";
    var company = rawTitle.split(/\s*[–|]\s*/)[0].trim();
    if (company.length > 50) company = company.substring(0, 50);

    // Hero headline: prefer extracted h1 over SEO title
    var heroHeadline = heroText.headline || company;
    var heroSubtitle = heroText.subtitle || "";
    var heroEyebrow = heroText.eyebrow || scanData.branch || "";

    var description =
      heroSubtitle ||
      (seo.description && typeof seo.description === "string"
        ? seo.description
        : seo.description && seo.description.text) ||
      "";

    var aboutTitle = aboutPage.title || "Ueber uns";
    var aboutText = _cleanPageText(aboutPage.text || "");
    var servicesTitle = servicesPage.title || "Unsere Leistungen";
    var servicesText = _cleanPageText(servicesPage.text || "");
    var serviceItems = _extractServiceItems(servicesText);

    var primaryColor = _findPrimaryColor(scanData);

    var pages = [
      {
        slug: "index",
        title: "Home",
        sections: [
          {
            type: "hero",
            eyebrow: heroEyebrow,
            headline: heroHeadline,
            subline: description || aboutText.substring(0, 200),
            image: heroImage.src || "",
            cta: "Jetzt starten",
            cta_href: "kontakt.html",
            cta2: "Mehr erfahren",
            cta2_href: "#about",
          },
          {
            type: "about",
            tag: "Ueber uns",
            title: aboutTitle,
            text: aboutText.substring(0, 2000),
            image: aboutPage.image || "",
            image_alt: aboutPage.image_alt || "",
            cta: "",
            cta_href: "#",
          },
          {
            type: "services",
            tag: "Leistungen",
            title: servicesTitle,
            lead: servicesText.substring(0, 300),
            items: serviceItems,
          },
        ],
      },
      { slug: "kontakt", title: "Kontakt", sections: [] },
      { slug: "impressum", title: "Impressum", sections: [] },
      { slug: "datenschutz", title: "Datenschutz", sections: [] },
    ];

    // Add sub-pages from scan (exclude homepage, about, services, legal)
    var skipUrls = ["kontakt", "impressum", "datenschutz", "agb", "disclaimer"];
    var scanPages = scanData.pages || [];
    for (var i = 0; i < scanPages.length && pages.length < 15; i++) {
      var sp = scanPages[i];
      var url = (sp.url || "").toLowerCase();
      if (!url || url === scanData.start_url) continue;
      if (aboutPage.url && url === aboutPage.url.toLowerCase()) continue;
      if (servicesPage.url && url === servicesPage.url.toLowerCase()) continue;
      var slug = url
        .split("/")
        .pop()
        .replace(/\.html?$/, "")
        .replace(/[^a-z0-9-]/g, "");
      if (!slug || skipUrls.indexOf(slug) >= 0) continue;
      var title = (sp.title || slug).split(/\s*[–|]\s*/)[0].trim();
      if (title.length > 50) title = title.substring(0, 50);
      pages.push({
        slug: slug,
        title: title,
        sections: [
          {
            type: "about",
            tag: "",
            title: title,
            text: _cleanPageText(sp.text || "").substring(0, 2000),
            image: "",
            image_alt: "",
          },
        ],
      });
    }

    return {
      company: company,
      description: description,
      logo_url: logo.src || "",
      primary_color: primaryColor,
      contact: {
        name: company,
        email: (contact.emails || [])[0] || "",
        phone: (contact.phones || [])[0] || "",
        address: (contact.cities || [])[0] || "",
      },
      footer: { tagline: description.substring(0, 100) },
      social_media: {},
      pages: pages,
    };
  }

  function _onGenerate() {
    var preset = _getSelectedPreset();
    var domain = _currentDomain;
    var cached = domain ? _scanCache[domain] : null;

    if (!cached) {
      alert("Bitte zuerst eine Domain scannen.");
      return;
    }

    var btn = _el.generateBtn;
    var originalText = btn.textContent;
    btn.textContent = "Generiere...";
    btn.disabled = true;
    btn.style.opacity = "0.6";

    var content = _buildContentFromScan(cached, preset);
    var state = typeof getState === "function" ? getState() : {};

    var body = {
      preset: preset,
      overrides: state,
      content: content,
    };

    fetch("api/v1/generate", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    })
      .then(function (res) {
        if (!res.ok) {
          return res.json().then(function (err) {
            throw new Error(err.error || "Fehler " + res.status);
          });
        }
        return res.json();
      })
      .then(function (data) {
        if (data.download_url) {
          window.location.href = data.download_url;
        } else {
          alert("Generierung erfolgreich, aber kein Download-Link erhalten.");
        }
      })
      .catch(function (err) {
        alert("Fehler bei der Generierung: " + err.message);
      })
      .finally(function () {
        btn.textContent = originalText;
        btn.disabled = false;
        btn.style.opacity = "1";
      });
  }

  // --- KI Refinement -----------------------------------------------------------

  function _onRefine() {
    var btn = document.getElementById("btn-ki-refine");
    var statusEl = document.getElementById("ki-refine-status");
    var domain = _currentDomain;
    var scanData = domain ? _scanCache[domain] : null;

    if (!scanData || !domain) {
      statusEl.style.display = "block";
      statusEl.style.color = "#ef4444";
      statusEl.textContent = "Bitte zuerst eine Domain scannen.";
      return;
    }

    btn.classList.add("btn-loading");
    btn.disabled = true;
    statusEl.style.display = "block";
    statusEl.style.color = "#64748b";
    statusEl.textContent = "KI analysiert und optimiert...";

    // Nur relevante Felder senden (nicht alle Seitentexte — verhindert Timeout)
    var trimmed = {
      title: scanData.title || "",
      branch: scanData.branch || "",
      hero_text: scanData.hero_text || {},
      selected: scanData.selected || {},
      contact: scanData.contact || {},
      ai_analysis: scanData.ai_analysis || scanData.ki_analysis || {},
      seo: scanData.seo || {},
      colors: scanData.colors || [],
      pages: (scanData.pages || []).slice(0, 3).map(function (p) {
        return {
          url: p.url,
          title: p.title,
          text: (p.text || p.content || "").substring(0, 800),
        };
      }),
    };

    ScannerClient.refine(domain, trimmed, ["all"])
      .then(function (result) {
        if (result.status !== "ok" || !result.refined) {
          throw new Error(result.message || result.error || "Kein Ergebnis");
        }

        var refined = result.refined;

        // Merge hero
        if (refined.hero) {
          if (!scanData.hero_text) scanData.hero_text = {};
          if (refined.hero.headline)
            scanData.hero_text.headline = refined.hero.headline;
          if (refined.hero.subtitle)
            scanData.hero_text.subtitle = refined.hero.subtitle;
          if (refined.hero.eyebrow)
            scanData.hero_text.eyebrow = refined.hero.eyebrow;
        }

        // Merge about
        if (refined.about) {
          if (!scanData.selected) scanData.selected = {};
          if (!scanData.selected.about) scanData.selected.about = {};
          if (refined.about.title)
            scanData.selected.about.title = refined.about.title;
          if (refined.about.text)
            scanData.selected.about.text = refined.about.text;
        }

        // Merge services
        if (refined.services) {
          if (!scanData.selected) scanData.selected = {};
          if (!scanData.selected.services) scanData.selected.services = {};
          if (refined.services.title)
            scanData.selected.services.title = refined.services.title;
          if (refined.services.lead && refined.services.items) {
            var parts = [refined.services.lead];
            refined.services.items.forEach(function (item) {
              parts.push(item.title + ": " + item.text);
            });
            scanData.selected.services.text = parts.join("\n\n");
          } else if (refined.services.text) {
            scanData.selected.services.text = refined.services.text;
          }
        }

        scanData.refined = true;

        // Re-inject
        if (window.ContentInjector) ContentInjector.inject(scanData);
        if (window.lucide) lucide.createIcons();

        // Show result panel so user sees WHAT changed
        _showRefineResults(refined, result.duration_ms, result.cached);

        btn.classList.remove("btn-loading");
        btn.disabled = false;
        if (window.btnSuccess) btnSuccess(btn, 2000);
        statusEl.style.color = "#16a34a";
        statusEl.textContent =
          "Texte optimiert! (" + (result.duration_ms / 1000).toFixed(1) + "s)";
        if (result.cached) statusEl.textContent += " (cached)";
      })
      .catch(function (err) {
        btn.classList.remove("btn-loading");
        btn.disabled = false;
        statusEl.style.color = "#ef4444";
        statusEl.textContent = "Fehler: " + err.message;
      });
  }

  // --- Refine result panel -----------------------------------------------------

  function _truncate(s, n) {
    s = String(s || "");
    return s.length > n ? s.substring(0, n - 1) + "…" : s;
  }

  function _mkEl(tag, opts, children) {
    var e = document.createElement(tag);
    if (opts) {
      if (opts.text) e.textContent = opts.text;
      if (opts.style) e.style.cssText = opts.style;
      if (opts.href) e.href = opts.href;
      if (opts.onclick) e.onclick = opts.onclick;
      if (opts.id) e.id = opts.id;
    }
    if (children) {
      for (var i = 0; i < children.length; i++) {
        if (children[i]) e.appendChild(children[i]);
      }
    }
    return e;
  }

  function _section(label, linkHref, titleText, bodyText) {
    var header = _mkEl("div", {
      style:
        "display:flex;justify-content:space-between;align-items:baseline;margin-bottom:4px;",
    });
    header.appendChild(
      _mkEl("div", {
        text: label,
        style:
          "font-size:10px;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;font-weight:600;",
      }),
    );
    if (linkHref) {
      header.appendChild(
        _mkEl("a", {
          text: "→ Sektion",
          href: linkHref,
          style: "font-size:10px;color:#3b82f6;text-decoration:none;",
        }),
      );
    }
    var wrapper = _mkEl("div", {
      style:
        "margin-bottom:14px;padding-top:10px;border-top:1px solid #f1f5f9;",
    });
    wrapper.appendChild(header);
    if (titleText) {
      wrapper.appendChild(
        _mkEl("div", {
          text: titleText,
          style: "font-weight:600;color:#0f172a;margin:2px 0;",
        }),
      );
    }
    if (bodyText) {
      wrapper.appendChild(
        _mkEl("div", {
          text: bodyText,
          style: "color:#475569;font-size:12px;",
        }),
      );
    }
    return wrapper;
  }

  function _showRefineResults(refined, durationMs, cached) {
    var existing = document.getElementById("ki-refine-results");
    if (existing) existing.remove();

    var panel = _mkEl("div", {
      id: "ki-refine-results",
      style:
        "position:fixed;top:16px;right:16px;width:360px;max-height:calc(100vh - 32px);" +
        "overflow-y:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;" +
        "box-shadow:0 10px 40px rgba(0,0,0,0.15);z-index:9999;font-family:system-ui,sans-serif;" +
        "font-size:13px;line-height:1.5;",
    });

    var hero = refined.hero || {};
    var about = refined.about || {};
    var services = refined.services || {};
    var items = services.items || [];
    var meta = refined.meta || {};

    var durationText = cached
      ? "aus Cache"
      : "in " + (durationMs / 1000).toFixed(1) + "s";

    // Header
    var header = _mkEl("div", {
      style:
        "padding:14px 16px 10px;border-bottom:1px solid #f1f5f9;display:flex;" +
        "align-items:center;justify-content:space-between;",
    });
    var titleRow = _mkEl("div", {
      style:
        "font-weight:600;color:#0f172a;display:flex;align-items:center;gap:8px;",
    });
    titleRow.appendChild(
      _mkEl("span", {
        style:
          "width:8px;height:8px;border-radius:50%;background:#16a34a;display:inline-block;",
      }),
    );
    titleRow.appendChild(document.createTextNode("KI hat optimiert "));
    titleRow.appendChild(
      _mkEl("span", {
        text: "(" + durationText + ")",
        style: "font-size:11px;color:#94a3b8;font-weight:400;",
      }),
    );
    header.appendChild(titleRow);
    header.appendChild(
      _mkEl("button", {
        text: "×",
        style:
          "background:none;border:none;color:#94a3b8;font-size:18px;" +
          "cursor:pointer;padding:0 4px;line-height:1;",
        onclick: function () {
          panel.remove();
        },
      }),
    );
    panel.appendChild(header);

    var body = _mkEl("div", { style: "padding:12px 16px;" });

    // Hero block
    var heroWrap = _mkEl("div", { style: "margin-bottom:14px;" });
    heroWrap.appendChild(
      _mkEl("div", {
        text: "Hero",
        style:
          "font-size:10px;text-transform:uppercase;letter-spacing:0.05em;" +
          "color:#94a3b8;font-weight:600;margin-bottom:4px;",
      }),
    );
    if (hero.eyebrow) {
      heroWrap.appendChild(
        _mkEl("div", {
          text: hero.eyebrow,
          style: "font-size:11px;color:#64748b;font-style:italic;",
        }),
      );
    }
    heroWrap.appendChild(
      _mkEl("div", {
        text: hero.headline || "—",
        style: "font-weight:600;color:#0f172a;margin:2px 0;",
      }),
    );
    if (hero.subtitle) {
      heroWrap.appendChild(
        _mkEl("div", {
          text: _truncate(hero.subtitle, 140),
          style: "color:#475569;font-size:12px;",
        }),
      );
    }
    body.appendChild(heroWrap);

    // About + Services
    body.appendChild(
      _section("Über uns", "#about", about.title, _truncate(about.text, 180)),
    );

    var servicesBlock = _section(
      "Leistungen",
      "#leistungen",
      services.title,
      _truncate(services.lead, 140),
    );
    if (items.length) {
      var ul = _mkEl("ul", {
        style: "list-style:none;padding:0;margin:4px 0 0;font-size:11px;",
      });
      items.forEach(function (item) {
        var li = _mkEl("li", { style: "margin:4px 0;" });
        li.appendChild(_mkEl("strong", { text: item.title || "" }));
        li.appendChild(_mkEl("br"));
        li.appendChild(
          _mkEl("span", {
            text: _truncate(item.text, 100),
            style: "color:#64748b;",
          }),
        );
        ul.appendChild(li);
      });
      servicesBlock.appendChild(ul);
    }
    body.appendChild(servicesBlock);

    // SEO Meta
    if (meta.seo_title || meta.seo_description) {
      var seoBlock = _mkEl("div", {
        style: "padding-top:10px;border-top:1px solid #f1f5f9;",
      });
      seoBlock.appendChild(
        _mkEl("div", {
          text: "SEO Meta",
          style:
            "font-size:10px;text-transform:uppercase;letter-spacing:0.05em;" +
            "color:#94a3b8;font-weight:600;margin-bottom:4px;",
        }),
      );
      if (meta.seo_title) {
        seoBlock.appendChild(
          _mkEl("div", {
            text: meta.seo_title,
            style: "font-size:12px;color:#0f172a;",
          }),
        );
      }
      if (meta.seo_description) {
        seoBlock.appendChild(
          _mkEl("div", {
            text: _truncate(meta.seo_description, 160),
            style: "font-size:11px;color:#64748b;margin-top:2px;",
          }),
        );
      }
      body.appendChild(seoBlock);
    }

    panel.appendChild(body);
    document.body.appendChild(panel);
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
      software: "corporate",
      "it-": "corporate",
      digital: "corporate",
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
