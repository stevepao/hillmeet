/**
 * Progressive enhancement: Check availability (calendar) with loading state and non-JS fallback
 */
(function() {
  'use strict';

  var btn = document.getElementById('check-availability');
  if (!btn || !window.HILLMEET_POLL) return;

  btn.addEventListener('click', function() {
    var slug = window.HILLMEET_POLL.slug;
    var secret = window.HILLMEET_POLL.secret;
    var url = '/poll/' + encodeURIComponent(slug) + '/check-availability?secret=' + encodeURIComponent(secret);
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    var label = btn.textContent;
    btn.textContent = 'Checking…';
    var spinner = document.createElement('span');
    spinner.className = 'spinner';
    spinner.setAttribute('aria-hidden', 'true');
    btn.insertBefore(spinner, btn.firstChild);

    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.busy) {
          Object.keys(data.busy).forEach(function(optionId) {
            var card = document.querySelector('.option-card[data-option-id="' + optionId + '"]');
            if (card && data.busy[optionId]) {
              card.classList.add('freebusy-busy');
              var badge = document.createElement('span');
              badge.className = 'badge badge-warn';
              badge.textContent = 'Busy';
              card.appendChild(badge);
            }
          });
        }
      })
      .catch(function() {})
      .then(function() {
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        btn.textContent = label;
        var s = btn.querySelector('.spinner');
        if (s) s.remove();
      });
  });
})();
