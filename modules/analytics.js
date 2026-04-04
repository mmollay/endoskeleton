/* ═══════════════════════════════════════════════════════════════════════════
   SSI Endoskeleton Module — analytics.js
   Google Analytics 4 setup. Respects cookie consent.
   Uses GA_ID from SITE_CONFIG or meta tag.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var CONFIG = window.SITE_CONFIG || {};
  var gaId = CONFIG.googleAnalyticsId || '';

  /* Try meta tag */
  if (!gaId) {
    var meta = document.querySelector('meta[name="ga-id"]');
    if (meta) gaId = meta.getAttribute('content') || '';
  }

  if (!gaId) return;

  function loadGA() {
    /* Prevent double-loading */
    if (window.gtag) return;

    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gaId);
    document.head.appendChild(script);

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', gaId, {
      anonymize_ip: true,
      cookie_flags: 'SameSite=None;Secure'
    });
  }

  /* Check cookie consent */
  var consent = localStorage.getItem('cookie-consent');
  if (consent === 'accepted') {
    loadGA();
  }

  /* Listen for consent changes */
  window.addEventListener('cookie-consent', function (e) {
    if (e.detail && e.detail.accepted) {
      loadGA();
    }
  });
})();
