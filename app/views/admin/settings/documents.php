<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $documents @var array $categories */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/settings') ?>">تنظیمات</a> / آیین‌نامه و اسناد</div>
        <h1>آیین‌نامه و اسناد</h1>
        <p>بارگذاری و مدیریت اسناد قابل‌دانلود سالن (آیین‌نامه‌ها، قراردادها، صورتجلسه‌ها و…)</p>
    </div>
</div>

<div class="mr-card mb-4">
    <div class="mr-card__head"><h2 class="mr-card__title">بارگذاری سند جدید</h2></div>
    <div class="mr-card__body">
        <form method="post" action="<?= url('/admin/settings/documents') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mr-field">
                    <label class="mr-label" for="doc_title">عنوان سند</label>
                    <input class="mr-input" id="doc_title" name="title" type="text" required value="<?= e(old('title')) ?>">
                    <div class="mr-error" data-error-for="title"><?= e(error_for('title')) ?></div>
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="doc_category">دسته‌بندی</label>
                    <select class="mr-input" id="doc_category" name="category" required>
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= old('category') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mr-field md:col-span-2">
                    <label class="mr-label" for="doc_description">توضیحات (اختیاری)</label>
                    <textarea class="mr-textarea" id="doc_description" name="description" rows="2"><?= e(old('description')) ?></textarea>
                </div>
                <div class="mr-field md:col-span-2">
                    <label class="mr-label" for="doc_file">فایل سند</label>
                    <input class="mr-input" id="doc_file" name="file" type="file" required
                           accept=".pdf,.docx,.pptx,.xlsx,.csv,.jpg,.jpeg,.png,.webp">
                    <small class="text-xs text-muted">فرمت‌های مجاز: PDF، Word (docx)، PowerPoint (pptx)، Excel (xlsx)، تصویر — حداکثر ۵ مگابایت.</small>
                </div>
            </div>
            <button class="mr-btn mr-btn--primary mt-2" type="submit">بارگذاری سند</button>
        </form>
    </div>
</div>

<div class="mr-card">
    <div class="mr-card__head"><h2 class="mr-card__title">اسناد بارگذاری‌شده (<?= fa(count($documents)) ?>)</h2></div>
    <div class="mr-card__body">
        <?php if ($documents === []): ?>
            <?= component('empty', ['icon' => '📄', 'title' => 'سندی بارگذاری نشده است']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>عنوان</th><th>دسته‌بندی</th><th>فایل</th><th>حجم</th><th>بارگذاری‌کننده</th><th>تاریخ</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($documents as $d): ?>
                        <tr>
                            <td>
                                <strong><?= e($d['title']) ?></strong>
                                <?php if (!empty($d['description'])): ?><br><small class="text-xs text-muted"><?= e($d['description']) ?></small><?php endif; ?>
                            </td>
                            <td><span class="mr-badge mr-badge--info"><?= e($categories[$d['category']] ?? $d['category']) ?></span></td>
                            <td class="text-xs" dir="ltr"><?= e($d['original_filename']) ?></td>
                            <td class="mr-num"><?= fa(round(((int)$d['file_size']) / 1024, 1)) ?> کیلوبایت</td>
                            <td><?= e($d['uploader_name'] ?? '—') ?></td>
                            <td class="text-xs"><?= jdate($d['created_at'], 'j F Y') ?></td>
                            <td class="flex gap-2">
                                <a class="mr-btn mr-btn--soft mr-btn--sm"
                                   href="<?= url('/admin/settings/documents/' . $d['id'] . '/download') ?>">دانلود</a>
                                <form method="post" action="<?= url('/admin/settings/documents/' . $d['id'] . '/delete') ?>">
                                    <?= csrf_field() ?>
                                    <button class="mr-btn mr-btn--danger mr-btn--sm" type="submit"
                                            data-confirm="این سند حذف شود؟">حذف</button>
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
<?php View::endSection();
