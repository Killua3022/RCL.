<?php
// subscribe.php — Handle newsletter signups from the homepage form
require_once __DIR__ . '/includes/config.php';
$d = db();

$email = trim($_POST['email'] ?? '');

if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Check if already subscribed
    $check = $d->prepare('SELECT id FROM subscribers WHERE email = ?');
    $check->execute([$email]);

    if (!$check->fetchColumn()) {
        $d->prepare('INSERT INTO subscribers (email, subscribed_at, is_active) VALUES (?, NOW(), 1)')
          ->execute([$email]);
    }
}

header('Location: index.php?subscribed=1');
exit;