<?php
/** Empty state. @var string $title @var string $text @var string $icon @var string|null $actionHref @var string|null $actionLabel */
?>
<div class="mr-state">
    <div class="mr-state__icon" aria-hidden="true"><?= $icon ?? '📭' ?></div>
    <div class="mr-state__title"><?= e($title ?? 'موردی یافت نشد') ?></div>
    <div class="mr-state__text"><?= e($text ?? 'هنوز داده‌ای برای نمایش ثبت نشده است.') ?></div>
    <?php if (!empty($actionHref)): ?>
        <a class="mr-btn mr-btn--soft mt-4" href="<?= url($actionHref) ?>"><?= e($actionLabel ?? 'افزودن') ?></a>
    <?php endif; ?>
</div>
