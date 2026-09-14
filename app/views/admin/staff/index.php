<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $result @var array $filters @var array $branches */
$rows = $result['data'];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / کارکنان</div>
        <h1>کارکنان</h1>
        <p><span class="mr-num"><?= fa((int)$result['total']) ?></span> نفر در تیم</p>
    </div>
    <a class="mr-btn mr-btn--primary" href="<?= url('/admin/staff/create') ?>">کارمند جدید</a>
</div>

<form class="mr-card mb-4" method="get" action="<?= url('/admin/staff') ?>">
    <div class="mr-card__body grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="mr-field mb-0 col-span-2">
            <label class="mr-label" for="search">جستجو</label>
            <input class="mr-input" id="search" name="search" placeholder="نام یا موبایل" value="<?= e($filters['search'] ?? '') ?>">
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="branch_id">شعبه</label>
            <select class="mr-select" id="branch_id" name="branch_id">
                <option value="">همه</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= (int)($filters['branch_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>>
                        <?= e($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button class="mr-btn mr-btn--primary flex-1" type="submit">فیلتر</button>
            <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/staff') ?>">حذف</a>
        </div>
    </div>
</form>

<?php if ($rows === []): ?>
    <?= component('empty', [
        'icon' => '👩‍🎨', 'title' => 'کارمندی ثبت نشده است',
        'text' => 'اعضای تیم را اضافه کنید تا بتوانید برایشان نوبت رزرو کنید.',
        'actionHref' => '/admin/staff/create', 'actionLabel' => 'افزودن کارمند',
    ]) ?>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php foreach ($rows as $s): ?>
            <article class="mr-card">
                <div class="mr-card__body">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="mr-avatar" style="width:48px;height:48px;background:<?= e($s['color'] ?? '#C9A227') ?>">
                            <?= e(mb_substr((string)$s['first_name'], 0, 1)) ?>
                        </span>
                        <div>
                            <a class="font-medium block" href="<?= url('/admin/staff/' . $s['id']) ?>">
                                <?= e($s['first_name'] . ' ' . $s['last_name']) ?>
                            </a>
                            <span class="text-xs text-muted"><?= e($s['job_title'] ?? '—') ?></span>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm border-b py-2">
                        <span class="text-muted">شعبه</span><span><?= e($s['branch_name'] ?? '—') ?></span>
                    </div>
                    <div class="flex justify-between text-sm border-b py-2">
                        <span class="text-muted">امتیاز</span>
                        <span class="mr-num">⭐ <?= fa(number_format((float)$s['rating'], 1)) ?></span>
                    </div>
                    <div class="flex justify-between text-sm py-2">
                        <span class="text-muted">وضعیت</span>
                        <?= component('status_badge', ['status' => $s['status']]) ?>
                    </div>
                    <div class="flex gap-2 mt-2">
                        <a class="mr-btn mr-btn--soft mr-btn--sm flex-1" href="<?= url('/admin/staff/' . $s['id']) ?>">پرونده</a>
                        <a class="mr-btn mr-btn--ghost mr-btn--sm" href="<?= url('/admin/staff/' . $s['id'] . '/edit') ?>">ویرایش</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?= component('pagination', ['page' => $result['page'], 'lastPage' => $result['last_page'], 'query' => $filters]) ?>
<?php endif; ?>
<?php View::endSection();
