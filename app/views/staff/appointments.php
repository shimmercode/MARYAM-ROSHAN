<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var array $appointments @var array $statuses @var array $transitions @var array $filters @var int $page @var int $lastPage */
?>
<form class="mr-card mb-4" method="get" action="<?= url('/staff/appointments') ?>">
    <div class="mr-card__body grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="mr-field mb-0">
            <label class="mr-label" for="status">وضعیت</label>
            <select class="mr-select" id="status" name="status">
                <option value="">همه</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mr-field mb-0">
            <label class="mr-label" for="date">تاریخ</label>
            <input class="mr-input mr-num" id="date" name="date" type="date" dir="ltr" value="<?= e($filters['date']) ?>">
        </div>
        <div class="flex items-end gap-2">
            <button class="mr-btn mr-btn--primary flex-1" type="submit">اعمال فیلتر</button>
            <a class="mr-btn mr-btn--ghost" href="<?= url('/staff/appointments') ?>">حذف</a>
        </div>
    </div>
</form>

<?php if ($appointments === []): ?>
    <?= component('empty', ['icon' => '🔍', 'title' => 'نوبتی با این شرایط یافت نشد', 'text' => 'فیلترها را تغییر دهید.']) ?>
<?php else: ?>
    <?php foreach ($appointments as $a): ?>
        <article class="mr-card mb-3">
            <div class="mr-card__body">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <div class="font-bold"><?= e($a['date_fa']) ?> — <span class="mr-num"><?= fa(substr((string)$a['start_time'], 0, 5)) ?></span></div>
                        <a class="text-sm" href="<?= url('/staff/customers/' . $a['customer_id']) ?>"><?= e($a['customer_name']) ?></a>
                    </div>
                    <?= component('status_badge', ['status' => $a['status']]) ?>
                </div>

                <div class="text-sm text-muted mb-1">✂️ <?= e($a['services'] ?? '') ?></div>
                <a class="text-sm mr-num" dir="ltr" href="tel:<?= e($a['mobile']) ?>"><?= fa($a['mobile']) ?></a>
                <?php if (!empty($a['notes'])): ?>
                    <div class="mr-alert mr-alert--info mt-2 text-xs"><span>📝</span><span><?= e($a['notes']) ?></span></div>
                <?php endif; ?>

                <?php $allowed = $transitions[$a['status']] ?? []; ?>
                <?php if ($allowed !== []): ?>
                    <div class="flex gap-2 flex-wrap mt-3">
                        <?php foreach ($allowed as $target): ?>
                            <form method="post" action="<?= url('/staff/appointments/' . $a['id'] . '/status') ?>"
                                  <?= in_array($target, ['CANCELLED', 'NO_SHOW'], true) ? 'data-confirm="از تغییر وضعیت مطمئن هستید؟"' : '' ?>>
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= e($target) ?>">
                                <button class="mr-btn mr-btn--sm <?= $target === 'COMPLETED' ? 'mr-btn--primary' : 'mr-btn--soft' ?>" type="submit">
                                    <?= e($statuses[$target] ?? $target) ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?= component('pagination', ['page' => $page, 'lastPage' => $lastPage, 'query' => $filters]) ?>
<?php endif; ?>
<?php View::endSection();
