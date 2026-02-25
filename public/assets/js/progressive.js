/**
 * progressive.js
 * Purpose: Progressive enhancement (check availability, loading state, toasts).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
(function() {
  'use strict';

  function showToast(message, linkUrl, linkText) {
    var existing = document.querySelector('.toast');
    if (existing) existing.remove();
    var t = document.createElement('div');
    t.className = 'toast';
    t.setAttribute('role', 'status');
    t.appendChild(document.createTextNode(message));
    if (linkUrl && linkText) {
      var a = document.createElement('a');
      a.href = linkUrl;
      a.textContent = linkText;
      a.style.cssText = 'color:inherit;text-decoration:underline;white-space:nowrap;';
      t.appendChild(document.createTextNode(' '));
      t.appendChild(a);
    }
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 4000);
  }

  var btn = document.getElementById('check-availability');
  if (!btn || !window.HILLMEET_POLL) return;

  var checkUrl = window.HILLMEET_POLL.checkAvailabilityUrl;
  var csrfToken = window.HILLMEET_POLL.csrfToken;
  if (!checkUrl || !csrfToken) return;

  btn.addEventListener('click', function() {
    var label = btn.textContent;
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    btn.textContent = 'Checking…';

    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    fetch(checkUrl, { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } })
      .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data || {} }; }); })
      .then(function(result) {
        var data = result.data;
        var isError = !result.ok || data.ok === false || (data.error_code || data.error);
        if (isError) {
          var msg = data.error_message || data.message || 'Could not check availability.';
          var hint = data.action_hint;
          var fullMsg = hint ? (msg + ' ' + hint) : msg;
          var calendarLink = document.querySelector('a[href*="/calendar"]');
          var showReconnect = (data.error_code === 'not_connected' || data.error_code === 'token_refresh_failed' || data.error_code === 'insufficient_permissions' || data.error_code === 'api_error') && calendarLink;
          if (showReconnect) {
            showToast(msg + ' ', calendarLink.getAttribute('href'), 'Reconnect calendar');
          } else {
            showToast(fullMsg);
          }
          return;
        }
        // Success: backend wrote freebusy to cache; reload to show badges and auto-accept button
        window.location.href = window.location.pathname + (window.location.search || '');
      })
      .catch(function() {
        showToast('Could not check availability. Try again.');
      })
      .then(function() {
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        btn.textContent = label;
      });
  });

  var autoAcceptBtn = document.getElementById('auto-accept-availability');
  var poll = window.HILLMEET_POLL;
  if (autoAcceptBtn && poll && poll.voteBatchUrl && checkUrl && csrfToken) {
    autoAcceptBtn.addEventListener('click', function() {
      var lastBusy = poll.lastBusy;
      if (!lastBusy || typeof lastBusy !== 'object' || Object.keys(lastBusy).length === 0) {
        showToast('Check availability first.');
        return;
      }
      var list = document.getElementById('poll-options-list');
      if (!list) return;
      var votes = {};
      list.querySelectorAll('.option-card').forEach(function(card) {
        var optionId = String(card.getAttribute('data-option-id') || '');
        if (!optionId) return;
        if (optionId in lastBusy) {
          votes[optionId] = lastBusy[optionId] ? 'no' : 'yes';
        } else {
          var active = card.querySelector('.vote-chip.active');
          votes[optionId] = active ? (active.getAttribute('data-vote') || active.value || '') : '';
        }
      });
      var formData = new FormData();
      formData.append('csrf_token', csrfToken);
      if (poll.secret) formData.append('secret', poll.secret);
      if (poll.invite) formData.append('invite', poll.invite);
      for (var oid in votes) {
        if (Object.prototype.hasOwnProperty.call(votes, oid)) {
          formData.append('votes[' + oid + ']', votes[oid] || '');
        }
      }
      var label = autoAcceptBtn.textContent;
      autoAcceptBtn.disabled = true;
      autoAcceptBtn.textContent = 'Applying…';
      fetch(poll.voteBatchUrl, { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data || {} }; }); })
        .then(function(result) {
          var data = result.data;
          if (result.ok && data.success) {
            showToast('Votes saved. Showing results.');
            var base = window.location.pathname + window.location.search;
            var sep = window.location.search ? '&' : '?';
            window.location.href = base + sep + 'expand=results';
          } else {
            autoAcceptBtn.disabled = false;
            autoAcceptBtn.textContent = label;
            showToast(data.error || 'Could not save votes.');
          }
        })
        .catch(function() {
          autoAcceptBtn.disabled = false;
          autoAcceptBtn.textContent = label;
          showToast('Could not save votes.');
        });
    });
  }
})();
