<?php
$pageTitle = 'Add time options';
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
  <form method="post" action="<?= \Hillmeet\Support\url('/poll/' . $poll->slug . '/options') ?>" id="options-form">
    <?= \Hillmeet\Support\Csrf::field() ?>
    <div id="options-container">
      <?php
      $pollTz = new DateTimeZone($poll->timezone);
      $utc = new DateTimeZone('UTC');
      foreach ($options as $i => $opt):
        $startLocal = (new DateTime($opt->start_utc, $utc))->setTimezone($pollTz)->format('Y-m-d\TH:i');
        $endLocal = (new DateTime($opt->end_utc, $utc))->setTimezone($pollTz)->format('Y-m-d\TH:i');
      ?>
        <div class="form-group option-row">
          <label>Start (<?= \Hillmeet\Support\e($poll->timezone) ?>)</label>
          <input type="datetime-local" name="options[<?= $i ?>][start]" class="input option-start" value="<?= \Hillmeet\Support\e($startLocal) ?>">
          <label>End (<?= \Hillmeet\Support\e($poll->timezone) ?>)</label>
          <input type="datetime-local" name="options[<?= $i ?>][end]" class="input option-end" value="<?= \Hillmeet\Support\e($endLocal) ?>" readonly tabindex="-1" aria-label="End time (auto from start + <?= $pollDurationMinutes ?> min)">
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" id="add-time">Add time</button>
    <hr style="margin:var(--space-5) 0;border-color:var(--border);">
  <h3>Bulk generate</h3>
  <p class="helper">Slots use event duration (<?= $pollDurationMinutes ?> min). We'll show times in each person's local timezone.</p>
  <div class="form-group">
    <label>Date range</label>
    <input type="date" id="gen-from" class="input" style="width:auto;">
    <input type="date" id="gen-to" class="input" style="width:auto;">
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
    <label>End time</label>
    <input type="time" id="gen-end" class="input" value="17:00" style="width:auto;">
  </div>
  <div class="form-group">
    <label>Gap between slots (minutes)</label>
    <input type="number" id="gen-gap" class="input" value="0" min="0" style="width:6rem;">
  </div>
  <button type="button" class="btn btn-secondary" id="generate-times">Generate time options</button>
    <hr style="margin:var(--space-5) 0;border-color:var(--border);">
    <button type="submit" class="btn btn-primary">Save & continue</button>
  </form>
</div>

<script>
(function() {
  var container = document.getElementById('options-container');
  var pollTz = <?= json_encode($poll->timezone) ?>;
  var durationMinutes = <?= $pollDurationMinutes ?>;

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

  function addRow(startVal, endVal) {
    var i = container.querySelectorAll('.option-row').length;
    var div = document.createElement('div');
    div.className = 'form-group option-row';
    div.innerHTML = '<label>Start (' + pollTz + ')</label><input type="datetime-local" name="options[' + i + '][start]" class="input option-start" value="' + (startVal || '') + '">' +
      '<label>End (' + pollTz + ')</label><input type="datetime-local" name="options[' + i + '][end]" class="input option-end" value="' + (endVal || '') + '" readonly tabindex="-1" aria-label="End (start + ' + durationMinutes + ' min)">';
    container.appendChild(div);
    var startInput = div.querySelector('.option-start');
    startInput.addEventListener('input', function() { setEndFromStart(this); });
    startInput.addEventListener('change', function() { setEndFromStart(this); });
    if (startVal && !endVal) setEndFromStart(startInput);
  }

  container.querySelectorAll('.option-start').forEach(function(startInput) {
    startInput.addEventListener('input', function() { setEndFromStart(this); });
    startInput.addEventListener('change', function() { setEndFromStart(this); });
  });

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
    var endTime = document.getElementById('gen-end').value;
    var gap = parseInt(document.getElementById('gen-gap').value, 10) || 0;
    var days = [].slice.call(document.querySelectorAll('.gen-dow:checked')).map(function(c) { return parseInt(c.value, 10); });
    if (!from || !to || days.length === 0) return;
    var start = new Date(from + 'T' + startTime);
    var end = new Date(to + 'T' + endTime);
    var current = new Date(start);
    while (current <= end) {
      var dow = current.getDay();
      if (days.indexOf(dow) !== -1) {
        var s = new Date(current);
        var e = new Date(current.getTime() + durationMinutes * 60000);
        var dayEnd = new Date(current.toDateString() + 'T' + endTime);
        if (e <= dayEnd) {
          addRow(formatInTz(s, pollTz), formatInTz(e, pollTz));
        }
      }
      current.setDate(current.getDate() + 1);
      current.setHours(parseInt(startTime.slice(0,2), 10), parseInt(startTime.slice(3), 10), 0, 0);
    }
  });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
