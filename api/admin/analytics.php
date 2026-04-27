<?php
// admin/analytics.php — Analytics Dashboard
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Analytics';
include __DIR__ . '/_layout.php';

$d = db();

// Date range
$days = (int)($_GET['days'] ?? 30);
$days = in_array($days,[7,14,30,90]) ? $days : 30;
$since = date('Y-m-d', strtotime("-$days days"));

// Totals
$totalViews   = (int)$d->prepare('SELECT COUNT(*) FROM analytics_pageviews WHERE viewed_at>=?')->execute([$since]) ? 0 : 0;
$s = $d->prepare('SELECT COUNT(*) FROM analytics_pageviews WHERE viewed_at>=?'); $s->execute([$since]);
$totalViews = (int)$s->fetchColumn();

$s = $d->prepare('SELECT COUNT(DISTINCT ip_hash) FROM analytics_pageviews WHERE viewed_at>=?'); $s->execute([$since]);
$uniqueVisitors = (int)$s->fetchColumn();

$s = $d->prepare("SELECT COUNT(*) FROM analytics_events WHERE event_type='play' AND event_at>=?"); $s->execute([$since]);
$totalPlays = (int)$s->fetchColumn();

$s = $d->prepare("SELECT COUNT(*) FROM subscribers WHERE subscribed_at>=?"); $s->execute([$since]);
$newSubs = (int)$s->fetchColumn();

// Daily views chart (last N days)
$chartData = [];
for ($i = $days-1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $s = $d->prepare('SELECT COUNT(*) FROM analytics_pageviews WHERE DATE(viewed_at)=?');
    $s->execute([$date]);
    $chartData[$date] = (int)$s->fetchColumn();
}
$chartMax = max(array_values($chartData) ?: [1]) ?: 1;

// Top pages
$s = $d->prepare('SELECT page, COUNT(*) as cnt FROM analytics_pageviews WHERE viewed_at>=? GROUP BY page ORDER BY cnt DESC LIMIT 10');
$s->execute([$since]);
$topPages = $s->fetchAll();

// Top tracks by plays
$topTracks = $d->query('SELECT id,title,plays,likes FROM tracks ORDER BY plays DESC LIMIT 8')->fetchAll();

// Top posts by views
$topPosts = $d->query('SELECT id,title,views FROM posts ORDER BY views DESC LIMIT 8')->fetchAll();

// Events breakdown
$s = $d->prepare('SELECT event_type, COUNT(*) as cnt FROM analytics_events WHERE event_at>=? GROUP BY event_type ORDER BY cnt DESC');
$s->execute([$since]);
$events = $s->fetchAll();

// Subscribers by day
$subChart = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $s = $d->prepare('SELECT COUNT(*) FROM subscribers WHERE DATE(subscribed_at)=?');
    $s->execute([$date]);
    $subChart[$date] = (int)$s->fetchColumn();
}
$subMax = max(array_values($subChart) ?: [1]) ?: 1;
?>

<!-- Date range filter -->
<div class="flex gap-2 mb-6 flex-wrap items-center">
  <span class="text-muted text-sm">Range:</span>
  <?php foreach ([7=>'7 Days',14=>'14 Days',30=>'30 Days',90=>'90 Days'] as $d_ => $l): ?>
  <a href="analytics.php?days=<?= $d_ ?>" class="btn btn-sm <?= $days===$d_?'btn-primary':'btn-secondary' ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<!-- Stats -->
<div class="stats-grid mb-6">
  <div class="stat-card"><div class="stat-icon">👁️</div><div class="stat-val"><?= number_format($totalViews) ?></div><div class="stat-label">Page Views</div></div>
  <div class="stat-card"><div class="stat-icon">👤</div><div class="stat-val"><?= number_format($uniqueVisitors) ?></div><div class="stat-label">Unique Visitors</div></div>
  <div class="stat-card"><div class="stat-icon">▶️</div><div class="stat-val"><?= number_format($totalPlays) ?></div><div class="stat-label">Track Plays</div></div>
  <div class="stat-card"><div class="stat-icon">💌</div><div class="stat-val"><?= number_format($newSubs) ?></div><div class="stat-label">New Subscribers</div></div>
</div>

