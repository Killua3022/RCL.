<?php
// admin/monetization.php — Ads & Monetization
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Monetization';
include __DIR__ . '/_layout.php';

$d   = db();
$msg = '';

// ── Save ad zone ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['form_type'] ?? '';

    if ($type === 'global') {
        $keys = ['adsense_header','adsense_sidebar','adsense_footer','monetization_on','analytics_id'];
        foreach ($keys as $k) {
            $val = $_POST[$k] ?? '';
            $d->prepare('UPDATE site_settings SET setting_val=? WHERE setting_key=?')->execute([$val,$k]);
        }
        logAction('Updated monetization settings');
        $msg = 'Global monetization settings saved.';
    }

    if ($type === 'zone') {
        $zid     = (int)($_POST['zone_id'] ?? 0);
        $code    = $_POST['ad_code'] ?? '';
        $active  = (int)isset($_POST['is_active']);
        $d->prepare('UPDATE ad_zones SET ad_code=?, is_active=? WHERE id=?')->execute([$code,$active,$zid]);
        logAction('Updated ad zone', "ID:$zid");
        $msg = 'Ad zone saved.';
    }

    header('Location: /admin/monetization?msg='.urlencode($msg));
    exit;
}

if (!empty($_GET['msg'])) $msg = h($_GET['msg']);

// Load zones
$zones = $d->query('SELECT * FROM ad_zones ORDER BY id')->fetchAll();

// Totals
$totalImp   = (int)$d->query('SELECT SUM(impressions) FROM ad_zones')->fetchColumn();
$totalClicks= (int)$d->query('SELECT SUM(clicks) FROM ad_zones')->fetchColumn();
$ctr        = $totalImp ? round($totalClicks/$totalImp*100, 2) : 0;

$monetOn = setting('monetization_on','1');
?>

<?php if ($msg): ?><div class="alert alert-success">✓ <?= $msg ?></div><?php endif; ?>

<!-- Stats -->
<div class="stats-grid mb-6">
  <div class="stat-card"><div class="stat-icon">📢</div><div class="stat-val"><?= number_format($totalImp) ?></div><div class="stat-label">Ad Impressions</div></div>
  <div class="stat-card"><div class="stat-icon">🖱️</div><div class="stat-val"><?= number_format($totalClicks) ?></div><div class="stat-label">Ad Clicks</div></div>
  <div class="stat-card"><div class="stat-icon">📊</div><div class="stat-val"><?= $ctr ?>%</div><div class="stat-label">Click-Through Rate</div></div>
  <div class="stat-card"><div class="stat-icon"><?= $monetOn?'✅':'❌' ?></div><div class="stat-val" style="font-size:1.2rem"><?= $monetOn?'Active':'Off' ?></div><div class="stat-label">Monetization</div></div>
</div>

<!-- Global Settings -->
<div class="card mb-6">
  <div class="card-head"><h3>⚙️ Global Ad Settings</h3></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="form_type" value="global">
      <div class="form-grid">
        <div class="field full">
          <label>Google Analytics / Tag Manager ID</label>
          <input type="text" name="analytics_id" value="<?= h(setting('analytics_id')) ?>" placeholder="G-XXXXXXXXXX or GTM-XXXXXXX">
          <span class="form-hint">Paste your GA4 Measurement ID or GTM Container ID. Leave blank to disable.</span>
        </div>
        <div class="field full">
          <label>AdSense Header Code (global &lt;head&gt; script)</label>
          <textarea name="adsense_header" rows="4" style="font-family:monospace;font-size:.78rem"><?= h(setting('adsense_header')) ?></textarea>
          <span class="form-hint">Paste your AdSense &lt;script&gt; tag here. Injected into every page &lt;head&gt;.</span>
        </div>
        <div class="field">
          <label>Sidebar Ad Code</label>
          <textarea name="adsense_sidebar" rows="4" style="font-family:monospace;font-size:.78rem"><?= h(setting('adsense_sidebar')) ?></textarea>
        </div>
        <div class="field">
          <label>Footer Ad Code</label>
          <textarea name="adsense_footer" rows="4" style="font-family:monospace;font-size:.78rem"><?= h(setting('adsense_footer')) ?></textarea>
        </div>
        <div class="field">
          <label>Monetization Enabled</label>
          <select name="monetization_on">
            <option value="1" <?= $monetOn==='1'?'selected':'' ?>>Yes — Show ads</option>
            <option value="0" <?= $monetOn!=='1'?'selected':'' ?>>No — Hide all ads</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-lg btn-primary mt-4">💾 Save Settings</button>
    </form>
  </div>
