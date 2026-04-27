<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// ── Simple auth (change these!) ──
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'music123');

session_start();

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['u'] === ADMIN_USER && $_POST['p'] === ADMIN_PASS) {
        $_SESSION['admin'] = true;
        header('Location: /admin/dashboard'); exit;
    }
    $loginErr = 'Invalid credentials.';
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/dashboard'); exit;
}

// Guard
if (empty($_SESSION['admin'])) { showLogin($loginErr ?? null); exit; }

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/settings.php';

$d    = db();
$site = getAppearanceSettings($d);

// Ensure lyrics column exists
try {
    $d->exec("ALTER TABLE tracks ADD COLUMN lyrics TEXT");
} catch (Exception $e) {}
try {
    $d->exec("ALTER TABLE tracks ADD COLUMN is_new TINYINT(1) DEFAULT 0");
} catch (Exception $e) {}

$msg = '';

// ── DELETE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    // Delete cover image if local
    $row = $d->query("SELECT cover_image, file_path FROM tracks WHERE id=$id")->fetch();
    if ($row) {
        if ($row['cover_image'] && file_exists(__DIR__ . '/' . $row['cover_image'])) {
            @unlink(__DIR__ . '/' . $row['cover_image']);
        }
        if ($row['file_path'] && file_exists(__DIR__ . '/' . $row['file_path'])) {
            @unlink(__DIR__ . '/' . $row['file_path']);
        }
    }
    $d->exec("DELETE FROM tracks WHERE id=$id");
    $msg = 'Track deleted.';
}

// ── SAVE (Add or Edit) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_track'])) {
    $id      = (int)($_POST['track_id'] ?? 0);
    $title   = trim($_POST['title']   ?? '');
    $artist  = trim($_POST['artist']  ?? '');
    $type    = trim($_POST['type']    ?? 'original');
    $ytId    = trim($_POST['youtube_id'] ?? '');
    $dur     = trim($_POST['duration'] ?? '');
    $sort    = (int)($_POST['sort_order'] ?? 0);
    $pub     = isset($_POST['is_published']) ? 1 : 0;
    $feat    = isset($_POST['is_featured'])  ? 1 : 0;
    $isNew   = isset($_POST['is_new'])       ? 1 : 0;
    $lyrics  = trim($_POST['lyrics']  ?? '');
    $color   = trim($_POST['cover_color'] ?? '');

    // Handle cover image upload
    $coverPath = trim($_POST['existing_cover'] ?? '');
    if (!empty($_FILES['cover_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $dir = __DIR__ . '/uploads/covers/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'cover_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $dir . $fname)) {
                $coverPath = 'uploads/covers/' . $fname;
                // Delete old
                if (!empty($_POST['existing_cover']) && file_exists(__DIR__ . '/' . $_POST['existing_cover'])) {
                    @unlink(__DIR__ . '/' . $_POST['existing_cover']);
                }
            }
        }
    }

    // Handle audio file upload
    $filePath = trim($_POST['existing_file'] ?? '');
    if (!empty($_FILES['audio_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['audio_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['mp3','wav','ogg','aac','flac','m4a'])) {
            $dir = __DIR__ . '/uploads/audio/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'track_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $dir . $fname)) {
                $filePath = 'uploads/audio/' . $fname;
                if (!empty($_POST['existing_file']) && file_exists(__DIR__ . '/' . $_POST['existing_file'])) {
                    @unlink(__DIR__ . '/' . $_POST['existing_file']);
                }
            }
        }
    }

    if ($id) {
        // Update
        $stmt = $d->prepare("UPDATE tracks SET
            title=?, artist=?, type=?, youtube_id=?, duration=?,
            cover_image=?, file_path=?, cover_color=?, lyrics=?,
            sort_order=?, is_published=?, is_featured=?, is_new=?
            WHERE id=?");
        $stmt->execute([$title,$artist,$type,$ytId,$dur,$coverPath,$filePath,$color,$lyrics,$sort,$pub,$feat,$isNew,$id]);
        $msg = '✓ Track updated successfully.';
    } else {
        // Insert
        $stmt = $d->prepare("INSERT INTO tracks
            (title,artist,type,youtube_id,duration,cover_image,file_path,cover_color,lyrics,sort_order,is_published,is_featured,is_new,plays)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0)");
        $stmt->execute([$title,$artist,$type,$ytId,$dur,$coverPath,$filePath,$color,$lyrics,$sort,$pub,$feat,$isNew]);
        $msg = '✓ Track added successfully.';
    }
    header('Location: /admin/dashboard?msg=' . urlencode($msg)); exit;
}