<!-- Charts row -->
<div class="flex gap-3 mb-6" style="flex-wrap:wrap">

  <!-- Page views chart -->
  <div class="card" style="flex:2;min-width:300px">
    <div class="card-head"><h3>📈 Daily Page Views (last <?= $days ?> days)</h3></div>
    <div class="card-body">
      <div style="display:flex;align-items:flex-end;gap:2px;height:160px;overflow-x:auto">
        <?php foreach ($chartData as $date => $cnt): ?>
        <div style="flex:1;min-width:6px;display:flex;flex-direction:column;align-items:center;height:100%;gap:2px">
          <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;width:100%">
            <div title="<?= $date ?>: <?= $cnt ?> views" style="background:var(--accent);border-radius:2px 2px 0 0;height:<?= max(2,round($cnt/$chartMax*130)) ?>px;opacity:.8;transition:.3s;min-height:2px"></div>
          </div>
          <?php if ($days <= 14): ?>
          <span style="font-size:.5rem;color:var(--muted);writing-mode:vertical-rl;transform:rotate(180deg)"><?= date('M d',strtotime($date)) ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Subscribers 7 day -->
  <div class="card" style="flex:1;min-width:220px">
    <div class="card-head"><h3>💌 New Subscribers (7 days)</h3></div>
    <div class="card-body">
      <div style="display:flex;align-items:flex-end;gap:6px;height:120px">
        <?php foreach ($subChart as $date => $cnt): ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;height:100%">
          <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;width:100%">
            <div style="background:#6c5ce7;border-radius:2px 2px 0 0;height:<?= max(2,round($cnt/$subMax*90)) ?>px;min-height:2px"></div>
          </div>
          <span style="font-size:.55rem;color:var(--muted)"><?= date('D',strtotime($date)) ?></span>
          <span style="font-size:.6rem;font-weight:700"><?= $cnt ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<div class="flex gap-3 mb-6" style="flex-wrap:wrap">

  <!-- Top Tracks -->
  <div class="card" style="flex:1;min-width:260px">
    <div class="card-head"><h3>🔥 Top Tracks (All Time)</h3></div>
    <div class="card-body" style="padding:0">
      <?php foreach ($topTracks as $i => $t): ?>
      <?php $pct = $topTracks[0]['plays'] ? round($t['plays']/$topTracks[0]['plays']*100) : 0; ?>
      <div style="padding:10px 16px;border-bottom:1px solid var(--border)">
        <div class="flex items-center gap-2 mb-4">
          <span style="font-size:.7rem;color:var(--muted);width:16px"><?= $i+1 ?></span>
          <div style="flex:1"><div class="fw-bold text-sm"><?= h($t['title']) ?></div></div>
          <span class="text-xs text-muted"><?= numFmt($t['plays']) ?> plays</span>
        </div>
        <div style="height:3px;background:var(--border);border-radius:2px"><div style="height:100%;width:<?= $pct ?>%;background:var(--accent);border-radius:2px"></div></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$topTracks): ?><div style="padding:20px;text-align:center;color:var(--muted)">No data yet.</div><?php endif; ?>
    </div>
  </div>

  <!-- Top Posts -->
  <div class="card" style="flex:1;min-width:260px">
    <div class="card-head"><h3>📖 Top Blog Posts</h3></div>
    <div class="card-body" style="padding:0">
      <?php foreach ($topPosts as $i => $p): ?>
      <?php $pct = $topPosts[0]['views'] ? round($p['views']/$topPosts[0]['views']*100) : 0; ?>
      <div style="padding:10px 16px;border-bottom:1px solid var(--border)">
        <div class="flex items-center gap-2 mb-4">
          <span style="font-size:.7rem;color:var(--muted);width:16px"><?= $i+1 ?></span>
          <div style="flex:1"><div class="fw-bold text-sm truncate"><?= h($p['title']) ?></div></div>
          <span class="text-xs text-muted"><?= numFmt($p['views']) ?> views</span>
        </div>
        <div style="height:3px;background:var(--border);border-radius:2px"><div style="height:100%;width:<?= $pct ?>%;background:#6c5ce7;border-radius:2px"></div></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$topPosts): ?><div style="padding:20px;text-align:center;color:var(--muted)">No data yet.</div><?php endif; ?>
    </div>
  </div>

  <!-- Events breakdown -->
  <div class="card" style="flex:1;min-width:200px">
    <div class="card-head"><h3>⚡ Event Breakdown</h3></div>
    <div class="card-body" style="padding:0">
      <?php if ($events): ?>
        <?php $maxEv = max(array_column($events,'cnt')) ?: 1; ?>
        <?php foreach ($events as $ev): ?>
        <div style="padding:10px 16px;border-bottom:1px solid var(--border)">
          <div class="flex items-center gap-2 mb-4">
            <div style="flex:1;font-size:.78rem;font-weight:600"><?= h($ev['event_type']) ?></div>
            <span class="text-xs fw-bold"><?= number_format($ev['cnt']) ?></span>
          </div>
          <div style="height:3px;background:var(--border);border-radius:2px"><div style="height:100%;width:<?= round($ev['cnt']/$maxEv*100) ?>%;background:#00b894;border-radius:2px"></div></div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="padding:20px;text-align:center;color:var(--muted)">No events tracked yet.</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Top Pages -->
<div class="card mb-6">
  <div class="card-head"><h3>🌐 Top Pages</h3></div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>#</th><th>Page</th><th>Views</th><th>Share</th></tr></thead>
      <tbody>
      <?php
      $maxPg = $topPages ? max(array_column($topPages,'cnt')) : 1;
      foreach ($topPages as $i => $pg):
        $pct = round($pg['cnt']/$maxPg*100);
      ?>
      <tr>
        <td class="text-muted text-xs"><?= $i+1 ?></td>
        <td class="fw-bold"><?= h($pg['page']) ?></td>
        <td><?= number_format($pg['cnt']) ?></td>
        <td style="width:200px">
          <div style="height:5px;background:var(--border);border-radius:3px">
            <div style="height:100%;width:<?= $pct ?>%;background:var(--accent);border-radius:3px"></div>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$topPages): ?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--muted)">No page view data yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/_layout_end.php'; ?>