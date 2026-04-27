<?php
// admin/index.php — Dashboard
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Dashboard';
include __DIR__ . '/_layout.php';

$d = db();

// Stats
$totalTracks      = (int)$d->query('SELECT COUNT(*) FROM tracks WHERE is_published=1')->fetchColumn();
$totalVideos      = (int)$d->query('SELECT COUNT(*) FROM videos WHERE is_published=1')->fetchColumn();
$totalPosts       = (int)$d->query('SELECT COUNT(*) FROM posts WHERE status="published"')->fetchColumn();
$totalSubs        = (int)$d->query('SELECT COUNT(*) FROM subscribers WHERE is_active=1')->fetchColumn();
$totalPlays       = (int)$d->query('SELECT SUM(plays) FROM tracks')->fetchColumn();
$totalPostViews   = (int)$d->query('SELECT SUM(views) FROM posts')->fetchColumn();
$totalVideoViews  = (int)$d->query('SELECT SUM(views) FROM videos')->fetchColumn();

// Recent subscribers
$recentSubs = $d->query('SELECT * FROM subscribers ORDER BY subscribed_at DESC LIMIT 6')->fetchAll();

// Recent posts
$recentPosts = $d->query('SELECT id,title,status,views,published_at FROM posts ORDER BY created_at DESC LIMIT 5')->fetchAll();

// Top tracks
$topTracks = $d->query('SELECT id,title,type,plays,likes FROM tracks ORDER BY plays DESC LIMIT 5')->fetchAll();

// Recent log
$recentLogs = $d->query('SELECT l.*,a.username FROM admin_logs l LEFT JOIN admin_users a ON a.id=l.admin_id ORDER BY l.logged_at DESC LIMIT 8')->fetchAll();

// Views last 7 days
$views7 = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $cnt  = (int)$d->prepare('SELECT COUNT(*) FROM analytics_pageviews WHERE DATE(viewed_at)=?')
                    ->execute([$date]) ? $d->prepare('SELECT COUNT(*) FROM analytics_pageviews WHERE DATE(viewed_at)=?')
                    ->execute([$date]) : 0;
    // simple approach:
    $s = $d->prepare('SELECT COUNT(*) FROM analytics_pageviews WHERE DATE(viewed_at)=?');
    $s->execute([$date]);
    $views7[$date] = (int)$s->fetchColumn();
}
$chartMax = max(array_values($views7) ?: [1]) ?: 1;
?>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon"></div><div class="stat-val"><?= $totalTracks ?></div><div class="stat-label">Published Tracks</div></div>
  <div class="stat-card"><div class="stat-icon"></div><div class="stat-val"><?= $totalVideos ?></div><div class="stat-label">Videos</div></div>
  <div class="stat-card"><div class="stat-icon"></div><div class="stat-val"><?= $totalPosts ?></div><div class="stat-label">Blog Posts</div></div>
  <div class="stat-card"><div class="stat-icon"></div><div class="stat-val"><?= number_format($totalSubs) ?></div><div class="stat-label">Subscribers</div></div>
  <div class="stat-card"><div class="stat-icon"></div><div class="stat-val"><?= numFmt($totalPlays) ?></div><div class="stat-label">Total Plays</div></div>
  <div class="stat-card"><div class="stat-icon"></div><div class="stat-val"><?= numFmt($totalPostViews + $totalVideoViews) ?></div><div class="stat-label">Total Views</div></div>
</div>

