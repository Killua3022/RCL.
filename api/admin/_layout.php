<?php
// admin/_layout.php — shared header + sidebar
// Usage: include __DIR__.'/_layout.php'; at top of each admin page
// Requires $pageTitle to be set before including

requireAdmin();
$admin = currentAdmin();
$cur   = basename($_SERVER['PHP_SELF']);

$navItems = [
  ['icon'=>'bx bxs-dashboard',      'label'=>'Dashboard',    'file'=>'index.php',        'group'=>'main'],
  ['icon'=>'bx bxs-music',          'label'=>'Music',        'file'=>'tracks.php',       'group'=>'content'],
  ['icon'=>'bx bxs-videos',         'label'=>'Videos',       'file'=>'videos.php',       'group'=>'content'],
  ['icon'=>'bx bxs-edit-alt',       'label'=>'Blog Posts',   'file'=>'posts.php',        'group'=>'content'],
  ['icon'=>'bx bxs-user-detail',    'label'=>'Subscribers',  'file'=>'subscribers.php',  'group'=>'content'],
  ['icon'=>'bx bx-line-chart',      'label'=>'Analytics',    'file'=>'analytics.php',    'group'=>'analytics'],
  ['icon'=>'bx bxs-dollar-circle',  'label'=>'Monetization', 'file'=>'monetization.php', 'group'=>'monetization'],
  ['icon'=>'bx bxs-palette',        'label'=>'Appearance',   'file'=>'appearance.php',   'group'=>'settings'],
  ['icon'=>'bx bxs-cog',            'label'=>'Settings',     'file'=>'settings.php',     'group'=>'settings'],
  ['icon'=>'bx bxs-group',          'label'=>'Admin Users',  'file'=>'users.php',        'group'=>'settings'],
  ['icon'=>'bx bx-history',         'label'=>'Activity Log', 'file'=>'logs.php',         'group'=>'settings'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'Admin') ?> — RCL Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --accent:#e50914;--accent-dk:#b20710;--accent-lt:#ff2b39;
  --bg:#0d0d0d;--bg2:#111;--sidebar:#0a0a0a;--card:#161616;--card2:#1a1a1a;
  --border:rgba(255,255,255,0.07);--border2:rgba(255,255,255,0.13);
  --text:#fff;--muted:#777;--dim:rgba(255,255,255,0.04);
  --ff:'Inter',sans-serif;--radius:8px;--t:.18s;
  --sidebar-w:230px;--topbar-h:60px;
}
body{font-family:var(--ff);background:var(--bg);color:var(--text);min-height:100vh;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}

/* ── TOPBAR ── */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);z-index:100;background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px;gap:16px}
.topbar-title{font-size:1rem;font-weight:700;flex:1}
.topbar-meta{display:flex;align-items:center;gap:12px}
.admin-pill{display:flex;align-items:center;gap:8px;background:var(--card2);border:1px solid var(--border);border-radius:20px;padding:5px 12px 5px 5px;font-size:.75rem;font-weight:600}
.admin-av{width:28px;height:28px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#fff}
.topbar-btn{display:inline-flex;align-items:center;gap:6px;font-size:.73rem;font-weight:600;color:var(--muted);background:var(--card);border:1px solid var(--border);border-radius:6px;padding:6px 12px;cursor:pointer;transition:var(--t);white-space:nowrap}
.topbar-btn:hover{color:var(--text);border-color:var(--border2)}

/* ── SIDEBAR ── */
.sidebar{position:fixed;top:0;left:0;bottom:0;width:var(--sidebar-w);background:var(--sidebar);border-right:1px solid var(--border);z-index:200;display:flex;flex-direction:column;overflow-y:auto}
.sidebar-logo{padding:18px 20px;border-bottom:1px solid var(--border);flex-shrink:0}
.sidebar-logo a{font-size:1.5rem;font-weight:900;color:var(--accent);letter-spacing:-.04em;text-transform:uppercase}
.sidebar-logo a em{color:#fff;font-style:normal}
.sidebar-logo span{display:block;font-size:.58rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-top:1px}
.sidebar-nav{flex:1;padding:12px 0}
.nav-group-label{font-size:.55rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);padding:16px 20px 5px;opacity:.6}
.nav-group-label:first-child{padding-top:8px}
.nav-item{display:flex;align-items:center;gap:10px;padding:8px 18px;margin:1px 8px;border-radius:6px;font-size:.8rem;font-weight:500;color:rgba(255,255,255,.55);cursor:pointer;transition:var(--t);white-space:nowrap}
.nav-item:hover{color:var(--text);background:var(--dim)}
.nav-item.active{color:var(--text);background:rgba(229,9,20,.12);font-weight:700}
.nav-item.active .nav-icon{color:var(--accent)}
.nav-icon{font-size:1rem;flex-shrink:0;width:20px;text-align:center}
.sidebar-footer{padding:12px 14px;border-top:1px solid var(--border)}
.logout-btn{display:flex;align-items:center;gap:8px;width:100%;padding:8px 10px;border-radius:6px;font-size:.78rem;font-weight:500;color:rgba(255,255,255,.4);background:none;border:none;cursor:pointer;transition:var(--t)}
.logout-btn:hover{color:#ff6b6b;background:rgba(229,9,20,.07)}

/* ── MAIN ── */
.main{margin-left:var(--sidebar-w);padding-top:var(--topbar-h);min-height:100vh}
.page{padding:28px 28px;max-width:1400px}

/* ── CARDS ── */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius)}
.card-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.card-head h3{font-size:.9rem;font-weight:700;flex:1}
.card-body{padding:20px}

