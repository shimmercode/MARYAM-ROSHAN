<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $backups */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/settings') ?>">تنظیمات</a> / پشتیبان‌گیری</div>
        <h1>پشتیبان‌گیری پایگاه داده</h1>
        <p>۱۰ نسخه آخر نگهداری و نسخه‌های قدیمی‌تر به‌صورت خودکار حذف می‌شوند</p>
    </div>
    <form method="post" action="<?= url('/admin/settings/backups') ?>">
        <?= csrf_field() ?>
        <button class="mr-btn mr-btn--primary" type="submit">ساخت نسخه پشتیبان</button>
    </form>
</div>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($backups === []): ?>
            <?= component('empty', ['icon' => '💾', 'title' => 'نسخه پشتیبانی وجود ندارد', 'text' => 'برای شروع، روی «ساخت نسخه پشتیبان» بزنید.']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>نام فایل</th><th>حجم</th><th>تاریخ</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td class="text-xs" dir="ltr"><?= e($b['name']) ?></td>
                            <td class="mr-num"><?= fa(round(((int)$b['size']) / 1024, 1)) ?> کیلوبایت</td>
                            <td class="text-xs"><?= jdate($b['created_at'], 'j F Y — H:i') ?></td>
                            <td class="flex gap-2">
                                <a class="mr-btn mr-btn--soft mr-btn--sm"
                                   href="<?= url('/admin/settings/backups/' . rawurlencode($b['name']) . '/download') ?>">دانلود</a>
                                <form method="post" action="<?= url('/admin/settings/backups/' . rawurlencode($b['name']) . '/delete') ?>">
                                    <?= csrf_field() ?>
                                    <button class="mr-btn mr-btn--danger mr-btn--sm" type="submit"
                                            data-confirm="این نسخه پشتیبان حذف شود؟">حذف</button>
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
