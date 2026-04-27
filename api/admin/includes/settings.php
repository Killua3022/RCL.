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

        // ── Component palette — Nav ───────────────────────────
        // Universal: not affected by light/dark theme switching
        'comp_nav_bg'            => 'rgba(0,0,0,0.9)',
        'comp_nav_bg_scrolled'   => '#1a1a1a',
        'comp_nav_text'          => '#ffffff',
        'comp_nav_muted'         => '#b3b3b3',
        'comp_nav_accent'        => '#e50914',
        'comp_nav_border'        => 'rgba(255,255,255,0.07)',
        'comp_nav_mobile_bg'     => 'rgba(0,0,0,0.98)',

        // ── Component palette — Mini Player ───────────────────
        'comp_player_bg'         => '#1f1f1f',
        'comp_player_border'     => 'rgba(255,255,255,0.1)',
        'comp_player_text'       => '#ffffff',
        'comp_player_muted'      => '#b3b3b3',
        'comp_player_accent'     => '#e50914',
        'comp_player_bar_bg'     => 'rgba(255,255,255,0.1)',
        'comp_player_shadow'     => 'rgba(0,0,0,0.8)',

        // ── Component palette — Footer ─────────────────────────
        'comp_footer_bg'         => '#000000',
        'comp_footer_border'     => 'rgba(255,255,255,0.07)',
        'comp_footer_text'       => '#ffffff',
        'comp_footer_muted'      => '#b3b3b3',
        'comp_footer_accent'     => '#e50914',
        'comp_footer_link'       => 'rgba(255,255,255,0.45)',

        // ── Component palette — Hero ──────────────────────────
        'comp_hero_vignette_l'   => 'rgba(0,0,0,0.82)',
        'comp_hero_vignette_r'   => 'rgba(0,0,0,0.1)',
        'comp_hero_vignette_b'   => 'rgba(0,0,0,0.95)',
        'comp_hero_vignette_t'   => 'rgba(0,0,0,0.65)',
        'comp_hero_text'         => '#ffffff',
        'comp_hero_desc'         => 'rgba(255,255,255,0.7)',
        'comp_hero_stat_label'   => 'rgba(255,255,255,0.55)',
        'comp_hero_divider'      => 'rgba(255,255,255,0.15)',
        'comp_hero_scroll'       => 'rgba(255,255,255,0.5)',
        'comp_hero_accent'       => '#e50914',

        // ── Component palette — Ticker ────────────────────────
        'comp_ticker_bg'         => '#e50914',
        'comp_ticker_text'       => '#ffffff',
        'comp_ticker_sep'        => 'rgba(255,255,255,0.4)',
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
 * Emit a <style> block that injects:
 *  1. The active theme's CSS variables (--bg, --text, --accent, etc.)
 *  2. The universal component palette (--c-nav-*, --c-player-*, etc.)
 *  3. The chosen font import + --ff variable
 *
 * Component variables are INDEPENDENT of the active theme, so nav/player/
 * footer/hero/ticker always use their own saved colors.
 */