/* ── STAT CARDS ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;transition:var(--t)}
.stat-card:hover{border-color:var(--border2)}
.stat-icon{font-size:1.5rem;margin-bottom:10px}
.stat-val{font-size:1.9rem;font-weight:800;letter-spacing:-.04em;line-height:1}
.stat-label{font-size:.65rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-top:4px}
.stat-delta{font-size:.7rem;font-weight:600;margin-top:6px}
.stat-delta.up{color:#4ade80}
.stat-delta.dn{color:#f87171}

/* ── TABLES ── */
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.82rem}
thead th{padding:10px 14px;text-align:left;font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);white-space:nowrap}
tbody td{padding:11px 14px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle}
tbody tr:hover td{background:rgba(255,255,255,.02)}
tbody tr:last-child td{border-bottom:none}

/* ── BADGES / PILLS ── */
.badge{display:inline-block;font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:3px 8px;border-radius:3px}
.badge-green{background:rgba(74,222,128,.12);color:#4ade80}
.badge-red{background:rgba(248,113,113,.12);color:#f87171}
.badge-yellow{background:rgba(251,191,36,.12);color:#fbbf24}
.badge-blue{background:rgba(96,165,250,.12);color:#60a5fa}
.badge-gray{background:rgba(255,255,255,.06);color:var(--muted)}
.badge-accent{background:rgba(229,9,20,.15);color:var(--accent)}

/* ── FORMS ── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-grid.full{grid-template-columns:1fr}
.field{display:flex;flex-direction:column;gap:7px}
.field.full{grid-column:span 2}
label,.field-label{font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)}
input[type=text],input[type=email],input[type=password],input[type=number],input[type=url],select,textarea{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:6px;color:var(--text);font-family:var(--ff);font-size:.88rem;padding:10px 13px;outline:none;transition:var(--t);width:100%}
input:focus,select:focus,textarea:focus{border-color:var(--accent);background:rgba(229,9,20,.04)}
select option{background:#1a1a1a;color:#fff}
textarea{resize:vertical;min-height:100px}
.form-hint{font-size:.68rem;color:var(--muted);margin-top:2px}

/* ── BUTTONS ── */
.btn{display:inline-flex;align-items:center;gap:6px;border:none;border-radius:6px;cursor:pointer;font-family:var(--ff);font-weight:700;letter-spacing:.04em;text-transform:uppercase;transition:var(--t);white-space:nowrap;text-decoration:none}
.btn-sm{font-size:.68rem;padding:6px 12px}
.btn-md{font-size:.78rem;padding:9px 18px}
.btn-lg{font-size:.85rem;padding:11px 24px}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{background:var(--accent-lt);transform:translateY(-1px)}
.btn-secondary{background:rgba(255,255,255,.08);color:var(--text);border:1px solid var(--border2)}
.btn-secondary:hover{background:rgba(255,255,255,.13)}
.btn-danger{background:rgba(248,113,113,.15);color:#f87171;border:1px solid rgba(248,113,113,.2)}
.btn-danger:hover{background:rgba(248,113,113,.25)}
.btn-success{background:rgba(74,222,128,.12);color:#4ade80}
.btn-success:hover{background:rgba(74,222,128,.2)}

/* ── ALERTS ── */
.alert{padding:12px 16px;border-radius:6px;font-size:.82rem;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px}
.alert-success{background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.25);color:#4ade80}
.alert-error{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);color:#f87171}
.alert-info{background:rgba(96,165,250,.1);border:1px solid rgba(96,165,250,.25);color:#60a5fa}

/* ── PAGINATION ── */
.pagination{display:flex;align-items:center;gap:5px;margin-top:16px;flex-wrap:wrap}
.page-link{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:5px;font-size:.78rem;font-weight:600;background:var(--card);border:1px solid var(--border);color:var(--muted);transition:var(--t)}
.page-link:hover{color:var(--text);border-color:var(--border2)}
.page-link.active{background:var(--accent);color:#fff;border-color:var(--accent)}

/* ── TOGGLES ── */
.toggle{position:relative;display:inline-block;width:42px;height:22px}
.toggle input{display:none}
.toggle-slider{position:absolute;inset:0;background:rgba(255,255,255,.1);border-radius:11px;cursor:pointer;transition:.25s}
.toggle input:checked+.toggle-slider{background:var(--accent)}
.toggle-slider::after{content:'';position:absolute;left:2px;top:2px;width:18px;height:18px;background:#fff;border-radius:50%;transition:.25s}
.toggle input:checked+.toggle-slider::after{transform:translateX(20px)}

/* ── CHART PLACEHOLDER ── */
.chart-area{height:220px;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.8rem;border:1px dashed var(--border);border-radius:6px;background:var(--dim)}

/* ── RESPONSIVE ── */
@media(max-width:900px){
  :root{--sidebar-w:0px}
  .sidebar{transform:translateX(-230px);width:230px;transition:.3s}
  .sidebar.open{transform:translateX(0)}
  .topbar{left:0}
  .topbar-menu-btn{display:flex!important}
}
.topbar-menu-btn{display:none;background:none;border:none;color:var(--text);font-size:1.3rem;cursor:pointer;padding:4px 8px;margin-right:4px}

/* ── MISC ── */
.flex{display:flex}.gap-2{gap:8px}.gap-3{gap:12px}.items-center{align-items:center}.justify-between{justify-content:space-between}.flex-wrap{flex-wrap:wrap}
.mb-4{margin-bottom:16px}.mb-6{margin-bottom:24px}.mt-4{margin-top:16px}
.text-muted{color:var(--muted)}.text-accent{color:var(--accent)}.text-sm{font-size:.78rem}.text-xs{font-size:.68rem}
.fw-bold{font-weight:700}.fw-800{font-weight:800}
.truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px}
.color-dot{display:inline-block;width:12px;height:12px;border-radius:50%;flex-shrink:0}
.search-bar{display:flex;align-items:center;gap:8px;background:var(--card2);border:1px solid var(--border);border-radius:6px;padding:7px 12px}
.search-bar input{background:none;border:none;color:var(--text);font-size:.82rem;outline:none;flex:1;min-width:0}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <a href="/admin/dashboard">RCL<em>.</em></a>
    <span>Admin Panel</span>
  </div>
  <nav class="sidebar-nav">
    <?php
    $groups = ['main'=>'Main','content'=>'Content','analytics'=>'Analytics','monetization'=>'Monetization','settings'=>'Settings'];
    $lastGroup = '';
    foreach ($navItems as $item):
      if ($item['group'] !== $lastGroup): $lastGroup = $item['group']; ?>
        <div class="nav-group-label"><?= $groups[$item['group']] ?></div>
      <?php endif; ?>
      <a href="<?= $item['file'] ?>" class="nav-item <?= $cur===$item['file']?'active':'' ?>">
  <i class="nav-icon <?= $item['icon'] ?>"></i>
  <?= h($item['label']) ?>
</a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="<?= str_replace('admin/','',dirname($_SERVER['PHP_SELF'])) ?>../index.php" target="_blank" class="logout-btn" style="margin-bottom:4px">
      <span>🌐</span> View Site
    </a>
    <a href="logout.php" class="logout-btn">
      <span></span> Sign Out
    </a>
  </div>
</aside>

<!-- TOPBAR -->
<header class="topbar">
  <button class="topbar-menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
  <div class="topbar-title"><?= h($pageTitle ?? '') ?></div>
  <div class="topbar-meta">
    <a href="<?= dirname(dirname($_SERVER['PHP_SELF'])) ?>/index.php" target="_blank" class="topbar-btn">🌐 View Site</a>
    <div class="admin-pill">
      <div class="admin-av"><?= strtoupper(substr($admin['username']??'A',0,1)) ?></div>
      <span><?= h($admin['username']??'Admin') ?></span>
    </div>
  </div>
</header>

<!-- MAIN CONTENT STARTS -->
<main class="main">
<div class="page">