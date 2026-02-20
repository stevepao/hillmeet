/**
 * Hillmeet – minimal app JS (copy link, view toggle, etc.)
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

  // Vote state: savedVotes (server) vs draftVotes (UI); dirty only when they differ; inline controls below options
  (function initVoteInlineControls() {
    var poll = window.HILLMEET_POLL;
    var listEl = document.getElementById('poll-options-list');
    var controlsEl = document.getElementById('vote-inline-controls');
    var statusEl = document.getElementById('vote-status-message');
    var actionsEl = document.getElementById('vote-inline-actions');
    if (!poll || !poll.voteBatchUrl || !poll.csrfToken || !listEl || !controlsEl) return;
    if (!poll.canEdit) return;

    function getStateFromDom() {
      var state = {};
      listEl.querySelectorAll('.option-card').forEach(function(card) {
        var optionId = card.getAttribute('data-option-id');
        if (!optionId) return;
        var active = card.querySelector('.vote-chip.active');
        state[optionId] = active ? (active.getAttribute('data-vote') || active.value || '') : '';
      });
      return state;
    }

    function copyState(s) {
      var out = {};
      for (var k in s) out[k] = s[k];
      return out;
    }

    function deepEqualVotes(a, b) {
      var keys = {};
      for (var k in a) keys[k] = true;
      for (var k in b) keys[k] = true;
      for (var k in keys) if ((a[k] || '') !== (b[k] || '')) return false;
      return true;
    }

    var selectedLabels = { yes: 'Works', maybe: 'If needed', no: "Can't" };
    function applyStateToDom(state) {
      listEl.querySelectorAll('.option-card').forEach(function(card) {
        var optionId = card.getAttribute('data-option-id');
        var value = state[optionId] || '';
        card.querySelectorAll('.vote-chip').forEach(function(btn) {
          var v = btn.getAttribute('data-vote') || btn.value || '';
          var isActive = v === value;
          btn.classList.toggle('active', isActive);
          btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        var labelEl = card.querySelector('.vote-selected-label');
        if (labelEl) labelEl.textContent = 'Selected: ' + (selectedLabels[value] || '—');
      });
    }

    var savedVotes = getStateFromDom();
    var draftVotes = copyState(savedVotes);
    var savedMessageTimeout = null;

    function countUnsaved() {
      var n = 0;
      var keys = {};
      for (var k in draftVotes) keys[k] = true;
      for (var k in savedVotes) keys[k] = true;
      for (var k in keys) if ((draftVotes[k] || '') !== (savedVotes[k] || '')) n++;
      return n;
    }

    function isDirty() {
      return !deepEqualVotes(draftVotes, savedVotes);
    }

    function updateInlineControls(showSavedMessage) {
      var dirty = isDirty();
      var submitBtn = document.getElementById('vote-submit');
      var cancelBtn = document.getElementById('vote-cancel');
      if (submitBtn) submitBtn.disabled = !dirty;
      if (showSavedMessage) {
        if (statusEl) statusEl.textContent = 'All changes saved • just now';
        if (actionsEl) actionsEl.hidden = true;
        if (savedMessageTimeout) clearTimeout(savedMessageTimeout);
        savedMessageTimeout = setTimeout(function() {
          savedMessageTimeout = null;
          if (statusEl) statusEl.textContent = '';
        }, 2500);
        return;
      }
      if (savedMessageTimeout) { clearTimeout(savedMessageTimeout); savedMessageTimeout = null; }
      if (dirty) {
        var n = countUnsaved();
        if (statusEl) statusEl.textContent = n === 1 ? 'Unsaved changes (1)' : 'Unsaved changes (' + n + ')';
        if (actionsEl) actionsEl.hidden = false;
      } else {
        if (statusEl) statusEl.textContent = '';
        if (actionsEl) actionsEl.hidden = true;
      }
    }

    listEl.querySelectorAll('.vote-form').forEach(function(form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        var optionId = (form.querySelector('input[name="option_id"]') || {}).value;
        var submitter = e.submitter;
        if (!optionId || !submitter || !submitter.classList.contains('vote-chip')) return;
        var vote = submitter.value || submitter.getAttribute('data-vote') || '';
        draftVotes[optionId] = vote;
        applyStateToDom(draftVotes);
        updateInlineControls(false);
      });
    });

    var cancelBtn = document.getElementById('vote-cancel');
    if (cancelBtn) {
      cancelBtn.addEventListener('click', function() {
        draftVotes = copyState(savedVotes);
        applyStateToDom(draftVotes);
        updateInlineControls(false);
      });
    }

    var submitBtn = document.getElementById('vote-submit');
    if (submitBtn) {
      submitBtn.addEventListener('click', function() {
        if (!isDirty()) return;
        var formData = new FormData();
        formData.append('csrf_token', poll.csrfToken);
        formData.append('slug', poll.slug);
        if (poll.secret) formData.append('secret', poll.secret);
        if (poll.invite) formData.append('invite', poll.invite);
        for (var optId in draftVotes) {
          if (draftVotes[optId]) formData.append('votes[' + optId + ']', draftVotes[optId]);
        }
        var originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving…';
        fetch(poll.voteBatchUrl, { method: 'POST', body: formData })
          .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, status: r.status, body: j }; }); })
          .then(function(result) {
            if (result.ok && result.body && result.body.success) {
              var resp = result.body.savedVotes || {};
              savedVotes = {};
              for (var k in resp) savedVotes[String(k)] = resp[k];
              draftVotes = copyState(savedVotes);
              applyStateToDom(draftVotes);
              showToast('Votes saved');
              updateInlineControls(true);
              var resultsSection = document.getElementById('results-section');
              var resultsContent = document.getElementById('results-content');
              if (resultsContent && resultsSection && resultsSection.hasAttribute('open') && poll.resultsUrl) {
                fetch(poll.resultsUrl).then(function(r) {
                  if (!r.ok) return '';
                  return r.text();
                }).then(function(html) {
                  if (html && html.trim().length > 0) resultsContent.innerHTML = html;
                }).catch(function() { /* keep existing content on fetch failure */ });
              }
            } else {
              var msg = result.body && result.body.error ? result.body.error : 'Could not save votes.';
              showToast(msg);
            }
          })
          .catch(function() {
            showToast('Could not save votes.');
          })
          .then(function() {
            submitBtn.disabled = !isDirty();
            submitBtn.textContent = originalText;
          });
      });
    }

    updateInlineControls(false);
  })();

  // Toggle results (expand/collapse); results are server-rendered, no fetch
  var toggleResults = document.getElementById('toggle-results');
  var resultsSection = document.getElementById('results-section');
  if (toggleResults && resultsSection) {
    toggleResults.addEventListener('click', function(e) {
      e.preventDefault();
      var open = resultsSection.hasAttribute('open');
      if (open) {
        resultsSection.removeAttribute('open');
        toggleResults.textContent = 'Show results';
        toggleResults.setAttribute('aria-expanded', 'false');
      } else {
        resultsSection.setAttribute('open', 'open');
        toggleResults.textContent = 'Hide results';
        toggleResults.setAttribute('aria-expanded', 'true');
      }
    });
  }
})();
