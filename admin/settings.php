<?php
// admin/settings.php — General Site Settings
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Settings';
include __DIR__ . '/_layout.php';

$d = db(); $msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['form_type'] ?? '';

    if ($type === 'password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $con = $_POST['confirm_password'] ?? '';
        $admin = currentAdmin();
        if (!password_verify($cur, $admin['password'])) {
            $msg = 'ERROR:Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $msg = 'ERROR:New password must be at least 8 characters.';
        } elseif ($new !== $con) {
            $msg = 'ERROR:Passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
            $d->prepare('UPDATE admin_users SET password=? WHERE id=?')->execute([$hash,$_SESSION['admin_id']]);
            logAction('Changed own password');
            $msg = 'Password updated successfully.';
        }
    }

    if ($type === 'profile') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        if ($username && $email) {
            $d->prepare('UPDATE admin_users SET username=?,email=? WHERE id=?')->execute([$username,$email,$_SESSION['admin_id']]);
            $_SESSION['admin_name'] = $username;
            logAction('Updated profile');
            $msg = 'Profile updated.';
        }
    }

    if (str_starts_with($msg, 'ERROR:')) {
        $err = substr($msg, 6); $msg = '';
    }

    header('Location: settings.php?msg='.urlencode($msg ?? ''));
    exit;
}

if (!empty($_GET['msg'])) $msg = h($_GET['msg']);
$admin = currentAdmin();
$err = '';
?>

<?php if ($msg): ?><div class="alert alert-success">✓ <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">⚠ <?= h($err) ?></div><?php endif; ?>

<!-- Profile -->
<div class="card mb-6">
  <div class="card-head"><h3>👤 My Profile</h3></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="form_type" value="profile">
      <div class="form-grid">
        <div class="field"><label>Username</label><input type="text" name="username" value="<?= h($admin['username']??'') ?>" required></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="<?= h($admin['email']??'') ?>" required></div>
      </div>
      <button type="submit" class="btn btn-md btn-primary mt-4">Save Profile</button>
    </form>
  </div>
</div>

<!-- Change Password -->
<div class="card mb-6">
  <div class="card-head"><h3>🔒 Change Password</h3></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="form_type" value="password">
      <div class="form-grid">
        <div class="field full"><label>Current Password</label><input type="password" name="current_password" required></div>
        <div class="field"><label>New Password (min 8 chars)</label><input type="password" name="new_password" required></div>
        <div class="field"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
      </div>
      <button type="submit" class="btn btn-md btn-primary mt-4">Update Password</button>
    </form>
  </div>
</div>

<!-- DB Info -->
<div class="card mb-6">
  <div class="card-head"><h3>🗄️ Database Info</h3></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="field"><label>Host</label><input type="text" value="<?= h(DB_HOST) ?>" disabled></div>
      <div class="field"><label>Database</label><input type="text" value="<?= h(DB_NAME) ?>" disabled></div>
    </div>
    <div class="alert alert-info mt-4" style="margin-top:16px">
      ℹ️ To manage your database directly, open <strong>phpMyAdmin</strong> and select the <code><?= h(DB_NAME) ?></code> database. Connection settings are in <code>includes/config.php</code>.
    </div>
  </div>
</div>

<?php include __DIR__ . '/_layout_end.php'; ?>