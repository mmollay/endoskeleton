/* ═══════════════════════════════════════════════════════════════════════════
   SSI Endoskeleton Module — newsletter.js
   Injects a newsletter signup section before the footer.
   Requires NEWSLETTER_API_URL in SITE_CONFIG or meta tag.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var CONFIG = window.SITE_CONFIG || {};
  var apiUrl = CONFIG.newsletterApiUrl || '';
  var apiKey = CONFIG.newsletterApiKey || '';

  /* Find insertion point */
  var footer = document.querySelector('.site-footer') || document.getElementById('site-footer');
  if (!footer) return;

  /* Create newsletter section */
  var section = document.createElement('section');
  section.className = 'section-dark text-center';
  section.id = 'newsletter';
  section.innerHTML =
    '<div class="container-narrow">'
    + '<p class="section-tag">Newsletter</p>'
    + '<h2 class="section-title" style="color:#fff;">Bleiben Sie informiert</h2>'
    + '<p class="section-lead mx-auto" style="color:rgba(255,255,255,0.7);">'
    + 'Erhalten Sie aktuelle Neuigkeiten und Angebote direkt in Ihr Postfach.</p>'
    + '<form class="newsletter-form" style="display:flex;gap:var(--space-sm);max-width:28rem;margin:var(--space-xl) auto 0;flex-wrap:wrap;justify-content:center;">'
    + '<label for="newsletter-email" class="sr-only">E-Mail-Adresse</label>'
    + '<input type="email" id="newsletter-email" name="email" placeholder="Ihre E-Mail-Adresse" required '
    + 'style="flex:1;min-width:12rem;padding:0.75rem 1rem;border:0.0625rem solid rgba(255,255,255,0.2);'
    + 'border-radius:var(--radius-sm);background:rgba(255,255,255,0.1);color:#fff;font-size:var(--fs-sm);">'
    + '<button type="submit" class="btn btn-primary">Anmelden</button>'
    + '</form>'
    + '<div class="newsletter-msg" style="margin-top:var(--space-md);font-size:var(--fs-sm);min-height:1.5rem;"></div>'
    + '</div>';

  footer.parentNode.insertBefore(section, footer);

  /* Form handler */
  var form = section.querySelector('.newsletter-form');
  var msgEl = section.querySelector('.newsletter-msg');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var email = form.querySelector('input[type="email"]').value.trim();
    if (!email) return;

    var btn = form.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Wird angemeldet...';
    msgEl.textContent = '';
    msgEl.style.color = '';

    if (!apiUrl) {
      msgEl.textContent = 'Danke! Wir haben Ihre Anmeldung erhalten.';
      msgEl.style.color = 'rgba(255,255,255,0.85)';
      btn.disabled = false;
      btn.textContent = 'Anmelden';
      form.reset();
      return;
    }

    var headers = { 'Content-Type': 'application/json' };
    if (apiKey) headers['Authorization'] = 'Bearer ' + apiKey;

    fetch(apiUrl, {
      method: 'POST',
      headers: headers,
      body: JSON.stringify({ email: email })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data.ok || data.success) {
        msgEl.textContent = 'Danke! Sie wurden erfolgreich angemeldet.';
        msgEl.style.color = 'rgba(255,255,255,0.85)';
        form.reset();
      } else {
        msgEl.textContent = data.error || data.message || 'Ein Fehler ist aufgetreten.';
        msgEl.style.color = '#e74c3c';
      }
    })
    .catch(function () {
      msgEl.textContent = 'Verbindungsfehler. Bitte versuchen Sie es später.';
      msgEl.style.color = '#e74c3c';
    })
    .finally(function () {
      btn.disabled = false;
      btn.textContent = 'Anmelden';
    });
  });
})();
