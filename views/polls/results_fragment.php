<?php
$voteLabels = ['yes' => 'Works', 'maybe' => 'If needed', 'no' => "Can't"];
?>
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
