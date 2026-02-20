/**
 * Progressive enhancement: Check availability (calendar) with loading state and visible feedback
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
  if (!checkUrl) return;

  btn.addEventListener('click', function() {
    var label = btn.textContent;
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    btn.textContent = 'Checking…';

    fetch(checkUrl, { headers: { 'Accept': 'application/json' } })
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
        var busy = data.busy || {};
        var list = document.getElementById('poll-options-list');
        if (list) {
          list.querySelectorAll('.option-card').forEach(function(card) {
            var optionId = card.getAttribute('data-option-id');
            var badge = card.querySelector('.freebusy-badge');
            if (!badge) return;
            if (optionId !== null && optionId !== undefined && optionId in busy) {
              badge.textContent = 'Your calendar: ' + (busy[optionId] ? 'Busy ⛔' : 'Free ✅');
            }
          });
        }
        showToast('Availability updated.');
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
})();
