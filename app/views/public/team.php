<?php
use App\Core\View;
View::extend('public');
View::section('content');
/** @var array $team */
?>
<section class="hero" style="padding-block:var(--mr-s7)">
    <div class="site-container">
        <h1 class="hero__title" style="font-size:clamp(1.6rem,3.4vw,2.5rem)">تیم متخصصان</h1>
        <p class="hero__lead">هر عضو تیم ما در حوزه تخصصی خود آموزش‌دیده و دارای سابقه اجرایی است.</p>
    </div>
</section>

<section class="section">
    <div class="site-container">
        <?php if ($team === []): ?>
            <?= component('empty', ['icon' => '🎓', 'title' => 'هنوز عضوی معرفی نشده است']) ?>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <?php foreach ($team as $m): ?>
                    <div class="team-card">
                        <?php if (!empty($m['avatar'])): ?>
                            <img class="team-card__avatar" src="<?= e(url($m['avatar'])) ?>" alt="<?= e($m['first_name']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="team-card__avatar"><?= e(mb_substr((string)$m['first_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <h2 class="text-md mb-1"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></h2>
                        <p class="text-sm text-muted mb-2"><?= e($m['job_title'] ?? '') ?></p>
                        <?php if (!empty($m['specialties'])): ?>
                            <p class="text-xs text-muted mb-2"><?= e($m['specialties']) ?></p>
                        <?php endif; ?>
                        <?php if ((float)$m['rating'] > 0): ?>
                            <div class="text-sm mb-3"><span class="stars">★</span> <span class="mr-num"><?= fa(number_format((float)$m['rating'], 1)) ?></span></div>
                        <?php endif; ?>
                        <a class="mr-btn mr-btn--soft mr-btn--sm" href="<?= url('/booking') ?>">رزرو نوبت</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php View::endSection();
