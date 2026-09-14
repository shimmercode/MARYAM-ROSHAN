<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $notifications @var string $status @var int $unreadCount */
$tabs = ['ALL' => 'همه', 'UNREAD' => 'خوانده‌نشده', 'READ' => 'خوانده‌شده', 'ARCHIVED' => 'بایگانی'];
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb">پنل مدیریت / اعلان‌ها</div>
        <h1>اعلان‌ها</h1>
        <p><span class="mr-num"><?= fa($unreadCount) ?></span> اعلان خوانده‌نشده</p>
    </div>
    <?php if ($unreadCount > 0): ?>
        <form method="post" action="<?= url('/admin/notifications/read-all') ?>">
            <?= csrf_field() ?>
            <button class="mr-btn mr-btn--ghost" type="submit">علامت‌گذاری همه به‌عنوان خوانده‌شده</button>
        </form>
    <?php endif; ?>
</div>

<nav class="flex gap-2 mb-4">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="mr-btn mr-btn--sm <?= $status === $key ? 'mr-btn--primary' : 'mr-btn--ghost' ?>"
           href="<?= url('/admin/notifications?status=' . $key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($notifications === []): ?>
            <?= component('empty', ['icon' => '🔔', 'title' => 'اعلانی وجود ندارد', 'text' => 'رویدادهای مهم سامانه اینجا نمایش داده می‌شوند.']) ?>
        <?php else: ?>
            <?php foreach ($notifications as $n): $unread = $n['status'] === 'UNREAD'; ?>
                <div class="flex items-start gap-3 border-b py-3 <?= $unread ? 'font-medium' : '' ?>">
                    <span class="mr-avatar" style="width:36px;height:36px"><?= e($n['icon'] ?: ($unread ? '🔵' : '⚪️')) ?></span>
                    <div class="flex-1">
                        <strong class="block"><?= e($n['title']) ?></strong>
                        <div class="text-sm text-muted"><?= e((string)$n['body']) ?></div>
                        <div class="text-xs text-muted mt-1"><?= e($n['created_fa']) ?></div>
                    </div>
                    <div class="flex gap-2">
                        <?php if (!empty($n['link'])): ?>
                            <a class="mr-btn mr-btn--soft mr-btn--sm" href="<?= url($n['link']) ?>">مشاهده</a>
                        <?php endif; ?>
                        <?php if ($unread): ?>
                            <form method="post" action="<?= url('/admin/notifications/' . $n['id'] . '/read') ?>">
                                <?= csrf_field() ?>
                                <button class="mr-btn mr-btn--ghost mr-btn--sm" type="submit">خواندم</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($n['status'] !== 'ARCHIVED'): ?>
                            <form method="post" action="<?= url('/admin/notifications/' . $n['id'] . '/archive') ?>">
                                <?= csrf_field() ?>
                                <button class="mr-iconbtn" type="submit" title="بایگانی">🗄</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php View::endSection();