// URL msg
if (!empty($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

// Fetch all tracks
$tracks = $d->query("SELECT * FROM tracks ORDER BY sort_order ASC, id DESC")->fetchAll();

// Edit mode
$editTrack = null;
if (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editTrack = $d->query("SELECT * FROM tracks WHERE id=$editId")->fetch();
}



// ════════════════════════════════════════════
function showLogin($err = null) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#0a0a0f;color:#e8e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center}
.login-box{background:#111118;border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:48px 40px;width:360px;text-align:center}
.login-icon{font-size:2.5rem;margin-bottom:16px}
.login-title{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:800;margin-bottom:6px}
.login-sub{font-size:.85rem;color:#666680;margin-bottom:32px}
input{width:100%;background:#16161f;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#e8e8f0;font-family:'DM Sans',sans-serif;font-size:.9rem;padding:12px 16px;outline:none;margin-bottom:12px;transition:border-color .2s}
input:focus{border-color:#1db954}
button{width:100%;padding:13px;border-radius:10px;background:#1db954;color:#000;font-family:'DM Sans',sans-serif;font-weight:700;font-size:.95rem;border:none;cursor:pointer;transition:background .15s}
button:hover{background:#1ed760}
.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:10px;font-size:.82rem;color:#f87171;margin-bottom:16px}
</style>
</head>
<body>
<div class="login-box">
  <div class="login-icon">🎵</div>
  <div class="login-title">Music Admin</div>
  <div class="login-sub">Sign in to manage your tracks</div>
  <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <form method="POST">
    <input type="text" name="u" placeholder="Username" required autofocus>
    <input type="password" name="p" placeholder="Password" required>
    <button type="submit" name="login">Sign In</button>
  </form>
</div>
</body>
</html>
<?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Music Admin — <?= h($site['logo_text']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">

<style>
:root{
  --bg:#0a0a0f; --surface:#111118; --card:#16161f; --card-h:#1e1e2a;
  --border:rgba(255,255,255,0.07); --green:#1db954; --red:#ef4444;
  --yellow:#f59e0b; --accent:#a855f7; --text:#e8e8f0; --muted:#666680;
  --dim:#444458; --sidebar:240px;
  --font-head:'Syne',sans-serif; --font:'DM Sans',sans-serif;
  --r:10px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font);background:var(--bg);color:var(--text);display:flex;min-height:100vh;overflow-x:hidden}

/* ── SIDEBAR ── */
.adm-sidebar{
  width:var(--sidebar);flex-shrink:0;background:var(--surface);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  padding:0;position:sticky;top:0;height:100vh;overflow-y:auto;
}
.adm-logo{
  padding:24px 20px;
  font-family:var(--font-head);font-size:1.1rem;font-weight:800;
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:10px;
}
.adm-logo-dot{
  width:30px;height:30px;border-radius:50%;background:var(--green);
  display:flex;align-items:center;justify-content:center;font-size:14px;color:#000;
}
.adm-nav{padding:12px;}
.adm-nav-item{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;border-radius:8px;
  color:var(--muted);font-size:.85rem;font-weight:500;
  cursor:pointer;text-decoration:none;
  transition:color .15s,background .15s;
  border:none;background:none;width:100%;font-family:var(--font);
}
.adm-nav-item:hover,.adm-nav-item.active{color:var(--text);background:var(--card)}
.adm-nav-item.active{color:var(--green)}
.adm-nav-sep{
  font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  color:var(--dim);padding:16px 12px 6px;
}
.adm-sidebar-footer{
  margin-top:auto;padding:16px 20px;border-top:1px solid var(--border);
  font-size:.78rem;color:var(--muted);
}
.adm-sidebar-footer a{color:var(--muted);text-decoration:none;transition:color .15s}
.adm-sidebar-footer a:hover{color:var(--text)}

/* ── MAIN ── */
.adm-main{flex:1;display:flex;flex-direction:column;min-width:0}
.adm-topbar{
  display:flex;align-items:center;justify-content:space-between;
  padding:16px 32px;
  background:rgba(10,10,15,0.8);backdrop-filter:blur(10px);
  border-bottom:1px solid var(--border);
  position:sticky;top:0;z-index:100;gap:16px;
}
.adm-topbar h1{font-family:var(--font-head);font-size:1.2rem;font-weight:700}
.adm-topbar-right{display:flex;align-items:center;gap:12px}

.adm-content{padding:32px;flex:1}

/* ── MSG ── */
.adm-msg{
  background:rgba(29,185,84,.1);border:1px solid rgba(29,185,84,.3);
  border-radius:8px;padding:12px 16px;font-size:.88rem;color:#4ade80;
  margin-bottom:24px;display:flex;align-items:center;gap:8px;
}

/* ── STATS ── */
.adm-stats{
  display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
  gap:16px;margin-bottom:32px;
}
.adm-stat{
  background:var(--card);border:1px solid var(--border);border-radius:var(--r);
  padding:20px;
}
.adm-stat-n{font-family:var(--font-head);font-size:2rem;font-weight:800;line-height:1}
.adm-stat-l{font-size:.75rem;color:var(--muted);margin-top:6px}

/* ── BTN ── */
.btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:9px 18px;border-radius:8px;
  font-family:var(--font);font-size:.85rem;font-weight:600;
  cursor:pointer;border:none;transition:all .15s;text-decoration:none;
}
.btn-green{background:var(--green);color:#000}
.btn-green:hover{background:#1ed760;transform:scale(1.02)}
.btn-ghost{background:var(--card);color:var(--text);border:1px solid var(--border)}
.btn-ghost:hover{background:var(--card-h)}
.btn-red{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.2)}
.btn-red:hover{background:rgba(239,68,68,.25)}
.btn-yellow{background:rgba(245,158,11,.15);color:#fbbf24;border:1px solid rgba(245,158,11,.2)}
.btn-yellow:hover{background:rgba(245,158,11,.25)}
.btn-sm{padding:6px 12px;font-size:.78rem}

/* ── TABLE ── */
.adm-table-wrap{
  background:var(--card);border:1px solid var(--border);
  border-radius:var(--r);overflow:hidden;
}
.adm-table-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:16px 20px;border-bottom:1px solid var(--border);
  gap:12px;flex-wrap:wrap;
}
.adm-table-head h2{font-family:var(--font-head);font-size:1rem;font-weight:700}
.adm-search{
  background:var(--surface);border:1px solid var(--border);border-radius:8px;
  color:var(--text);font-family:var(--font);font-size:.85rem;
  padding:8px 14px;outline:none;width:220px;transition:border-color .2s;
}
.adm-search:focus{border-color:var(--green)}
.adm-table{width:100%;border-collapse:collapse}
.adm-table th{
  text-align:left;padding:10px 16px;
  font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  color:var(--muted);border-bottom:1px solid var(--border);
}
.adm-table td{padding:12px 16px;border-bottom:1px solid var(--border);vertical-align:middle}
.adm-table tbody tr:last-child td{border-bottom:none}
.adm-table tbody tr:hover td{background:rgba(255,255,255,0.02)}

.td-art{display:flex;align-items:center;gap:12px}
.td-thumb{
  width:44px;height:44px;border-radius:8px;
  background:var(--surface);flex-shrink:0;overflow:hidden;
  display:flex;align-items:center;justify-content:center;font-size:18px;
}
.td-thumb img{width:100%;height:100%;object-fit:cover}
.td-title{font-size:.88rem;font-weight:600}
.td-artist{font-size:.75rem;color:var(--muted);margin-top:2px}
.td-tag{
  display:inline-block;padding:3px 8px;border-radius:4px;
  font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
}
.tag-pub{background:rgba(29,185,84,.15);color:#4ade80}
.tag-draft{background:rgba(100,100,128,.2);color:var(--muted)}
.tag-type{background:rgba(168,85,247,.15);color:#c084fc}
.td-actions{display:flex;align-items:center;gap:6px}

/* ── MODAL / FORM ── */
.adm-overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);
  z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px;
}
.adm-modal{
  background:var(--card);border:1px solid var(--border);border-radius:16px;
  width:100%;max-width:680px;max-height:90vh;overflow-y:auto;
  scrollbar-width:thin;scrollbar-color:var(--dim) transparent;
}
.adm-modal-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:20px 24px;border-bottom:1px solid var(--border);
  position:sticky;top:0;background:var(--card);z-index:1;
}
.adm-modal-head h2{font-family:var(--font-head);font-size:1.1rem;font-weight:700}
.adm-modal-body{padding:24px}
.adm-modal-close{
  background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;
  transition:color .15s;
}
.adm-modal-close:hover{color:var(--text)}

/* ── FORM ── */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.form-row.single{grid-template-columns:1fr}
.form-group{display:flex;flex-direction:column;gap:6px}
label{font-size:.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
input[type=text],input[type=url],input[type=number],select,textarea{
  background:var(--surface);border:1px solid var(--border);border-radius:8px;
  color:var(--text);font-family:var(--font);font-size:.88rem;
  padding:10px 14px;outline:none;transition:border-color .2s;
}
input:focus,select:focus,textarea:focus{border-color:var(--green)}
textarea{resize:vertical;min-height:100px;line-height:1.6}
select option{background:var(--card)}
.form-check{display:flex;align-items:center;gap:8px;cursor:pointer}
.form-check input{width:16px;height:16px;accent-color:var(--green)}
.form-checks{display:flex;gap:20px;flex-wrap:wrap}

/* Image preview */
.img-preview{
  width:80px;height:80px;border-radius:8px;border:1px solid var(--border);
  object-fit:cover;margin-top:6px;display:none;
}
.img-preview.show{display:block}

/* ── CONFIRM ── */
.adm-confirm{
  position:fixed;inset:0;background:rgba(0,0,0,.8);
  z-index:2000;display:none;align-items:center;justify-content:center;
}
.adm-confirm.show{display:flex}
.adm-confirm-box{
  background:var(--card);border:1px solid var(--border);border-radius:12px;
  padding:28px;text-align:center;max-width:320px;width:100%;
}
.adm-confirm-box h3{font-family:var(--font-head);margin-bottom:8px}
.adm-confirm-box p{font-size:.85rem;color:var(--muted);margin-bottom:20px}
.adm-confirm-btns{display:flex;gap:10px;justify-content:center}

/* ── RESPONSIVE ── */
@media(max-width:768px){
  .adm-sidebar{display:none}
  .adm-content{padding:16px}
  .form-row{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<aside class="adm-sidebar">
  <div class="adm-logo">
    <div class="adm-logo-dot">♪</div>
    <?= h($site['logo_text']) ?>
  </div>
  <nav class="adm-nav">
    <div class="adm-nav-sep">Content</div>
    <button class="adm-nav-item active" onclick="showPanel('tracks')">
      <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
      Tracks
    </button>
    <a class="adm-nav-item" href="/admin/music" target="_blank">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      View Music Page
    </a>
    <a class="adm-nav-item" href="/admin/dashboard" target="_blank">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
      View Site
    </a>
  </nav>
  <div class="adm-sidebar-footer">
    Logged in as <strong><?= ADMIN_USER ?></strong><br>
    <a href="?logout=1">Sign out →</a>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="adm-main">

  <!-- Topbar -->
  <div class="adm-topbar">
    <h1>🎵 Track Manager</h1>
    <div class="adm-topbar-right">
      <a href="/admin/music" target="_blank" class="btn btn-ghost btn-sm">↗ View Page</a>
      <button class="btn btn-green" onclick="openModal()">+ Add Track</button>
    </div>
  </div>

  <!-- Content -->
  <div class="adm-content">

    <?php if ($msg): ?>
    <div class="adm-msg">✓ <?= h($msg) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="adm-stats">
      <?php
        $total     = count($tracks);
        $published = count(array_filter($tracks, fn($t) => $t['is_published']));
        $drafts    = $total - $published;
        $totalPlay = array_sum(array_column($tracks, 'plays'));
      ?>
      <div class="adm-stat">
        <div class="adm-stat-n"><?= $total ?></div>
        <div class="adm-stat-l">Total Tracks</div>
      </div>
      <div class="adm-stat">
        <div class="adm-stat-n" style="color:var(--green)"><?= $published ?></div>
        <div class="adm-stat-l">Published</div>
      </div>
      <div class="adm-stat">
        <div class="adm-stat-n" style="color:var(--muted)"><?= $drafts ?></div>
        <div class="adm-stat-l">Drafts</div>
      </div>
      <div class="adm-stat">
        <div class="adm-stat-n" style="color:var(--accent)"><?= number_format($totalPlay) ?></div>
        <div class="adm-stat-l">Total Plays</div>
      </div>
    </div>

    <!-- Track Table -->
    <div class="adm-table-wrap">
      <div class="adm-table-head">
        <h2>All Tracks</h2>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <input type="text" class="adm-search" id="tableSearch" placeholder="🔍 Search tracks…" oninput="filterTable(this.value)">
          <button class="btn btn-green" onclick="openModal()">+ Add Track</button>
        </div>
      </div>
      <div style="overflow-x:auto">
        <table class="adm-table" id="mainTable">
          <thead>
            <tr>
              <th>Track</th>
              <th>Type</th>
              <th>Source</th>
              <th>Plays</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tracks as $t): ?>
            <tr data-search="<?= h(strtolower($t['title'] . ' ' . $t['artist'])) ?>">
              <td>
                <div class="td-art">
                  <div class="td-thumb">
                    <?php if (!empty($t['cover_image'])): ?>
                    <img src="/<?= h($t['cover_image']) ?>" alt="">
                    <?php elseif (!empty($t['youtube_id'])): ?>
                    <img src="https://img.youtube.com/vi/<?= h($t['youtube_id']) ?>/default.jpg" alt="">
                    <?php else: ?>🎵<?php endif; ?>
                  </div>
                  <div>
                    <div class="td-title"><?= h($t['title']) ?></div>
                    <div class="td-artist"><?= h($t['artist']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="td-tag tag-type"><?= h($t['type'] ?? 'original') ?></span></td>
              <td style="font-size:.78rem;color:var(--muted)">
                <?php if (!empty($t['file_path'])): ?>
                  Audio file
                <?php elseif (!empty($t['youtube_id'])): ?>
                  YouTube
                <?php else: ?>
                  None
                <?php endif; ?>
              </td>
              <td style="font-size:.85rem;color:var(--muted)"><?= number_format($t['plays'] ?? 0) ?></td>
              <td>
                <span class="td-tag <?= $t['is_published'] ? 'tag-pub' : 'tag-draft' ?>">
                  <?= $t['is_published'] ? 'Published' : 'Draft' ?>
                </span>
              </td>
              <td>
                <div class="td-actions">
                  <button class="btn btn-yellow btn-sm" onclick='openEdit(<?= json_encode([
                    "id"           => (int)$t["id"],
                    "title"        => $t["title"],
                    "artist"       => $t["artist"],
                    "type"         => $t["type"] ?? "original",
                    "youtube_id"   => $t["youtube_id"] ?? "",
                    "duration"     => $t["duration"] ?? "",
                    "cover_color"  => $t["cover_color"] ?? "",
                    "lyrics"       => $t["lyrics"] ?? "",
                    "sort_order"   => (int)($t["sort_order"] ?? 0),
                    "is_published" => (bool)$t["is_published"],
                    "is_featured"  => (bool)($t["is_featured"] ?? 0),
                    "is_new"       => (bool)($t["is_new"] ?? 0),
                    "cover_image"  => $t["cover_image"] ?? "",
                    "file_path"    => $t["file_path"] ?? "",
                  ], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Edit</button>
                  <button class="btn btn-red btn-sm" onclick="confirmDelete(<?= (int)$t['id'] ?>,'<?= addslashes(h($t['title'])) ?>')">Delete</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$tracks): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:40px">No tracks yet. Add your first track!</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /adm-content -->
</div><!-- /adm-main -->

<!-- ══ ADD/EDIT MODAL ══ -->
<div class="adm-overlay" id="modal" style="display:none">
  <div class="adm-modal">
    <div class="adm-modal-head">
      <h2 id="modalTitle">Add Track</h2>
      <button class="adm-modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="adm-modal-body">
      <form method="POST" enctype="multipart/form-data" id="trackForm">
        <input type="hidden" name="save_track" value="1">
        <input type="hidden" name="track_id" id="f_id" value="0">
        <input type="hidden" name="existing_cover" id="f_existing_cover">
        <input type="hidden" name="existing_file" id="f_existing_file">

        <!-- Basic Info -->
        <div class="form-row">
          <div class="form-group">
            <label>Song Title *</label>
            <input type="text" name="title" id="f_title" placeholder="e.g. Starlight" required>
          </div>
          <div class="form-group">
            <label>Artist *</label>
            <input type="text" name="artist" id="f_artist" placeholder="e.g. RCL" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Type</label>
            <select name="type" id="f_type">
              <option value="original">Original</option>
              <option value="cover">Cover</option>
              <option value="remix">Remix</option>
              <option value="live">Live</option>
              <option value="acoustic">Acoustic</option>
            </select>
          </div>
          <div class="form-group">
            <label>Duration (e.g. 3:24)</label>
            <input type="text" name="duration" id="f_duration" placeholder="3:24">
          </div>
        </div>

        <!-- Audio Source -->
        <div class="form-row">
          <div class="form-group">
            <label>YouTube Video ID</label>
            <input type="text" name="youtube_id" id="f_youtube_id" placeholder="dQw4w9WgXcQ">
            <small style="color:var(--muted);font-size:.72rem">The part after youtube.com/watch?v=</small>
          </div>
          <div class="form-group">
            <label>Sort Order</label>
            <input type="number" name="sort_order" id="f_sort" value="0" min="0">
          </div>
        </div>

        <!-- Audio File Upload -->
        <div class="form-row single">
          <div class="form-group">
            <label>Upload Audio File (MP3, WAV, etc.)</label>
            <input type="file" name="audio_file" accept=".mp3,.wav,.ogg,.aac,.flac,.m4a" style="color:var(--text)">
            <small style="color:var(--muted);font-size:.72rem" id="existingFileLabel"></small>
          </div>
        </div>

        <!-- Cover -->
        <div class="form-row">
          <div class="form-group">
            <label>Cover Image Upload</label>
            <input type="file" name="cover_image" accept="image/*" style="color:var(--text)" onchange="previewImg(this)">
            <img class="img-preview" id="imgPreview" alt="preview">
          </div>
          <div class="form-group">
            <label>Cover Gradient CSS (fallback)</label>
            <input type="text" name="cover_color" id="f_color" placeholder="linear-gradient(135deg,#1a0533,#0d1b3e)">
            <small style="color:var(--muted);font-size:.72rem">Used if no image. E.g. #ff0000 or gradient.</small>
          </div>
        </div>

        <!-- Lyrics -->
        <div class="form-row single">
          <div class="form-group">
            <label>Lyrics (one line per line)</label>
            <textarea name="lyrics" id="f_lyrics" placeholder="[Verse 1]&#10;Your lyrics here...&#10;&#10;[Chorus]&#10;More lyrics..."></textarea>
          </div>
        </div>

        <!-- Toggles -->
        <div class="form-row single" style="margin-bottom:24px">
          <div class="form-group">
            <div class="form-checks">
              <label class="form-check">
                <input type="checkbox" name="is_published" id="f_pub" value="1">
                <span>Published</span>
              </label>
              <label class="form-check">
                <input type="checkbox" name="is_featured" id="f_feat" value="1">
                <span>Featured</span>
              </label>
              <label class="form-check">
                <input type="checkbox" name="is_new" id="f_new" value="1">
                <span>New Release badge</span>
              </label>
            </div>
          </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end">
          <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn btn-green" id="submitBtn">Add Track</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ CONFIRM DELETE ══ -->
<div class="adm-confirm" id="confirmBox">
  <div class="adm-confirm-box">
    <h3>Delete Track?</h3>
    <p id="confirmMsg">This action cannot be undone.</p>
    <form method="POST">
      <input type="hidden" name="delete_id" id="deleteId">
      <div class="adm-confirm-btns">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('confirmBox').classList.remove('show')">Cancel</button>
        <button type="submit" class="btn btn-red">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── MODAL ──
function openModal() {
  document.getElementById('modalTitle').textContent = 'Add Track';
  document.getElementById('submitBtn').textContent = 'Add Track';
  document.getElementById('trackForm').reset();
  document.getElementById('f_id').value = 0;
  document.getElementById('f_existing_cover').value = '';
  document.getElementById('f_existing_file').value = '';
  document.getElementById('imgPreview').classList.remove('show');
  document.getElementById('existingFileLabel').textContent = '';
  document.getElementById('modal').style.display = 'flex';
}

function openEdit(t) {
  document.getElementById('modalTitle').textContent = 'Edit Track';
  document.getElementById('submitBtn').textContent  = 'Save Changes';
  document.getElementById('f_id').value      = t.id;
  document.getElementById('f_title').value   = t.title;
  document.getElementById('f_artist').value  = t.artist;
  document.getElementById('f_type').value    = t.type;
  document.getElementById('f_youtube_id').value = t.youtube_id;
  document.getElementById('f_duration').value   = t.duration;
  document.getElementById('f_sort').value    = t.sort_order;
  document.getElementById('f_color').value   = t.cover_color;
  document.getElementById('f_lyrics').value  = t.lyrics;
  document.getElementById('f_pub').checked   = t.is_published;
  document.getElementById('f_feat').checked  = t.is_featured;
  document.getElementById('f_new').checked   = t.is_new;
  document.getElementById('f_existing_cover').value = t.cover_image;
  document.getElementById('f_existing_file').value  = t.file_path;

  if (t.cover_image) {
    const p = document.getElementById('imgPreview');
    p.src = '/' + t.cover_image;
    p.classList.add('show');
  } else {
    document.getElementById('imgPreview').classList.remove('show');
  }

  document.getElementById('existingFileLabel').textContent =
    t.file_path ? ' Current: ' + t.file_path : '';

  document.getElementById('modal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('modal').style.display = 'none';
}

document.getElementById('modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ── CONFIRM DELETE ──
function confirmDelete(id, title) {
  document.getElementById('deleteId').value = id;
  document.getElementById('confirmMsg').textContent = `Delete "${title}"? This cannot be undone.`;
  document.getElementById('confirmBox').classList.add('show');
}

// ── IMG PREVIEW ──
function previewImg(input) {
  const p = document.getElementById('imgPreview');
  if (input.files && input.files[0]) {
    const r = new FileReader();
    r.onload = e => { p.src = e.target.result; p.classList.add('show'); };
    r.readAsDataURL(input.files[0]);
  }
}

// ── TABLE SEARCH ──
function filterTable(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#mainTable tbody tr').forEach(row => {
    row.style.display = (row.dataset.search || '').includes(q) ? '' : 'none';
  });
}

// ── PANEL SWITCH ──
function showPanel(p) {
  document.querySelectorAll('.adm-nav-item').forEach(b => b.classList.remove('active'));
  event.currentTarget.classList.add('active');
}
</script>
</body>
</html>