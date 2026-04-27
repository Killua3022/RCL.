<?php
define('DB_HOST',    $_ENV['DB_HOST']    ?? getenv('DB_HOST')    ?: 'aws-0-ap-southeast-1.pooler.supabase.com');
define('DB_NAME',    $_ENV['DB_NAME']    ?? getenv('DB_NAME')    ?: 'postgres');
define('DB_USER',    $_ENV['DB_USER']    ?? getenv('DB_USER')    ?: 'postgres.itcondmcocvtlyvsbyit');
define('DB_PASS',    $_ENV['DB_PASS']    ?? getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8');

define('ADMIN_SESSION_NAME', 'rcl_admin_session');
define('SITE_ROOT',   dirname(__DIR__) . '/');
define('UPLOAD_DIR',  SITE_ROOT . 'uploads/');
define('MAX_UPLOAD_MB', 50);
define('ALLOWED_IMG',   ['jpg','jpeg','png','gif','webp']);
define('ALLOWED_AUDIO', ['mp3','wav','ogg','m4a']);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'pgsql:host='.DB_HOST.';port=6543;dbname='.DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = db()->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll();
            $cache = array_column($rows, 'setting_val', 'setting_key');
        } catch (PDOException $e) { $cache = []; }
    }
    return $cache[$key] ?? $default;
}

function requireAdmin(): void {
    session_name(ADMIN_SESSION_NAME);
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin/login'); exit;
    }
}

function currentAdmin(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_id'])) return null;
    static $a = null;
    if (!$a) {
        $s = db()->prepare('SELECT * FROM admin_users WHERE id=?');
        $s->execute([$_SESSION['admin_id']]);
        $a = $s->fetch() ?: null;
    }
    return $a;
}

function logAction(string $action, string $details = ''): void {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $s = db()->prepare('INSERT INTO admin_logs (admin_id, action, details, ip) VALUES (?,?,?,?)');
        $s->execute([$_SESSION['admin_id'] ?? null, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (PDOException $e) {}
}

function uploadFile(array $file, string $subdir, array $allowed): string|false {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return false;
    if ($file['size'] > MAX_UPLOAD_MB * 1024 * 1024) return false;
    $dir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return false;
    $name = uniqid('rcl_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) return false;
    return 'uploads/' . $subdir . '/' . $name;
}

function trackView(string $page, string $refType = '', int $refId = 0): void {
    try {
        $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d'));
        $s  = db()->prepare('INSERT INTO analytics_pageviews (page, ref_type, ref_id, ip_hash, user_agent) VALUES (?,?,?,?,?)');
        $s->execute([$page, $refType ?: null, $refId ?: null, $ip, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    } catch (PDOException $e) {}
}

function trackEvent(string $type, string $refType = '', int $refId = 0, string $value = ''): void {
    try {
        $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d'));
        $s  = db()->prepare('INSERT INTO analytics_events (event_type, ref_type, ref_id, value, ip_hash) VALUES (?,?,?,?,?)');
        $s->execute([$type, $refType ?: null, $refId ?: null, $value, $ip]);
    } catch (PDOException $e) {}
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function slug(string $s): string { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-'); }
function numFmt(int $n): string {
    if ($n >= 1_000_000) return round($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000)     return round($n / 1_000, 1) . 'k';
    return (string)$n;
}
