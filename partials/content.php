<?php
/** @var array|null $currentClass */
/** @var array      $allMethods */
/** @var string     $selectedClass */
/** @var string     $selectedMethod */
/** @var array      $classes */
require_once __DIR__ . '/../render.php';
?>
<section id="content">
  <?php if (!$currentClass): ?>

    <div class="empty-state">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="1" color="#6e6e96">
        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
        <path d="M2 17l10 5 10-5"/>
        <path d="M2 12l10 5 10-5"/>
      </svg>
      Select a class from the sidebar to explore its API.
    </div>

  <?php elseif ($selectedMethod && isset($allMethods[$selectedMethod])): ?>

    <?php $m = $allMethods[$selectedMethod]; ?>
    <div class="breadcrumb">
      <a href="?class=<?= urlencode($selectedClass) ?>"><?= htmlspecialchars($selectedClass) ?></a>
      &rsaquo; <?= htmlspecialchars($selectedMethod) ?>
    </div>
    <?= render_method_card($selectedMethod, $m, false, $selectedClass, $classes) ?>

  <?php else: ?>

    <h1><?= htmlspecialchars($selectedClass) ?></h1>

    <?php if (!empty($currentClass['inherits'])): ?>
      <div class="inh-chain">
        <span class="inh-chain-label">Inherits:</span>
        <?php foreach ($currentClass['inherits'] as $par): ?>
          <a class="inh-link" href="?class=<?= urlencode($par) ?>"><?= htmlspecialchars($par) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php
      $ownCount = count($classes[$selectedClass]['methods']);
      $inhCount = count($allMethods) - $ownCount;
    ?>
    <div class="method-count-bar">
      <div class="mcb-item"><span class="mcb-dot-own"></span> <?= $ownCount ?> own methods</div>
      <?php if ($inhCount > 0): ?>
        <div class="mcb-item"><span class="mcb-dot-inh"></span> <?= $inhCount ?> inherited</div>
      <?php endif; ?>
    </div>

    <?php if (!empty($currentClass['desc'])): ?>
      <div class="class-desc"><?= render_desc($currentClass['desc'], $classes) ?></div>
    <?php endif; ?>

    <h2>Methods</h2>

    <?php foreach ($allMethods as $mn => $m): ?>
      <?= render_method_card($mn, $m, true, $selectedClass, $classes) ?>
    <?php endforeach; ?>

  <?php endif; ?>
</section>