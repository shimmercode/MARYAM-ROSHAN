<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $result @var array $filters @var array $branches @var array $tiers @var array $tags */
$rows = $result['data'];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / مشتریان</div>
        <h1>مشتریان</h1>
        <p><span class="mr-num"><?= fa((int)$result['total']) ?></span> مشتری ثبت شده است</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/import') ?>">ورود از فایل</a>
        <a class="mr-btn mr-btn--primary" href="<?= url('/admin/customers/create') ?>">مشتری جدید</a>
    </div>
</div>

<form class="mr-card mb-4" method="get" action="<?= url('/admin/customers') ?>">
    <div class="mr-card__body grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="mr-field mb-0 col-span-2">
            <label class="mr-label" for="search">جستجو</label>
            <input class="mr-input" id="search" name="search" placeholder="نام، موبایل یا کد مشتری"
                   value="<?= e($filters['search']) ?>">
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="status">وضعیت</label>
            <select class="mr-select" id="status" name="status">
                <option value="">همه</option>
                <?php foreach (['ACTIVE' => 'فعال', 'INACTIVE' => 'غیرفعال', 'BLACKLIST' => 'لیست سیاه'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="tier_id">سطح وفاداری</label>
            <select class="mr-select" id="tier_id" name="tier_id">
                <option value="">همه</option>
                <?php foreach ($tiers as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)$filters['tier_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button class="mr-btn mr-btn--primary flex-1" type="submit">فیلتر</button>
            <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/customers') ?>">حذف</a>
        </div>
    </div>
</form>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($rows === []): ?>
            <?= component('empty', [
                'icon' => '🙍‍♀️', 'title' => 'مشتری‌ای یافت نشد',
                'text' => 'با فیلترهای فعلی نتیجه‌ای وجود ندارد یا هنوز مشتری ثبت نشده است.',
                'actionHref' => '/admin/customers/create', 'actionLabel' => 'ثبت مشتری جدید',
            ]) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead>
                    <tr>
                        <th>کد</th><th>نام</th><th>موبایل</th><th>سطح</th>
                        <th>مراجعه</th><th>مجموع خرید</th><th>آخرین مراجعه</th><th>وضعیت</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $c): ?>
                        <tr>
                            <td class="mr-num text-xs"><?= e($c['code']) ?></td>
                            <td>
                                <a class="font-medium" href="<?= url('/admin/customers/' . $c['id']) ?>">
                                    <?= e($c['first_name'] . ' ' . $c['last_name']) ?>
                                </a>
                            </td>
                            <td class="mr-num" dir="ltr"><?= fa($c['mobile']) ?></td>
                            <td><span class="mr-badge mr-badge--gold"><?= e($c['tier_name'] ?? 'برنزی') ?></span></td>
                            <td class="mr-num"><?= fa((int)$c['visits_count']) ?></td>
                            <td class="mr-num"><?= money($c['total_spent'], false) ?></td>
                            <td class="text-xs"><?= $c['last_visit_at'] ? jdate($c['last_visit_at'], 'j F Y') : '—' ?></td>
                            <td><?= component('status_badge', ['status' => $c['status']]) ?></td>
                            <td class="mr-table__actions">
                                <a class="mr-iconbtn mr-iconbtn--sm" href="<?= url('/admin/customers/' . $c['id']) ?>" title="پرونده" aria-label="پرونده"><i class="bi bi-eye"></i></a>
                                <a class="mr-iconbtn mr-iconbtn--sm" href="<?= url('/admin/customers/' . $c['id'] . '/edit') ?>" title="ویرایش" aria-label="ویرایش"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= component('pagination', [
                'page' => $result['page'], 'lastPage' => $result['last_page'], 'query' => $filters,
            ]) ?>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection();
