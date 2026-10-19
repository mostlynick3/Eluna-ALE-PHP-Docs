<?php
require_once __DIR__ . '/parse.php';
require_once __DIR__ . '/render.php';
require_once __DIR__ . '/fetch.php';

defined('CONFIG_FILE')      || define('CONFIG_FILE',      __DIR__ . '/config.php');
defined('CONFIG_DIST_FILE') || define('CONFIG_DIST_FILE', __DIR__ . '/config.php.dist');
defined('SOURCE_DIR')       || define('SOURCE_DIR',       __DIR__ . '/source');

// ── RECONFIGURE ───────────────────────────────────────────────────────────────
if (isset($_GET['reconfigure'])) {
    if (file_exists(CONFIG_FILE)) {
        $cfg = require CONFIG_FILE;
        $cfg['setConf'] = 0;
        write_config($cfg);
    }
    header('Location: /');
    exit;
}

// ── CONFIG GUARD ──────────────────────────────────────────────────────────────
$configExists = file_exists(CONFIG_FILE);
$config       = $configExists ? (require CONFIG_FILE) : [];

if (!$configExists || empty($config['setConf'])) {
    require __DIR__ . '/setup.php';
    exit;
}

// ── AUTO-REFETCH ──────────────────────────────────────────────────────────────
if (!empty($config['refetch']['enabled'])) {
    $rf    = $config['refetch'];
    $units = ['minutes' => 60, 'hours' => 3600, 'days' => 86400];
    $secs  = ($rf['interval_value'] ?? 1) * ($units[$rf['interval_unit'] ?? 'days'] ?? 86400);
    if (time() - ($rf['last_fetched'] ?? 0) >= $secs) {
        if (do_refetch($config)) {
            $config['refetch']['last_fetched'] = time();
            write_config($config);
        }
    }
}

// ── PARSE ─────────────────────────────────────────────────────────────────────
$classes     = parse_headers($config['headers_dir']);
$tree        = build_tree($classes);
$searchIndex = build_search_index($classes);

// ── DEBUG ─────────────────────────────────────────────────────────────────────
if (isset($_GET['debug_class'])) {
    $debugClass  = $_GET['debug_class'];
    $debugMethod = $_GET['debug_method'] ?? null;
    if (isset($classes[$debugClass])) {
        echo '<pre>';
        if ($debugMethod && isset($classes[$debugClass]['methods'][$debugMethod])) {
            var_dump($classes[$debugClass]['methods'][$debugMethod]);
        } else {
            var_dump($classes[$debugClass]['methods']);
        }
        echo '</pre>';
        exit;
    }
}

// ── ROUTING ───────────────────────────────────────────────────────────────────
$selectedClass  = $_GET['class']  ?? '';
$selectedMethod = $_GET['method'] ?? '';

$currentClass = $classes[$selectedClass] ?? null;
$allMethods   = $currentClass ? get_all_methods($selectedClass, $classes) : [];

$title = $config['site_title'];

if ($selectedMethod !== '' && $selectedClass !== '') {
    $pageTitle = $selectedClass . ':' . $selectedMethod . ' - ' . $title;
} elseif ($selectedClass !== '') {
    $pageTitle = $selectedClass . ' - ' . $title;
} else {
    $pageTitle = $title;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div id="app">
  <?php include __DIR__ . '/partials/topbar.php'; ?>
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main id="main">
    <?php include __DIR__ . '/partials/content.php'; ?>
  </main>
  <div id="sidebar-overlay"></div>
</div>
<script src="assets/app.js"></script>
</body>
</html>