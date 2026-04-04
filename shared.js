/* ═══════════════════════════════════════════════════════════════════════════
   SSI WebGenerator Endoskeleton — shared.js v3.17.0
   Core JavaScript for all generated websites.
   Navigation, footer, cookie consent, theme toggle, scroll reveal,
   module loader, smooth scroll, skip-to-content.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  /* ─── DEFAULT CONFIG ─────────────────────────────────────────────────── */
  var defaultConfig = {
    name: '{{SITE_NAME}}',
    logo: 'img/logo.png',
    nav: [],
    contact: {},
    social: {},
    modules: [],
    legal: {
      impressum: 'impressum.html',
      datenschutz: 'datenschutz.html'
    },
    cookieText: 'Diese Website verwendet Cookies, um Ihnen ein optimales Erlebnis zu bieten.',
    footer: {
      tagline: '',
      credit: true
    }
  };

  /* Merge user config with defaults */
  var CONFIG = {};
  function mergeConfig() {
    var user = window.SITE_CONFIG || {};
    CONFIG = Object.assign({}, defaultConfig, user);
    CONFIG.contact = Object.assign({}, defaultConfig.contact, user.contact || {});
    CONFIG.social = Object.assign({}, defaultConfig.social, user.social || {});
    CONFIG.legal = Object.assign({}, defaultConfig.legal, user.legal || {});
    CONFIG.footer = Object.assign({}, defaultConfig.footer, user.footer || {});
    CONFIG.nav = user.nav || defaultConfig.nav;
    CONFIG.modules = user.modules || defaultConfig.modules;
  }

  /* ─── HELPERS ────────────────────────────────────────────────────────── */
  var currentPage = (function () {
    var path = window.location.pathname.split('/').pop() || 'index.html';
    if (path === '' || path === '/') path = 'index.html';
    return path;
  })();

  function isActive(href) {
    var h = href.split('/').pop() || 'index.html';
    return h === currentPage;
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  /* ─── 1. SKIP-TO-CONTENT ─────────────────────────────────────────────── */
  function initSkipLink() {
    var existing = document.querySelector('.skip-link');
    if (existing) return;

    var main = document.querySelector('main, #main-content, [role="main"]');
    if (!main) {
      main = document.querySelector('section');
      if (main && !main.id) main.id = 'main-content';
    }
    if (!main) return;

    var targetId = main.id || 'main-content';
    if (!main.id) main.id = targetId;

    var link = document.createElement('a');
    link.className = 'skip-link';
    link.href = '#' + targetId;
    link.textContent = 'Zum Inhalt springen';
    document.body.insertBefore(link, document.body.firstChild);
  }

  /* ─── 2. NAVIGATION ─────────────────────────────────────────────────── */
  function buildNavigation() {
    var el = document.getElementById('site-header');
    if (!el || !CONFIG.nav.length) return;

    /* Desktop links */
    var linksHtml = '';
    CONFIG.nav.forEach(function (item) {
      if (item.children && item.children.length) {
        var subHtml = '<ul class="nav-sub">';
        item.children.forEach(function (child) {
          subHtml += '<li><a href="' + escapeHtml(child.href) + '"'
            + (isActive(child.href) ? ' class="active" aria-current="page"' : '')
            + '>' + escapeHtml(child.text || child.label) + '</a></li>';
        });
        subHtml += '</ul>';
        linksHtml += '<li class="nav-dropdown"><a href="' + escapeHtml(item.href) + '"'
          + (isActive(item.href) ? ' class="active" aria-current="page"' : '')
          + '>' + escapeHtml(item.text || item.label) + '</a>' + subHtml + '</li>';
      } else {
        var cls = item.cta ? ' class="nav-cta"' : (isActive(item.href) ? ' class="active" aria-current="page"' : '');
        linksHtml += '<li><a href="' + escapeHtml(item.href) + '"' + cls + '>'
          + escapeHtml(item.text || item.label) + '</a></li>';
      }
    });

    /* Mobile links */
    var mobileHtml = '';
    CONFIG.nav.forEach(function (item) {
      mobileHtml += '<a href="' + escapeHtml(item.href) + '"'
        + (isActive(item.href) ? ' class="active"' : '')
        + (item.cta ? ' class="nav-cta" style="text-align:center;margin-top:1rem;border-radius:var(--radius-sm);"' : '')
        + '>' + escapeHtml(item.text || item.label) + '</a>';
      if (item.children) {
        item.children.forEach(function (child) {
          mobileHtml += '<a href="' + escapeHtml(child.href)
            + '" style="padding-left:1.5rem;font-size:1.1rem;"'
            + (isActive(child.href) ? ' class="active"' : '')
            + '>' + escapeHtml(child.text || child.label) + '</a>';
        });
      }
    });

    /* Logo */
    var logoHtml = CONFIG.logo
      ? '<img src="' + escapeHtml(CONFIG.logo) + '" alt="' + escapeHtml(CONFIG.name) + ' Logo" width="160" height="40">'
      : escapeHtml(CONFIG.name);

    el.innerHTML =
      '<nav class="site-nav" role="navigation" aria-label="Hauptnavigation">'
      + '<div class="nav-inner">'
      + '<a href="index.html" class="nav-logo" aria-label="' + escapeHtml(CONFIG.name) + ' Startseite">'
      + logoHtml + '</a>'
      + '<ul class="nav-links" role="list">' + linksHtml + '</ul>'
      + '<button class="nav-hamburger" aria-label="Navigation öffnen" aria-expanded="false">'
      + '<span></span><span></span><span></span></button>'
      + '</div></nav>'
      + '<div class="nav-mobile" role="navigation" aria-label="Mobile Navigation">'
      + mobileHtml + '</div>';

    /* Hamburger toggle */
    var burger = el.querySelector('.nav-hamburger');
    var mobile = el.querySelector('.nav-mobile');
    if (burger && mobile) {
      burger.addEventListener('click', function () {
        var open = mobile.classList.toggle('open');
        burger.classList.toggle('open');
        burger.setAttribute('aria-expanded', String(open));
        burger.setAttribute('aria-label', open ? 'Navigation schliessen' : 'Navigation öffnen');
        document.body.style.overflow = open ? 'hidden' : '';
      });
      mobile.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () {
          mobile.classList.remove('open');
          burger.classList.remove('open');
          burger.setAttribute('aria-expanded', 'false');
          burger.setAttribute('aria-label', 'Navigation öffnen');
          document.body.style.overflow = '';
        });
      });
    }

    /* Nav behavior: sticky (default) | autohide | transparent | shrink */
    /* Nav style:    light (default) | dark | colored | glass             */
    var nav = el.querySelector('.site-nav');
    if (nav) {
      var lastScroll = 0;
      var ticking = false;

      /* Initialer Stil anwenden */
      var initStyle = CONFIG.navStyle || 'light';
      if (initStyle !== 'light') {
        nav.classList.add('nav-' + initStyle);
      }

      /* Initiales Verhalten anwenden */
      var initBehavior = CONFIG.navBehavior || 'sticky';
      if (initBehavior === 'transparent') {
        nav.classList.add('nav-transparent');
      }
      if (initBehavior === 'shrink') {
        nav.classList.add('nav-shrink');
      }

      function applyNavState(current) {
        /* Behavior + style live lesen — Demo-Switcher kann sie ändern */
        var behavior = (window.SITE_CONFIG && window.SITE_CONFIG.navBehavior != null)
          ? window.SITE_CONFIG.navBehavior
          : (CONFIG.navBehavior || 'sticky');

        /* Scrolled-Klasse */
        nav.classList.toggle('scrolled', current > 20);

        /* Autohide */
        if (behavior === 'autohide') {
          if (current > lastScroll && current > 80) {
            nav.classList.add('nav-hidden');
          } else {
            nav.classList.remove('nav-hidden');
          }
        } else {
          nav.classList.remove('nav-hidden');
        }

        /* Transparent */
        if (behavior === 'transparent') {
          nav.classList.add('nav-transparent');
          nav.classList.toggle('nav-solid', current > 80);
        } else {
          nav.classList.remove('nav-transparent', 'nav-solid');
        }

        /* Shrink */
        nav.classList.toggle('nav-shrink', behavior === 'shrink');
      }

      window.addEventListener('scroll', function () {
        if (!ticking) {
          requestAnimationFrame(function () {
            applyNavState(window.scrollY);
            lastScroll = window.scrollY <= 0 ? 0 : window.scrollY;
            ticking = false;
          });
          ticking = true;
        }
      }, { passive: true });

      /* Sofortiges Update bei Behavior-Wechsel via Demo */
      window._applyNavState = function () { applyNavState(window.scrollY); };
    }
  }

  /* ─── 3. FOOTER ──────────────────────────────────────────────────────── */
  function buildFooter() {
    var el = document.getElementById('site-footer');
    if (!el) return;

    /* Navigation links */
    var navLinksHtml = '';
    CONFIG.nav.forEach(function (item) {
      if (!item.cta) {
        navLinksHtml += '<a href="' + escapeHtml(item.href) + '">'
          + escapeHtml(item.text || item.label) + '</a>';
      }
    });

    /* Contact column */
    var contactHtml = '';
    var c = CONFIG.contact;
    if (c.name) contactHtml += '<p>' + escapeHtml(c.name) + '</p>';
    if (c.phone) contactHtml += '<a href="tel:' + escapeHtml(c.phone.replace(/\s/g, '')) + '">' + escapeHtml(c.phone) + '</a>';
    if (c.email) contactHtml += '<a href="mailto:' + escapeHtml(c.email) + '">' + escapeHtml(c.email) + '</a>';
    if (c.address) contactHtml += '<p>' + escapeHtml(c.address) + '</p>';

    /* Social links */
    var socialHtml = '';
    var socialMap = { facebook: 'Facebook', instagram: 'Instagram', twitter: 'X/Twitter', linkedin: 'LinkedIn', youtube: 'YouTube' };
    Object.keys(CONFIG.social).forEach(function (key) {
      if (CONFIG.social[key]) {
        socialHtml += '<a href="' + escapeHtml(CONFIG.social[key])
          + '" target="_blank" rel="noopener noreferrer">'
          + (socialMap[key] || key) + '</a>';
      }
    });

    /* Logo */
    var logoHtml = CONFIG.logo
      ? '<div class="footer-logo"><img src="' + escapeHtml(CONFIG.logo)
        + '" alt="' + escapeHtml(CONFIG.name) + ' Logo" width="140" height="36"></div>'
      : '<div class="footer-logo"><strong>' + escapeHtml(CONFIG.name) + '</strong></div>';

    /* Tagline */
    var tagline = CONFIG.footer.tagline
      ? '<p class="footer-tagline">' + escapeHtml(CONFIG.footer.tagline) + '</p>'
      : '';

    /* Credit */
    var credit = CONFIG.footer.credit !== false
      ? '<p class="footer-powered">Webdesign von <a href="https://www.ssi.at" target="_blank" rel="noopener noreferrer">SSI</a></p>'
      : '';

    el.innerHTML =
      '<footer class="site-footer" role="contentinfo">'
      + '<div class="footer-grid">'
      + '<div class="footer-about">' + logoHtml + tagline
      + (socialHtml ? '<div class="mt-md">' + socialHtml + '</div>' : '')
      + '</div>'
      + '<div class="footer-col"><h4>Navigation</h4>' + navLinksHtml + '</div>'
      + '<div class="footer-col"><h4>Kontakt</h4>' + contactHtml + '</div>'
      + '<div class="footer-col"><h4>Rechtliches</h4>'
      + '<a href="' + escapeHtml(CONFIG.legal.impressum) + '">Impressum</a>'
      + '<a href="' + escapeHtml(CONFIG.legal.datenschutz) + '">Datenschutz</a>'
      + '</div>'
      + '</div>'
      + '<div class="footer-bottom">'
      + '<p>&copy; ' + new Date().getFullYear() + ' ' + escapeHtml(CONFIG.name)
      + '. Alle Rechte vorbehalten.</p>'
      + credit
      + '</div>'
      + '</footer>';
  }

  /* ─── 4. COOKIE CONSENT (DSGVO) ─────────────────────────────────────── */
  function initCookieBanner() {
    if (localStorage.getItem('cookie-consent') !== null) return;

    var banner = document.createElement('div');
    banner.id = 'cookie-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Cookie-Hinweis');
    banner.innerHTML =
      '<div class="cb-text">' + escapeHtml(CONFIG.cookieText)
      + ' <a href="' + escapeHtml(CONFIG.legal.datenschutz) + '">Mehr erfahren</a></div>'
      + '<div class="cb-buttons">'
      + '<button class="cb-btn cb-decline" type="button">Ablehnen</button>'
      + '<button class="cb-btn cb-accept" type="button">Akzeptieren</button>'
      + '</div>';
    document.body.appendChild(banner);

    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        banner.classList.add('cb-visible');
      });
    });

    function closeBanner(accepted) {
      localStorage.setItem('cookie-consent', accepted ? 'accepted' : 'declined');
      banner.classList.remove('cb-visible');
      setTimeout(function () {
        if (banner.parentNode) banner.parentNode.removeChild(banner);
      }, 400);

      /* Fire custom event so modules can react */
      window.dispatchEvent(new CustomEvent('cookie-consent', {
        detail: { accepted: accepted }
      }));
    }

    banner.querySelector('.cb-accept').addEventListener('click', function () {
      closeBanner(true);
    });

    banner.querySelector('.cb-decline').addEventListener('click', function () {
      closeBanner(false);
    });
  }

  /* ─── 5. THEME TOGGLE ───────────────────────────────────────────────── */
  function initThemeToggle() {
    /* Respect system preference */
    var saved = localStorage.getItem('theme');
    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (saved === 'dark' || (!saved && prefersDark)) {
      document.documentElement.setAttribute('data-theme', 'dark');
    }

    var btn = document.createElement('button');
    btn.className = 'theme-toggle';
    btn.setAttribute('aria-label', 'Farbschema wechseln');
    btn.setAttribute('type', 'button');

    function updateIcon() {
      var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      /* Sun for dark mode (click to go light), moon for light mode (click to go dark) */
      btn.innerHTML = isDark ? '&#9728;' : '&#9790;';
    }

    updateIcon();
    document.body.appendChild(btn);

    btn.addEventListener('click', function () {
      var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('theme', 'light');
      } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
      }
      updateIcon();
    });

    /* Listen for system preference changes */
    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
        if (!localStorage.getItem('theme')) {
          if (e.matches) {
            document.documentElement.setAttribute('data-theme', 'dark');
          } else {
            document.documentElement.removeAttribute('data-theme');
          }
          updateIcon();
        }
      });
    }
  }

  /* ─── 6. SCROLL REVEAL ──────────────────────────────────────────────── */
  function initReveal() {
    var els = document.querySelectorAll('.reveal');
    if (!els.length || !('IntersectionObserver' in window)) {
      /* Fallback: show everything */
      els.forEach(function (el) { el.classList.add('visible'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.1,
      rootMargin: '0px 0px -40px 0px'
    });

    els.forEach(function (el) {
      observer.observe(el);
    });
  }

  /* ─── 7. SMOOTH SCROLL ──────────────────────────────────────────────── */
  function initSmoothScroll() {
    document.addEventListener('click', function (e) {
      var target = e.target.closest('a[href^="#"]');
      if (!target) return;

      var id = target.getAttribute('href');
      if (id === '#') return;

      var el = document.querySelector(id);
      if (!el) return;

      e.preventDefault();
      var offset = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--nav-height'), 10) || 70;
      var top = el.getBoundingClientRect().top + window.pageYOffset - offset;

      window.scrollTo({
        top: top,
        behavior: 'smooth'
      });

      /* Update URL without scrolling */
      if (history.pushState) {
        history.pushState(null, null, id);
      }
    });
  }

  /* ─── 8. FAQ TOGGLE ─────────────────────────────────────────────────── */
  function initFaq() {
    document.querySelectorAll('.faq-question').forEach(function (btn) {
      btn.setAttribute('role', 'button');
      btn.setAttribute('tabindex', '0');

      var item = btn.parentElement;
      var answer = item.querySelector('.faq-answer');
      if (answer) {
        var id = 'faq-answer-' + Math.random().toString(36).substr(2, 9);
        answer.id = id;
        btn.setAttribute('aria-expanded', item.classList.contains('open') ? 'true' : 'false');
        btn.setAttribute('aria-controls', id);
      }

      function toggle() {
        var wasOpen = item.classList.contains('open');
        item.classList.toggle('open');
        btn.setAttribute('aria-expanded', String(!wasOpen));
      }

      btn.addEventListener('click', toggle);
      btn.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          toggle();
        }
      });
    });
  }

  /* ─── 9. MODULE LOADER ──────────────────────────────────────────────── */
  function loadModules() {
    var modules = CONFIG.modules || [];

    /* Also check data-modules on body */
    var bodyModules = document.body.getAttribute('data-modules');
    if (bodyModules) {
      bodyModules.split(',').forEach(function (m) {
        m = m.trim();
        if (m && modules.indexOf(m) === -1) modules.push(m);
      });
    }

    if (!modules.length) return;

    /* Determine base path for modules */
    var scripts = document.querySelectorAll('script[src*="shared.js"]');
    var basePath = 'modules/';
    if (scripts.length) {
      var src = scripts[0].getAttribute('src');
      var dir = src.substring(0, src.lastIndexOf('/') + 1);
      basePath = dir + 'modules/';
    }

    modules.forEach(function (mod) {
      var script = document.createElement('script');
      script.src = basePath + mod + '.js';
      script.async = true;
      script.onerror = function () {
        console.warn('[Endoskeleton] Module "' + mod + '" konnte nicht geladen werden.');
      };
      document.body.appendChild(script);
    });
  }

  /* ─── 10. CONTACT FORM (AJAX) ───────────────────────────────────────── */
  function initContactForm() {
    var form = document.querySelector('form[data-ajax]');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var submitBtn = form.querySelector('[type="submit"]');
      var originalText = submitBtn ? submitBtn.textContent : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Wird gesendet...';
      }

      /* Remove previous messages */
      var prevMsg = form.querySelector('.form-success, .form-error-msg');
      if (prevMsg) prevMsg.parentNode.removeChild(prevMsg);

      var formData = new FormData(form);

      fetch(form.action || 'contact.php', {
        method: 'POST',
        body: formData
      })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        var msg = document.createElement('div');
        if (data.ok) {
          msg.className = 'form-success';
          msg.textContent = data.message || 'Nachricht erfolgreich gesendet!';
          form.reset();
        } else {
          msg.className = 'form-error-msg';
          msg.style.cssText = 'color:var(--error);padding:var(--space-md);background:rgba(192,57,43,0.1);border-radius:var(--radius-sm);margin-top:var(--space-md);font-size:var(--fs-sm);';
          msg.textContent = data.error || 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
        }
        form.appendChild(msg);
      })
      .catch(function () {
        var msg = document.createElement('div');
        msg.className = 'form-error-msg';
        msg.style.cssText = 'color:var(--error);padding:var(--space-md);background:rgba(192,57,43,0.1);border-radius:var(--radius-sm);margin-top:var(--space-md);font-size:var(--fs-sm);';
        msg.textContent = 'Verbindungsfehler. Bitte versuchen Sie es später erneut.';
        form.appendChild(msg);
      })
      .finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalText;
        }
      });
    });
  }

  /* ─── INIT ───────────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    mergeConfig();
    initSkipLink();
    buildNavigation();
    buildFooter();

    /* Footer-Stil aus SITE_CONFIG anwenden (z.B. von Demo-Switcher oder URL-State) */
    var footerStyleInit = CONFIG.footerStyle;
    if (footerStyleInit && footerStyleInit !== 'dark') {
      var footerEl = document.querySelector('.site-footer');
      if (footerEl) {
        if (footerStyleInit === 'light') footerEl.classList.add('site-footer--light');
        else footerEl.classList.add('footer-' + footerStyleInit);
      }
    }

    initCookieBanner();
    initThemeToggle();
    initReveal();
    initSmoothScroll();
    initFaq();
    initContactForm();
    loadModules();
  });
})();
