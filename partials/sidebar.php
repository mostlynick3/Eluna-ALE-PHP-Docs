<?php
/** @var array  $classes */
/** @var string $selectedClass */
/** @var array  $tree */
?>
<aside id="sidebar">
  <div id="sidebar-header">Classes</div>
  <div id="sidebar-tree"></div>
</aside>

<script>
  window.TREE_DATA      = <?= json_encode($tree, JSON_UNESCAPED_UNICODE) ?>;
  window.CLASSES_DATA   = <?= json_encode(
      array_map(fn($c) => [
          'name'        => $c['name'],
          'inherits'    => $c['inherits'],
          'methodCount' => count($c['methods']),
      ], $classes),
      JSON_UNESCAPED_UNICODE
  ) ?>;
  window.SELECTED_CLASS = <?= json_encode($selectedClass) ?>;
</script>