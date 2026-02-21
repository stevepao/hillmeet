<?php
$pageTitle = 'Add time options';
$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/light.css"><script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>';
$content = ob_start();
?>
<h1>Add time options</h1>
<div class="steps">
  <a href="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/edit') ?>">Details</a>
  <span class="active">Add times</span>
  <span>Share</span>
</div>

<?php $pollDurationMinutes = (int) ($poll->duration_minutes ?? 60); ?>
<div class="card" style="margin-top:var(--space-5);">
  <p class="helper" style="margin-bottom:var(--space-3);">Event duration: <strong><?= $pollDurationMinutes ?> min</strong> (set when creating the poll). Each slot is a start time; end is start + duration.</p>
  <h2>Manual entry</h2>
  <form method="post" action="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/options') ?>" id="options-form" data-csrf="<?= \Hillmeet\Support\e(\Hillmeet\Support\Csrf::token()) ?>" data-option-delete-url="<?= \Hillmeet\Support\e(\Hillmeet\Support\url('/poll/' . $poll->slug . '/option-delete')) ?>">
    <?= \Hillmeet\Support\Csrf::field() ?>
    <div id="options-container">
      <?php
      $pollTz = new DateTimeZone($poll->timezone);
      $utc = new DateTimeZone('UTC');
      foreach ($options as $i => $opt):
        $startLocal = (new DateTime($opt->start_utc, $utc))->setTimezone($pollTz)->format('Y-m-d\TH:i');
        $endLocal = (new DateTime($opt->end_utc, $utc))->setTimezone($pollTz)->format('Y-m-d\TH:i');
      ?>
        <div class="form-group option-row" data-option-id="<?= (int) $opt->id ?>">
          <div style="display:flex;align-items:flex-start;gap:var(--space-2);flex-wrap:wrap;">
            <div style="flex:1;min-width:0;">
              <label>Start (<?= \Hillmeet\Support\e($poll->timezone) ?>)</label>
              <input type="text" name="options[<?= $i ?>][start]" class="input option-start flatpickr-datetime" value="<?= \Hillmeet\Support\e($startLocal) ?>" placeholder="Date & time" autocomplete="off">
              <label>End (<?= \Hillmeet\Support\e($poll->timezone) ?>)</label>
              <input type="text" name="options[<?= $i ?>][end]" class="input option-end" value="<?= \Hillmeet\Support\e($endLocal) ?>" readonly tabindex="-1" aria-label="End time (auto from start + <?= $pollDurationMinutes ?> min)" autocomplete="off">
            </div>
            <button type="button" class="btn btn-secondary btn-sm option-delete-btn" data-option-id="<?= (int) $opt->id ?>" aria-label="Delete this time option" title="Delete">&#10005;</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" id="add-time">Add time</button>
    <hr style="margin:var(--space-5) 0;border-color:var(--border);">
  <h3>Bulk generate</h3>
  <p class="helper">Slots use event duration (<?= $pollDurationMinutes ?> min). We'll show times in each person's local timezone.</p>
  <div class="form-group">
    <label>Date range</label>
    <input type="text" id="gen-from" class="input flatpickr-date" style="width:auto;" placeholder="From" autocomplete="off">
    <input type="text" id="gen-to" class="input flatpickr-date" style="width:auto;" placeholder="To" autocomplete="off">
  </div>
  <div class="form-group">
    <label>Days of week</label>
    <div class="checkbox-group">
      <?php foreach (['Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6,'Sun'=>0] as $day => $dow): ?>
        <label class="checkbox-label"><input type="checkbox" class="gen-dow" value="<?= $dow ?>"> <?= $day ?></label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="form-group">
    <label>Start time</label>
    <input type="time" id="gen-start" class="input" value="09:00" style="width:auto;">
  </div>
  <button type="button" class="btn btn-secondary" id="generate-times">Generate time options</button>
    <hr style="margin:var(--space-5) 0;border-color:var(--border);">
    <button type="submit" class="btn btn-primary">Save & continue</button>
  </form>
</div>

<div id="confirm-delete-option-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="confirm-delete-option-title" hidden>
  <div class="card" style="max-width: 28rem;">
    <h2 id="confirm-delete-option-title">Delete time option?</h2>
    <p class="helper">Delete this time option? Votes for this option will be removed.</p>
    <div style="display: flex; gap: var(--space-2); justify-content: flex-end; margin-top: var(--space-4);">
      <button type="button" class="btn btn-secondary" id="confirm-delete-option-cancel">Cancel</button>
      <button type="button" class="btn btn-primary" id="confirm-delete-option-confirm" style="background: var(--danger); border-color: var(--danger);">Delete</button>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function() {
(function() {
  var container = document.getElementById('options-container');
  var pollTz = <?= json_encode($poll->timezone) ?>;
  var durationMinutes = <?= $pollDurationMinutes ?>;

  function showToast(msg) {
    var existing = document.querySelector('.toast');
    if (existing) existing.remove();
    var t = document.createElement('div');
    t.className = 'toast';
    t.setAttribute('role', 'status');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 3500);
  }

  function setEndFromStart(startInput) {
    var row = startInput.closest('.option-row');
    if (!row) return;
    var endInput = row.querySelector('.option-end');
    var startVal = startInput.value;
    if (!startVal) { endInput.value = ''; return; }
    var d = new Date(startVal);
    d.setMinutes(d.getMinutes() + durationMinutes);
    var y = d.getFullYear(), m = String(d.getMonth() + 1).padStart(2, '0'), day = String(d.getDate()).padStart(2, '0');
    var h = String(d.getHours()).padStart(2, '0'), min = String(d.getMinutes()).padStart(2, '0');
    endInput.value = y + '-' + m + '-' + day + 'T' + h + ':' + min;
  }

  var fpDateFormat = 'Y-m-d\\TH:i';
  var fpDateOnlyFormat = 'Y-m-d';

  function initFlatpickrStart(el) {
    if (typeof flatpickr === 'undefined') return;
    flatpickr(el, {
      enableTime: true,
      time_24hr: false,
      dateFormat: fpDateFormat,
      altInput: true,
      altFormat: 'M j, Y h:i K',
      minuteIncrement: 5,
      allowInput: true,
      onChange: function(sel, datestr) { setEndFromStart(el); }
    });
    el.addEventListener('input', function() { setEndFromStart(this); });
    el.addEventListener('change', function() { setEndFromStart(this); });
  }

  function initFlatpickrDate(el) {
    if (typeof flatpickr === 'undefined') return;
    flatpickr(el, { dateFormat: fpDateOnlyFormat, allowInput: true });
  }

  function addRow(startVal, endVal) {
    var i = container.querySelectorAll('.option-row').length;
    var div = document.createElement('div');
    div.className = 'form-group option-row';
    div.innerHTML = '<div style="display:flex;align-items:flex-start;gap:var(--space-2);flex-wrap:wrap;"><div style="flex:1;min-width:0;">' +
      '<label>Start (' + pollTz + ')</label><input type="text" name="options[' + i + '][start]" class="input option-start flatpickr-datetime" value="' + (startVal || '') + '" placeholder="Date & time" autocomplete="off">' +
      '<label>End (' + pollTz + ')</label><input type="text" name="options[' + i + '][end]" class="input option-end" value="' + (endVal || '') + '" readonly tabindex="-1" aria-label="End (start + ' + durationMinutes + ' min)" autocomplete="off">' +
      '</div><button type="button" class="btn btn-secondary btn-sm option-remove-row" aria-label="Remove this row" title="Remove">&#10005;</button></div>';
    container.appendChild(div);
    div.querySelector('.option-remove-row').addEventListener('click', function() { div.remove(); });
    var startInput = div.querySelector('.option-start');
    initFlatpickrStart(startInput);
    if (startVal && !endVal) setEndFromStart(startInput);
  }

  container.querySelectorAll('.option-start').forEach(function(startInput) {
    initFlatpickrStart(startInput);
  });
  document.querySelectorAll('.flatpickr-date').forEach(initFlatpickrDate);

  document.getElementById('add-time').addEventListener('click', function() { addRow(); });

  function formatInTz(d, tz) {
    var p = new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(d);
    var parts = {}; p.forEach(function(x) { parts[x.type] = x.value; });
    return parts.year + '-' + parts.month + '-' + parts.day + 'T' + parts.hour + ':' + parts.minute;
  }

  document.getElementById('generate-times').addEventListener('click', function() {
    var from = document.getElementById('gen-from').value;
    var to = document.getElementById('gen-to').value;
    var startTime = document.getElementById('gen-start').value;
    var days = [].slice.call(document.querySelectorAll('.gen-dow:checked')).map(function(c) { return parseInt(c.value, 10); });

    if (!from || !to) {
      showToast('Please pick a date range (From and To).');
      return;
    }
    if (days.length === 0) {
      showToast('Please select at least one day of the week.');
      return;
    }

    var startHour = parseInt(startTime.slice(0, 2), 10);
    var startMin = parseInt(startTime.slice(3, 5), 10) || 0;
    var fromParts = from.split('-');
    var toParts = to.split('-');
    var rangeStart = new Date(parseInt(fromParts[0], 10), parseInt(fromParts[1], 10) - 1, parseInt(fromParts[2], 10), startHour, startMin, 0, 0);
    var rangeEnd = new Date(parseInt(toParts[0], 10), parseInt(toParts[1], 10) - 1, parseInt(toParts[2], 10), startHour, startMin, 0, 0);

    if (rangeStart > rangeEnd) {
      showToast('From date must be on or before To date.');
      return;
    }

    /* DEBUG (temporary): set HILLMEET_DEBUG_GEN=true in console to enable; remove when verified */
    if (window.HILLMEET_DEBUG_GEN === undefined) window.HILLMEET_DEBUG_GEN = true;
    function dbg() { if (window.HILLMEET_DEBUG_GEN) console.log.apply(console, arguments); }
    dbg('[gen] 1) Parsed range', 'start', rangeStart.toISOString(), 'valid', !isNaN(rangeStart.getTime()), 'end', rangeEnd.toISOString(), 'valid', !isNaN(rangeEnd.getTime()));
    dbg('[gen] 2) Selected weekdays from UI (0=Sun..6=Sat)', days);

    var current = new Date(rangeStart.getTime());
    var added = 0;
    var candidateSlots = [];
    var loggedOne = false;

    while (current <= rangeEnd) {
      var dow = current.getDay();
      dbg('[gen] 3) Iterated date', current.toISOString().slice(0, 10), 'weekday', dow, '(0=Sun,1=Mon,...,6=Sat)');

      if (days.indexOf(dow) !== -1) {
        var s = new Date(current.getTime());
        var e = new Date(current.getTime() + durationMinutes * 60000);
        candidateSlots.push({ start: s.toISOString(), end: e.toISOString() });
        if (!loggedOne) {
          dbg('[gen] 4) One matching day: startDateTime', s.toISOString(), 'endDateTime', e.toISOString());
          loggedOne = true;
        }
        addRow(formatInTz(s, pollTz), formatInTz(e, pollTz));
        added++;
      }
      current.setDate(current.getDate() + 1);
      current.setHours(startHour, startMin, 0, 0);
    }

    dbg('[gen] 5) Candidate slots', candidateSlots);

    if (added > 0) showToast('Added ' + added + ' time option' + (added === 1 ? '' : 's') + '.');
    else showToast('No slots in that range. Try a wider date range or different days.');
  });

  var optionModal = document.getElementById('confirm-delete-option-modal');
  var optionDeleteUrl = document.getElementById('options-form') && document.getElementById('options-form').getAttribute('data-option-delete-url');
  var optionCsrf = document.getElementById('options-form') && document.getElementById('options-form').getAttribute('data-csrf');
  var optionIdToDelete = null;
  var optionRowToRemove = null;
  if (optionModal && optionDeleteUrl && optionCsrf) {
    function showOptionModal() { optionModal.hidden = false; optionModal.style.display = 'flex'; }
    function hideOptionModal() { optionModal.hidden = true; optionModal.style.display = 'none'; optionIdToDelete = null; optionRowToRemove = null; }
    container.querySelectorAll('.option-delete-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var id = btn.getAttribute('data-option-id');
        var row = btn.closest('.option-row');
        if (!id || !row) return;
        optionIdToDelete = id;
        optionRowToRemove = row;
        showOptionModal();
      });
    });
    document.getElementById('confirm-delete-option-cancel').addEventListener('click', hideOptionModal);
    document.getElementById('confirm-delete-option-confirm').addEventListener('click', function() {
      if (!optionIdToDelete || !optionRowToRemove) { hideOptionModal(); return; }
      var formData = new FormData();
      formData.append('csrf_token', optionCsrf);
      formData.append('option_id', optionIdToDelete);
      var confirmBtn = document.getElementById('confirm-delete-option-confirm');
      confirmBtn.disabled = true;
      fetch(optionDeleteUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(function(r) { return r.text().then(function(text) { return { ok: r.ok, status: r.status, text: text }; }); })
        .then(function(result) {
          confirmBtn.disabled = false;
          hideOptionModal();
          var body = null;
          try { body = result.text ? JSON.parse(result.text) : null; } catch (e) { body = null; }
          if (result.ok && result.status === 200 && body && body.success) {
            optionRowToRemove.remove();
            showToast('Time option removed.');
            return;
          }
          if (result.ok && result.status === 200 && !body) {
            optionRowToRemove.remove();
            window.location.reload();
            return;
          }
          if (body && body.error) showToast(body.error);
          else showToast('Could not delete option.');
          window.location.reload();
        })
        .catch(function() {
          confirmBtn.disabled = false;
          hideOptionModal();
          window.location.reload();
        });
    });
    optionModal.addEventListener('click', function(e) { if (e.target === optionModal) hideOptionModal(); });
  }
})();
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
