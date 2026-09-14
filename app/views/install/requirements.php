<?php
use App\Core\View;
View::extend('blank');
View::section('content');
/** @var array $checks @var bool $passed */
?>
<div class="mr-auth__head">
    <div class="mr-auth__mark">۲</div>
    <h1 class="text-xl mb-1">بررسی پیش‌نیازها</h1>
    <p class="text-sm text-muted m-0">موارد الزامی باید سبز باشند تا نصب ادامه یابد.</p>
</div>
<div class="mr-auth__body">
    <div class="mr-steps"><span class="done"></span><span class="done"></span><span></span><span></span></div>
    <?= partial('flash') ?>

    <div class="border rounded-lg overflow-hidden">
        <?php foreach ($checks as $c): ?>
            <div class="flex items-center justify-between px-4 py-3 border-b">
                <div>
                    <div class="text-sm font-medium"><?= e($c['label']) ?>
                        <?php if (!$c['required']): ?><span class="mr-badge">اختیاری</span><?php endif; ?>
                    </div>
                    <div class="text-xs text-muted"><?= e($c['hint']) ?></div>
                </div>
                <span class="mr-badge <?= $c['ok'] ? 'mr-badge--success' : ($c['required'] ? 'mr-badge--danger' : 'mr-badge--warning') ?>">
                    <?= $c['ok'] ? '✓ تأیید' : '✕ ناموفق' ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($passed): ?>
        <a class="mr-btn mr-btn--primary mr-btn--block mr-btn--lg mt-4" href="<?= url('/install/database') ?>">ادامه</a>
    <?php else: ?>
        <div class="mr-alert mr-alert--error mt-4">
            <span aria-hidden="true">⚠️</span>
            <span>برخی پیش‌نیازهای الزامی برقرار نیست. پس از رفع، صفحه را بازخوانی کنید.</span>
        </div>
        <a class="mr-btn mr-btn--ghost mr-btn--block mt-2" href="<?= url('/install/requirements') ?>">بررسی مجدد</a>
    <?php endif; ?>
</div>
<?php View::endSection();
