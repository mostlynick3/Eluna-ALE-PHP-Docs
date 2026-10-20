<?php
define('STATIC_BUILD', true);
define('CONFIG_FILE',  __DIR__ . '/config.php');
define('SOURCE_DIR',   __DIR__ . '/source');

$sources = [
    'mod-ale' => [
        'url'         => 'https://github.com/azerothcore/mod-ale/archive/refs/heads/master.zip',
        'dest'        => 'source/mod-ale',
        'title'       => 'mod-ale API',
        'headers_dir' => 'source/mod-ale/src/LuaEngine/methods',
    ],
    'ElunaTrinityCore' => [
        'url'         => 'https://github.com/ElunaLuaEngine/Eluna/archive/refs/heads/master.zip',
        'dest'        => 'source/Eluna',
        'title'       => 'Eluna TrinityCore API',
        'headers_dir' => 'source/Eluna/methods/TrinityCore',
    ],
    'ElunaCMangos' => [
        'url'         => 'https://github.com/ElunaLuaEngine/Eluna/archive/refs/heads/master.zip',
        'dest'        => 'source/Eluna',
        'title'       => 'Eluna CMaNGOS API',
        'headers_dir' => 'source/Eluna/methods/CMangos',
    ],
    'ElunaMangos' => [
        'url'         => 'https://github.com/ElunaLuaEngine/Eluna/archive/refs/heads/master.zip',
        'dest'        => 'source/Eluna',
        'title'       => 'Eluna MaNGOS API',
        'headers_dir' => 'source/Eluna/methods/Mangos',
    ],
    'ElunaVMangos' => [
        'url'         => 'https://github.com/ElunaLuaEngine/Eluna/archive/refs/heads/master.zip',
        'dest'        => 'source/Eluna',
        'title'       => 'Eluna VMaNGOS API',
        'headers_dir' => 'source/Eluna/methods/VMangos',
    ],
];

if (in_array('--list-sources', $argv ?? [], true)) {
    $seen = [];
    foreach ($sources as $source) {
        if (isset($seen[$source['dest']])) continue;
        $seen[$source['dest']] = true;
        echo $source['dest'] . "\t" . $source['url'] . "\n";
    }
    exit;
}

$repo       = getenv('GITHUB_REPOSITORY') ?: '';
$repoName   = $repo ? explode('/', $repo)[1] : '';
$branchName = getenv('BRANCH_NAME') ?: '';
$isIndex    = $branchName === '__index__';
$buildAll   = getenv('BUILD_ALL') === '1';

$distDir = __DIR__ . '/dist';
@mkdir($distDir, 0777, true);

require_once __DIR__ . '/parse.php';
require_once __DIR__ . '/render.php';

function rewrite_links(string $html, string $base): string {
    $parts = preg_split('/(<script\b[^>]*>.*?<\/script>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $html;
    }
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            continue;
        }
        $part = preg_replace_callback('/href="(\?class=([^&"]+)&amp;method=([^"]+))"/', function ($m) use ($base) {
            return 'href="' . $base . '/' . urldecode($m[2]) . '/' . urldecode($m[3]) . '/"';
        }, $part);
        $part = preg_replace_callback('/href="(\?class=([^"&]+))"/', function ($m) use ($base) {
            return 'href="' . $base . '/' . urldecode($m[2]) . '/"';
        }, $part);
        $parts[$i] = preg_replace('/href="\?"/', 'href="' . $base . '/"', $part);
    }
    return implode('', $parts);
}

function render_full_page(string $title, string $selectedClass, string $selectedMethod, array $classes, array $tree, array $searchIndex, array $config, string $base): string {
    $currentClass = $classes[$selectedClass] ?? null;
    $allMethods   = $currentClass ? get_all_methods($selectedClass, $classes) : [];
    $baseJson     = json_encode($base);
    $searchJson   = json_encode($searchIndex, JSON_UNESCAPED_UNICODE);

    ob_start();
    include __DIR__ . '/partials/topbar.php';
    $topbar = ob_get_clean();

    ob_start();
    include __DIR__ . '/partials/sidebar.php';
    $sidebar = ob_get_clean();

    ob_start();
    include __DIR__ . '/partials/content.php';
    $content = ob_get_clean();

    if ($selectedMethod !== '' && $selectedClass !== '') {
        $pageTitle = $selectedClass . ':' . $selectedMethod . ' - ' . $title;
    } elseif ($selectedClass !== '') {
        $pageTitle = $selectedClass . ' - ' . $title;
    } else {
        $pageTitle = $title;
    }
    $pageTitle = htmlspecialchars($pageTitle);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$pageTitle}</title>
  <link rel="stylesheet" href="{$base}/assets/style.css">
