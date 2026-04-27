<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// admin/videos.php — Manage Videos
require_once __DIR__ . '/../includes/config.php';

// ── ALL LOGIC BEFORE ANY OUTPUT ─────────────────────────────
$d      = db();
$msg    = '';
$err    = '';
$action = $_GET['action'] ?? '';
$editId = (int)($_GET['edit'] ?? 0);

// ── Handle form ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $youtube_id  = trim($_POST['youtube_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category'] ?? 'Video');
    $duration    = trim($_POST['duration'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 'true' : 'false';
    $is_published = isset($_POST['is_published']) ? 'true' : 'false';
    $sort_order  = (int)($_POST['sort_order'] ?? 0);

    // Strip full YouTube URL down to 11-char ID
    if (preg_match('/(?:v=|youtu\.be\/|embed\/)([a-zA-Z0-9_-]{11})/', $youtube_id, $m)) {
        $youtube_id = $m[1];
    }

    // Video file upload
    $video_file = trim($_POST['existing_video'] ?? '');
    if (!empty($_FILES['video_file']['name'])) {
        $up = uploadFile($_FILES['video_file'], 'videos', ['mp4','webm','mov']);
        if ($up) $video_file = $up;
        else     $err = 'Invalid video file. Allowed: mp4, webm, mov';
    }

    if (!$err && $title) {
        if ($id) {
            $d->prepare('UPDATE videos SET title=?,youtube_id=?,video_file=?,description=?,category=?,duration=?,is_featured=?,is_published=?,sort_order=? WHERE id=?')
              ->execute([$title,$youtube_id,$video_file,$description,$category,$duration,$is_featured,$is_published,$sort_order,$id]);
            logAction('Updated video', "ID:$id – $title");
            header('Location: /admin/videos?msg=' . urlencode("Video updated: $title"));
        } else {
            $d->prepare('INSERT INTO videos (title,youtube_id,video_file,description,category,duration,is_featured,is_published,sort_order) VALUES (?,?,?,?,?,?,?,?,?)')
              ->execute([$title,$youtube_id,$video_file,$description,$category,$duration,$is_featured,$is_published,$sort_order]);
            logAction('Added video', $title);
            header('Location: /admin/videos?msg=' . urlencode("Video added: $title"));
        }
        exit;
    }
    // Validation error — fall through to show form with $err
}

// ── Delete ───────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $s  = $d->prepare('SELECT title FROM videos WHERE id=?');
    $s->execute([$id]);
    $t  = $s->fetchColumn();
    $d->prepare('DELETE FROM videos WHERE id=?')->execute([$id]);
    logAction('Deleted video', "ID:$id – $t");
    header('Location: /admin/videos?msg=Video+deleted');
    exit;
}

// ── Toggle publish ────────────────────────────────────────────
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $d->prepare('UPDATE videos SET is_published=NOT is_published WHERE id=?')->execute([$id]);
    header('Location: /admin/videos');
    exit;
}

// ── Set featured ─────────────────────────────────────────────
if (isset($_GET['feature'])) {
    $id = (int)$_GET['feature'];
    $d->query('UPDATE videos SET is_featured=false');
    $d->prepare('UPDATE videos SET is_featured=true WHERE id=?')->execute([$id]);
    header('Location: /admin/videos?msg=Featured+video+updated');
    exit;
}

// ── Flash message ─────────────────────────────────────────────
if (!empty($_GET['msg'])) $msg = h($_GET['msg']);

// ── Load edit data ────────────────────────────────────────────
$editVideo = null;
if ($editId) {
    $s = $d->prepare('SELECT * FROM videos WHERE id=?');
    $s->execute([$editId]);
    $editVideo = $s->fetch();
    $action    = 'edit';
}

// ── List data ─────────────────────────────────────────────────
$videos = $d->query('SELECT * FROM videos ORDER BY sort_order, id DESC')->fetchAll();

// ── NOW safe to output HTML ───────────────────────────────────
$pageTitle = 'Videos';
include __DIR__ . '/_layout.php';
?>

<?php if ($msg): ?>
<div class="alert alert-success">✓ <?= $msg ?></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-error">⚠ <?= h($err) ?></div>
<?php endif; ?>

