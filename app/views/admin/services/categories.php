<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $categories */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/services') ?>">خدمات</a> / دسته‌بندی</div>
        <h1>دسته‌بندی خدمات</h1>
        <p>دسته‌ها ترتیب نمایش خدمات در سایت و صندوق را تعیین می‌کنند.</p>
    </div>
    <a class="mr-btn mr-btn--ghost" href="<?= url('/admin/services') ?>">بازگشت</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="col-span-2">
        <div class="mr-card">
            <div class="mr-card__body">
                <?php if ($categories === []): ?>
                    <?= component('empty', ['icon' => '🗂', 'title' => 'دسته‌ای تعریف نشده است']) ?>
                <?php else: ?>
                    <div class="mr-table__wrap">
                        <table class="mr-table">
                            <thead><tr><th>نام</th><th>آیکن</th><th>تعداد خدمات</th><th>ترتیب</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($categories as $c): ?>
                                <tr>
                                    <td>
                                        <form class="flex gap-2 items-center" method="post"
                                              action="<?= url('/admin/services/categories/' . $c['id']) ?>">
                                            <?= csrf_field() ?>
                                            <input class="mr-input mr-input--sm" name="name" value="<?= e($c['name']) ?>" required>
                                            <input type="hidden" name="icon" value="<?= e($c['icon'] ?? '') ?>">
                                            <input type="hidden" name="sort_order" value="<?= (int)$c['sort_order'] ?>">
                                            <button class="mr-btn mr-btn--soft mr-btn--sm" type="submit">ذخیره</button>
                                        </form>
                                    </td>
                                    <td><?= e($c['icon'] ?? '') ?></td>
                                    <td class="mr-num"><?= fa((int)($c['services_count'] ?? 0)) ?></td>
                                    <td class="mr-num"><?= fa((int)$c['sort_order']) ?></td>
                                    <td>
                                        <form method="post" action="<?= url('/admin/services/categories/' . $c['id'] . '/delete') ?>"
                                              data-confirm="حذف این دسته؟ خدمات آن بدون دسته می‌شوند.">
                                            <?= csrf_field() ?>
                                            <button class="mr-btn mr-btn--danger mr-btn--sm" type="submit">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <aside>
        <div class="mr-card">
            <div class="mr-card__head"><h2 class="mr-card__title">دسته جدید</h2></div>
            <div class="mr-card__body">
                <form method="post" action="<?= url('/admin/services/categories') ?>">
                    <?= csrf_field() ?>
                    <div class="mr-field">
                        <label class="mr-label" for="name">نام دسته <span class="req">*</span></label>
                        <input class="mr-input" id="name" name="name" required>
                        <div class="mr-error" data-error-for="name"><?= e(error_for('name')) ?></div>
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="icon">آیکن (ایموجی)</label>
                        <input class="mr-input" id="icon" name="icon" maxlength="8" placeholder="💇‍♀️">
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="sort_order">ترتیب نمایش</label>
                        <input class="mr-input mr-num" id="sort_order" name="sort_order" type="number" value="0">
                    </div>
                    <div class="mr-field">
                        <label class="mr-label" for="description">توضیح</label>
                        <textarea class="mr-textarea" id="description" name="description" rows="2"></textarea>
                    </div>
                    <button class="mr-btn mr-btn--primary mr-btn--block" type="submit">افزودن دسته</button>
                </form>
            </div>
        </div>
    </aside>
</div>
<?php View::endSection();
