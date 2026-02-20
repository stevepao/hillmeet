<?php
$voteLabels = ['yes' => 'Works', 'maybe' => 'If needed', 'no' => "Can't"];
$resultsDebug = $resultsDebug ?? null;
?>
<?php if (!empty($resultsDebug)): ?>
<div class="results-debug muted" style="font-size:var(--text-xs); margin-bottom:var(--space-4); padding:var(--space-3); background:var(--card-2); border-radius:var(--radius-md); border:1px solid var(--border);">
  <strong>Results debug</strong>
  <p style="margin:var(--space-1) 0 0;">poll_participants: <?= (int) ($resultsDebug['participants_count'] ?? 0) ?> · distinct voters: <?= (int) ($resultsDebug['voters_count'] ?? 0) ?></p>
  <?php if (!empty($resultsDebug['mismatch'])): ?>
    <p style="margin:var(--space-1) 0 0;">Mismatch (user_ids with votes but not in poll_participants): <?= implode(', ', array_map('intval', $resultsDebug['mismatch'])) ?></p>
  <?php endif; ?>
  <p style="margin:var(--space-2) 0 0;"><strong>Participants (id, email):</strong></p>
  <ul style="margin:0; padding-left:1.25rem;">
    <?php foreach ($resultsDebug['participants'] ?? [] as $p): ?>
      <li><?= (int) $p->id ?> — <?= \Hillmeet\Support\e($p->email) ?></li>
    <?php endforeach; ?>
  </ul>
  <p style="margin:var(--space-2) 0 0;"><strong>Voters (id, email):</strong></p>
  <ul style="margin:0; padding-left:1.25rem;">
    <?php foreach ($resultsDebug['voters'] ?? [] as $v): ?>
      <li><?= (int) $v->id ?> — <?= \Hillmeet\Support\e($v->email) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
<div class="your-saved-votes" style="margin-bottom:var(--space-4);">
  <h4 style="font-size:var(--text-base); margin:0 0 var(--space-2);">Your saved votes</h4>
  <ul class="your-votes-list" style="list-style:none; padding:0; margin:0; font-size:var(--text-sm);">
    <?php foreach ($options as $opt):
      $startLocal = (new DateTime($opt->start_utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($poll->timezone))->format('D M j, g:i A');
      $v = $myVotes[$opt->id] ?? null;
      $label = $v ? ($voteLabels[$v] ?? $v) : '—';
    ?>
      <li style="padding:var(--space-1) 0; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; gap:var(--space-3);">
        <span><?= \Hillmeet\Support\e($startLocal) ?></span>
        <span><?= \Hillmeet\Support\e($label) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<div class="results-table-wrap">
  <table class="results-table">
    <thead>
      <tr>
        <th>Time</th>
        <?php foreach ($participants as $p): ?>
          <th><?= \Hillmeet\Support\e($p->name ?: $p->email) ?></th>
        <?php endforeach; ?>
        <th>Score</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($options as $opt):
        $startLocal = (new DateTime($opt->start_utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($poll->timezone))->format('D M j, g:i A');
        $totals = $results['totals'][$opt->id] ?? ['yes' => 0, 'maybe' => 0, 'no' => 0];
        $score = $totals['yes'] * 2 + $totals['maybe'];
        $isBest = ($results['best_option_id'] ?? null) === $opt->id;
        $matrix = $results['matrix'][$opt->id] ?? [];
      ?>
        <tr class="<?= $isBest ? 'best-slot' : '' ?>">
          <td><?= \Hillmeet\Support\e($startLocal) ?></td>
          <?php foreach ($participants as $p): ?>
            <td class="vote-cell"><?= isset($matrix[$p->id]) ? \Hillmeet\Support\e($voteLabels[$matrix[$p->id]] ?? $matrix[$p->id]) : '—' ?></td>
          <?php endforeach; ?>
          <td><?= $score ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
