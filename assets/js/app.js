/**
 * app.js
 * Purpose: Hillmeet app JS (copy link, view toggle, vote batch, results, lock, etc.).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
(function() {
  'use strict';

  function byId(id) {
    return document.getElementById(id);
  }

  function copyLink() {
    var el = byId('poll-url');
    if (!el) return;
    el.select && el.select();
    try {
      document.execCommand('copy');
      showToast('Link copied');
    } catch (e) {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(el.value).then(function() {
          showToast('Link copied');
        });
      }
    }
  }

  function showToast(message) {
    var existing = document.querySelector('.toast');
    if (existing) existing.remove();
    var t = document.createElement('div');
    t.className = 'toast';
    t.setAttribute('role', 'status');
    t.textContent = message;
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 3000);
  }

  document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'copy-link') {
      e.preventDefault();
      copyLink();
    }
  });

  // View toggle (list/grid) – persist in sessionStorage
  var listEl = document.getElementById('poll-options-list');
  if (listEl) {
    var view = sessionStorage.getItem('hillmeet_poll_view') || 'list';
    listEl.classList.toggle('poll-view-list', view === 'list');
    listEl.classList.toggle('poll-view-grid', view === 'grid');
    document.querySelectorAll('.view-toggle').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var v = this.getAttribute('data-view');
        sessionStorage.setItem('hillmeet_poll_view', v);
        listEl.classList.toggle('poll-view-list', v === 'list');
        listEl.classList.toggle('poll-view-grid', v === 'grid');
        document.querySelectorAll('.view-toggle').forEach(function(b) {
          b.setAttribute('aria-pressed', b.getAttribute('data-view') === v ? 'true' : 'false');
        });
      });
      btn.setAttribute('aria-pressed', btn.getAttribute('data-view') === view ? 'true' : 'false');
    });
  }

  // Toggle results (expand/collapse)
  var toggleResults = document.getElementById('toggle-results');
  var resultsSection = document.getElementById('results-section');
  var resultsContent = document.getElementById('results-content');
  if (toggleResults && resultsSection && resultsContent && window.HILLMEET_POLL) {
    toggleResults.addEventListener('click', function() {
      var open = resultsSection.hasAttribute('open');
      if (open) {
        resultsSection.removeAttribute('open');
        toggleResults.textContent = 'Show results';
        toggleResults.setAttribute('aria-expanded', 'false');
      } else {
        if (resultsContent.innerHTML.trim() === 'Loading…' || resultsContent.querySelector('table') === null) {
          fetch(window.HILLMEET_POLL.resultsUrl)
            .then(function(r) { return r.text(); })
            .then(function(html) {
              resultsContent.innerHTML = html;
            });
        }
        resultsSection.setAttribute('open', 'open');
        toggleResults.textContent = 'Hide results';
        toggleResults.setAttribute('aria-expanded', 'true');
      }
    });
  }
})();
