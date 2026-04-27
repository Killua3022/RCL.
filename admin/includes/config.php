<?php
// ============================================================
//  includes/config.php — Database connection & global config
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'rcl_db');
define('DB_USER',    'root');   // change to your MySQL user
define('DB_PASS',    '');       // change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

define('ADMIN_SESSION_NAME', 'rcl_admin_session');
define('SITE_ROOT',   dirname(__DIR__) . '/');
define('UPLOAD_DIR',  SITE_ROOT . 'uploads/');
define('MAX_UPLOAD_MB', 50);
define('ALLOWED_IMG',   ['jpg','jpeg','png','gif','webp']);
define('ALLOWED_AUDIO', ['mp3','wav','ogg','m4a']);

// ── PDO singleton ────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Settings helper ──────────────────────────────────────────
function setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = db()->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll();
            $cache = array_column($rows, 'setting_val', 'setting_key');
        } catch (PDOException $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

// ── Admin auth check ─────────────────────────────────────────
function requireAdmin(): void {
    session_name(ADMIN_SESSION_NAME);
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

// ── Current admin ────────────────────────────────────────────
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

// ── Log admin action ─────────────────────────────────────────
function logAction(string $action, string $details = ''): void {
    // Wrapped in try/catch so a missing table never crashes the app
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $s = db()->prepare(
            'INSERT INTO admin_logs (admin_id, action, details, ip) VALUES (?,?,?,?)'
        );
        $s->execute([
            $_SESSION['admin_id'] ?? null,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (PDOException $e) {
        // Silently skip — table may not exist yet
        // Run create_tables.sql in phpMyAdmin to fix permanently
    }
}

// ── Safe file upload ─────────────────────────────────────────
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

// ── Analytics tracker ────────────────────────────────────────
function trackView(string $page, string $refType = '', int $refId = 0): void {
    try {
        $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d'));
        $s  = db()->prepare(
            'INSERT INTO analytics_pageviews (page, ref_type, ref_id, ip_hash, user_agent)
             VALUES (?,?,?,?,?)'
        );
        $s->execute([
            $page,
            $refType ?: null,
            $refId   ?: null,
            $ip,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (PDOException $e) {
        // Silently skip
    }
}

function trackEvent(string $type, string $refType = '', int $refId = 0, string $value = ''): void {
    try {
        $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d'));
        $s  = db()->prepare(
            'INSERT INTO analytics_events (event_type, ref_type, ref_id, value, ip_hash)
             VALUES (?,?,?,?,?)'
        );
        $s->execute([
            $type,
            $refType ?: null,
            $refId   ?: null,
            $value,
            $ip,
        ]);
    } catch (PDOException $e) {
        // Silently skip
    }
}

// ── Helpers ──────────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function slug(string $s): string {
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
}

function numFmt(int $n): string {
    if ($n >= 1_000_000) return round($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000)     return round($n / 1_000, 1) . 'k';
    return (string)$n;
}