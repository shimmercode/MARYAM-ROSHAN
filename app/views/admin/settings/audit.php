<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $logs @var int $page @var int $lastPage @var int $total @var array $actions @var array $filters */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/settings') ?>">تنظیمات</a> / فعالیت‌ها</div>
        <h1>گزارش فعالیت‌ها</h1>
        <p><span class="mr-num"><?= fa($total) ?></span> رویداد ثبت‌شده</p>
    </div>
</div>

<form class="mr-card mb-4" method="get" action="<?= url('/admin/settings/audit') ?>">
    <div class="mr-card__body flex gap-3">
        <select class="mr-select flex-1" name="action" aria-label="نوع فعالیت">
            <option value="">همه فعالیت‌ها</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?= e($a['action']) ?>" <?= ($filters['action'] ?? '') === $a['action'] ? 'selected' : '' ?>>
                    <?= e($a['action']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="mr-btn mr-btn--primary" type="submit">فیلتر</button>
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/settings/audit') ?>">حذف فیلتر</a>
    </div>
</form>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($logs === []): ?>
            <?= component('empty', ['icon' => '🧾', 'title' => 'رویدادی ثبت نشده است']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>زمان</th><th>کاربر</th><th>فعالیت</th><th>موجودیت</th><th>شناسه</th><th>IP</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="text-xs"><?= e($l['created_fa']) ?></td>
                            <td><?= e($l['user_name'] ?? 'سیستم') ?></td>
                            <td><code class="text-xs" dir="ltr"><?= e($l['action']) ?></code></td>
                            <td class="text-xs"><?= e($l['entity'] ?? '—') ?></td>
                            <td class="mr-num text-xs"><?= $l['entity_id'] ? fa((int)$l['entity_id']) : '—' ?></td>
                            <td class="mr-num text-xs" dir="ltr"><?= e($l['ip_address'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= component('pagination', ['page' => $page, 'lastPage' => $lastPage, 'query' => $filters]) ?>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection();
