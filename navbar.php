<?php


$pageTitle   = $pageTitle   ?? 'DocuMind';
$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = isset($_SESSION['user_id']);
$userName    = $isLoggedIn ? htmlspecialchars($_SESSION['user_name'] ?? 'User', ENT_QUOTES) : '';
$userRole    = $isLoggedIn ? htmlspecialchars($_SESSION['user_role'] ?? 'Student', ENT_QUOTES) : '';
$userInitial = $isLoggedIn ? strtoupper(mb_substr($_SESSION['user_name'] ?? 'U', 0, 1)) : '';

$navLinks = [
    'dashboard.php'  => ['label' => 'Workspace',        'icon' => 'grid'],
    'history.php'    => ['label' => 'My Directory',       'icon' => 'database'],
    'profile.php'    => ['label' => 'Profile',          'icon' => 'user'],
];
?>
<!doctype html>
<html lang="en" data-theme="system">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="DocuMind — AI-powered PDF summarizer and question generator">
  <title><?= htmlspecialchars($pageTitle) ?> — DocuMind</title>
  <!-- DocuMind Favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%230ea5e9'/><path d='M30 22h28l16 16v40a6 6 0 0 1-6 6H30a6 6 0 0 1-6-6V28a6 6 0 0 1 6-6z' fill='none' stroke='white' stroke-width='7' stroke-linejoin='round'/><path d='M56 22v18h18' fill='none' stroke='white' stroke-width='7' stroke-linejoin='round'/><line x1='38' y1='54' x2='62' y2='54' stroke='white' stroke-width='7' stroke-linecap='round'/><line x1='38' y1='68' x2='52' y2='68' stroke='white' stroke-width='7' stroke-linecap='round'/></svg>">
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

  <!-- Feather Icons (lightweight, crisp SVG set) -->
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js" defer></script>

  <style>
    /* ════════════════════════════════════════════════════════════════
       §0 — CSS Custom Properties · 3-Mode Theme Engine
       ════════════════════════════════════════════════════════════════ */

    /* Light (default) */
    :root,
    [data-theme="light"] {
      --bg-canvas:        #ffffff;
      --bg-surface:       #fafafa;
      --bg-elevated:      #ffffff;
      --bg-sunken:        #f4f4f5;
      --bg-overlay:       rgba(255,255,255,.85);

      --border-base:      #e4e4e7;
      --border-subtle:    #f0f0f0;
      --border-strong:    #d4d4d8;

      --text-primary:     #09090b;
      --text-secondary:   #52525b;
      --text-tertiary:    #a1a1aa;
      --text-inverse:     #ffffff;

      --accent:           #18181b;
      --accent-hover:     #3f3f46;
      --accent-subtle:    #f4f4f5;
      --accent-ring:      rgba(24,24,27,.15);

      --brand:            #0ea5e9;
      --brand-hover:      #0284c7;
      --brand-subtle:     #f0f9ff;

      --success:          #16a34a;
      --warning:          #ca8a04;
      --danger:           #dc2626;

      --shadow-xs:        0 1px 2px rgba(0,0,0,.05);
      --shadow-sm:        0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.05);
      --shadow-md:        0 4px 6px rgba(0,0,0,.06), 0 2px 4px rgba(0,0,0,.04);
      --shadow-lg:        0 10px 15px rgba(0,0,0,.07), 0 4px 6px rgba(0,0,0,.04);
      --shadow-card:      0 0 0 1px var(--border-base), 0 2px 8px rgba(0,0,0,.06);

      --nav-bg:           rgba(255,255,255,.92);
      --nav-border:       var(--border-base);

      --scrollbar-thumb:  #d4d4d8;
      --scrollbar-track:  #f4f4f5;
    }

    /* Dark */
    [data-theme="dark"] {
      --bg-canvas:        #0a0a0a;
      --bg-surface:       #111111;
      --bg-elevated:      #18181b;
      --bg-sunken:        #09090b;
      --bg-overlay:       rgba(18,18,22,.90);

      --border-base:      #27272a;
      --border-subtle:    #1e1e21;
      --border-strong:    #3f3f46;

      --text-primary:     #fafafa;
      --text-secondary:   #a1a1aa;
      --text-tertiary:    #52525b;
      --text-inverse:     #09090b;

      --accent:           #fafafa;
      --accent-hover:     #e4e4e7;
      --accent-subtle:    #27272a;
      --accent-ring:      rgba(250,250,250,.12);

      --brand:            #38bdf8;
      --brand-hover:      #7dd3fc;
      --brand-subtle:     #082f49;

      --success:          #22c55e;
      --warning:          #eab308;
      --danger:           #f87171;

      --shadow-xs:        0 1px 2px rgba(0,0,0,.4);
      --shadow-sm:        0 1px 3px rgba(0,0,0,.5), 0 1px 2px rgba(0,0,0,.4);
      --shadow-md:        0 4px 6px rgba(0,0,0,.4), 0 2px 4px rgba(0,0,0,.35);
      --shadow-lg:        0 10px 15px rgba(0,0,0,.5), 0 4px 6px rgba(0,0,0,.4);
      --shadow-card:      0 0 0 1px var(--border-base), 0 2px 12px rgba(0,0,0,.4);

      --nav-bg:           rgba(10,10,10,.92);
      --nav-border:       var(--border-base);

      --scrollbar-thumb:  #3f3f46;
      --scrollbar-track:  #18181b;
    }

    /* ════════════════════════════════════════════════════════════════
       §1 — Global Reset & Base
       ════════════════════════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html {
      font-size: 16px;
      scroll-behavior: smooth;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      text-rendering: optimizeLegibility;
    }

    body {
      font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
      background-color: var(--bg-canvas);
      color: var(--text-primary);
      line-height: 1.6;
      min-height: 100vh;
      transition: background-color .2s ease, color .2s ease;
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: var(--scrollbar-track); }
    ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--border-strong); }

    /* Selection */
    ::selection { background: var(--brand); color: #fff; }

    /* Typography scale */
    h1,h2,h3,h4,h5,h6 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; line-height: 1.2; color: var(--text-primary); }
    p  { color: var(--text-secondary); }
    a  { color: var(--brand); text-decoration: none; transition: color .15s ease; }
    a:hover { color: var(--brand-hover); }

    /* ════════════════════════════════════════════════════════════════
       §2 — Uiverse-Style Elevated Card (.uiverse-card)
       ════════════════════════════════════════════════════════════════ */
    .uiverse-card {
      background: var(--bg-elevated);
      border: 1px solid var(--border-base);
      border-radius: 14px;
      box-shadow: var(--shadow-card);
      padding: 1.75rem;
      position: relative;
      overflow: hidden;
      transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
    }
    .uiverse-card:hover {
      box-shadow: var(--shadow-lg), 0 0 0 1px var(--border-strong);
      transform: translateY(-1px);
    }
    .uiverse-card::before {
      content: '';
      position: absolute;
      inset: 0;
      border-radius: inherit;
      background: linear-gradient(135deg, rgba(255,255,255,.04) 0%, transparent 60%);
      pointer-events: none;
    }
    [data-theme="dark"] .uiverse-card::before {
      background: linear-gradient(135deg, rgba(255,255,255,.03) 0%, transparent 60%);
    }

    /* Flat surface variant */
    .uiverse-card.flat {
      box-shadow: var(--shadow-xs);
    }
    .uiverse-card.flat:hover {
      box-shadow: var(--shadow-md), 0 0 0 1px var(--border-strong);
    }

    /* ════════════════════════════════════════════════════════════════
       §3 — Segmented Tab Switcher (.uiverse-tabs)
       ════════════════════════════════════════════════════════════════ */
    .uiverse-tabs {
      display: inline-flex;
      background: var(--bg-sunken);
      border: 1px solid var(--border-base);
      border-radius: 10px;
      padding: 4px;
      gap: 2px;
      position: relative;
    }

    .uiverse-tabs .tab-btn {
      position: relative;
      z-index: 1;
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .45rem 1.1rem;
      border: none;
      background: transparent;
      border-radius: 7px;
      font-family: 'Inter', sans-serif;
      font-size: .82rem;
      font-weight: 500;
      color: var(--text-secondary);
      cursor: pointer;
      white-space: nowrap;
      transition: color .2s ease, background .2s ease;
      outline: none;
      -webkit-user-select: none;
      user-select: none;
    }

    .uiverse-tabs .tab-btn svg {
      width: 14px;
      height: 14px;
      stroke-width: 2px;
      transition: stroke .2s ease;
    }

    .uiverse-tabs .tab-btn:hover:not(.active) {
      color: var(--text-primary);
      background: var(--bg-elevated);
    }

    .uiverse-tabs .tab-btn.active {
      background: var(--bg-elevated);
      color: var(--text-primary);
      box-shadow: var(--shadow-sm), 0 0 0 1px var(--border-base);
    }

    /* PDF / text mode specific accent */
    .uiverse-tabs .tab-btn.active[data-tab="pdf"]  { color: var(--brand); }
    .uiverse-tabs .tab-btn.active[data-tab="text"] { color: var(--success); }

    /* ════════════════════════════════════════════════════════════════
       §4 — Animated Tactile Buttons (.btn-animated)
       ════════════════════════════════════════════════════════════════ */
    .btn-animated {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      padding: .55rem 1.35rem;
      border-radius: 8px;
      font-family: 'Inter', sans-serif;
      font-size: .875rem;
      font-weight: 600;
      letter-spacing: -.01em;
      cursor: pointer;
      border: 1px solid transparent;
      outline: none;
      text-decoration: none;
      overflow: hidden;
      transition:
        background   .2s ease,
        color        .2s ease,
        border-color .2s ease,
        transform    .12s ease,
        box-shadow   .2s ease;
      -webkit-user-select: none;
      user-select: none;
    }

    /* Ripple/sweep pseudo-element */
    .btn-animated::after {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(255,255,255,.12);
      transform: scaleX(0);
      transform-origin: left;
      transition: transform .25s ease;
      border-radius: inherit;
    }
    .btn-animated:hover::after { transform: scaleX(1); }

    /* 3-D press-down */
    .btn-animated:active {
      transform: translateY(2px) scale(.988);
      box-shadow: none !important;
    }

    /* Primary (solid zinc/obsidian) */
    .btn-animated.btn-primary-anim {
      background: var(--accent);
      color: var(--text-inverse);
      border-color: var(--accent);
      box-shadow: 0 1px 2px rgba(0,0,0,.12), 0 3px 8px rgba(0,0,0,.1), inset 0 1px 0 rgba(255,255,255,.1);
    }
    .btn-animated.btn-primary-anim:hover {
      background: var(--accent-hover);
      border-color: var(--accent-hover);
      box-shadow: 0 2px 4px rgba(0,0,0,.15), 0 6px 18px rgba(0,0,0,.12), inset 0 1px 0 rgba(255,255,255,.1);
    }

    /* Outline */
    .btn-animated.btn-outline-anim {
      background: transparent;
      color: var(--text-primary);
      border-color: var(--border-base);
      box-shadow: var(--shadow-xs);
    }
    .btn-animated.btn-outline-anim:hover {
      background: var(--bg-sunken);
      border-color: var(--border-strong);
      box-shadow: var(--shadow-sm);
    }
    .btn-animated.btn-outline-anim::after { background: rgba(0,0,0,.04); }
    [data-theme="dark"] .btn-animated.btn-outline-anim::after { background: rgba(255,255,255,.04); }

    /* Brand (sky blue) */
    .btn-animated.btn-brand-anim {
      background: var(--brand);
      color: #ffffff;
      border-color: var(--brand);
      box-shadow: 0 1px 2px rgba(14,165,233,.2), 0 3px 8px rgba(14,165,233,.2);
    }
    .btn-animated.btn-brand-anim:hover {
      background: var(--brand-hover);
      border-color: var(--brand-hover);
      box-shadow: 0 2px 4px rgba(14,165,233,.3), 0 6px 18px rgba(14,165,233,.2);
    }

    /* Danger */
    .btn-animated.btn-danger-anim {
      background: transparent;
      color: var(--danger);
      border-color: var(--border-base);
      box-shadow: var(--shadow-xs);
    }
    .btn-animated.btn-danger-anim:hover {
      background: #fef2f2;
      border-color: #fecaca;
      color: var(--danger);
    }
    [data-theme="dark"] .btn-animated.btn-danger-anim:hover {
      background: #2d1515;
      border-color: #7f1d1d;
    }

    /* ════════════════════════════════════════════════════════════════
       §5 — Top Navigation Bar
       ════════════════════════════════════════════════════════════════ */
    #dm-navbar {
      position: sticky;
      top: 0;
      z-index: 1000;
      width: 100%;
      background: var(--nav-bg);
      border-bottom: 1px solid var(--nav-border);
      backdrop-filter: saturate(180%) blur(16px);
      -webkit-backdrop-filter: saturate(180%) blur(16px);
      transition: background .2s ease, border-color .2s ease;
    }

    .dm-nav-inner {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 1.5rem;
      height: 60px;
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }

    /* Brand */
    .dm-brand {
      display: flex;
      align-items: center;
      gap: .6rem;
      text-decoration: none;
      flex-shrink: 0;
    }
    .dm-brand-wordmark {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 1.075rem;
      font-weight: 800;
      letter-spacing: -.035em;
      color: var(--text-primary);
      line-height: 1;
    }
    .dm-brand-wordmark span {
      color: var(--brand);
    }

    /* Nav links */
    .dm-nav-links {
      display: flex;
      align-items: center;
      gap: .15rem;
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .dm-nav-links li a {
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .4rem .75rem;
      border-radius: 7px;
      font-size: .825rem;
      font-weight: 500;
      color: var(--text-secondary);
      text-decoration: none;
      transition: color .2s ease, background .2s ease;
      white-space: nowrap;
    }
    .dm-nav-links li a svg {
      width: 13px;
      height: 13px;
      stroke-width: 2.2px;
      flex-shrink: 0;
    }
    .dm-nav-links li a:hover {
      color: var(--text-primary);
      background: var(--accent-subtle);
    }
    .dm-nav-links li a.active {
      color: var(--text-primary);
      background: var(--accent-subtle);
      font-weight: 600;
    }

    /* Animated underline indicator */
    .dm-nav-links li a::after {
      content: '';
      position: absolute;
      bottom: 4px;
      left: .75rem;
      right: .75rem;
      height: 2px;
      background: var(--brand);
      border-radius: 99px;
      transform: scaleX(0);
      transform-origin: center;
      transition: transform .2s cubic-bezier(.4,0,.2,1);
    }
    .dm-nav-links li a:hover::after,
    .dm-nav-links li a.active::after {
      transform: scaleX(1);
    }

    /* Spacer */
    .dm-spacer { flex: 1 1 auto; }

    /* ── Theme Switcher ── */
    .dm-theme-switcher {
      display: inline-flex;
      align-items: center;
      background: var(--bg-sunken);
      border: 1px solid var(--border-base);
      border-radius: 8px;
      padding: 3px;
      gap: 2px;
      flex-shrink: 0;
    }

    .dm-theme-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 30px;
      height: 28px;
      border: none;
      background: transparent;
      border-radius: 6px;
      cursor: pointer;
      font-size: .8rem;
      color: var(--text-tertiary);
      transition: background .2s ease, color .2s ease, transform .15s ease;
      outline: none;
      position: relative;
    }
    .dm-theme-btn:hover {
      background: var(--bg-elevated);
      color: var(--text-primary);
    }
    .dm-theme-btn.active {
      background: var(--bg-elevated);
      color: var(--text-primary);
      box-shadow: var(--shadow-xs), 0 0 0 1px var(--border-base);
    }
    .dm-theme-btn:active { transform: scale(.92); }

    /* Theme tooltip */
    .dm-theme-btn::before {
      content: attr(data-tooltip);
      position: absolute;
      bottom: calc(100% + 8px);
      left: 50%;
      transform: translateX(-50%);
      background: var(--accent);
      color: var(--text-inverse);
      font-size: .68rem;
      font-weight: 600;
      letter-spacing: .02em;
      white-space: nowrap;
      padding: .25rem .55rem;
      border-radius: 5px;
      pointer-events: none;
      opacity: 0;
      transition: opacity .15s ease, bottom .15s ease;
    }
    .dm-theme-btn:hover::before {
      opacity: 1;
      bottom: calc(100% + 6px);
    }

    /* ── User area ── */
    .dm-user-area {
      display: flex;
      align-items: center;
      gap: .65rem;
      flex-shrink: 0;
    }

    .dm-avatar {
      width: 32px;
      height: 32px;
      border-radius: 99px;
      background: var(--accent);
      color: var(--text-inverse);
      font-size: .75rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid var(--border-strong);
      flex-shrink: 0;
      letter-spacing: -.01em;
    }

    .dm-user-meta { line-height: 1.15; }
    .dm-user-name {
      font-size: .8rem;
      font-weight: 600;
      color: var(--text-primary);
      max-width: 120px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .dm-user-role {
      font-size: .7rem;
      color: var(--text-tertiary);
      font-weight: 500;
    }

    /* ── Mobile hamburger ── */
    .dm-hamburger {
      display: none;
      flex-direction: column;
      gap: 5px;
      cursor: pointer;
      padding: .4rem;
      border: none;
      background: transparent;
      border-radius: 6px;
      outline: none;
      flex-shrink: 0;
    }
    .dm-hamburger span {
      display: block;
      width: 20px;
      height: 1.5px;
      background: var(--text-secondary);
      border-radius: 2px;
      transition: transform .25s ease, opacity .25s ease;
    }
    .dm-hamburger.open span:nth-child(1) { transform: translateY(6.5px) rotate(45deg); }
    .dm-hamburger.open span:nth-child(2) { opacity: 0; }
    .dm-hamburger.open span:nth-child(3) { transform: translateY(-6.5px) rotate(-45deg); }

    /* Mobile menu */
    #dm-mobile-menu {
      display: none;
      border-top: 1px solid var(--border-base);
      background: var(--nav-bg);
      backdrop-filter: saturate(180%) blur(16px);
      -webkit-backdrop-filter: saturate(180%) blur(16px);
      padding: .75rem 1.5rem 1rem;
    }
    #dm-mobile-menu.open { display: block; }
    #dm-mobile-menu .dm-nav-links {
      flex-direction: column;
      align-items: flex-start;
      gap: .2rem;
    }
    #dm-mobile-menu .dm-nav-links li { width: 100%; }
    #dm-mobile-menu .dm-nav-links li a { width: 100%; padding: .55rem .75rem; }
    #dm-mobile-menu .dm-nav-links li a::after { display: none; }

    @media (max-width: 768px) {
      .dm-nav-links:not(#dm-mobile-menu .dm-nav-links),
      .dm-user-meta,
      .dm-theme-switcher { display: none; }
      .dm-hamburger { display: flex; }
      .dm-user-area  { gap: .4rem; }
    }

    /* ════════════════════════════════════════════════════════════════
       §6 — Page-content wrapper
       ════════════════════════════════════════════════════════════════ */
    .dm-page-wrap {
      max-width: 1280px;
      margin: 0 auto;
      padding: 2rem 1.5rem;
    }

    /* ════════════════════════════════════════════════════════════════
       §7 — Utility helpers
       ════════════════════════════════════════════════════════════════ */
    .text-muted-dm   { color: var(--text-secondary); }
    .text-faint-dm   { color: var(--text-tertiary); }
    .border-dm       { border: 1px solid var(--border-base); }
    .bg-surface-dm   { background: var(--bg-surface); }
    .bg-elevated-dm  { background: var(--bg-elevated); }
    .bg-sunken-dm    { background: var(--bg-sunken); }
    .radius-sm       { border-radius: 6px; }
    .radius-md       { border-radius: 10px; }
    .radius-lg       { border-radius: 14px; }
    .radius-xl       { border-radius: 18px; }
    .shadow-card-dm  { box-shadow: var(--shadow-card); }

    /* Focus ring */
    :focus-visible {
      outline: 2px solid var(--brand);
      outline-offset: 2px;
      border-radius: 4px;
    }

    /* Animated skeleton loader */
    .skeleton {
      background: linear-gradient(90deg, var(--bg-sunken) 25%, var(--border-subtle) 50%, var(--bg-sunken) 75%);
      background-size: 200% 100%;
      animation: skeleton-sweep 1.4s infinite ease;
      border-radius: 6px;
    }
    @keyframes skeleton-sweep {
      0%   { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }

    /* Badge pill */
    .badge-dm {
      display: inline-flex;
      align-items: center;
      gap: .3rem;
      padding: .2rem .6rem;
      border-radius: 99px;
      font-size: .72rem;
      font-weight: 600;
      letter-spacing: .01em;
      background: var(--accent-subtle);
      color: var(--text-secondary);
      border: 1px solid var(--border-base);
    }
    .badge-dm.brand { background: var(--brand-subtle); color: var(--brand); border-color: transparent; }
    .badge-dm.success { background: #f0fdf4; color: var(--success); border-color: transparent; }
    [data-theme="dark"] .badge-dm.success { background: #052e16; }

    /* Divider */
    .divider-dm {
      height: 1px;
      background: var(--border-base);
      margin: 1.5rem 0;
    }

    /* Alert */
    .alert-dm {
      padding: .85rem 1rem;
      border-radius: 8px;
      border: 1px solid;
      font-size: .875rem;
      display: flex;
      align-items: flex-start;
      gap: .6rem;
    }
    .alert-dm.info    { background: var(--brand-subtle); border-color: rgba(14,165,233,.2); color: var(--brand); }
    .alert-dm.success { background: #f0fdf4; border-color: rgba(22,163,74,.2);  color: var(--success); }
    .alert-dm.danger  { background: #fef2f2; border-color: rgba(220,38,38,.2);  color: var(--danger); }
    [data-theme="dark"] .alert-dm.success { background: #052e16; }
    [data-theme="dark"] .alert-dm.danger  { background: #2d1515; }

    /* ════════════════════════════════════════════════════════════════
       §8 — System prefers-color-scheme baseline (pre-JS)
       ════════════════════════════════════════════════════════════════ */
    /* Applied only when data-theme="system" and no JS override active */
    @media (prefers-color-scheme: dark) {
      html[data-theme="system"] {
        --bg-canvas:      #0a0a0a;
        --bg-surface:     #111111;
        --bg-elevated:    #18181b;
        --bg-sunken:      #09090b;
        --bg-overlay:     rgba(18,18,22,.90);
        --border-base:    #27272a;
        --border-subtle:  #1e1e21;
        --border-strong:  #3f3f46;
        --text-primary:   #fafafa;
        --text-secondary: #a1a1aa;
        --text-tertiary:  #52525b;
        --text-inverse:   #09090b;
        --accent:         #fafafa;
        --accent-hover:   #e4e4e7;
        --accent-subtle:  #27272a;
        --accent-ring:    rgba(250,250,250,.12);
        --brand:          #38bdf8;
        --brand-hover:    #7dd3fc;
        --brand-subtle:   #082f49;
        --success:        #22c55e;
        --warning:        #eab308;
        --danger:         #f87171;
        --shadow-xs:      0 1px 2px rgba(0,0,0,.4);
        --shadow-sm:      0 1px 3px rgba(0,0,0,.5),0 1px 2px rgba(0,0,0,.4);
        --shadow-md:      0 4px 6px rgba(0,0,0,.4),0 2px 4px rgba(0,0,0,.35);
        --shadow-lg:      0 10px 15px rgba(0,0,0,.5),0 4px 6px rgba(0,0,0,.4);
        --shadow-card:    0 0 0 1px var(--border-base),0 2px 12px rgba(0,0,0,.4);
        --nav-bg:         rgba(10,10,10,.92);
        --nav-border:     var(--border-base);
        --scrollbar-thumb:#3f3f46;
        --scrollbar-track:#18181b;
      }
    }
  </style>
</head>
<body>

<!-- ════════════════════════════════════════════════════════════════
     TOP NAVIGATION BAR
     ════════════════════════════════════════════════════════════════ -->
<header id="dm-navbar" role="banner">
  <div class="dm-nav-inner">

    <!-- Brand -->
    <a href="dashboard.php" class="dm-brand" aria-label="DocuMind Home">
      <!-- Custom Geometric SVG Logo: Document + AI Spark -->
      <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <!-- Document body -->
        <rect x="4" y="3" width="16" height="21" rx="2.5" fill="var(--accent)" opacity=".06"/>
        <rect x="4" y="3" width="16" height="21" rx="2.5" stroke="var(--accent)" stroke-width="1.4"/>
        <!-- Document lines (text rows) -->
        <line x1="8" y1="9"  x2="16" y2="9"  stroke="var(--accent)" stroke-width="1.3" stroke-linecap="round"/>
        <line x1="8" y1="13" x2="16" y2="13" stroke="var(--accent)" stroke-width="1.3" stroke-linecap="round"/>
        <line x1="8" y1="17" x2="13" y2="17" stroke="var(--accent)" stroke-width="1.3" stroke-linecap="round"/>
        <!-- Folded corner -->
        <path d="M16 3 L20 7" stroke="var(--accent)" stroke-width="1.4" stroke-linecap="round"/>
        <path d="M16 3 L16 7 L20 7" fill="var(--bg-canvas)" stroke="var(--accent)" stroke-width="1.4" stroke-linejoin="round"/>
        <!-- AI Spark (4-point star burst) -->
        <g transform="translate(20.5, 20.5)">
          <path d="M0,-6 L1.2,-1.2 L6,0 L1.2,1.2 L0,6 L-1.2,1.2 L-6,0 L-1.2,-1.2 Z"
                fill="var(--brand)" opacity=".9"/>
          <circle cx="0" cy="0" r="1.3" fill="var(--brand)"/>
        </g>
      </svg>
      <span class="dm-brand-wordmark">Docu<span>Mind</span></span>
    </a>

    <!-- Desktop Nav Links -->
    <nav aria-label="Main navigation">
      <ul class="dm-nav-links" role="list">
        <?php foreach ($navLinks as $href => $meta):
          $isActive = ($currentPage === $href) ? 'active' : '';
        ?>
        <li>
          <a href="<?= $href ?>" class="<?= $isActive ?>"
             <?= $isActive ? 'aria-current="page"' : '' ?>>
            <i data-feather="<?= $meta['icon'] ?>" aria-hidden="true"></i>
            <?= $meta['label'] ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="dm-spacer" aria-hidden="true"></div>

    <!-- 3-Mode Theme Switcher -->
    <div class="dm-theme-switcher" role="group" aria-label="Color theme">
      <button class="dm-theme-btn" id="themeLight" data-theme-val="light" data-tooltip="Light" aria-label="Light mode" title="Light mode">☀️</button>
      <button class="dm-theme-btn" id="themeDark"  data-theme-val="dark"  data-tooltip="Dark"  aria-label="Dark mode"  title="Dark mode">🌙</button>
      <button class="dm-theme-btn" id="themeAuto"  data-theme-val="system" data-tooltip="System" aria-label="System mode" title="Follow system">💻</button>
    </div>

    <?php if ($isLoggedIn): ?>
    <!-- User Area -->
    <div class="dm-user-area">
      <div class="dm-avatar" aria-hidden="true"><?= $userInitial ?></div>
      <div class="dm-user-meta">
        <div class="dm-user-name"><?= $userName ?></div>
        <div class="dm-user-role"><?= $userRole ?></div>
      </div>
      <a href="logout.php" class="btn-animated btn-danger-anim" aria-label="Sign out">
        <i data-feather="log-out" style="width:13px;height:13px;stroke-width:2.2px;" aria-hidden="true"></i>
        Logout
      </a>
    </div>
    <?php else: ?>
    <div class="dm-user-area">
      <a href="index.php"    class="btn-animated btn-outline-anim">Log in</a>
      <a href="register.php" class="btn-animated btn-primary-anim">Sign up</a>
    </div>
    <?php endif; ?>

    <!-- Hamburger (mobile) -->
    <button class="dm-hamburger" id="dmHamburger" aria-label="Toggle menu" aria-expanded="false" aria-controls="dm-mobile-menu">
      <span></span><span></span><span></span>
    </button>

  </div><!-- /.dm-nav-inner -->

  <!-- Mobile dropdown menu -->
  <div id="dm-mobile-menu" role="navigation" aria-label="Mobile navigation">
    <ul class="dm-nav-links" role="list">
      <?php foreach ($navLinks as $href => $meta):
        $isActive = ($currentPage === $href) ? 'active' : '';
      ?>
      <li>
        <a href="<?= $href ?>" class="<?= $isActive ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
          <i data-feather="<?= $meta['icon'] ?>" aria-hidden="true"></i>
          <?= $meta['label'] ?>
        </a>
      </li>
      <?php endforeach; ?>
      <?php if ($isLoggedIn): ?>
      <li>
        <a href="logout.php" style="color:var(--danger);">
          <i data-feather="log-out" aria-hidden="true"></i>
          Logout
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </div>
</header><!-- /#dm-navbar -->

<!-- ════════════════════════════════════════════════════════════════
     THEME ENGINE & INTERACTIVE SCRIPTS
     ════════════════════════════════════════════════════════════════ -->
<script>
(function () {
  'use strict';

  // ── Theme Engine ────────────────────────────────────────────────
  const STORAGE_KEY = 'dm_theme';
  const html        = document.documentElement;

  /** Apply a theme value to <html data-theme> */
  function applyTheme(mode) {
    html.setAttribute('data-theme', mode);
  }

  /** Refresh active state on switcher buttons */
  function syncButtons(mode) {
    document.querySelectorAll('.dm-theme-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.themeVal === mode);
      btn.setAttribute('aria-pressed', btn.dataset.themeVal === mode ? 'true' : 'false');
    });
  }

  /** Read saved preference or fall back to system */
  function getSavedTheme() {
    return localStorage.getItem(STORAGE_KEY) || 'system';
  }

  /** Public: switch theme, persist, update UI */
  function setTheme(mode) {
    localStorage.setItem(STORAGE_KEY, mode);
    applyTheme(mode);
    syncButtons(mode);
  }

  // Apply immediately (before paint) to avoid FOUC
  const saved = getSavedTheme();
  applyTheme(saved);

  // Wire up buttons once DOM is ready
  document.addEventListener('DOMContentLoaded', function () {
    syncButtons(saved);

    document.querySelectorAll('.dm-theme-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setTheme(this.dataset.themeVal);
      });
    });

    // Listen to OS-level changes (only matters when mode === 'system')
    const mql = window.matchMedia('(prefers-color-scheme: dark)');
    mql.addEventListener('change', function () {
      if (getSavedTheme() === 'system') {
        // Re-apply system — CSS @media handles it, but refreshing
        // data-theme triggers any JS-side watchers
        applyTheme('system');
      }
    });

    // ── Hamburger ──────────────────────────────────────────────────
    const hamburger  = document.getElementById('dmHamburger');
    const mobileMenu = document.getElementById('dm-mobile-menu');

    if (hamburger && mobileMenu) {
      hamburger.addEventListener('click', function () {
        const open = mobileMenu.classList.toggle('open');
        hamburger.classList.toggle('open', open);
        hamburger.setAttribute('aria-expanded', open ? 'true' : 'false');
      });

      // Close on outside click
      document.addEventListener('click', function (e) {
        if (!hamburger.contains(e.target) && !mobileMenu.contains(e.target)) {
          mobileMenu.classList.remove('open');
          hamburger.classList.remove('open');
          hamburger.setAttribute('aria-expanded', 'false');
        }
      });
    }

    // ── Feather Icons ──────────────────────────────────────────────
    if (typeof feather !== 'undefined') {
      feather.replace({ 'stroke-width': 2, width: 14, height: 14 });
    }
  });

  // ── Expose globally for other pages ────────────────────────────
  window.DocuMind = window.DocuMind || {};
  window.DocuMind.setTheme = setTheme;
  window.DocuMind.getTheme = getSavedTheme;
})();
</script>
