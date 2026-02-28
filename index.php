<?php
require_once __DIR__ . '/parse.php';
require_once __DIR__ . '/render.php';

$config  = require __DIR__ . '/config.php';
$classes = parse_headers($config['headers_dir']);
$tree    = build_tree($classes);
$searchIndex = build_search_index($classes);

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

$selectedClass  = $_GET['class']  ?? '';
$selectedMethod = $_GET['method'] ?? '';

$currentClass = $classes[$selectedClass] ?? null;
$allMethods   = $currentClass ? get_all_methods($selectedClass, $classes) : [];

$title = $config['site_title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div id="app">
  <?php include __DIR__ . '/partials/topbar.php'; ?>
  <?php include __DIR__ . '/partials/sidebar.php'; ?>
  <main id="main">
    <?php include __DIR__ . '/partials/method_list.php'; ?>
    <?php include __DIR__ . '/partials/content.php'; ?>
  </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>