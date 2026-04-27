<?php
// ============================================================
//  includes/settings.php — Appearance helpers for front-end
// ============================================================

/**
 * Load all site_settings rows into a keyed array,
 * merged with sensible defaults.
 */
function getAppearanceSettings(PDO $db): array {
    $defaults = [
        // ── Identity ──────────────────────────────────────────
        'logo_text'              => 'RCL',
        'logo_dot_color'         => '#e50914',
        'logo_image'             => '',
        'site_tagline'           => 'Music. Blogs. Streams.',

        // ── Hero ──────────────────────────────────────────────
        'hero_title_line1'       => 'Feel Every Beat.',
        'hero_title_line2'       => 'Live Every Story.',
        'hero_desc'              => 'Songs, stories, and streams — all in one place.',

        // ── CTA ───────────────────────────────────────────────
        'cta_title_line1'        => 'Join the',
        'cta_title_line2'        => 'RCL Community.',
        'cta_sub'                => 'New drops, behind-the-scenes content, and early access to live streams — straight to your inbox.',

        // ── Footer ────────────────────────────────────────────
        'footer_tagline'         => 'Music. Blogs. Streams.<br>All from the heart.',

        // ── Socials ───────────────────────────────────────────
        'social_youtube'         => 'https://youtube.com/@9xbloc959',
        'social_spotify'         => '',
        'social_instagram'       => '',
        'social_tiktok'          => '',
        'social_youtube_label'   => 'YT',
        'social_spotify_label'   => 'SP',
        'social_instagram_label' => 'IG',
        'social_tiktok_label'    => 'TK',

        // ── Theme ─────────────────────────────────────────────
        'active_theme'           => 'dark',

        // ── Dark palette ──────────────────────────────────────
        'dark_bg'                => '#141414',
        'dark_bg2'               => '#1a1a1a',
        'dark_bg3'               => '#000000',
        'dark_card'              => '#1f1f1f',
        'dark_text'              => '#ffffff',
        'dark_muted'             => '#b3b3b3',
        'dark_accent'            => '#e50914',
        'dark_accent_dk'         => '#b20710',
        'dark_accent_lt'         => '#ff2b39',
        'dark_grad'              => 'linear-gradient(135deg, #e50914 0%, #7b2ff7 100%)',

        // ── Light palette ─────────────────────────────────────
        'light_bg'               => '#f5f5f5',
        'light_bg2'              => '#ebebeb',
        'light_bg3'              => '#ffffff',
        'light_card'             => '#ffffff',
        'light_text'             => '#0d0d0d',
        'light_muted'            => '#666666',
        'light_accent'           => '#e50914',
        'light_accent_dk'        => '#b20710',
        'light_accent_lt'        => '#ff2b39',
        'light_grad'             => 'linear-gradient(135deg, #e50914 0%, #7b2ff7 100%)',

        // ── Typography ────────────────────────────────────────
        'font_main'              => 'Inter',
        'font_custom_url'        => '',
    ];

    // Pull everything from DB
    try {
        $rows = $db->query('SELECT setting_key, setting_val FROM site_settings')->fetchAll(PDO::FETCH_ASSOC);
        $db_settings = array_column($rows, 'setting_val', 'setting_key');
    } catch (PDOException $e) {
        $db_settings = [];
    }

    // Merge: DB values win over defaults
    return array_merge($defaults, $db_settings);
}

/**
 * Emit a <style> block that injects the active theme's CSS variables
 * plus the chosen font, so every page picks up the admin's choices.
 */
function siteStyleTag(array $site): string {
    $theme = ($site['active_theme'] === 'light') ? 'light' : 'dark';

    $bg       = esc($site["{$theme}_bg"]);
    $bg2      = esc($site["{$theme}_bg2"]);
    $bg3      = esc($site["{$theme}_bg3"]);
    $card     = esc($site["{$theme}_card"]);
    $text     = esc($site["{$theme}_text"]);
    $muted    = esc($site["{$theme}_muted"]);
    $accent   = esc($site["{$theme}_accent"]);
    $accentDk = esc($site["{$theme}_accent_dk"]);
    $accentLt = esc($site["{$theme}_accent_lt"]);
    $grad     = esc($site["{$theme}_grad"]);

    $font      = esc($site['font_main'] ?: 'Inter');
    $customUrl = trim($site['font_custom_url'] ?? '');

    // Font import — use custom URL if set, else Google Fonts
    if ($customUrl) {
        $fontImport = '<link rel="stylesheet" href="' . esc($customUrl) . '">';
    } else {
        $gfSlug     = str_replace(' ', '+', $font);
        $fontImport = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family='
                    . $gfSlug
                    . ':wght@300;400;500;600;700;800;900&display=swap">';
    }

    return <<<HTML
{$fontImport}
<style>
:root {
  --bg:        {$bg};
  --bg2:       {$bg2};
  --bg3:       {$bg3};
  --card:      {$card};
  --text:      {$text};
  --muted:     {$muted};
  --accent:    {$accent};
  --accent-dk: {$accentDk};
  --accent-lt: {$accentLt};
  --grad:      {$grad};
  --ff:        '{$font}', 'Helvetica Neue', Arial, sans-serif;
}
body { font-family: var(--ff); }
</style>
HTML;
}