function siteStyleTag(array $site): string {
    $theme = ($site['active_theme'] === 'light') ? 'light' : 'dark';

    // ── Theme palette ─────────────────────────────────────────────────────
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

    // ── Component palette — Nav ───────────────────────────────────────────
    $cNavBg         = esc($site['comp_nav_bg']          ?? 'rgba(0,0,0,0.9)');
    $cNavBgScrolled = esc($site['comp_nav_bg_scrolled'] ?? '#1a1a1a');
    $cNavText       = esc($site['comp_nav_text']         ?? '#ffffff');
    $cNavMuted      = esc($site['comp_nav_muted']        ?? '#b3b3b3');
    $cNavAccent     = esc($site['comp_nav_accent']       ?? '#e50914');
    $cNavBorder     = esc($site['comp_nav_border']       ?? 'rgba(255,255,255,0.07)');
    $cNavMobileBg   = esc($site['comp_nav_mobile_bg']    ?? 'rgba(0,0,0,0.98)');

    // ── Component palette — Mini Player ──────────────────────────────────
    $cPlayerBg     = esc($site['comp_player_bg']      ?? '#1f1f1f');
    $cPlayerBorder = esc($site['comp_player_border']  ?? 'rgba(255,255,255,0.1)');
    $cPlayerText   = esc($site['comp_player_text']    ?? '#ffffff');
    $cPlayerMuted  = esc($site['comp_player_muted']   ?? '#b3b3b3');
    $cPlayerAccent = esc($site['comp_player_accent']  ?? '#e50914');
    $cPlayerBarBg  = esc($site['comp_player_bar_bg']  ?? 'rgba(255,255,255,0.1)');
    $cPlayerShadow = esc($site['comp_player_shadow']  ?? 'rgba(0,0,0,0.8)');

    // ── Component palette — Footer ────────────────────────────────────────
    $cFooterBg     = esc($site['comp_footer_bg']     ?? '#000000');
    $cFooterBorder = esc($site['comp_footer_border'] ?? 'rgba(255,255,255,0.07)');
    $cFooterText   = esc($site['comp_footer_text']   ?? '#ffffff');
    $cFooterMuted  = esc($site['comp_footer_muted']  ?? '#b3b3b3');
    $cFooterAccent = esc($site['comp_footer_accent'] ?? '#e50914');
    $cFooterLink   = esc($site['comp_footer_link']   ?? 'rgba(255,255,255,0.45)');

    // ── Component palette — Hero ──────────────────────────────────────────
    $cHeroVigL      = esc($site['comp_hero_vignette_l']  ?? 'rgba(0,0,0,0.82)');
    $cHeroVigR      = esc($site['comp_hero_vignette_r']  ?? 'rgba(0,0,0,0.1)');
    $cHeroVigB      = esc($site['comp_hero_vignette_b']  ?? 'rgba(0,0,0,0.95)');
    $cHeroVigT      = esc($site['comp_hero_vignette_t']  ?? 'rgba(0,0,0,0.65)');
    $cHeroText      = esc($site['comp_hero_text']         ?? '#ffffff');
    $cHeroDesc      = esc($site['comp_hero_desc']         ?? 'rgba(255,255,255,0.7)');
    $cHeroStatLabel = esc($site['comp_hero_stat_label']   ?? 'rgba(255,255,255,0.55)');
    $cHeroDivider   = esc($site['comp_hero_divider']      ?? 'rgba(255,255,255,0.15)');
    $cHeroScroll    = esc($site['comp_hero_scroll']       ?? 'rgba(255,255,255,0.5)');
    $cHeroAccent    = esc($site['comp_hero_accent']       ?? '#e50914');

    // ── Component palette — Ticker ────────────────────────────────────────
    $cTickerBg   = esc($site['comp_ticker_bg']  ?? '#e50914');
    $cTickerText = esc($site['comp_ticker_text'] ?? '#ffffff');
    $cTickerSep  = esc($site['comp_ticker_sep']  ?? 'rgba(255,255,255,0.4)');

    // ── Font ──────────────────────────────────────────────────────────────
    $font      = esc($site['font_main'] ?: 'Inter');
    $customUrl = trim($site['font_custom_url'] ?? '');

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
  /* ── Theme palette ── */
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

  /* ── Component palette — Nav (universal, not theme-affected) ── */
  --c-nav-bg:          {$cNavBg};
  --c-nav-bg-scrolled: {$cNavBgScrolled};
  --c-nav-text:        {$cNavText};
  --c-nav-muted:       {$cNavMuted};
  --c-nav-accent:      {$cNavAccent};
  --c-nav-border:      {$cNavBorder};
  --c-nav-mobile-bg:   {$cNavMobileBg};

  /* ── Component palette — Mini Player ── */
  --c-player-bg:       {$cPlayerBg};
  --c-player-border:   {$cPlayerBorder};
  --c-player-text:     {$cPlayerText};
  --c-player-muted:    {$cPlayerMuted};
  --c-player-accent:   {$cPlayerAccent};
  --c-player-bar-bg:   {$cPlayerBarBg};
  --c-player-shadow:   {$cPlayerShadow};

  /* ── Component palette — Footer ── */
  --c-footer-bg:       {$cFooterBg};
  --c-footer-border:   {$cFooterBorder};
  --c-footer-text:     {$cFooterText};
  --c-footer-muted:    {$cFooterMuted};
  --c-footer-accent:   {$cFooterAccent};
  --c-footer-link:     {$cFooterLink};

  /* ── Component palette — Hero ── */
  --c-hero-vignette-l: {$cHeroVigL};
  --c-hero-vignette-r: {$cHeroVigR};
  --c-hero-vignette-b: {$cHeroVigB};
  --c-hero-vignette-t: {$cHeroVigT};
  --c-hero-text:       {$cHeroText};
  --c-hero-desc:       {$cHeroDesc};
  --c-hero-stat-label: {$cHeroStatLabel};
  --c-hero-divider:    {$cHeroDivider};
  --c-hero-scroll:     {$cHeroScroll};
  --c-hero-accent:     {$cHeroAccent};

  /* ── Component palette — Ticker ── */
  --c-ticker-bg:   {$cTickerBg};
  --c-ticker-text: {$cTickerText};
  --c-ticker-sep:  {$cTickerSep};
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

    return <<<HTML
<a href="{$href}" class="{$class}">
  {$logoText}<span style="color:{$dotColor}">.</span>
</a>
HTML;
}

/**
 * Render social icon links for footer / nav.
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
 */
function saveSettings(PDO $db, array $data): void {
    foreach ($data as $key => $value) {
        saveSetting($db, (string)$key, (string)$value);
    }
}

// ── Internal escape helper ────────────────────────────────────────────────────
if (!function_exists('esc')) {
    function esc(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}