<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/**
 * @var string $entity @var string $label @var string $filename @var array $columns
 * @var array $headers @var array $valid @var array $invalid @var array $summary @var array $branches
 */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/import') ?>">ورود اطلاعات</a> / پیش‌نمایش</div>
        <h1>پیش‌نمایش «<?= e($label) ?>»</h1>
        <p>فایل: <span dir="ltr"><?= e($filename) ?></span></p>
    </div>
    <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/import') ?>">انصراف و بارگذاری مجدد</a>
</div>

<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <?= component('kpi', ['label' => 'کل سطرها', 'icon' => '📄', 'value' => fa((int)$summary['total'])]) ?>
    <?= component('kpi', ['label' => 'رکورد جدید', 'icon' => '➕', 'value' => fa((int)$summary['insert'])]) ?>
    <?= component('kpi', ['label' => 'به‌روزرسانی', 'icon' => '🔄', 'value' => fa((int)$summary['update'])]) ?>
    <?= component('kpi', ['label' => 'دارای خطا', 'icon' => '⚠️', 'value' => fa((int)$summary['failed'])]) ?>
</section>

<?php if ($invalid !== []): ?>
    <div class="mr-card mb-4">
        <div class="mr-card__head">
            <h2 class="mr-card__title">سطرهای دارای خطا</h2>
            <span class="mr-badge mr-badge--danger"><?= fa(count($invalid)) ?> سطر</span>
        </div>
        <div class="mr-card__body">
            <p class="mr-help mb-3">این سطرها ثبت نخواهند شد. آن‌ها را در فایل اصلاح و دوباره بارگذاری کنید.</p>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>سطر</th><th>خطاها</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($invalid, 0, 100) as $row): ?>
                        <tr>
                            <td class="mr-num"><?= fa((int)$row['line']) ?></td>
                            <td>
                                <?php foreach ($row['errors'] as $err): ?>
                                    <span class="mr-badge mr-badge--danger"><?= e($err) ?></span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="mr-card mb-4">
    <div class="mr-card__head">
        <h2 class="mr-card__title">سطرهای آماده ثبت</h2>
        <span class="mr-badge mr-badge--success"><?= fa(count($valid)) ?> سطر (حداکثر ۱۰۰ سطر نمایش داده می‌شود)</span>
    </div>
    <div class="mr-card__body">
        <?php if ($valid === []): ?>
            <?= component('empty', ['icon' => '🚫', 'title' => 'هیچ سطر معتبری وجود ندارد', 'text' => 'فایل را اصلاح کنید و دوباره بارگذاری نمایید.', 'actionHref' => '/admin/import', 'actionLabel' => 'بازگشت']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead>
                    <tr>
                        <th>سطر</th><th>اقدام</th>
                        <?php foreach ($columns as $title): ?><th><?= e($title) ?></th><?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($valid as $row): ?>
                        <tr>
                            <td class="mr-num"><?= fa((int)$row['line']) ?></td>
                            <td>
                                <span class="mr-badge mr-badge--<?= $row['action'] === 'INSERT' ? 'success' : 'info' ?>">
                                    <?= $row['action'] === 'INSERT' ? 'جدید' : 'به‌روزرسانی' ?>
                                </span>
                            </td>
                            <?php foreach (array_keys($columns) as $col): $val = $row['data'][$col] ?? ''; ?>
                                <td class="<?= is_numeric($val) ? 'mr-num' : '' ?>"><?= e(is_numeric($val) ? (string)fa($val) : (string)$val) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($valid !== []): ?>
    <form class="mr-card" method="post" action="<?= url('/admin/import/commit') ?>">
        <?= csrf_field() ?>
        <div class="mr-card__body flex items-end gap-3">
            <div class="mr-field mb-0">
                <label class="mr-label" for="branch_id">شعبه مقصد</label>
                <select class="mr-select" id="branch_id" name="branch_id">
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="mr-btn mr-btn--primary" type="submit"
                    data-confirm="<?= fa(count($valid)) ?> سطر ثبت نهایی شود؟">
                ثبت نهایی <?= fa(count($valid)) ?> سطر
            </button>
        </div>
    </form>
<?php endif; ?>
<?php View::endSection();
