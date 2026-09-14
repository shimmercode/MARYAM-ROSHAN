<?php
use App\Core\View;
View::extend('public');
View::section('content');
/** @var array $branches */
?>
<section class="hero" style="padding-block:var(--mr-s7)">
    <div class="site-container">
        <h1 class="hero__title" style="font-size:clamp(1.6rem,3.4vw,2.5rem)">تماس با ما</h1>
        <p class="hero__lead">برای مشاوره، رزرو تلفنی یا هر پرسشی با ما در ارتباط باشید.</p>
    </div>
</section>

<section class="section">
    <div class="site-container grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h2 class="text-xl mb-4">اطلاعات تماس</h2>
            <?php if ($branches === []): ?>
                <?= component('empty', ['icon' => '📍', 'title' => 'اطلاعات شعبه ثبت نشده است']) ?>
            <?php else: ?>
                <?php foreach ($branches as $b): ?>
                    <div class="mr-card mb-4">
                        <div class="mr-card__body">
                            <h3 class="text-md mb-2"><?= e($b['name']) ?></h3>
                            <p class="text-sm text-muted mb-2">📍 <?= e($b['address']) ?></p>
                            <p class="text-sm mb-2 mr-num" dir="ltr">📞 <?= fa($b['phone']) ?></p>
                            <p class="text-sm text-muted mr-num">
                                🕐 شنبه تا پنجشنبه <?= fa(substr((string)$b['opening_time'], 0, 5)) ?>
                                تا <?= fa(substr((string)$b['closing_time'], 0, 5)) ?>
                            </p>
                            <?php if (!empty($b['latitude']) && !empty($b['longitude'])): ?>
                                <a class="mr-btn mr-btn--soft mr-btn--sm mt-3" target="_blank" rel="noopener"
                                   href="https://www.google.com/maps?q=<?= e($b['latitude']) ?>,<?= e($b['longitude']) ?>">مسیریابی</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div>
            <h2 class="text-xl mb-4">ارسال پیام</h2>
            <div class="mr-card">
                <div class="mr-card__body">
                    <form method="post" action="<?= url('/contact') ?>" id="contactForm">
                        <?= csrf_field() ?>
                        <div class="mr-field">
                            <label class="mr-label" for="name">نام و نام خانوادگی <span class="req">*</span></label>
                            <input class="mr-input" id="name" name="name" value="<?= e(old('name')) ?>" required>
                            <div class="mr-error" data-error-for="name"><?= e(error_for('name')) ?></div>
                        </div>
                        <div class="mr-field">
                            <label class="mr-label" for="mobile">موبایل <span class="req">*</span></label>
                            <input class="mr-input mr-num" id="mobile" name="mobile" dir="ltr" inputmode="numeric"
                                   placeholder="09121234567" value="<?= e(old('mobile')) ?>" required>
                            <div class="mr-error" data-error-for="mobile"><?= e(error_for('mobile')) ?></div>
                        </div>
                        <div class="mr-field">
                            <label class="mr-label" for="subject">موضوع</label>
                            <input class="mr-input" id="subject" name="subject" value="<?= e(old('subject')) ?>">
                        </div>
                        <div class="mr-field">
                            <label class="mr-label" for="message">پیام شما <span class="req">*</span></label>
                            <textarea class="mr-textarea" id="message" name="message" required><?= e(old('message')) ?></textarea>
                            <div class="mr-error" data-error-for="message"><?= e(error_for('message')) ?></div>
                        </div>
                        <button class="mr-btn mr-btn--primary mr-btn--block" type="submit" id="contactSubmit">ارسال پیام</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { busy } from '<?= asset('js/core.js') ?>';
document.getElementById('contactForm')?.addEventListener('submit', () => {
    busy(document.getElementById('contactSubmit'), true, 'در حال ارسال…');
});
</script>
<?php View::endSection();
