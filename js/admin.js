/* bl-plugin-comments — admin.js */
(function () {
  'use strict';
  var adminRoot = document.getElementById('blc-admin-root');
  var I18N = {
    enabled: (adminRoot && adminRoot.dataset.labelEnabled) || 'Enabled',
    disabled: (adminRoot && adminRoot.dataset.labelDisabled) || 'Disabled',
    errorAction: (adminRoot && adminRoot.dataset.errorAction) || 'Error while processing action. Please try again.',
    savingOk: (adminRoot && adminRoot.dataset.savingOk) || 'Saved',
    savingError: (adminRoot && adminRoot.dataset.savingError) || 'Error',
    savingNetworkError: (adminRoot && adminRoot.dataset.savingNetworkError) || 'Network error',
    smtpTestRunning: (adminRoot && adminRoot.dataset.smtpTestRunning) || 'SMTP test in progress...'
  };

  /* ═══════════════════════════════════════════════
     TABS
  ══════════════════════════════════════════════════ */
  var tabs    = document.querySelectorAll('.blc-tab');
  var panels  = document.querySelectorAll('.blc-tab-content');

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var target = this.dataset.tab;

      tabs.forEach(function (t) {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
      });
      panels.forEach(function (p) {
        p.classList.remove('active');
      });

      this.classList.add('active');
      this.setAttribute('aria-selected', 'true');
      var panel = document.getElementById('blc-tab-' + target);
      if (panel) panel.classList.add('active');

      // Mettre à jour le hash sans scroll
      try {
        history.replaceState(null, '', window.location.pathname + window.location.search + '#tab-' + target);
      } catch (e) {}
    });
  });

  function createPagination(options) {
    var container = options.container;
    var items = options.items;
    var perPage = options.perPage;
    var windowSize = options.windowSize || 5;
    var renderItem = options.renderItem;
    var onPageChange = options.onPageChange || function () {};

    if (!container || !items || !items.length || items.length <= perPage) {
      if (typeof renderItem === 'function') {
        items.forEach(function (item) { renderItem(item, true); });
      }
      return;
    }

    var totalPages = Math.ceil(items.length / perPage);
    var currentPage = 1;
    var nav = document.createElement('nav');
    nav.className = 'blc-pagination';
    nav.setAttribute('aria-label', options.ariaLabel || 'Pagination');
    container.appendChild(nav);

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
      items.forEach(function (item, index) {
        if (typeof renderItem === 'function') {
          renderItem(item, index >= startIndex && index < endIndex);
        }
      });
      renderControls();
      onPageChange(currentPage);
    }

    function goToPage(page) {
      currentPage = Math.max(1, Math.min(totalPages, page));
      renderPage();
    }

    renderPage();
  }

  /* ═══════════════════════════════════════════════
     ACCORDION PAGE BLOCKS
  ══════════════════════════════════════════════════ */
  document.querySelectorAll('.blc-page-block__header').forEach(function (header) {
    header.addEventListener('click', function () {
      var targetId = this.dataset.toggle;
      var body = document.getElementById(targetId);
      if (!body) return;

      var isOpen = body.classList.contains('open');
      body.classList.toggle('open');

      var chevron = this.querySelector('.blc-chevron');
      if (chevron) {
        chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
      }
    });
  });

  function openFirstVisiblePendingBlock() {
    var pendingBadges = document.querySelectorAll('.blc-badge--pending');
    for (var i = 0; i < pendingBadges.length; i++) {
      var badge = pendingBadges[i];
      var block = badge.closest('.blc-page-block');
      if (!block || block.dataset.autoOpened === '1') continue;
      if (block.style.display === 'none') continue;
      var header = block.querySelector('.blc-page-block__header');
      if (header) {
        header.click();
        block.dataset.autoOpened = '1';
      }
      break;
    }
  }

  // Pagination onglet Modération (5 blocs par page)
  var moderationPanel = document.getElementById('blc-tab-moderation');
  if (moderationPanel) {
    var moderationBlocks = Array.prototype.slice.call(
      moderationPanel.querySelectorAll('.blc-page-block')
    );
    createPagination({
      container: moderationPanel,
      items: moderationBlocks,
      perPage: 5,
      windowSize: 5,
      ariaLabel: 'Pagination modération',
      renderItem: function (item, visible) {
        item.style.display = visible ? '' : 'none';
      },
      onPageChange: function () {
        openFirstVisiblePendingBlock();
      }
    });
  }

  // Pagination onglet Pages (5 lignes par page)
  var pagesPanel = document.getElementById('blc-tab-pages');
  if (pagesPanel) {
    var pagesTable = pagesPanel.querySelector('.blc-pages-table');
    if (pagesTable) {
      var pageRows = Array.prototype.slice.call(
        pagesTable.querySelectorAll('.blc-pages-row')
      );
      createPagination({
        container: pagesPanel,
        items: pageRows,
        perPage: 5,
        windowSize: 5,
        ariaLabel: 'Pagination des pages',
        renderItem: function (item, visible) {
          item.style.display = visible ? '' : 'none';
        }
      });
    }
  }

  /* ═══════════════════════════════════════════════
     ACTIONS DE MODÉRATION (approve / delete)
  ══════════════════════════════════════════════════ */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.blc-action-btn');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    var action    = btn.dataset.action;
    var pageKey   = btn.dataset.pageKey;
    var commentId = btn.dataset.commentId || '';

    // Confirmation pour suppressions
    var confirmMsg = btn.dataset.confirm
      || (action.indexOf('delete') !== -1 || action.indexOf('clear') !== -1
          ? 'Confirmer la suppression ?'
          : null);

    if (confirmMsg && !confirm(confirmMsg)) return;

    // Désactiver le bouton pendant la requête
    btn.disabled = true;
    btn.style.opacity = '.6';

    var fd = new FormData();
    fd.append('bl_comment_action', action);
    fd.append('page_key',   pageKey);
    fd.append('comment_id', commentId);

    fetch(window.location.href, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) {
        if (r.ok) {
          window.location.reload();
        } else {
          alert(I18N.errorAction);
          btn.disabled = false;
          btn.style.opacity = '';
        }
      })
      .catch(function () {
        // Fallback — recharge la page
        window.location.reload();
      });
  });

  /* ═══════════════════════════════════════════════
     TOGGLE PAGES (onglet Pages)
  ══════════════════════════════════════════════════ */
  document.querySelectorAll('.blc-page-toggle').forEach(function (input) {
    input.addEventListener('change', function () {
      var pageKey = this.dataset.pageKey;
      var enabled = this.checked;
      var toggleInput = this;

      var statusEl = this.closest('.blc-toggle').querySelector('.blc-toggle__status');
      if (statusEl) statusEl.textContent = enabled ? I18N.enabled : I18N.disabled;

      var fd = new FormData();
      fd.append('bl_toggle_comments', '1');
      fd.append('page_key', pageKey);
      fd.append('enabled',  enabled ? '1' : '0');

      fetch(window.location.href, {
        method:  'POST',
        body:    fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function (r) {
        if (!r.ok) {
          throw new Error('HTTP ' + r.status);
        }
        return r.json();
      })
      .then(function (data) {
        if (!data.ok) {
          // Rollback
          toggleInput.checked = !enabled;
          if (statusEl) statusEl.textContent = !enabled ? I18N.enabled : I18N.disabled;
        }
      })
      .catch(function () {
        // Rollback visible en cas d'erreur serveur/réseau
        toggleInput.checked = !enabled;
        if (statusEl) statusEl.textContent = !enabled ? I18N.enabled : I18N.disabled;
      });
    });
  });

  /* ═══════════════════════════════════════════════
     TEST SMTP (onglet Réglages)
  ══════════════════════════════════════════════════ */
  var smtpTestBtn = document.getElementById('blc-smtp-test-btn');
  var smtpTestResult = document.getElementById('blc-smtp-test-result');

  if (smtpTestBtn) {
    smtpTestBtn.addEventListener('click', function () {
      var smtpEnabled = document.querySelector('input[name="smtpEnabled"][type="checkbox"]');
      var smtpHost = document.querySelector('input[name="smtpHost"]');
      var smtpPort = document.querySelector('input[name="smtpPort"]');
      var smtpEncryption = document.querySelector('select[name="smtpEncryption"]');
      var smtpAuth = document.querySelector('input[name="smtpAuth"][type="checkbox"]');
      var smtpUsername = document.querySelector('input[name="smtpUsername"]');
      var smtpPassword = document.querySelector('input[name="smtpPassword"]');

      var fd = new FormData();
      fd.append('bl_comment_action', 'test_smtp');
      fd.append('smtpEnabled', smtpEnabled && smtpEnabled.checked ? '1' : '0');
      fd.append('smtpHost', smtpHost ? smtpHost.value : '');
      fd.append('smtpPort', smtpPort ? smtpPort.value : '');
      fd.append('smtpEncryption', smtpEncryption ? smtpEncryption.value : 'tls');
      fd.append('smtpAuth', smtpAuth && smtpAuth.checked ? '1' : '0');
      fd.append('smtpUsername', smtpUsername ? smtpUsername.value : '');
      fd.append('smtpPassword', smtpPassword ? smtpPassword.value : '');

      var originalText = smtpTestBtn.textContent;
      smtpTestBtn.disabled = true;

      if (smtpTestResult) {
        smtpTestResult.textContent = I18N.smtpTestRunning;
        smtpTestResult.style.color = '';
      }

      fetch(window.location.href, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function (r) {
        if (!r.ok) {
          throw new Error('HTTP ' + r.status);
        }
        return r.json();
      })
      .then(function (data) {
        if (!smtpTestResult) return;
        smtpTestResult.textContent = data && data.message ? data.message : I18N.savingError;
        smtpTestResult.style.color = data && data.ok ? '#1f7a1f' : '#a31d1d';
      })
      .catch(function () {
        if (!smtpTestResult) return;
        smtpTestResult.textContent = I18N.savingNetworkError;
        smtpTestResult.style.color = '#a31d1d';
      })
      .finally(function () {
        smtpTestBtn.disabled = false;
        smtpTestBtn.textContent = originalText;
      });
    });
  }

  /* ═══════════════════════════════════════════════
     ÉDITEUR DE PAGE — panneau comments toggle
  ══════════════════════════════════════════════════ */
  var editorPanel = document.getElementById('blc-editor-panel');
  if (editorPanel) {
    editorPanel.style.display = '';

    // Chercher la sidebar de l'éditeur Bludit (Bootstrap col)
    var sidebar = document.querySelector('.col-md-3, .col-sm-4, #panel-right, .card-settings');
    if (sidebar) {
      sidebar.appendChild(editorPanel);
    } else {
      // Fallback : widget flottant
      Object.assign(editorPanel.style, {
        position: 'fixed',
        bottom:   '20px',
        right:    '20px',
        zIndex:   '9999',
        width:    '220px',
        boxShadow:'0 4px 16px rgba(0,0,0,.12)',
        borderRadius: '10px',
      });
      document.body.appendChild(editorPanel);
    }
  }

  var editorToggle = document.getElementById('blc-page-comments-toggle');
  if (editorToggle) {
    editorToggle.addEventListener('change', function () {
      var pageKey = this.dataset.pageKey;
      var enabled = this.checked;
      var toggleInput = this;

      var label   = document.getElementById('blc-editor-toggle-label');
      var saving  = document.getElementById('blc-editor-saving');

      if (label) label.textContent = enabled ? I18N.enabled : I18N.disabled;

      var fd = new FormData();
      fd.append('bl_toggle_comments', '1');
      fd.append('page_key', pageKey);
      fd.append('enabled',  enabled ? '1' : '0');

      fetch(window.location.href, {
        method:  'POST',
        body:    fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function (r) {
        if (!r.ok) {
          throw new Error('HTTP ' + r.status);
        }
        return r.json();
      })
      .then(function (data) {
        if (!data.ok) {
          toggleInput.checked = !enabled;
          if (label) label.textContent = !enabled ? I18N.enabled : I18N.disabled;
        }
        if (saving) {
          saving.textContent = data.ok ? I18N.savingOk : I18N.savingError;
          saving.classList.add('visible');
          setTimeout(function () { saving.classList.remove('visible'); }, 2200);
        }
      })
      .catch(function () {
        toggleInput.checked = !enabled;
        if (label) label.textContent = !enabled ? I18N.enabled : I18N.disabled;
        if (saving) {
          saving.textContent = I18N.savingNetworkError;
          saving.classList.add('visible');
          setTimeout(function () { saving.classList.remove('visible'); }, 2200);
        }
      });
    });
  }

})();
