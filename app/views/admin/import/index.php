<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $entities @var array $columns @var array $history @var array $branches */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / ورود اطلاعات</div>
        <h1>ورود اطلاعات از فایل</h1>
        <p>انتقال مشتریان، خدمات و محصولات از اکسل با گزارش خطای سطر‌به‌سطر</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="col-span-2">
        <div class="mr-card mb-4">
            <div class="mr-card__head"><h2 class="mr-card__title">۱. بارگذاری فایل</h2></div>
            <form class="mr-card__body" method="post" action="<?= url('/admin/import/preview') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="mr-alert mr-alert--info mb-3">
                    فایل اکسل خود را با فرمت <strong>CSV UTF-8</strong> ذخیره کنید. حداکثر حجم: ۴ مگابایت.
                    ابتدا قالب نمونه را دانلود و ستون‌ها را مطابق آن تنظیم کنید.
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="entity">نوع اطلاعات <span class="req">*</span></label>
                    <select class="mr-select" id="entity" name="entity" required>
                        <?php foreach ($entities as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="branch_id">شعبه مقصد</label>
                    <select class="mr-select" id="branch_id" name="branch_id">
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="file">فایل CSV <span class="req">*</span></label>
                    <input class="mr-input" id="file" name="file" type="file" accept=".csv,text/csv" required>
                </div>
                <button class="mr-btn mr-btn--primary" type="submit">بررسی و پیش‌نمایش</button>
            </form>
        </div>

        <div class="mr-card">
            <div class="mr-card__head"><h2 class="mr-card__title">تاریخچه ورود اطلاعات</h2></div>
            <div class="mr-card__body">
                <?php if ($history === []): ?>
                    <?= component('empty', ['icon' => '📥', 'title' => 'هنوز فایلی وارد نشده است']) ?>
                <?php else: ?>
                    <div class="mr-table__wrap">
                        <table class="mr-table">
                            <thead><tr><th>تاریخ</th><th>نوع</th><th>فایل</th><th>جدید</th><th>به‌روز</th><th>ناموفق</th><th>کاربر</th></tr></thead>
                            <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td class="text-xs"><?= jdate($h['created_at'], 'j F — H:i') ?></td>
                                    <td><?= e($entities[$h['entity']] ?? $h['entity']) ?></td>
                                    <td class="text-xs"><?= e($h['filename']) ?></td>
                                    <td class="mr-num"><?= fa((int)$h['imported']) ?></td>
                                    <td class="mr-num"><?= fa((int)$h['updated']) ?></td>
                                    <td class="mr-num <?= (int)$h['failed'] > 0 ? 'text-danger' : '' ?>"><?= fa((int)$h['failed']) ?></td>
                                    <td class="text-xs"><?= e($h['user_name'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a class="mr-btn mr-btn--ghost mr-btn--sm mt-3" href="<?= url('/admin/import/errors') ?>">دانلود خطاهای آخرین ورود</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <aside>
        <?php foreach ($entities as $key => $label): ?>
            <div class="mr-card mb-4">
                <div class="mr-card__head">
                    <h2 class="mr-card__title">ستون‌های <?= e($label) ?></h2>
                </div>
                <div class="mr-card__body">
                    <ul class="text-sm">
                        <?php foreach ($columns[$key] as $col => $title): ?>
                            <li class="flex justify-between border-b py-1">
                                <span><?= e($title) ?></span>
                                <code class="text-xs" dir="ltr"><?= e($col) ?></code>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="mr-btn mr-btn--soft mr-btn--sm mr-btn--block mt-3"
                       href="<?= url('/admin/import/template/' . $key) ?>">دانلود قالب نمونه</a>
                </div>
            </div>
        <?php endforeach; ?>
    </aside>
</div>
<?php View::endSection();
