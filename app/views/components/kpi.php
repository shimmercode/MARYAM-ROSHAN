<?php
/**
 * KPI tile — literal `.kc`/`.kt`/`.ki`/`.kv`/`.kl`/`.kch`/`.ks` mockup vocabulary.
 * Same call signature as before this rebuild, so every existing call site
 * (dashboard, customer 360, staff profile, invoices index) needed no changes.
 *
 * @var string      $label
 * @var string      $value
 * @var string|null $icon
 * @var float|null  $delta   percentage change (null → hidden)
 * @var string|null $hint
 * @var string|null $href
 * @var string|null $tone    primary|success|warning|danger|info (default: derived from delta)
 */
$delta = $delta ?? null;
$dir   = $delta === null ? '' : ($delta >= 0 ? 'up' : 'down');
$tag   = !empty($href) ? 'a' : 'div';
$tone  = $tone ?? ($dir === 'down' ? 'danger' : 'primary');
$toneClass = match ($tone) {
    'success' => 's',
    'warning' => 'w',
    'danger'  => 'd',
    'info'    => 'a',
    default   => '',
};
?>
<<?= $tag ?> class="kc <?= $toneClass ?>" <?= !empty($href) ? 'href="' . url($href) . '"' : '' ?>>
    <div class="kt">
        <span class="kl"><?= e($label) ?></span>
        <?php if (!empty($icon)): ?>
            <span class="ki" aria-hidden="true"><?= $icon ?></span>
        <?php endif; ?>
    </div>
    <div class="kv mr-num"><?= e($value) ?></div>
    <?php if ($delta !== null): ?>
        <span class="kch <?= $dir === 'up' ? 'up' : 'dn' ?>">
            <?= $delta >= 0 ? '▲' : '▼' ?> <?= fa(abs((float)$delta)) ?>٪
        </span>
        <span class="ks">نسبت به دیروز</span>
    <?php elseif (!empty($hint)): ?>
        <span class="ks"><?= e($hint) ?></span>
    <?php endif; ?>
</<?= $tag ?>>