</head>
<body>
<div id="app">
{$topbar}
{$sidebar}
<main id="main">
{$content}
</main>
<div id="sidebar-overlay"></div>
</div>
<script>
(function() {
  const base = {$baseJson};
  function rewrite(url) {
    if (!url) return url;
    url = String(url);
    return url
      .replace(/\?class=([^&]+)&(?:amp;)?method=([^&]+)/, function(_, c, m) {
        return base + '/' + decodeURIComponent(c) + '/' + decodeURIComponent(m) + '/';
      })
      .replace(/\?class=([^&]+)/, function(_, c) {
        return base + '/' + decodeURIComponent(c) + '/';
      })
      .replace(/^\?$/, base + '/');
  }
  if (location.search) {
    const target = rewrite(location.search);
    if (target !== location.search) {
      location.replace(target);
      return;
    }
  }
  document.addEventListener('click', function(e) {
    const a = e.target.closest('a');
    if (!a) return;
    const href = a.getAttribute('href');
    if (!href || !href.startsWith('?')) return;
    e.preventDefault();
    location.href = rewrite(href);
  }, true);

  const index = {$searchJson};
  document.addEventListener('DOMContentLoaded', function() {
    const srBox   = document.getElementById('search-results');
    const srInput = document.getElementById('search');
    if (!srInput || !srBox) return;
    srInput.addEventListener('input', function() {
      const q = srInput.value.trim().toLowerCase();
      if (!q) { srBox.classList.remove('open'); srBox.innerHTML = ''; return; }
      const hits = index.filter(x =>
        x.name.toLowerCase().includes(q) ||
        (x.desc  && x.desc.toLowerCase().includes(q)) ||
        (x.class && x.class.toLowerCase().includes(q))
      );
      if (!hits.length) { srBox.classList.remove('open'); srBox.innerHTML = ''; return; }
      srBox.innerHTML = hits.map(h => {
        const url = h.type === 'class'
          ? base + '/' + encodeURIComponent(h.name) + '/'
          : base + '/' + encodeURIComponent(h.class) + '/' + encodeURIComponent(h.name) + '/';
        const sub = h.type === 'method' ? '<span class="sr-class">' + escHtml(h.class) + '.</span>' : '';
        return '<div class="sr-item" onclick="location.href=\'' + url + '\'">'
          + '<span class="sr-badge ' + h.type + '">' + h.type + '</span>'
          + sub + '<span class="sr-name">' + escHtml(h.name) + '</span>'
          + '<span class="sr-desc">' + fmtSearch(h.desc || '') + '</span>'
          + '</div>';
      }).join('');
      srBox.classList.add('open');
    });
    function escHtml(s) {
      return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fmtSearch(s) {
      let h = escHtml(s);
      h = h.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
      h = h.replace(/`([^`]+)`/g, '<code class="inline-code">$1</code>');
      h = h.replace(/\[(\w+):(\w+)\]/g, function(m, c, mn) {
        return '<a class="doc-link" href="?class=' + encodeURIComponent(c) + '&method=' + encodeURIComponent(mn) + '">' + c + ':' + mn + '</a>';
      });
      h = h.replace(/\[(\w+)\]/g, function(m, c) {
        return '<a class="doc-link" href="?class=' + encodeURIComponent(c) + '">' + c + '</a>';
      });
      return h;
    }
    document.addEventListener('click', function(e) {
      if (!srInput.contains(e.target) && !srBox.contains(e.target)) {
        srBox.classList.remove('open');
        srBox.innerHTML = '';
      }
    });
    srInput.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') { srBox.classList.remove('open'); srBox.innerHTML = ''; srInput.blur(); }
    });
  });
})();
</script>
<script src="{$base}/assets/app.js"></script>
</body>
</html>
HTML;

    return rewrite_links($html, $base);
}

function write_page(string $path, string $html): void {
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $html);
}

function build_source(string $key, array $source, string $outDir, string $basePath): void {
    $config = [
        'headers_dir' => __DIR__ . '/' . $source['headers_dir'],
        'site_title'  => $source['title'],
        'setConf'     => 1,
        'refetch'     => ['enabled' => false, 'interval_unit' => 'days', 'interval_value' => 1, 'last_fetched' => 0, 'zip_url' => '', 'dest_key' => ''],
    ];

    $classes     = parse_headers($config['headers_dir']);
    $tree        = build_tree($classes);
    $searchIndex = build_search_index($classes);
    $title       = $config['site_title'];

    @mkdir($outDir . '/assets', 0777, true);

    write_page(
        $outDir . '/index.html',
        render_full_page($title, '', '', $classes, $tree, $searchIndex, $config, $basePath)
    );

    foreach ($classes as $className => $cls) {
        $allMethods = get_all_methods($className, $classes);

        write_page(
            $outDir . '/' . $className . '/index.html',
            render_full_page($title, $className, '', $classes, $tree, $searchIndex, $config, $basePath)
        );

        foreach ($allMethods as $methodName => $method) {
            write_page(
                $outDir . '/' . $className . '/' . $methodName . '/index.html',
                render_full_page($title, $className, $methodName, $classes, $tree, $searchIndex, $config, $basePath)
            );
        }
    }

    copy(__DIR__ . '/assets/style.css', $outDir . '/assets/style.css');
    copy(__DIR__ . '/assets/app.js',    $outDir . '/assets/app.js');

    echo 'Done. ' . $key . ': ' . count($classes) . " classes written to {$outDir}\n";
}

if ($isIndex) {
    build_index_page($repoName, $distDir, $sources);
    exit;
}

if ($buildAll) {
    foreach ($sources as $key => $source) {
        build_source($key, $source, $distDir . '/' . $key, '/' . $repoName . '/' . $key);
    }
    build_index_page($repoName, $distDir, $sources);
    exit;
}

if (!isset($sources[$branchName])) {
    echo "No source mapping for: {$branchName}\n";
    exit(1);
}

build_source($branchName, $sources[$branchName], $distDir, '/' . $repoName . '/' . $branchName);

function build_index_page(string $repoName, string $distDir, array $sources): void {
    if (count($sources) === 1) {
        $key = array_key_first($sources);
        $url = '/' . $repoName . '/' . $key . '/';
        $href = htmlspecialchars($url, ENT_QUOTES);
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="0; url={$href}">
  <title>Eluna / ALE API</title>
</head>
<body>
  <a href="{$href}">{$href}</a>
</body>
</html>
HTML;

        file_put_contents($distDir . '/index.html', $html);
        echo "Done. Single-source index redirects to {$url}\n";
        return;
    }

    $cards = '';
    foreach (array_keys($sources) as $key) {
        $url   = '/' . $repoName . '/' . htmlspecialchars($key) . '/';
        $label = htmlspecialchars($key);
        $cards .= <<<HTML
      <a class="branch-card" href="{$url}">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></svg>
        {$label}
      </a>
HTML;
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Eluna / ALE API</title>
  <style>
    :root { --bg: #0f0f14; --surface: #16161f; --border: #2a2a3d; --purple: #9d6fdb; --purple2: #7c4fc4; --purple3: #c9a0ff; --text: #d4d4e8; --muted: #6e6e96; }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .container { text-align: center; padding: 40px 20px; }
    h1 { font-size: 28px; font-weight: 700; color: var(--purple3); margin-bottom: 10px; }
    .sub { color: var(--muted); font-size: 15px; margin-bottom: 36px; }
    .branches { display: flex; flex-wrap: wrap; gap: 14px; justify-content: center; max-width: 700px; }
    .branch-card { display: inline-flex; align-items: center; gap: 10px; padding: 14px 24px; border-radius: 10px; background: var(--surface); border: 1px solid var(--border); color: var(--purple3); font-size: 15px; font-weight: 600; text-decoration: none; transition: background .15s, border-color .15s, color .15s; }
    .branch-card:hover { background: var(--purple2); border-color: var(--purple); color: #fff; }
  </style>
</head>
<body>
  <div class="container">
    <svg width="64" height="64" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin: 0 auto 20px; display:block">
      <circle cx="18" cy="18" r="18" fill="#6b21d6"/>
      <text x="18" y="13" text-anchor="middle" font-family="'Segoe UI',sans-serif" font-weight="700" font-size="6.5" fill="#e9d5ff" letter-spacing="0.3">LUA</text>
      <text x="18" y="23" text-anchor="middle" font-family="'Consolas',monospace" font-weight="700" font-size="7.5" fill="#f3e8ff" letter-spacing="0.5">API</text>
      <path d="M8 27 Q18 31 28 27" stroke="#c4b5fd" stroke-width="1.2" fill="none" stroke-linecap="round"/>
    </svg>
    <h1>Eluna / ALE API</h1>
    <p class="sub">Which API would you like to browse?</p>
    <div class="branches">
      {$cards}
    </div>
  </div>
</body>
</html>
HTML;

    file_put_contents($distDir . '/index.html', $html);
    echo 'Done. Index page written with ' . count($sources) . " branches.\n";
}