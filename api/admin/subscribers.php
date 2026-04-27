<?php
// admin/subscribers.php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Subscribers';
include __DIR__ . '/_layout.php';

$d = db(); $msg = '';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $d->prepare('DELETE FROM subscribers WHERE id=?')->execute([$id]);
    header('Location: /admin/subscribers?msg=Subscriber+removed');
    exit;
}
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $d->prepare('UPDATE subscribers SET is_active=1-is_active WHERE id=?')->execute([$id]);
    header('Location: /admin/subscribers');
    exit;
}
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="subscribers_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out,['ID','Email','Name','Source','Active','Date']);
    foreach ($d->query('SELECT * FROM subscribers ORDER BY subscribed_at DESC')->fetchAll() as $s) {
        fputcsv($out,[$s['id'],$s['email'],$s['name']??'',$s['source']??'',$s['is_active']?'yes':'no',$s['subscribed_at']]);
    }
    fclose($out);
    exit;
}

if (!empty($_GET['msg'])) $msg = h($_GET['msg']);

$page  = max(1,(int)($_GET['p']??1));
$per   = 25;
$total = (int)$d->query('SELECT COUNT(*) FROM subscribers')->fetchColumn();
$subs  = $d->prepare('SELECT * FROM subscribers ORDER BY subscribed_at DESC LIMIT ? OFFSET ?');
$subs->execute([$per, ($page-1)*$per]);
$subs  = $subs->fetchAll();
$pages = ceil($total/$per);

$active = (int)$d->query('SELECT COUNT(*) FROM subscribers WHERE is_active=1')->fetchColumn();
?>

<?php if ($msg): ?><div class="alert alert-success">✓ <?= $msg ?></div><?php endif; ?>

<div class="stats-grid mb-6">
  <div class="stat-card"><div class="stat-icon">💌</div><div class="stat-val"><?= number_format($total) ?></div><div class="stat-label">Total</div></div>
  <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-val"><?= number_format($active) ?></div><div class="stat-label">Active</div></div>
  <div class="stat-card"><div class="stat-icon">❌</div><div class="stat-val"><?= number_format($total-$active) ?></div><div class="stat-label">Unsubscribed</div></div>
</div>

<div class="card">
  <div class="card-head">
    <h3>Subscribers (<?= number_format($total) ?>)</h3>
    <a href="subscribers.php?export=1" class="btn btn-sm btn-secondary">⬇ Export CSV</a>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>#</th><th>Email</th><th>Source</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($subs as $s): ?>
      <tr>
        <td class="text-muted text-xs"><?= $s['id'] ?></td>
        <td class="fw-bold"><?= h($s['email']) ?></td>
        <td class="text-muted text-xs"><?= h($s['source']??'—') ?></td>
        <td><a href="subscribers.php?toggle=<?= $s['id'] ?>" class="badge <?= $s['is_active']?'badge-green':'badge-gray' ?>"><?= $s['is_active']?'Active':'Off' ?></a></td>
        <td class="text-xs text-muted"><?= date('M d, Y',strtotime($s['subscribed_at'])) ?></td>
        <td><a href="subscribers.php?delete=<?= $s['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Remove subscriber?">🗑️</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$subs): ?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted)">No subscribers yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="card-body">
    <div class="pagination">
      <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="subscribers.php?p=<?= $i ?>" class="page-link <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/_layout_end.php'; ?>