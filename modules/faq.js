/* ═══════════════════════════════════════════════════════════════════════════
   SSI Endoskeleton Module — faq.js
   Enhanced accordion behavior for .faq-item elements.
   Supports optional single-open mode via data-faq-single="true".
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var faqLists = document.querySelectorAll('.faq-list');
  if (!faqLists.length) return;

  faqLists.forEach(function (list) {
    var singleMode = list.getAttribute('data-faq-single') === 'true';
    var items = list.querySelectorAll('.faq-item');

    items.forEach(function (item) {
      var question = item.querySelector('.faq-question');
      var answer = item.querySelector('.faq-answer');
      if (!question || !answer) return;

      /* Setup ARIA */
      var id = 'faq-' + Math.random().toString(36).substr(2, 9);
      answer.id = id;
      question.setAttribute('role', 'button');
      question.setAttribute('tabindex', '0');
      question.setAttribute('aria-expanded', item.classList.contains('open') ? 'true' : 'false');
      question.setAttribute('aria-controls', id);
      answer.setAttribute('role', 'region');
      answer.setAttribute('aria-labelledby', question.id || '');

      /* Smooth height animation */
      if (item.classList.contains('open')) {
        answer.style.display = 'block';
        answer.style.maxHeight = answer.scrollHeight + 'px';
      } else {
        answer.style.display = 'none';
        answer.style.maxHeight = '0';
      }
      answer.style.overflow = 'hidden';
      answer.style.transition = 'max-height 0.3s ease';

      function toggle() {
        var isOpen = item.classList.contains('open');

        /* Close others in single mode */
        if (singleMode && !isOpen) {
          items.forEach(function (other) {
            if (other !== item && other.classList.contains('open')) {
              other.classList.remove('open');
              var otherQ = other.querySelector('.faq-question');
              var otherA = other.querySelector('.faq-answer');
              if (otherQ) otherQ.setAttribute('aria-expanded', 'false');
              if (otherA) {
                otherA.style.maxHeight = '0';
                setTimeout(function () { otherA.style.display = 'none'; }, 300);
              }
            }
          });
        }

        if (isOpen) {
          item.classList.remove('open');
          question.setAttribute('aria-expanded', 'false');
          answer.style.maxHeight = '0';
          setTimeout(function () { answer.style.display = 'none'; }, 300);
        } else {
          item.classList.add('open');
          question.setAttribute('aria-expanded', 'true');
          answer.style.display = 'block';
          requestAnimationFrame(function () {
            answer.style.maxHeight = answer.scrollHeight + 'px';
          });
        }
      }

      question.addEventListener('click', toggle);
      question.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          toggle();
        }
      });
    });
  });
})();