<div class="flex gap-3 mb-6" style="flex-wrap:wrap;align-items:flex-start">

  <!-- Views Chart -->
  <div class="card" style="flex:2;min-width:300px">
    <div class="card-head"><h3>Page Views — Last 7 Days</h3></div>
    <div class="card-body">
      <div style="display:flex;align-items:flex-end;gap:8px;height:140px">
        <?php foreach ($views7 as $date => $cnt): ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%">
          <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;width:100%">
            <div style="background:var(--accent);border-radius:3px 3px 0 0;height:<?= max(4,round($cnt/$chartMax*110)) ?>px;transition:.4s;opacity:.85;min-height:4px"></div>
          </div>
          <span style="font-size:.55rem;color:var(--muted)"><?= date('D', strtotime($date)) ?></span>
          <span style="font-size:.6rem;font-weight:700"><?= $cnt ?: '0' ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Top Tracks -->
  <div class="card" style="flex:1;min-width:260px">
    <div class="card-head"><h3>Top Tracks</h3><a href="/admin/tracks" class="btn btn-sm btn-secondary">All →</a></div>
    <div class="card-body" style="padding:0">
      <?php foreach ($topTracks as $i => $t): ?>
      <div class="flex items-center gap-2" style="padding:10px 16px;border-bottom:1px solid var(--border)">
        <span style="font-size:.7rem;color:var(--muted);width:14px;flex-shrink:0"><?= $i+1 ?></span>
        <div style="flex:1;min-width:0">
          <div class="truncate fw-bold text-sm"><?= h($t['title']) ?></div>
          <div class="text-xs text-muted"><?= numFmt($t['plays']) ?> plays</div>
        </div>
        <span class="badge badge-accent"><?= h($t['type']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  

</div>

<div class="flex gap-3 mb-6" style="flex-wrap:wrap;align-items:flex-start">

  <!-- Recent Posts -->
  <div class="card" style="flex:2;min-width:300px">
    <div class="card-head"><h3>Recent Posts</h3><a href="/admin/posts" class="btn btn-sm btn-secondary">All →</a><a href="posts.php?action=new" class="btn btn-sm btn-primary">+ New</a></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Title</th><th>Status</th><th>Views</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recentPosts as $p): ?>
        <tr>
          <td class="truncate fw-bold"><?= h($p['title']) ?></td>
          <td><span class="badge <?= $p['status']==='published'?'badge-green':($p['status']==='draft'?'badge-gray':'badge-yellow') ?>"><?= h($p['status']) ?></span></td>
          <td><?= number_format($p['views']) ?></td>
          <td class="text-xs text-muted"><?= $p['published_at'] ? date('M d',strtotime($p['published_at'])) : '—' ?></td>
          <td><a href="posts.php?edit=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">Edit</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Subscribers -->
  <div class="card" style="flex:1;min-width:240px">
    <div class="card-head"><h3>New Subscribers</h3><a href="/admin/subscribers" class="btn btn-sm btn-secondary">All →</a></div>
    <div class="card-body" style="padding:0">
      <?php foreach ($recentSubs as $s): ?>
      <div style="padding:9px 16px;border-bottom:1px solid var(--border)">
        <div class="text-sm fw-bold truncate"><?= h($s['email']) ?></div>
        <div class="text-xs text-muted"><?= date('M d, Y', strtotime($s['subscribed_at'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$recentSubs): ?><div class="card-body text-muted text-sm">No subscribers yet.</div><?php endif; ?>
    </div>
  </div>

</div>

<!-- Activity Log -->
<div class="card mb-6">
  <div class="card-head"><h3>Recent Activity</h3><a href="logs.php" class="btn btn-sm btn-secondary">Full Log →</a></div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Admin</th><th>Action</th><th>Details</th><th>IP</th><th>Time</th></tr></thead>
      <tbody>
      <?php foreach ($recentLogs as $l): ?>
      <tr>
        <td class="fw-bold"><?= h($l['username'] ?? 'System') ?></td>
        <td><?= h($l['action']) ?></td>
        <td class="text-muted text-xs truncate"><?= h($l['details'] ?? '') ?></td>
        <td class="text-xs text-muted"><?= h($l['ip'] ?? '') ?></td>
        <td class="text-xs text-muted"><?= date('M d H:i', strtotime($l['logged_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recentLogs): ?><tr><td colspan="5" class="text-muted text-sm" style="text-align:center;padding:20px">No activity yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Quick Actions -->
<div class="card mb-6">
  <div class="card-head"><h3>⚡ Quick Actions</h3></div>
  <div class="card-body flex gap-2 flex-wrap">
    <a href="tracks.php?action=new"       class="btn btn-md btn-primary">+ Add Track</a>
    <a href="videos.php?action=new"       class="btn btn-md btn-primary">+ Add Video</a>
    <a href="posts.php?action=new"        class="btn btn-md btn-primary">+ New Post</a>
    <a href="/admin/appearance"              class="btn btn-md btn-secondary">Customize</a>
    <a href="/admin/monetization"            class="btn btn-md btn-secondary">Ads</a>
    <a href="/admin/analytics"              class="btn btn-md btn-secondary">Analytics</a>
  </div>
</div>

<?php include __DIR__ . '/_layout_end.php'; ?>