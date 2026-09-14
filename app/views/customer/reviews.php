<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var array $reviews @var array $reviewable */
?>
<?php if ($reviewable !== []): ?>
    <section class="mr-card mb-4">
        <div class="mr-card__head"><h2 class="mr-card__title">ثبت نظر جدید</h2></div>
        <div class="mr-card__body">
            <form method="post" action="<?= url('/customer/reviews') ?>">
                <?= csrf_field() ?>
                <div class="mr-field">
                    <label class="mr-label" for="appointment_id">کدام نوبت؟ <span class="req">*</span></label>
                    <select class="mr-select" id="appointment_id" name="appointment_id" required>
                        <?php foreach ($reviewable as $a): ?>
                            <option value="<?= (int)$a['id'] ?>">
                                <?= jdate($a['appointment_date'], 'j F Y') ?> — <?= e($a['services'] ?? '') ?> (<?= e($a['staff_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mr-field">
                    <span class="mr-label">امتیاز شما <span class="req">*</span></span>
                    <div class="rating-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="r<?= $i ?>" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                            <label for="r<?= $i ?>" title="<?= fa($i) ?> ستاره">★</label>
                        <?php endfor; ?>
                    </div>
                    <div class="mr-error" data-error-for="rating"><?= e(error_for('rating')) ?></div>
                </div>

                <div class="mr-field">
                    <label class="mr-label" for="title">عنوان</label>
                    <input class="mr-input" id="title" name="title" maxlength="120">
                </div>
                <div class="mr-field">
                    <label class="mr-label" for="comment">نظر شما</label>
                    <textarea class="mr-textarea" id="comment" name="comment" rows="3"
                              placeholder="تجربه‌تان از این مراجعه چطور بود؟"></textarea>
                </div>
                <button class="mr-btn mr-btn--primary mr-btn--block" type="submit">ثبت نظر</button>
                <p class="mr-help mt-2">نظر شما پس از بررسی توسط مدیریت در سایت منتشر می‌شود.</p>
            </form>
        </div>
    </section>
<?php endif; ?>

<section class="mr-card">
    <div class="mr-card__head"><h2 class="mr-card__title">نظرهای ثبت‌شده شما</h2></div>
    <div class="mr-card__body">
        <?php if ($reviews === []): ?>
            <?= component('empty', ['icon' => '💬', 'title' => 'هنوز نظری ثبت نکرده‌اید', 'text' => 'پس از هر مراجعه می‌توانید تجربه خود را ثبت کنید.']) ?>
        <?php else: ?>
            <?php foreach ($reviews as $r): ?>
                <div class="border-b py-3">
                    <div class="flex justify-between items-center mb-1">
                        <span class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span>
                        <?= component('status_badge', ['status' => $r['status']]) ?>
                    </div>
                    <?php if (!empty($r['title'])): ?><strong class="text-sm block"><?= e($r['title']) ?></strong><?php endif; ?>
                    <p class="text-sm text-muted"><?= e($r['comment'] ?? '') ?></p>
                    <small class="text-xs text-muted"><?= jdate($r['created_at'], 'j F Y') ?><?= !empty($r['service_name']) ? ' · ' . e($r['service_name']) : '' ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php View::endSection();
