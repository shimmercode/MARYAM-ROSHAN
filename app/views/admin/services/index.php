<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $result @var array $filters @var array $categories @var array $branches */
$rows = $result['data'];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / خدمات</div>
        <h1>خدمات</h1>
        <p><span class="mr-num"><?= fa((int)$result['total']) ?></span> خدمت تعریف شده است</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/services/categories') ?>">دسته‌بندی‌ها</a>
        <a class="mr-btn mr-btn--primary" href="<?= url('/admin/services/create') ?>">خدمت جدید</a>
    </div>
</div>

<form class="mr-card mb-4" method="get" action="<?= url('/admin/services') ?>">
    <div class="mr-card__body grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="mr-field mb-0 col-span-2">
            <label class="mr-label" for="search">جستجو</label>
            <input class="mr-input" id="search" name="search" placeholder="نام خدمت" value="<?= e($filters['search'] ?? '') ?>">
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="category_id">دسته</label>
            <select class="mr-select" id="category_id" name="category_id">
                <option value="">همه</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)($filters['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button class="mr-btn mr-btn--primary flex-1" type="submit">فیلتر</button>
            <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/services') ?>">حذف</a>
        </div>
    </div>
</form>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($rows === []): ?>
            <?= component('empty', [
                'icon' => '✂️', 'title' => 'خدمتی ثبت نشده است',
                'text' => 'اولین خدمت سالن را تعریف کنید تا امکان رزرو فراهم شود.',
                'actionHref' => '/admin/services/create', 'actionLabel' => 'افزودن خدمت',
            ]) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>نام</th><th>دسته</th><th>مدت</th><th>قیمت</th><th>رزرو آنلاین</th><th>وضعیت</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $s): ?>
                        <tr>
                            <td>
                                <a class="font-medium" href="<?= url('/admin/services/' . $s['id'] . '/edit') ?>"><?= e($s['name']) ?></a>
                                <?php if ((int)$s['is_featured'] === 1): ?><span class="mr-badge mr-badge--gold">ویژه</span><?php endif; ?>
                            </td>
                            <td class="text-xs"><?= e($s['category_name'] ?? '—') ?></td>
                            <td class="mr-num"><?= fa((int)$s['duration_minutes']) ?> دقیقه</td>
                            <td class="mr-num"><?= money($s['price'], false) ?></td>
                            <td><?= (int)$s['online_booking'] === 1 ? '✅' : '—' ?></td>
                            <td><?= component('status_badge', ['status' => $s['status']]) ?></td>
                            <td class="flex gap-1">
                                <a class="mr-btn mr-btn--ghost mr-btn--sm" href="<?= url('/admin/services/' . $s['id'] . '/edit') ?>">ویرایش</a>
                                <form method="post" action="<?= url('/admin/services/' . $s['id'] . '/toggle') ?>">
                                    <?= csrf_field() ?>
                                    <button class="mr-btn mr-btn--soft mr-btn--sm" type="submit">
                                        <?= $s['status'] === 'ACTIVE' ? 'غیرفعال' : 'فعال' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= component('pagination', ['page' => $result['page'], 'lastPage' => $result['last_page'], 'query' => $filters]) ?>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection();
