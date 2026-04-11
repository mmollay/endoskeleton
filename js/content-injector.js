(function (global) {
  "use strict";

  // ─── Branch label mapping ───────────────────────────────────────────────────
  var BRANCH_LABELS = {
    gastronomie: "Gastronomie",
    tourismus: "Tourismus",
    natur: "Natur & Bio",
    handwerk: "Handwerk",
    gesundheit: "Gesundheit",
    kreativ: "Kreativ & Design",
    corporate: "Business",
    tech: "Technologie",
    bildung: "Bildung",
    sport: "Sport",
  };

  // ─── Internal state: captured originals ────────────────────────────────────
  var _originals = null;

  // ═══════════════════════════════════════════════════════════════════════════
  // Internal helpers
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Get text content of the first matching element, or '' if not found.
   */
  function _getText(selector) {
    var el = document.querySelector(selector);
    return el ? el.textContent : "";
  }

  /**
   * Get an attribute value from the first matching element, or '' if not found.
   */
  function _getAttr(selector, attr) {
    var el = document.querySelector(selector);
    return el ? el.getAttribute(attr) || "" : "";
  }

  /**
   * Set textContent on ALL matching elements.
   */
  function _setAllText(selector, value) {
    var els = document.querySelectorAll(selector);
    for (var i = 0; i < els.length; i++) {
      els[i].textContent = value;
    }
  }

  /**
   * Set textContent AND lock element against i18n overwrite.
   * Moves data-i18n to data-i18n-original so applyLang() won't touch it.
   */
  function _setTextAndLock(selector, value) {
    var els = document.querySelectorAll(selector);
    for (var i = 0; i < els.length; i++) {
      els[i].textContent = value;
      var key = els[i].getAttribute("data-i18n");
      if (key) {
        els[i].setAttribute("data-i18n-original", key);
        els[i].removeAttribute("data-i18n");
      }
    }
  }

  /**
   * Set an attribute on ALL matching elements.
   */
  function _setAllAttr(selector, attr, value) {
    var els = document.querySelectorAll(selector);
    for (var i = 0; i < els.length; i++) {
      els[i].setAttribute(attr, value);
    }
  }

  /**
   * Get a CSS custom property from :root.
   */
  function _getCSSVar(name) {
    return getComputedStyle(document.documentElement)
      .getPropertyValue(name)
      .trim();
  }

  /**
   * Set a CSS custom property on :root.
   */
  function _setCSSVar(name, value) {
    document.documentElement.style.setProperty(name, value);
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // Internal: color utilities
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Find the best primary color from an array of color strings.
   * Skips white, black, near-white, near-black, and grays.
   * Accepts hex (#fff, #ffffff) and rgb(r,g,b) formats.
   */
  function _findBestColor(colors) {
    for (var i = 0; i < colors.length; i++) {
      var c = colors[i];
      if (!c || c === "none" || c.indexOf("rgba") !== -1) continue;

      var r, g, b;
      if (c.charAt(0) === "#") {
        var hex = c.replace("#", "");
        if (hex.length === 3) {
          hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        r = parseInt(hex.substring(0, 2), 16);
        g = parseInt(hex.substring(2, 4), 16);
        b = parseInt(hex.substring(4, 6), 16);
      } else if (c.indexOf("rgb(") === 0) {
        var parts = c.match(/\d+/g);
        if (!parts || parts.length < 3) continue;
        r = parseInt(parts[0]);
        g = parseInt(parts[1]);
        b = parseInt(parts[2]);
      } else {
        continue;
      }

      // Skip near-white (lightness > 200)
      if (r > 200 && g > 200 && b > 200) continue;
      // Skip near-black (lightness < 35)
      if (r < 35 && g < 35 && b < 35) continue;
      // Skip grays (low saturation: all channels within 40 of each other)
      var maxC = Math.max(r, g, b);
      var minC = Math.min(r, g, b);
      if (maxC - minC < 40) continue;

      return c;
    }
    return null;
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // Internal: page content injection
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Determine if a page object matches the homepage.
   * url === '/' or slug === 'index'
   */
  function _isHomepage(page) {
    var url = (page.url || "").replace(/\/$/, "");
    return (
      url === "/" ||
      url === "" ||
      page.slug === "index" ||
      url.match(/^https?:\/\/[^\/]+\/?$/)
    );
  }

  /**
   * Determine if a page object matches the about page.
   */
  function _isAboutPage(page) {
    var slug = (page.slug || "").toLowerCase();
    var url = (page.url || "").toLowerCase();
    var title = (page.title || "").toLowerCase();
    if (slug === "about" || slug === "ueber-uns" || slug === "about-us")
      return true;
    if (url.indexOf("/about") !== -1 || url.indexOf("/ueber-uns") !== -1)
      return true;
    if (title.indexOf("about") !== -1 || title.indexOf("über uns") !== -1)
      return true;
    return false;
  }

  /**
   * Determine if a page object matches the services page.
   */
  function _isServicesPage(page) {
    var slug = (page.slug || "").toLowerCase();
    var url = (page.url || "").toLowerCase();
    var title = (page.title || "").toLowerCase();
    if (slug === "services" || slug === "leistungen") return true;
    if (url.indexOf("/leistungen") !== -1 || url.indexOf("/services") !== -1)
      return true;
    if (title.indexOf("leistungen") !== -1 || title.indexOf("services") !== -1)
      return true;
    return false;
  }

  /**
   * Clean scanned page text: remove nav-like lines (single-word uppercase,
   * short labels), collapse whitespace, return meaningful paragraphs.
   */
  function _cleanText(raw) {
    if (!raw) return [];
    var lines = raw.split(/\n+/);
    var cleaned = [];
    for (var i = 0; i < lines.length; i++) {
      var line = lines[i].trim();
      if (!line) continue;
      // Skip lines that look like nav items
      if (line.length < 15) continue;
      if (/^[A-ZÄÖÜ\s]{2,40}$/.test(line)) continue; // ALL-CAPS short
      // Skip pure link-like lines
      if (/^(home|impressum|datenschutz|kontakt|agb|cookies?)\s*$/i.test(line))
        continue;
      cleaned.push(line);
    }
    return cleaned;
  }

  /**
   * Split cleaned text into paragraphs, each at least 40 chars.
   */
  function _extractParagraphs(raw, maxParagraphs) {
    var lines = _cleanText(raw);
    var paragraphs = [];
    var current = "";
    for (var i = 0; i < lines.length; i++) {
      if (current) current += " ";
      current += lines[i];
      if (
        current.length > 200 ||
        lines[i].endsWith(".") ||
        lines[i].endsWith("!")
      ) {
        if (current.length >= 40) {
          paragraphs.push(current.trim());
          if (paragraphs.length >= (maxParagraphs || 10)) break;
        }
        current = "";
      }
    }
    if (
      current &&
      current.length >= 40 &&
      paragraphs.length < (maxParagraphs || 10)
    ) {
      paragraphs.push(current.trim());
    }
    return paragraphs;
  }

  /**
   * Filter scan images to usable content images (skip logos, tiny icons).
   */
  function _getContentImages(scanData) {
    if (!scanData.images) return [];
    var logoUrl =
      (scanData.logo && scanData.logo.src) || scanData.logo_url || "";
    var result = [];
    for (var i = 0; i < scanData.images.length; i++) {
      var img = scanData.images[i];
      var url = img.url || img;
      if (!url || typeof url !== "string") continue;
      // Skip logo
      if (logoUrl && url === logoUrl) continue;
      if (/logo/i.test(url)) continue;
      // Skip tiny images (icons, tracking pixels)
      if (img.size_bytes && img.size_bytes < 5000) continue;
      // Skip SVG icons
      if (/\.svg($|\?)/i.test(url)) continue;
      result.push(url);
    }
    return result;
  }

  /**
   * Return images filtered by role (Scanner v1.5.0+). If scan.images[i].role
   * does not exist (old scan), returns an empty array so callers can fall
   * back to index-based selection.
   */
  function _getImagesByRole(scanData, role) {
    if (!scanData.images) return [];
    var out = [];
    for (var i = 0; i < scanData.images.length; i++) {
      var img = scanData.images[i];
      if (!img) continue;
      var src = typeof img === "string" ? img : img.url;
      if (!src) continue;
      if (img.role === role) {
        out.push({
          src: src,
          alt: img.alt || "",
          width: img.width || 0,
          height: img.height || 0,
        });
      }
    }
    return out;
  }

  /**
   * Inject real content from scanned pages into DOM sections.
   */
  function _injectPageContent(scanData) {
    var pages = scanData.pages || [];
    var selected = scanData.selected || {};

    // ── Find pages by type — prefer scan.selected (Scanner v1.6.0+) ──────
    var homepage = null;
    var aboutPage = selected.about || null;
    var servicesPage = selected.services || null;

    if (!homepage || !aboutPage || !servicesPage) {
      for (var i = 0; i < pages.length; i++) {
        var page = pages[i];
        if (!homepage && _isHomepage(page)) homepage = page;
        if (!aboutPage && _isAboutPage(page)) aboutPage = page;
        if (!servicesPage && _isServicesPage(page)) servicesPage = page;
      }
    }

    if (!pages.length && !aboutPage && !servicesPage) return;

    // ── Extract paragraphs from available pages ─────────────────────────────
    var homePars = homepage
      ? _extractParagraphs(homepage.text || homepage.content || "", 5)
      : [];
    var aboutPars = aboutPage
      ? _extractParagraphs(aboutPage.text || aboutPage.content || "", 3)
      : [];
    var servicesPars = servicesPage
      ? _extractParagraphs(servicesPage.text || servicesPage.content || "", 5)
      : [];

    // ── About section ────────────────────────────────────────────────────────
    // Fallback title: dedicated about page OR "Über uns" (German default)
    var aboutTitle =
      (aboutPage &&
        aboutPage.title &&
        aboutPage.title.split(/\s+[–|]\s+|\s+-\s+/)[0].trim()) ||
      "Über uns";
    _setTextAndLock('[data-i18n="about.title"]', aboutTitle);
    _setTextAndLock('[data-i18n-original="about.title"]', aboutTitle);

    var aboutText = (
      aboutPars[0] ||
      homePars[1] ||
      homePars[0] ||
      ""
    ).substring(0, 400);
    if (aboutText) {
      _setTextAndLock('[data-i18n="about.text"]', aboutText);
      _setTextAndLock('[data-i18n-original="about.text"]', aboutText);
    }

    // ── Services section ─────────────────────────────────────────────────────
    var servicesTitle =
      (servicesPage &&
        servicesPage.title &&
        servicesPage.title.split(/\s+[–|]\s+|\s+-\s+/)[0].trim()) ||
      "Unsere Leistungen";
    _setTextAndLock('[data-i18n="services.title"]', servicesTitle);
    _setTextAndLock('[data-i18n-original="services.title"]', servicesTitle);

    var servicesText = (
      servicesPars[0] ||
      homePars[2] ||
      homePars[1] ||
      ""
    ).substring(0, 300);
    if (servicesText) {
      _setTextAndLock('[data-i18n="services.lead"]', servicesText);
      _setTextAndLock('[data-i18n-original="services.lead"]', servicesText);
    }

    // Individual service items from paragraphs or other pages
    var serviceItems = servicesPars.slice(1, 4);
    if (serviceItems.length >= 3) {
      ["s1", "s2", "s3"].forEach(function (key, idx) {
        if (serviceItems[idx]) {
          var text = serviceItems[idx].substring(0, 150);
          _setTextAndLock('[data-i18n="services.' + key + '.text"]', text);
          _setTextAndLock(
            '[data-i18n-original="services.' + key + '.text"]',
            text,
          );
        }
      });
    }

    // ── Hero images from scan ────────────────────────────────────────────────
    var contentImages = _getContentImages(scanData);
    var heroImg =
      (scanData.hero_image &&
        typeof scanData.hero_image.src === "string" &&
        scanData.hero_image.src) ||
      contentImages[0];
    if (heroImg) {
      // 1. Background-Image on hero wrappers (fullscreen, banner, veil)
      var heroBgElements = document.querySelectorAll(
        ".hero--fullscreen, .hero--banner, .hero--veil, " +
          ".hero-bg, .hero-image, .hero-fullscreen, [data-hero-bg]",
      );
      for (var j = 0; j < heroBgElements.length; j++) {
        heroBgElements[j].style.backgroundImage = "url('" + heroImg + "')";
      }
      // 2. <img> src in split hero (where image is a real element)
      var heroImgEls = document.querySelectorAll(
        ".hero--split img, .hero-split img, .hero-media img",
      );
      for (var k = 0; k < heroImgEls.length; k++) {
        heroImgEls[k].src = heroImg;
        heroImgEls[k].removeAttribute("srcset");
      }
      // 3. About section image — prefer scan.selected.about.image,
      //    then role="about", then contentImages[1]
      var aboutImgEl = document.querySelector(
        ".about-image img, .section-about img, #about img",
      );
      if (aboutImgEl) {
        var preselectedAbout = scanData.selected && scanData.selected.about;
        var aboutRoleImgs = _getImagesByRole(scanData, "about");
        var aboutSrc =
          (preselectedAbout && preselectedAbout.image) ||
          (aboutRoleImgs.length > 0 ? aboutRoleImgs[0].src : null) ||
          (contentImages.length > 1 ? contentImages[1] : null);
        var aboutAlt =
          (preselectedAbout && preselectedAbout.image_alt) ||
          (aboutRoleImgs.length > 0 ? aboutRoleImgs[0].alt : "");
        if (aboutSrc) {
          aboutImgEl.src = aboutSrc;
          aboutImgEl.removeAttribute("srcset");
          if (aboutAlt) {
            aboutImgEl.alt = aboutAlt;
          }
        }
      }

      // 4. Services cards — inject up to 6 role="services" images
      var serviceImgs = _getImagesByRole(scanData, "services").slice(0, 6);
      if (serviceImgs.length > 0) {
        var cardImgEls = document.querySelectorAll(
          ".services-card img, .service-item img, .service-card img, " +
            "[data-service-card] img, .services-grid img",
        );
        for (
          var ci = 0;
          ci < cardImgEls.length && ci < serviceImgs.length;
          ci++
        ) {
          cardImgEls[ci].src = serviceImgs[ci].src;
          cardImgEls[ci].removeAttribute("srcset");
          if (serviceImgs[ci].alt) {
            cardImgEls[ci].alt = serviceImgs[ci].alt;
          }
        }
      }
    }
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // Public API
  // ═══════════════════════════════════════════════════════════════════════════

  var ContentInjector = {
    /**
     * Capture current DOM placeholder values so they can be restored later.
     * Stores: hero title/subtitle/eyebrow, about title/text,
     *         contact email/phone, nav + footer logo src, --primary CSS var.
     */
    captureOriginals: function () {
      _originals = {
        heroTitle: _getText('[data-i18n="hero.title"]'),
        heroSubtitle: _getText('[data-i18n="hero.subtitle"]'),
        heroEyebrow: _getText('[data-i18n="hero.eyebrow"]'),
        aboutTitle: _getText('[data-i18n="about.title"]'),
        aboutText: _getText('[data-i18n="about.text"]'),
        contactEmail: _getText(".contact-email"),
        contactEmailHref: _getAttr(".contact-email-link", "href"),
        contactPhone: _getText(".contact-phone"),
        navLogoSrc: _getAttr(".site-nav .logo img", "src"),
        footerLogoSrc: _getAttr(".site-footer .logo img", "src"),
        primaryColor: _getCSSVar("--primary"),
      };
    },

    /**
     * Inject scan data into the live DOM.
     *
     * @param {Object} scanData  Result object from the Scanner API.
     */
    inject: function (scanData) {
      if (!scanData) return;

      // ── Hero title ──────────────────────────────────────────────────────────
      if (scanData.title) {
        _setTextAndLock('[data-i18n="hero.title"]', scanData.title);
        _setAllText('[data-i18n-original="hero.title"]', scanData.title);
      }

      // ── Hero subtitle / description ─────────────────────────────────────────
      var description = scanData.description;
      if (!description && scanData.seo && scanData.seo.meta_description) {
        description = scanData.seo.meta_description.text;
      }
      if (
        !description &&
        scanData.pages &&
        scanData.pages[0] &&
        scanData.pages[0].meta
      ) {
        description = scanData.pages[0].meta.description;
      }
      // Fallback: extract first meaningful paragraph from homepage text
      if (!description && scanData.pages && scanData.pages[0]) {
        var homeText = scanData.pages[0].text || "";
        if (homeText) {
          var firstPars = _extractParagraphs(homeText, 1);
          if (firstPars.length > 0) {
            description = firstPars[0].substring(0, 200);
          }
        }
      }
      if (description) {
        _setTextAndLock('[data-i18n="hero.subtitle"]', description);
        _setAllText('[data-i18n-original="hero.subtitle"]', description);
      }

      // ── Hero eyebrow / branch ───────────────────────────────────────────────
      // API returns: branch (string) or ki_analysis.analysis.branch
      var branch = scanData.branch;
      if (!branch || branch.indexOf("Unbekannt") !== -1) {
        branch = null;
        if (scanData.ki_analysis && scanData.ki_analysis.analysis) {
          branch = scanData.ki_analysis.analysis.branch;
        }
      }
      if (branch && branch.indexOf("Unbekannt") === -1) {
        var label = BRANCH_LABELS[branch] || branch;
        _setTextAndLock('[data-i18n="hero.eyebrow"]', label);
        _setAllText('[data-i18n-original="hero.eyebrow"]', label);
      } else if (scanData.domain) {
        _setTextAndLock(
          '[data-i18n="hero.eyebrow"]',
          scanData.domain.toUpperCase(),
        );
        _setAllText(
          '[data-i18n-original="hero.eyebrow"]',
          scanData.domain.toUpperCase(),
        );
      }

      // ── Primary color ───────────────────────────────────────────────────────
      // API returns hex colors like #ffffff or rgb() strings — find first dark/saturated color
      if (scanData.colors && scanData.colors.length > 0) {
        var primaryColor = _findBestColor(scanData.colors);
        if (primaryColor) {
          _setCSSVar("--primary", primaryColor);
        }
      }

      // ── SITE_CONFIG: Name, Logo, Contact, Social ────────────────────────
      // shared.js exposes updateSiteConfig() which merges + re-renders nav/footer
      var companyName = scanData.title
        ? scanData.title.split(/[–\-|]/)[0].trim()
        : scanData.domain;

      var logoUrl = scanData.logo_url;
      if (!logoUrl && scanData.logo && scanData.logo.src) {
        logoUrl = scanData.logo.src;
      }

      var contactEmail = scanData.contact
        ? scanData.contact.email ||
          (scanData.contact.emails && scanData.contact.emails[0]) ||
          ""
        : "";
      var contactPhone = scanData.contact
        ? scanData.contact.phone ||
          (scanData.contact.phones && scanData.contact.phones[0]) ||
          ""
        : "";
      var contactCity = scanData.contact ? scanData.contact.ort || "" : "";

      var socialLinks = {};
      if (scanData.tech && scanData.tech.social_media) {
        socialLinks = scanData.tech.social_media;
      }

      // ── Navigation from scanned pages ────────────────────────────────────
      var navItems = [];
      if (scanData.pages && scanData.pages.length > 1) {
        var seenTitles = {};
        var seenUrls = {};
        // Pre-mark homepage title(s) so duplicates with same title (e.g. /index.html)
        // don't reappear in nav
        for (var hi = 0; hi < scanData.pages.length; hi++) {
          var hp = scanData.pages[hi];
          if (_isHomepage(hp) && hp.title) {
            seenTitles[hp.title.trim().toLowerCase()] = true;
          }
        }
        for (
          var pi = 0;
          pi < scanData.pages.length && navItems.length < 7;
          pi++
        ) {
          var pg = scanData.pages[pi];
          var pgUrl = pg.url || "";
          var pgTitle = (pg.title || "").trim();
          if (!pgTitle || pg.error) continue;

          // Skip homepage variants
          if (_isHomepage(pg)) continue;

          // Skip legal pages (come in footer anyway)
          if (/impressum|datenschutz|agb|privacy|cookie/i.test(pgTitle))
            continue;
          if (/impressum|datenschutz|agb|privacy|cookie/i.test(pgUrl)) continue;

          // Deduplicate by title
          var normalizedTitle = pgTitle.toLowerCase();
          if (seenTitles[normalizedTitle]) continue;
          seenTitles[normalizedTitle] = true;

          // Dedupe by path
          var path =
            pgUrl
              .replace(/^https?:\/\/[^\/]+/, "")
              .replace(/\.(html?|php|aspx?)$/i, "")
              .replace(/\/$/, "") || "/";
          if (seenUrls[path]) continue;
          seenUrls[path] = true;

          // Shorten labels like "Leistungen – SSI | Webhosting, …" → "Leistungen"
          // Only split on separators with surrounding whitespace to preserve
          // hyphenated names like "Smart-Kit"
          var label = pgTitle.split(/\s+[–|]\s+|\s+-\s+/)[0].trim() || pgTitle;
          if (label.length > 25) label = label.substring(0, 25).trim();

          // Keep original site URL so nav links point to the real pages
          navItems.push({
            label: label,
            href: pgUrl,
          });
        }
      }

      var configUpdate = {
        name: companyName,
        contact: {
          name: companyName,
          email: contactEmail,
          phone: contactPhone,
          address: contactCity,
        },
        social: socialLinks,
      };
      if (logoUrl) {
        configUpdate.logo = logoUrl;
      }
      if (navItems.length > 0) {
        configUpdate.nav = navItems;
      }

      if (typeof window.updateSiteConfig === "function") {
        window.updateSiteConfig(configUpdate);
      }

      // ── Page content + scan images ──────────────────────────────────────────
      if (scanData.pages && scanData.pages.length) {
        _injectPageContent(scanData);
      }
    },

    /**
     * Restore the original placeholder values captured by captureOriginals().
     * No-op if captureOriginals() was never called.
     */
    reset: function () {
      if (!_originals) return;

      _setAllText('[data-i18n="hero.title"]', _originals.heroTitle);
      _setAllText('[data-i18n="hero.subtitle"]', _originals.heroSubtitle);
      _setAllText('[data-i18n="hero.eyebrow"]', _originals.heroEyebrow);
      _setAllText('[data-i18n="about.title"]', _originals.aboutTitle);
      _setAllText('[data-i18n="about.text"]', _originals.aboutText);
      _setAllText(".contact-email", _originals.contactEmail);
      _setAllText(".contact-phone", _originals.contactPhone);

      _setAllAttr(".contact-email-link", "href", _originals.contactEmailHref);
      _setAllAttr(".site-nav .logo img", "src", _originals.navLogoSrc);
      _setAllAttr(".site-footer .logo img", "src", _originals.footerLogoSrc);

      if (_originals.primaryColor) {
        _setCSSVar("--primary", _originals.primaryColor);
      } else {
        document.documentElement.style.removeProperty("--primary");
      }
    },
  };

  global.ContentInjector = ContentInjector;
})(typeof window !== "undefined" ? window : this);
