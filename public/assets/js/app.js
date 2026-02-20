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

  // Vote state and submit bar (JS-enhanced: instant selection, single Submit)
  (function initVoteSubmitBar() {
    var poll = window.HILLMEET_POLL;
    var listEl = document.getElementById('poll-options-list');
    var barEl = document.getElementById('vote-submit-bar');
    if (!poll || !poll.voteBatchUrl || !poll.csrfToken || !listEl || !barEl) return;

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

    var initialState = getStateFromDom();
    var state = {};
    for (var k in initialState) state[k] = initialState[k];
    var dirty = false;

    function showBar() {
      barEl.hidden = false;
      barEl.classList.add('is-visible');
    }
    function hideBar() {
      barEl.hidden = true;
      barEl.classList.remove('is-visible');
    }

    listEl.querySelectorAll('.vote-form').forEach(function(form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        var optionId = (form.querySelector('input[name="option_id"]') || {}).value;
        var submitter = e.submitter;
        if (!optionId || !submitter || !submitter.classList.contains('vote-chip')) return;
        var vote = submitter.value || submitter.getAttribute('data-vote') || '';
        state[optionId] = vote;
        applyStateToDom(state);
        dirty = true;
        showBar();
      });
    });

    var cancelBtn = document.getElementById('vote-cancel');
    if (cancelBtn) {
      cancelBtn.addEventListener('click', function() {
        for (var k in initialState) state[k] = initialState[k];
        applyStateToDom(state);
        dirty = false;
        hideBar();
      });
    }

    var submitBtn = document.getElementById('vote-submit');
    if (submitBtn) {
      submitBtn.addEventListener('click', function() {
        var formData = new FormData();
        formData.append('csrf_token', poll.csrfToken);
        if (poll.secret) formData.append('secret', poll.secret);
        if (poll.invite) formData.append('invite', poll.invite);
        for (var optId in state) {
          if (state[optId]) formData.append('votes[' + optId + ']', state[optId]);
        }
        var originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving…';
        fetch(poll.voteBatchUrl, { method: 'POST', body: formData })
          .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, body: j }; }); })
          .then(function(result) {
            if (result.ok && result.body.success) {
              showToast('Votes saved');
              initialState = {};
              for (var k in state) initialState[k] = state[k];
              dirty = false;
              hideBar();
              var resultsSection = document.getElementById('results-section');
              var resultsContent = document.getElementById('results-content');
              if (resultsContent && resultsSection && resultsSection.hasAttribute('open') && poll.resultsUrl) {
                fetch(poll.resultsUrl).then(function(r) { return r.text(); }).then(function(html) {
                  resultsContent.innerHTML = html;
                });
              }
            } else {
              showToast(result.body && result.body.error ? result.body.error : 'Could not save votes.');
            }
          })
          .catch(function() {
            showToast('Could not save votes.');
          })
          .then(function() {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
          });
      });
    }
  })();

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
