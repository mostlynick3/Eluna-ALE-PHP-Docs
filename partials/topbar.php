<?php
/** @var string $title */
/** @var array  $searchIndex */
?>
<header id="topbar">
  <a id="logo" href="?">
    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="18" cy="18" r="18" fill="#6b21d6"/>
      <text x="18" y="13" text-anchor="middle" font-family="'Segoe UI',sans-serif"
        font-weight="700" font-size="6.5" fill="#e9d5ff" letter-spacing="0.3">LUA</text>
      <text x="18" y="23" text-anchor="middle" font-family="'Consolas',monospace"
        font-weight="700" font-size="7.5" fill="#f3e8ff" letter-spacing="0.5">API</text>
      <path d="M8 27 Q18 31 28 27" stroke="#c4b5fd" stroke-width="1.2" fill="none" stroke-linecap="round"/>
    </svg>
    <span><?= htmlspecialchars($title) ?></span>
  </a>
  <div id="search-wrap">
    <svg id="search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
      stroke="currentColor" stroke-width="2">
      <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
    </svg>
    <input id="search" type="text" placeholder="Search classes and methods…"
      autocomplete="off" spellcheck="false">
    <div id="search-results"></div>
  </div>
</header>
<script>
  window.SEARCH_INDEX = <?= json_encode($searchIndex, JSON_UNESCAPED_UNICODE) ?>;
</script>