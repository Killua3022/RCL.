<?php
// admin/tracks.php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/settings.php';

$d = db();

// Ensure extra columns exist
foreach (['lyrics TEXT', 'is_new BOOLEAN DEFAULT false', 'is_featured BOOLEAN DEFAULT false'] as $col) {
    try { $d->exec("ALTER TABLE tracks ADD COLUMN $col"); } catch (Exception $e) {}
}

$msg     = '';
$msgType = 'success';

// ══════════════════════════════════════════
//  ALL POST HANDLING — BEFORE ANY OUTPUT/INCLUDE
// ══════════════════════════════════════════

// ── DELETE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    $id  = (int)$_POST['delete_id'];
    $row = $d->query("SELECT cover_image, file_path FROM tracks WHERE id=$id")->fetch();
    if ($row) {
        $base = dirname(__DIR__) . '/';
        if ($row['cover_image'] && file_exists($base . $row['cover_image'])) @unlink($base . $row['cover_image']);
        if ($row['file_path']   && file_exists($base . $row['file_path']))   @unlink($base . $row['file_path']);
    }
    $d->exec("DELETE FROM tracks WHERE id=$id");
    header('Location: /admin/tracks?msg=deleted');
    exit;
}

// ── SAVE (add / edit) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_track'])) {
    $id     = (int)($_POST['track_id'] ?? 0);
    $title  = trim($_POST['title']      ?? '');
    $artist = trim($_POST['artist']     ?? '');
    $type   = trim($_POST['type']       ?? 'original');
    $ytId   = trim($_POST['youtube_id'] ?? '');
    $dur    = trim($_POST['duration']   ?? '');
    $sort   = (int)($_POST['sort_order']  ?? 0);
    $pub    = isset($_POST['is_published']) ? 'true' : 'false';
    $feat   = isset($_POST['is_featured']) ? 'true' : 'false';
    $isNew  = isset($_POST['is_new']) ? 'true' : 'false';
    $lyrics = trim($_POST['lyrics']     ?? '');
    $color  = trim($_POST['cover_color']?? '');
    $base   = dirname(__DIR__) . '/';

    // Cover image upload
    $coverPath = trim($_POST['existing_cover'] ?? '');
    if (!empty($_FILES['cover_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $dir = $base . 'uploads/covers/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'cover_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $dir . $fname)) {
                if ($coverPath && file_exists($base . $coverPath)) @unlink($base . $coverPath);
                $coverPath = 'uploads/covers/' . $fname;
            }
        }
    }

    // Audio file upload
    $filePath = trim($_POST['existing_file'] ?? '');
    if (!empty($_FILES['audio_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['audio_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['mp3','wav','ogg','aac','flac','m4a'])) {
            $dir = $base . 'uploads/audio/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'track_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $dir . $fname)) {
                if ($filePath && file_exists($base . $filePath)) @unlink($base . $filePath);
                $filePath = 'uploads/audio/' . $fname;
            }
        }
    }

    if (!$title) {
        $msg     = 'Title is required.';
        $msgType = 'error';
        // fall through — will render page below with error shown
    } else {
        if ($id) {
            $stmt = $d->prepare("UPDATE tracks SET
                title=?,artist=?,type=?,youtube_id=?,duration=?,
                cover_image=?,file_path=?,cover_color=?,lyrics=?,
                sort_order=?,is_published=?,is_featured=?,is_new=?
                WHERE id=?");
            $stmt->execute([$title,$artist,$type,$ytId,$dur,$coverPath,$filePath,$color,$lyrics,$sort,$pub,$feat,$isNew,$id]);
        } else {
            $stmt = $d->prepare("INSERT INTO tracks
                (title,artist,type,youtube_id,duration,cover_image,file_path,cover_color,lyrics,sort_order,is_published,is_featured,is_new,plays)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0)");
            $stmt->execute([$title,$artist,$type,$ytId,$dur,$coverPath,$filePath,$color,$lyrics,$sort,$pub,$feat,$isNew]);
        }
        // Redirect happens HERE — before _layout.php is ever included
        header('Location: /admin/tracks?msg=' . ($id ? 'updated' : 'added'));
        exit;
    }
}

