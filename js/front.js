/* bl-plugin-comments — front.js */
(function () {
  'use strict';

  /* ── Compteur de caractères ─────────────────── */
  var textarea = document.getElementById('blc-content');
  var counter  = document.getElementById('blc-char-current');

  if (textarea && counter) {
    var max = parseInt(textarea.getAttribute('maxlength'), 10) || 1000;

    function updateCount() {
      var len = textarea.value.length;
      counter.textContent = len;

      if (len > max * 0.95)      counter.style.color = '#ef4444';
      else if (len > max * 0.80) counter.style.color = '#f59e0b';
      else                        counter.style.color = '';
    }

    textarea.addEventListener('input', updateCount);
    updateCount();
  }

  /* ── Auto-dismiss des alertes ───────────────── */
  document.querySelectorAll('.blc-alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s, max-height .4s';
      el.style.opacity    = '0';
      el.style.maxHeight  = '0';
      el.style.overflow   = 'hidden';
      setTimeout(function () { el.remove(); }, 450);
    }, 6000);
  });

  /* ── Scroll vers #comments si redirecté ─────── */
  if (window.location.hash === '#comments') {
    var section = document.getElementById('comments');
    if (section) {
      setTimeout(function () {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 150);
    }
  }

  /* ── Toggle notification email ──────────────── */
  var notifyInput = document.getElementById('blc-notify');
  var emailFieldWrapper = document.getElementById('blc-email-field-wrapper');
  var emailInput = document.getElementById('blc-email');

  function updateEmailFieldVisibility() {
    if (!notifyInput || !emailFieldWrapper || !emailInput) {
      return;
    }

    var shouldShow = notifyInput.checked;
    emailFieldWrapper.style.display = shouldShow ? '' : 'none';
    emailInput.disabled = !shouldShow;

    if (!shouldShow) {
      emailInput.value = '';
    }
  }

  if (notifyInput && emailFieldWrapper && emailInput) {
    notifyInput.addEventListener('change', updateEmailFieldVisibility);
    updateEmailFieldVisibility();
  }

  function setupCommentsPagination() {
    var commentsSection = document.getElementById('comments');
    var list = document.querySelector('.blc-front__list');
    if (!list) return;

    var comments = Array.prototype.slice.call(
      list.querySelectorAll('.blc-front__comment')
    );
    var perPage = 10;
    if (commentsSection) {
      var configured = parseInt(commentsSection.dataset.commentsPerPage || '', 10);
      if (!isNaN(configured) && configured > 0) {
        perPage = configured;
      }
    }
    var windowSize = 5;

    if (comments.length <= perPage) return;

    var totalPages = Math.ceil(comments.length / perPage);
    var currentPage = 1;
    var nav = document.createElement('nav');
    nav.className = 'blc-pagination blc-pagination--front';
    var paginationLabel = (commentsSection && commentsSection.dataset.paginationLabel) || 'Comments pagination';
    nav.setAttribute('aria-label', paginationLabel);
    list.insertAdjacentElement('afterend', nav);

    function renderControls() {
      nav.innerHTML = '';

      var prevBtn = document.createElement('button');
      prevBtn.type = 'button';
      prevBtn.className = 'blc-pagination__btn blc-pagination__btn--nav';
      prevBtn.textContent = '‹';
      prevBtn.disabled = currentPage === 1;
      prevBtn.addEventListener('click', function () {
        if (currentPage > 1) {
          goToPage(currentPage - 1);
        }
      });
      nav.appendChild(prevBtn);

      var start = Math.floor((currentPage - 1) / windowSize) * windowSize + 1;
      var end = Math.min(totalPages, start + windowSize - 1);

      for (var p = start; p <= end; p++) {
        var pageBtn = document.createElement('button');
        pageBtn.type = 'button';
        pageBtn.className = 'blc-pagination__btn' + (p === currentPage ? ' is-active' : '');
        pageBtn.textContent = String(p);
        pageBtn.setAttribute('aria-current', p === currentPage ? 'page' : 'false');
        (function (pageNumber) {
          pageBtn.addEventListener('click', function () { goToPage(pageNumber); });
        })(p);
        nav.appendChild(pageBtn);
      }

      var nextBtn = document.createElement('button');
      nextBtn.type = 'button';
      nextBtn.className = 'blc-pagination__btn blc-pagination__btn--nav';
      nextBtn.textContent = '›';
      nextBtn.disabled = currentPage === totalPages;
      nextBtn.addEventListener('click', function () {
        if (currentPage < totalPages) {
          goToPage(currentPage + 1);
        }
      });
      nav.appendChild(nextBtn);
    }

    function renderPage() {
      var startIndex = (currentPage - 1) * perPage;
      var endIndex = startIndex + perPage;
      comments.forEach(function (comment, index) {
        comment.style.display = index >= startIndex && index < endIndex ? '' : 'none';
      });
      renderControls();
    }

    function goToPage(page) {
      currentPage = Math.max(1, Math.min(totalPages, page));
      renderPage();
    }

    renderPage();
  }

  setupCommentsPagination();

})();