<?php if ($action === 'new' || $action === 'edit'): ?>
<div class="card mb-6">
  <div class="card-head">
    <h3><?= $editVideo ? 'Edit Video' : '+ Add New Video' ?></h3>
    <a href="/admin/videos" class="btn btn-sm btn-secondary">← Back</a>
  </div>
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <?php if ($editVideo): ?>
      <input type="hidden" name="id"             value="<?= $editVideo['id'] ?>">
      <input type="hidden" name="existing_video" value="<?= h($editVideo['video_file'] ?? '') ?>">
      <?php endif; ?>

      <div class="form-grid">
        <div class="field full">
          <label>Video Title *</label>
          <input type="text" name="title" value="<?= h($editVideo['title'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>YouTube Video ID or URL</label>
          <input type="text" name="youtube_id"
                 value="<?= h($editVideo['youtube_id'] ?? '') ?>"
                 placeholder="dQw4w9WgXcQ or full URL">
          <span class="form-hint">Paste full YouTube URL or just the video ID</span>
        </div>
        <div class="field">
          <label>Category</label>
          <select name="category">
            <?php foreach (['Live','Studio','Cover','BTS','Original','Tutorial','Vlog'] as $c): ?>
            <option value="<?= $c ?>" <?= ($editVideo['category'] ?? '') === $c ? 'selected' : '' ?>>
              <?= $c ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Duration (e.g. 5:12)</label>
          <input type="text" name="duration" value="<?= h($editVideo['duration'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Sort Order</label>
          <input type="number" name="sort_order" value="<?= (int)($editVideo['sort_order'] ?? 0) ?>">
        </div>
        <div class="field full">
          <label>Description</label>
          <textarea name="description" rows="3"><?= h($editVideo['description'] ?? '') ?></textarea>
        </div>
        <div class="field">
          <label>Upload Video File (mp4/webm — optional, YouTube preferred)</label>
          <input type="file" name="video_file" accept="video/*">
          <?php if (!empty($editVideo['video_file'])): ?>
          <span class="form-hint">Current: <?= h($editVideo['video_file']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field" style="justify-content:center">
          <label>&nbsp;</label>
          <div class="flex gap-3 flex-wrap" style="margin-top:4px">
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" name="is_published"
                     <?= ($editVideo['is_published'] ?? 1) ? 'checked' : '' ?>>
              Published
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" name="is_featured"
                     <?= ($editVideo['is_featured'] ?? 0) ? 'checked' : '' ?>>
              Featured (Hero)
            </label>
          </div>
        </div>
      </div><!-- /.form-grid -->

      <div class="flex gap-2 mt-4">
        <button type="submit" class="btn btn-lg btn-primary">
          💾 <?= $editVideo ? 'Save Changes' : 'Add Video' ?>
        </button>
        <a href="/admin/videos" class="btn btn-lg btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h3>🎬 All Videos (<?= count($videos) ?>)</h3>
    <?php if ($action !== 'new'): ?>
    <a href="videos.php?action=new" class="btn btn-sm btn-primary">+ Add Video</a>
    <?php endif; ?>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Title</th><th>YouTube ID</th><th>Category</th>
          <th>Views</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($videos as $v): ?>
      <tr>
        <td class="text-muted text-xs"><?= $v['id'] ?></td>
        <td>
          <div class="fw-bold"><?= h($v['title']) ?></div>
          <?php if ($v['is_featured']): ?>
            <span class="badge badge-yellow">Featured</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($v['youtube_id']): ?>
          <a href="https://youtu.be/<?= h($v['youtube_id']) ?>" target="_blank"
             class="text-accent text-xs"><?= h($v['youtube_id']) ?> ↗</a>
          <?php else: ?>
          <span class="text-muted text-xs">—</span>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-blue"><?= h($v['category'] ?? '—') ?></span></td>
        <td><?= number_format($v['views']) ?></td>
        <td>
          <a href="videos.php?toggle=<?= $v['id'] ?>"
             class="badge <?= $v['is_published'] ? 'badge-green' : 'badge-gray' ?>">
            <?= $v['is_published'] ? 'Live' : 'Draft' ?>
          </a>
        </td>
        <td>
          <div class="flex gap-2">
            <a href="videos.php?edit=<?= $v['id'] ?>"
               class="btn btn-sm btn-secondary">Edit</a>
            <?php if (!$v['is_featured']): ?>
            <a href="videos.php?feature=<?= $v['id'] ?>"
               class="btn btn-sm btn-secondary" title="Set as featured">⭐</a>
            <?php endif; ?>
            <a href="videos.php?delete=<?= $v['id'] ?>"
               class="btn btn-sm btn-danger"
               data-confirm="Delete «<?= h($v['title']) ?>»?">Delete</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$videos): ?>
      <tr>
        <td colspan="7" style="text-align:center;padding:30px;color:var(--muted)">
          No videos yet. <a href="videos.php?action=new" style="color:var(--accent)">Add one →</a>
        </td>
      </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/_layout_end.php'; ?>