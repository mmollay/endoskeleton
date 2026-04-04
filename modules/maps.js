/* ═══════════════════════════════════════════════════════════════════════════
   SSI Endoskeleton Module — maps.js
   Embeds Google Maps iframe in element with id="map-container".
   Uses GOOGLE_MAPS_KEY from SITE_CONFIG or meta tag.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var container = document.getElementById('map-container');
  if (!container) return;

  var CONFIG = window.SITE_CONFIG || {};
  var apiKey = CONFIG.googleMapsKey || '';

  /* Try meta tag if no config key */
  if (!apiKey) {
    var meta = document.querySelector('meta[name="google-maps-key"]');
    if (meta) apiKey = meta.getAttribute('content') || '';
  }

  var address = container.getAttribute('data-address') || CONFIG.contact && CONFIG.contact.address || '';
  var zoom = container.getAttribute('data-zoom') || '15';
  var height = container.getAttribute('data-height') || '400';

  if (!address && !container.getAttribute('data-lat')) {
    container.innerHTML = '<p style="text-align:center;padding:var(--space-xl);color:var(--text-muted);">Kartenadresse nicht konfiguriert.</p>';
    return;
  }

  /* Build embed URL */
  var src = '';
  var lat = container.getAttribute('data-lat');
  var lng = container.getAttribute('data-lng');

  if (lat && lng) {
    src = 'https://www.google.com/maps/embed/v1/view?key=' + encodeURIComponent(apiKey)
      + '&center=' + encodeURIComponent(lat) + ',' + encodeURIComponent(lng)
      + '&zoom=' + encodeURIComponent(zoom);
  } else if (apiKey) {
    src = 'https://www.google.com/maps/embed/v1/place?key=' + encodeURIComponent(apiKey)
      + '&q=' + encodeURIComponent(address)
      + '&zoom=' + encodeURIComponent(zoom);
  } else {
    /* Fallback: no API key, use embed without key */
    src = 'https://maps.google.com/maps?q=' + encodeURIComponent(address)
      + '&z=' + encodeURIComponent(zoom) + '&output=embed';
  }

  var iframe = document.createElement('iframe');
  iframe.src = src;
  iframe.width = '100%';
  iframe.height = height;
  iframe.style.border = '0';
  iframe.style.borderRadius = 'var(--radius-md)';
  iframe.setAttribute('allowfullscreen', '');
  iframe.setAttribute('loading', 'lazy');
  iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
  iframe.setAttribute('title', 'Google Maps: ' + (address || 'Standort'));

  container.innerHTML = '';
  container.appendChild(iframe);
})();