/**
 * Render the site logo (image or text) wrapped in an anchor.
 */
function renderLogo(array $site, string $href = 'index.php', string $class = 'logo'): string {
    $logoText  = htmlspecialchars($site['logo_text']      ?? 'RCL',      ENT_QUOTES, 'UTF-8');
    $dotColor  = htmlspecialchars($site['logo_dot_color'] ?? '#e50914',  ENT_QUOTES, 'UTF-8');
    $logoImage = trim($site['logo_image'] ?? '');
    $href      = htmlspecialchars($href,  ENT_QUOTES, 'UTF-8');
    $class     = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

    if ($logoImage) {
        $src = htmlspecialchars('/' . ltrim($logoImage, '/'), ENT_QUOTES, 'UTF-8');
        return <<<HTML
<a href="{$href}" class="{$class}">
  <img src="{$src}" alt="{$logoText}" style="height:36px;width:auto;display:block;">
</a>
HTML;
    }

    // Text logo: "RCL" in accent color + "." in dot color (customizable separately)
    return <<<HTML
<a href="{$href}" class="{$class}">
  {$logoText}<span style="color:{$dotColor}">.</span>
</a>
HTML;
}

/**
 * Render social icon links for footer / nav.
 * Only outputs links whose URL is set and non-empty.
 */
function renderSocials(array $site, string $class = 'social-link'): string {
    $platforms = [
        'youtube'   => ['label' => $site['social_youtube_label']   ?? 'YT', 'url' => $site['social_youtube']   ?? ''],
        'spotify'   => ['label' => $site['social_spotify_label']   ?? 'SP', 'url' => $site['social_spotify']   ?? ''],
        'instagram' => ['label' => $site['social_instagram_label'] ?? 'IG', 'url' => $site['social_instagram'] ?? ''],
        'tiktok'    => ['label' => $site['social_tiktok_label']    ?? 'TK', 'url' => $site['social_tiktok']    ?? ''],
    ];

    $out = '';
    foreach ($platforms as $p) {
        $url = trim($p['url']);
        if (!$url || $url === '#') continue;
        $label = htmlspecialchars($p['label'], ENT_QUOTES, 'UTF-8');
        $href  = htmlspecialchars($url,        ENT_QUOTES, 'UTF-8');
        $out  .= '<a href="' . $href . '" class="' . $class
               . '" target="_blank" rel="noopener" aria-label="' . $label . '">'
               . $label . '</a>' . "\n";
    }
    return $out;
}

/**
 * Save a single setting to the DB (upsert).
 * Use this from your admin panel when saving appearance changes.
 *
 * Example:
 *   saveSetting($db, 'dark_accent', '#ff6600');
 *   saveSetting($db, 'active_theme', 'light');
 */
function saveSetting(PDO $db, string $key, string $value): void {
    $stmt = $db->prepare(
        'INSERT INTO site_settings (setting_key, setting_val)
         VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_val = :v'
    );
    $stmt->execute([':k' => $key, ':v' => $value]);
}

/**
 * Save multiple settings at once.
 * Pass an associative array of key => value pairs.
 *
 * Example:
 *   saveSettings($db, $_POST['theme']); // where $_POST['theme'] is the form array
 */
function saveSettings(PDO $db, array $data): void {
    foreach ($data as $key => $value) {
        saveSetting($db, (string)$key, (string)$value);
    }
}

// ── Internal escape helper ────────────────────────────────────────────────────
// Safe to call even if config.php defines h() separately.
if (!function_exists('esc')) {
    function esc(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}