</div>

<!-- Ad Zones -->
<div class="card mb-6">
  <div class="card-head"><h3>📍 Ad Zones</h3></div>
  <div class="card-body" style="padding:0">
    <?php foreach ($zones as $z): ?>
    <details style="border-bottom:1px solid var(--border)">
      <summary style="padding:14px 20px;cursor:pointer;display:flex;align-items:center;gap:10px;list-style:none;user-select:none">
        <span style="flex:1;font-weight:700"><?= h($z['zone_name']) ?></span>
        <span class="badge <?= $z['is_active']?'badge-green':'badge-gray' ?>"><?= $z['is_active']?'Active':'Disabled' ?></span>
        <span class="text-xs text-muted"><?= number_format($z['impressions']) ?> imp · <?= number_format($z['clicks']) ?> clicks</span>
        <span style="color:var(--muted)">▾</span>
      </summary>
      <div style="padding:16px 20px;background:var(--bg2)">
        <form method="POST">
          <input type="hidden" name="form_type" value="zone">
          <input type="hidden" name="zone_id" value="<?= $z['id'] ?>">
          <div class="field mb-4">
            <label>Ad Code for "<?= h($z['zone_name']) ?>"</label>
            <textarea name="ad_code" rows="5" style="font-family:monospace;font-size:.78rem"><?= h($z['ad_code'] ?? '') ?></textarea>
            <span class="form-hint">Paste AdSense &lt;ins&gt; block or any HTML ad code. Leave blank to show nothing here.</span>
          </div>
          <label class="flex items-center gap-2 text-sm mb-4"><input type="checkbox" name="is_active" <?= $z['is_active']?'checked':'' ?>> Zone Active</label>
          <button type="submit" class="btn btn-md btn-primary">💾 Save Zone</button>
        </form>
      </div>
    </details>
    <?php endforeach; ?>
  </div>
</div>

<!-- Monetization Tips -->
<div class="card">
  <div class="card-head"><h3>💡 Monetization Tips</h3></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">
      <?php
      $tips = [
        ['💰','Google AdSense','Apply at adsense.google.com. Once approved, paste your &lt;script&gt; code in the Header Code field above.'],
        ['🎵','Spotify for Artists','Upload your tracks to Spotify via DistroKid or TuneCore. Add the Spotify link in each track\'s Stream URL field.'],
        ['📺','YouTube Partner','Enable monetization on your YouTube channel at youtube.com/monetization.'],
        ['💌','Email Sponsorships','Use your subscriber list for sponsored newsletter drops. Export subscribers below.'],
        ['🛒','Merch & Links','Add affiliate or merch links in your blog posts or nav items via Settings → Navigation.'],
        ['🎁','Fan Support','Add a Patreon or Ko-fi link in your social links in Appearance settings.'],
      ];
      foreach ($tips as [$icon,$title,$desc]):
      ?>
      <div style="background:var(--card2);border:1px solid var(--border);border-radius:6px;padding:16px">
        <div style="font-size:1.5rem;margin-bottom:8px"><?= $icon ?></div>
        <div class="fw-bold text-sm mb-4"><?= $title ?></div>
        <div class="text-xs text-muted" style="line-height:1.6"><?= $desc ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/_layout_end.php'; ?>