// ── URL flash messages (GET) ──
if (!$msg && !empty($_GET['msg'])) {
    $msgs = ['added'=>'✓ Track added.','updated'=>'✓ Track updated.','deleted'=>'✓ Track deleted.'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

// ══════════════════════════════════════════
//  Safe to include layout / output HTML now
// ══════════════════════════════════════════
$pageTitle = 'Music Tracks';
include __DIR__ . '/_layout.php';

// ── Fetch all tracks for display ──
$tracks = $d->query("SELECT * FROM tracks ORDER BY sort_order ASC, id DESC")->fetchAll();
$total  = count($tracks);
$pub    = count(array_filter($tracks, fn($t) => $t['is_published']));
$plays  = array_sum(array_column($tracks, 'plays'));

// Top 5 by plays
$topPickIds = [];
$sorted = $tracks;
usort($sorted, fn($a, $b) => ($b['plays'] ?? 0) <=> ($a['plays'] ?? 0));
foreach (array_slice($sorted, 0, 5) as $tp) {
    $topPickIds[] = (int)$tp['id'];
}

function fmtN($n) {
    if ($n >= 1000000) return round($n/1000000,1).'M';
    if ($n >= 1000)    return round($n/1000,1).'k';
    return $n;
}
?>

<!-- STATS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-val"><?= $total ?></div>
    <div class="stat-label">Total Tracks</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-val"><?= $pub ?></div>
    <div class="stat-label">Published</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-val"><?= $total - $pub ?></div>
    <div class="stat-label">Drafts</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-val"><?= fmtN($plays) ?></div>
    <div class="stat-label">Total Plays</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"></div>
    <div class="stat-val"><?= min(5, $total) ?></div>
    <div class="stat-label">Top Picks</div>
  </div>
</div>

<!-- FLASH -->
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<!-- TRACK TABLE -->
<div class="card">
  <div class="card-head">
    <h3>All Tracks</h3>
    <div class="flex gap-2 items-center flex-wrap" style="margin-left:auto">
      <div class="search-bar" style="width:220px">
        <span style="color:var(--muted);font-size:.8rem"></span>
        <input type="text" id="srch" placeholder="Search tracks…" oninput="filterRows(this.value)">
      </div>
      <button class="btn btn-primary btn-sm" onclick="openModal()">+ Add Track</button>
    </div>
  </div>

  <div class="tbl-wrap">
    <table id="tbl">
      <thead>
        <tr>
          <th style="width:36px">#</th>
          <th>Track</th>
          <th>Type</th>
          <th>Source</th>
          <th>Plays</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tracks as $i => $t): ?>
        <?php $isTopPick = in_array((int)$t['id'], $topPickIds); ?>
        <tr data-q="<?= h(strtolower($t['title'].' '.$t['artist'])) ?>">
          <td class="text-muted text-xs"><?= $i+1 ?></td>
          <td>
            <div class="flex gap-2 items-center">
              <div style="width:42px;height:42px;border-radius:6px;background:#222;flex-shrink:0;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:18px">
                <?php if (!empty($t['cover_image'])): ?>
                  <img src="/<?= h($t['cover_image']) ?>" style="width:100%;height:100%;object-fit:cover">
                <?php elseif (!empty($t['youtube_id'])): ?>
                  <img src="https://img.youtube.com/vi/<?= h($t['youtube_id']) ?>/mqdefault.jpg" style="width:100%;height:100%;object-fit:cover">
                <?php else: ?>
                  <div style="width:100%;height:100%;background:linear-gradient(135deg,#1a0533,#0d1b3e);display:flex;align-items:center;justify-content:center;color:#555;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em">No Art</div>
                <?php endif; ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:.85rem"><?= h($t['title']) ?></div>
                <div class="text-muted text-xs"><?= h($t['artist']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="badge badge-blue"><?= h($t['type'] ?? 'original') ?></span></td>
          <td class="text-xs text-muted">
            <?php if (!empty($t['file_path'])): ?>🎵 File
            <?php elseif (!empty($t['youtube_id'])): ?>▶ YouTube
            <?php else: ?>— None<?php endif; ?>
          </td>
          <td class="text-sm"><?= fmtN($t['plays'] ?? 0) ?></td>
          <td>
            <span class="badge <?= $t['is_published'] ? 'badge-green' : 'badge-gray' ?>">
              <?= $t['is_published'] ? 'Live' : 'Draft' ?>
            </span>
            <?php if ($isTopPick): ?>
            <span class="badge badge-accent" style="margin-left:4px" title="Top 5 by plays">🏆 Top Pick</span>
            <?php endif; ?>
            <?php if (!empty($t['is_featured'])): ?>
            <span class="badge badge-accent" style="margin-left:4px">Featured</span>
            <?php endif; ?>
            <?php if (!empty($t['is_new'])): ?>
            <span class="badge badge-yellow" style="margin-left:4px">New</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-secondary btn-sm"
                onclick='openEdit(<?= json_encode([
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
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this track? This cannot be undone.')">
                <input type="hidden" name="delete_id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$tracks): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">No tracks yet — add your first one!</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ══════════════════════════════════════════
     ADD / EDIT MODAL
══════════════════════════════════════════ -->
<div id="overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:20px">
  <div id="modalBox" style="background:var(--card);border:1px solid var(--border);border-radius:12px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto;scrollbar-width:thin">

    <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--card);z-index:1">
      <h2 id="modalHeading" style="font-size:1rem;font-weight:700">Add Track</h2>
      <button type="button" onclick="closeModal()" style="background:none;border:none;color:var(--muted);font-size:1.3rem;cursor:pointer;line-height:1">✕</button>
    </div>

    <form method="POST" enctype="multipart/form-data" id="trackForm" style="padding:22px">
      <input type="hidden" name="save_track" value="1">
      <input type="hidden" name="track_id"      id="f_id"  value="0">
      <input type="hidden" name="existing_cover" id="f_ec"  value="">
      <input type="hidden" name="existing_file"  id="f_ef"  value="">

      <div class="form-grid mb-4">
        <div class="field">
          <label>Song Title *</label>
          <input type="text" name="title" id="f_title" placeholder="e.g. Starlight" required>
        </div>
        <div class="field">
          <label>Artist</label>
          <input type="text" name="artist" id="f_artist" placeholder="e.g. RCL">
        </div>
      </div>

      <div class="form-grid mb-4">
        <div class="field">
          <label>Type</label>
          <select name="type" id="f_type">
            <option value="original">Original</option>
            <option value="cover">Cover</option>
            <option value="remix">Remix</option>
            <option value="live">Live</option>
            <option value="acoustic">Acoustic</option>
          </select>
        </div>
        <div class="field">
          <label>Duration (e.g. 3:24)</label>
          <input type="text" name="duration" id="f_dur" placeholder="3:24">
        </div>
      </div>

      <div class="form-grid mb-4">
        <div class="field">
          <label>YouTube Video ID</label>
          <input type="text" name="youtube_id" id="f_yt" placeholder="dQw4w9WgXcQ" oninput="ytThumbPreview(this.value)">
          <div class="form-hint">The ID after youtube.com/watch?v=</div>
        </div>
        <div class="field">
          <label>Upload Audio File</label>
          <input type="file" name="audio_file" accept=".mp3,.wav,.ogg,.aac,.flac,.m4a" style="color:var(--text)">
          <div class="form-hint" id="f_ef_label"></div>
        </div>
      </div>

      <div class="form-grid mb-4">
        <div class="field">
          <label>Cover Image</label>
          <input type="file" name="cover_image" accept="image/*" style="color:var(--text)" onchange="previewCover(this)">
          <img id="coverPreview" style="display:none;width:72px;height:72px;object-fit:cover;border-radius:6px;margin-top:8px;border:1px solid var(--border)" alt="">
        </div>
        <div class="field">
          <label>Gradient Fallback CSS</label>
          <input type="text" name="cover_color" id="f_color" placeholder="linear-gradient(135deg,#1a0533,#c0392b)">
          <div class="form-hint">Used when no image is set</div>
        </div>
      </div>

      <div class="field full mb-4">
        <label>Lyrics</label>
        <textarea name="lyrics" id="f_lyrics" style="min-height:130px" placeholder="[Verse 1]&#10;Your lyrics here...&#10;&#10;[Chorus]&#10;Chorus lyrics..."></textarea>
        <div class="form-hint">One lyric line per line. Shown in the Lyrics panel on the music page.</div>
      </div>

      <div class="form-grid mb-4">
        <div class="field">
          <label>Sort Order</label>
          <input type="number" name="sort_order" id="f_sort" value="0" min="0">
        </div>
        <div class="field" style="justify-content:flex-end;padding-top:20px">
          <div class="flex gap-3 flex-wrap">
            <label style="display:flex;align-items:center;gap:7px;text-transform:none;letter-spacing:0;font-size:.82rem;cursor:pointer">
              <label class="toggle"><input type="checkbox" name="is_published" id="f_pub"><span class="toggle-slider"></span></label>
              Published
            </label>
            <label style="display:flex;align-items:center;gap:7px;text-transform:none;letter-spacing:0;font-size:.82rem;cursor:pointer">
              <label class="toggle"><input type="checkbox" name="is_featured" id="f_feat"><span class="toggle-slider"></span></label>
              Featured
            </label>
            <label style="display:flex;align-items:center;gap:7px;text-transform:none;letter-spacing:0;font-size:.82rem;cursor:pointer">
              <label class="toggle"><input type="checkbox" name="is_new" id="f_new"><span class="toggle-slider"></span></label>
              New badge
            </label>
          </div>
        </div>
      </div>

      <div class="flex gap-2 justify-between" style="padding-top:8px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-secondary btn-md" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-md" id="submitBtn">Add Track</button>
      </div>
    </form>
  </div>
</div>

<script>
const overlay = document.getElementById('overlay');

function openModal() {
  document.getElementById('modalHeading').textContent = 'Add Track';
  document.getElementById('submitBtn').textContent    = 'Add Track';
  document.getElementById('trackForm').reset();
  document.getElementById('f_id').value  = '0';
  document.getElementById('f_ec').value  = '';
  document.getElementById('f_ef').value  = '';
  document.getElementById('f_ef_label').textContent = '';
  document.getElementById('coverPreview').style.display = 'none';
  overlay.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function openEdit(t) {
  document.getElementById('modalHeading').textContent = 'Edit Track';
  document.getElementById('submitBtn').textContent    = 'Save Changes';
  document.getElementById('trackForm').reset();
  document.getElementById('f_id').value     = t.id;
  document.getElementById('f_title').value  = t.title;
  document.getElementById('f_artist').value = t.artist;
  document.getElementById('f_type').value   = t.type;
  document.getElementById('f_yt').value     = t.youtube_id;
  document.getElementById('f_dur').value    = t.duration;
  document.getElementById('f_sort').value   = t.sort_order;
  document.getElementById('f_color').value  = t.cover_color;
  document.getElementById('f_lyrics').value = t.lyrics;
  document.getElementById('f_pub').checked  = !!t.is_published;
  document.getElementById('f_feat').checked = !!t.is_featured;
  document.getElementById('f_new').checked  = !!t.is_new;
  document.getElementById('f_ec').value     = t.cover_image;
  document.getElementById('f_ef').value     = t.file_path;
  document.getElementById('f_ef_label').textContent = t.file_path ? '📎 ' + t.file_path : '';

  const prev = document.getElementById('coverPreview');
  if (t.cover_image) {
    prev.src = '/' + t.cover_image;
    prev.style.display = 'block';
  } else if (t.youtube_id) {
    prev.src = 'https://img.youtube.com/vi/' + t.youtube_id + '/mqdefault.jpg';
    prev.style.display = 'block';
  } else {
    prev.style.display = 'none';
  }

  overlay.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  overlay.style.display = 'none';
  document.body.style.overflow = '';
}

// Click outside = do nothing (intentional)
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' && overlay.style.display === 'flex') closeModal();
});

function previewCover(input) {
  if (input.files && input.files[0]) {
    const r = new FileReader();
    r.onload = function(e) {
      const p = document.getElementById('coverPreview');
      p.src = e.target.result;
      p.style.display = 'block';
    };
    r.readAsDataURL(input.files[0]);
  }
}

// Live YouTube thumbnail preview while typing YT ID
function ytThumbPreview(ytId) {
  const prev = document.getElementById('coverPreview');
  const coverFile = document.getElementById('f_ec').value;
  if (coverFile) return; // already has a cover image, don't override
  ytId = ytId.trim();
  if (ytId.length >= 11) {
    prev.src = 'https://img.youtube.com/vi/' + ytId + '/mqdefault.jpg';
    prev.style.display = 'block';
  } else {
    prev.style.display = 'none';
  }
}

function filterRows(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#tbl tbody tr[data-q]').forEach(function(row) {
    row.style.display = row.dataset.q.includes(q) ? '' : 'none';
  });
}
</script>

<?php include __DIR__ . '/_layout_end.php'; ?>