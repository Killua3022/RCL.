<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// admin/posts.php — Manage Blog Posts
require_once __DIR__ . '/../includes/config.php';

// ── ALL LOGIC BEFORE ANY OUTPUT ─────────────────────────────
$d      = db();
$msg    = '';
$err    = '';
$action = $_GET['action'] ?? '';
$editId = (int)($_GET['edit'] ?? 0);

// ── Handle form ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id           = (int)($_POST['id'] ?? 0);
    $title        = trim($_POST['title'] ?? '');
    $excerpt      = trim($_POST['excerpt'] ?? '');
    $content      = trim($_POST['content'] ?? '');
    $cover_emoji  = trim($_POST['cover_emoji'] ?? '🎤');
    $cover_color  = trim($_POST['cover_color'] ?? '');
    $tags         = trim($_POST['tags'] ?? '');
    $status       = $_POST['status'] ?? 'draft';
    $published_at = trim($_POST['published_at'] ?? '');

    $slug = slug($title);

    // Ensure unique slug
    $check = $d->prepare('SELECT id FROM posts WHERE slug=? AND id!=?');
    $check->execute([$slug, $id]);
    if ($check->fetchColumn()) $slug .= '-' . time();

    $pub_date = ($status === 'published' && !$published_at)
        ? date('Y-m-d H:i:s')
        : ($published_at ?: null);

    // Cover image upload
    $cover_image = trim($_POST['existing_cover'] ?? '');
    if (!empty($_FILES['cover_image']['name'])) {
        $up = uploadFile($_FILES['cover_image'], 'images', ALLOWED_IMG);
        if ($up) $cover_image = $up;
    }

    if ($title) {
        if ($id) {
            $d->prepare('UPDATE posts SET title=?,slug=?,excerpt=?,content=?,cover_emoji=?,cover_color=?,cover_image=?,tags=?,status=?,published_at=? WHERE id=?')
              ->execute([$title,$slug,$excerpt,$content,$cover_emoji,$cover_color,$cover_image,$tags,$status,$pub_date,$id]);
            logAction('Updated post', "ID:$id – $title");
            header('Location: posts.php?msg=' . urlencode("Post updated: $title"));
        } else {
            $d->prepare('INSERT INTO posts (title,slug,excerpt,content,cover_emoji,cover_color,cover_image,tags,status,published_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
              ->execute([$title,$slug,$excerpt,$content,$cover_emoji,$cover_color,$cover_image,$tags,$status,$pub_date]);
            logAction('Created post', $title);
            header('Location: posts.php?msg=' . urlencode("Post created: $title"));
        }
        exit;
    }
    // Missing title — fall through with form visible
    $err = 'Post title is required.';
}

// ── Delete ───────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $s  = $d->prepare('SELECT title FROM posts WHERE id=?');
    $s->execute([$id]);
    $t  = $s->fetchColumn();
    $d->prepare('DELETE FROM posts WHERE id=?')->execute([$id]);
    logAction('Deleted post', "ID:$id – $t");
    header('Location: posts.php?msg=Post+deleted');
    exit;
}

// ── Toggle publish ────────────────────────────────────────────
if (isset($_GET['toggle'])) {
    $id  = (int)$_GET['toggle'];
    $s   = $d->prepare('SELECT status FROM posts WHERE id=?');
    $s->execute([$id]);
    $cur = $s->fetchColumn();
    $new = $cur === 'published' ? 'draft' : 'published';
    $pd  = $new === 'published' ? date('Y-m-d H:i:s') : null;
    $d->prepare('UPDATE posts SET status=?, published_at=COALESCE(published_at,?) WHERE id=?')
      ->execute([$new, $pd, $id]);
    header('Location: posts.php');
    exit;
}

// ── Flash message ─────────────────────────────────────────────
if (!empty($_GET['msg'])) $msg = h($_GET['msg']);

// ── Load edit data ────────────────────────────────────────────
$editPost = null;
if ($editId) {
    $s = $d->prepare('SELECT * FROM posts WHERE id=?');
    $s->execute([$editId]);
    $editPost = $s->fetch();
    $action   = 'edit';
}

// ── List data ─────────────────────────────────────────────────
$filter = $_GET['filter'] ?? '';
$allowedFilters = ['published', 'draft', 'scheduled'];
$whereClause = in_array($filter, $allowedFilters) ? " WHERE status='" . $filter . "'" : '';
$posts = $d->query('SELECT * FROM posts' . $whereClause . ' ORDER BY created_at DESC')->fetchAll();

$counts = [
    'all'       => (int)$d->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'published' => (int)$d->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn(),
    'draft'     => (int)$d->query("SELECT COUNT(*) FROM posts WHERE status='draft'")->fetchColumn(),
];

