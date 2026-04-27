<?php
// admin/login.php
require_once __DIR__ . '/../includes/config.php';

session_name(ADMIN_SESSION_NAME);
session_start();

// Already logged in → dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $s = db()->prepare('SELECT * FROM admin_users WHERE (username=? OR email=?) AND is_active=1');
        $s->execute([$username, $username]);
        $admin = $s->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // Update last login
            db()->prepare('UPDATE admin_users SET last_login=NOW(), login_ip=? WHERE id=?')
                ->execute([$_SERVER['REMOTE_ADDR'] ?? '', $admin['id']]);

            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

$siteTitle = 'RCL Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — RCL</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--accent:#e50914;--bg:#0a0a0a;--card:#151515;--border:rgba(255,255,255,0.08);--text:#fff;--muted:#888;--ff:'Inter',sans-serif}
body{font-family:var(--ff);background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-wrap{width:100%;max-width:420px}
.login-logo{text-align:center;margin-bottom:2.5rem}
.login-logo span{font-size:2.5rem;font-weight:900;color:var(--accent);letter-spacing:-.04em;text-transform:uppercase}
.login-logo span em{color:#fff;font-style:normal}
.login-logo p{font-size:.75rem;color:var(--muted);letter-spacing:.12em;text-transform:uppercase;margin-top:4px}
.card{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:2.5rem}
h2{font-size:1.4rem;font-weight:800;margin-bottom:.4rem;letter-spacing:-.02em}
.sub{font-size:.82rem;color:var(--muted);margin-bottom:2rem}
.field{margin-bottom:1.25rem}
label{display:block;font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:7px}
input{width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:6px;color:#fff;font-family:var(--ff);font-size:.9rem;padding:11px 14px;outline:none;transition:.2s}
input:focus{border-color:var(--accent);background:rgba(229,9,20,.05)}
.btn{width:100%;background:var(--accent);border:none;border-radius:6px;color:#fff;font-family:var(--ff);font-size:.88rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:13px;cursor:pointer;transition:.2s;margin-top:.5rem}
.btn:hover{background:#ff2b39;transform:translateY(-1px)}
.error{background:rgba(229,9,20,.12);border:1px solid rgba(229,9,20,.3);border-radius:6px;padding:10px 14px;font-size:.82rem;color:#ff6b6b;margin-bottom:1.25rem}
.hint{text-align:center;font-size:.72rem;color:var(--muted);margin-top:1.5rem}
.hint code{color:var(--accent);background:rgba(229,9,20,.1);padding:2px 6px;border-radius:3px;font-size:.7rem}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-logo">
    <div><span>RCL<em>.</em></span></div>
    <p>Admin Panel</p>
  </div>
  <div class="card">
    <h2>Sign In</h2>
    <p class="sub">Access restricted to authorized administrators only.</p>
    <?php if ($error): ?>
    <div class="error">⚠ <?= h($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="field">
        <label>Username or Email</label>
        <input type="text" name="username" value="<?= h($_POST['username'] ?? '') ?>" autocomplete="username" required>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn">Sign In →</button>
    </form>
  </div>
  <p class="hint">Default: <code>admin</code> / <code>Admin@1234</code> — change after first login</p>
</div>
</body>
</html>