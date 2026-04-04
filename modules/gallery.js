/* ═══════════════════════════════════════════════════════════════════════════
   SSI Endoskeleton Module — gallery.js
   Lightbox gallery with keyboard nav, touch swipe, and counter.
   Works with any element having class .gallery-item containing an img.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var items = document.querySelectorAll('.gallery-item img');
  if (!items.length) return;

  var images = [];
  items.forEach(function (img) {
    images.push({
      src: img.getAttribute('data-full') || img.src,
      alt: img.alt || ''
    });
  });

  var currentIndex = 0;

  /* Create lightbox DOM */
  var lb = document.createElement('div');
  lb.className = 'lightbox';
  lb.setAttribute('role', 'dialog');
  lb.setAttribute('aria-label', 'Bildergalerie');
  lb.innerHTML =
    '<button class="lightbox-close" aria-label="Schliessen">&times;</button>'
    + '<button class="lightbox-nav lightbox-prev" aria-label="Vorheriges Bild">&#8249;</button>'
    + '<img src="" alt="" />'
    + '<button class="lightbox-nav lightbox-next" aria-label="Nächstes Bild">&#8250;</button>'
    + '<div class="lightbox-counter"></div>';
  document.body.appendChild(lb);

  var lbImg = lb.querySelector('img');
  var lbCounter = lb.querySelector('.lightbox-counter');
  var lbClose = lb.querySelector('.lightbox-close');
  var lbPrev = lb.querySelector('.lightbox-prev');
  var lbNext = lb.querySelector('.lightbox-next');

  function showImage(index) {
    if (index < 0) index = images.length - 1;
    if (index >= images.length) index = 0;
    currentIndex = index;
    lbImg.src = images[index].src;
    lbImg.alt = images[index].alt;
    lbCounter.textContent = (index + 1) + ' / ' + images.length;
  }

  function openLightbox(index) {
    showImage(index);
    lb.classList.add('active');
    document.body.style.overflow = 'hidden';
    lbClose.focus();
  }

  function closeLightbox() {
    lb.classList.remove('active');
    document.body.style.overflow = '';
  }

  /* Click handlers on gallery items */
  items.forEach(function (img, i) {
    img.style.cursor = 'pointer';
    img.setAttribute('tabindex', '0');
    img.setAttribute('role', 'button');
    img.setAttribute('aria-label', 'Bild vergrößern: ' + (img.alt || 'Bild ' + (i + 1)));

    img.addEventListener('click', function () { openLightbox(i); });
    img.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openLightbox(i);
      }
    });
  });

  /* Navigation */
  lbClose.addEventListener('click', closeLightbox);
  lbPrev.addEventListener('click', function (e) { e.stopPropagation(); showImage(currentIndex - 1); });
  lbNext.addEventListener('click', function (e) { e.stopPropagation(); showImage(currentIndex + 1); });

  /* Click on backdrop to close */
  lb.addEventListener('click', function (e) {
    if (e.target === lb) closeLightbox();
  });

  /* Keyboard navigation */
  document.addEventListener('keydown', function (e) {
    if (!lb.classList.contains('active')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') showImage(currentIndex - 1);
    if (e.key === 'ArrowRight') showImage(currentIndex + 1);
  });

  /* Touch swipe support */
  var touchStartX = 0;
  var touchEndX = 0;
  var swipeThreshold = 50;

  lb.addEventListener('touchstart', function (e) {
    touchStartX = e.changedTouches[0].screenX;
  }, { passive: true });

  lb.addEventListener('touchend', function (e) {
    touchEndX = e.changedTouches[0].screenX;
    var diff = touchStartX - touchEndX;
    if (Math.abs(diff) > swipeThreshold) {
      if (diff > 0) {
        showImage(currentIndex + 1);
      } else {
        showImage(currentIndex - 1);
      }
    }
  }, { passive: true });
})();
