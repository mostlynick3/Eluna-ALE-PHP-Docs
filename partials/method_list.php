<?php
/** @var array  $allMethods */
/** @var string $selectedClass */
/** @var string $selectedMethod */
/** @var array  $currentClass */
?>
<nav id="method-list">
  <div id="method-header">
    <?= $currentClass ? htmlspecialchars($selectedClass) . ' Methods' : 'Methods' ?>
  </div>

  <?php if ($currentClass): ?>
    <?php foreach ($allMethods as $mn => $m): ?>
      <?php $isInh = isset($m['inherited_from']); ?>
      <a class="method-item<?= $mn === $selectedMethod ? ' active' : '' ?><?= $isInh ? ' inherited' : '' ?>"
         href="?class=<?= urlencode($selectedClass) ?>&method=<?= urlencode($mn) ?>">
        <span class="<?= $isInh ? 'inh-dot' : 'own-dot' ?>"></span>
        <?= htmlspecialchars($mn) ?>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
</nav>