// ── NOW safe to output HTML ───────────────────────────────────
$pageTitle = 'Blog Posts';
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
    <h3><?= $editPost ? '✏️ Edit Post' : '+ New Post' ?></h3>
    <a href="posts.php" class="btn btn-sm btn-secondary">← Back</a>
  </div>
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <?php if ($editPost): ?>
      <input type="hidden" name="id"             value="<?= $editPost['id'] ?>">
      <input type="hidden" name="existing_cover" value="<?= h($editPost['cover_image'] ?? '') ?>">
      <?php endif; ?>

      <div class="form-grid">
        <div class="field full">
          <label>Post Title *</label>
          <input type="text" name="title" value="<?= h($editPost['title'] ?? '') ?>" required>
        </div>
        <div class="field full">
          <label>Excerpt (short summary)</label>
          <textarea name="excerpt" rows="2"><?= h($editPost['excerpt'] ?? '') ?></textarea>
        </div>
        <div class="field full">
          <label>Full Content (HTML supported)</label>
          <textarea name="content" rows="12"
                    style="font-family:monospace;font-size:.82rem"><?= h($editPost['content'] ?? '') ?></textarea>
        </div>
        <div class="field">
          <label>Cover Emoji</label>
          <input type="text" name="cover_emoji"
                 value="<?= h($editPost['cover_emoji'] ?? '🎤') ?>" maxlength="4">
        </div>
        <div class="field">
          <label>Cover Color / Gradient (CSS)</label>
          <input type="text" name="cover_color"
                 value="<?= h($editPost['cover_color'] ?? 'linear-gradient(135deg,#1a1a2e,#2d1b69)') ?>">
        </div>
        <div class="field">
          <label>Cover Image (optional)</label>
          <input type="file" name="cover_image" accept="image/*">
          <?php if (!empty($editPost['cover_image'])): ?>
          <span class="form-hint">Current: <?= h($editPost['cover_image']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field">
          <label>Tags (comma-separated)</label>
          <input type="text" name="tags"
                 value="<?= h($editPost['tags'] ?? '') ?>"
                 placeholder="Vocal Tips, Tutorial, Personal">
        </div>
        <div class="field">
          <label>Status</label>
          <select name="status">
            <option value="draft"     <?= ($editPost['status'] ?? 'draft') === 'draft'     ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= ($editPost['status'] ?? '')      === 'published' ? 'selected' : '' ?>>Published</option>
            <option value="scheduled" <?= ($editPost['status'] ?? '')      === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
          </select>
        </div>
        <div class="field">
          <label>Publish Date</label>
          <input type="datetime-local" name="published_at"
                 value="<?= $editPost['published_at']
                     ? date('Y-m-d\TH:i', strtotime($editPost['published_at']))
                     : '' ?>">
        </div>
      </div><!-- /.form-grid -->

      <div class="flex gap-2 mt-4">
        <button type="submit" class="btn btn-lg btn-primary">
          💾 <?= $editPost ? 'Save Changes' : 'Create Post' ?>
        </button>
        <a href="posts.php" class="btn btn-lg btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h3>✍️ Posts</h3>
    <div class="flex gap-2 flex-wrap items-center" style="flex:1;margin-left:16px">
      <a href="posts.php"
         class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">
        All (<?= $counts['all'] ?>)
      </a>
      <a href="posts.php?filter=published"
         class="btn btn-sm <?= $filter === 'published' ? 'btn-primary' : 'btn-secondary' ?>">
        Published (<?= $counts['published'] ?>)
      </a>
      <a href="posts.php?filter=draft"
         class="btn btn-sm <?= $filter === 'draft' ? 'btn-primary' : 'btn-secondary' ?>">
        Draft (<?= $counts['draft'] ?>)
      </a>
    </div>
    <?php if ($action !== 'new'): ?>
    <a href="posts.php?action=new" class="btn btn-sm btn-primary">+ New Post</a>
    <?php endif; ?>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Title</th><th>Tags</th><th>Status</th>
          <th>Views</th><th>Published</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td class="text-muted text-xs"><?= $p['id'] ?></td>
        <td>
          <div class="fw-bold"><?= h($p['title']) ?></div>
          <div class="text-xs text-muted">/<?= h($p['slug']) ?></div>
        </td>
        <td>
          <?php foreach (array_filter(array_map('trim', explode(',', $p['tags'] ?? ''))) as $tag): ?>
          <span class="badge badge-gray" style="margin:1px"><?= h($tag) ?></span>
          <?php endforeach; ?>
        </td>
        <td>
          <a href="posts.php?toggle=<?= $p['id'] ?>"
             class="badge <?= $p['status'] === 'published' ? 'badge-green' : ($p['status'] === 'draft' ? 'badge-gray' : 'badge-yellow') ?>">
            <?= h($p['status']) ?>
          </a>
        </td>
        <td><?= number_format($p['views']) ?></td>
        <td class="text-xs text-muted">
          <?= $p['published_at'] ? date('M d, Y', strtotime($p['published_at'])) : '—' ?>
        </td>
        <td>
          <div class="flex gap-2">
            <a href="posts.php?edit=<?= $p['id'] ?>"
               class="btn btn-sm btn-secondary">Edit</a>
            <a href="posts.php?delete=<?= $p['id'] ?>"
               class="btn btn-sm btn-danger"
               data-confirm="Delete «<?= h($p['title']) ?>»?">Delete</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$posts): ?>
      <tr>
        <td colspan="7" style="text-align:center;padding:30px;color:var(--muted)">
          No posts found.
          <?php if (!$filter): ?>
          <a href="posts.php?action=new" style="color:var(--accent)">Create one →</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/_layout_end.php'; ?>