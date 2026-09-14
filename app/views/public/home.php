<?php
use App\Core\View;
View::extend('public');
View::section('content');
/** @var array $categories @var array $featured @var array $team @var array $reviews @var array $posts @var array $stats @var array|null $branch */
?>
<section class="hero">
    <div class="site-container grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
        <div>
            <span class="hero__eyebrow">✨ رزرو آنلاین در کمتر از یک دقیقه</span>
            <h1 class="hero__title">زیبایی شما، <em>تخصص ما</em></h1>
            <p class="hero__lead">
                از رنگ و کراتین مو تا مراقبت پوست، ناخن و میکاپ عروس — با تیمی از متخصصان مجرب
                و مواد درجه‌یک، در فضایی آرام و حرفه‌ای.
            </p>
            <div class="flex gap-3 mt-5 flex-wrap">
                <a class="mr-btn mr-btn--primary mr-btn--lg" href="<?= url('/booking') ?>">رزرو نوبت آنلاین</a>
                <a class="mr-btn mr-btn--ghost mr-btn--lg" href="<?= url('/services') ?>">مشاهده خدمات و قیمت‌ها</a>
            </div>
            <div class="hero__stats">
                <div class="hero__stat"><b class="mr-num"><?= fa($stats['customers']) ?>+</b><span>مشتری</span></div>
                <div class="hero__stat"><b class="mr-num"><?= fa($stats['services']) ?></b><span>خدمت تخصصی</span></div>
                <div class="hero__stat"><b class="mr-num"><?= fa($stats['staff']) ?></b><span>متخصص</span></div>
            </div>
        </div>
        <div class="mr-card p-5 shadow-lg" style="border-radius:var(--mr-radius-lg)">
            <h2 class="text-lg mb-3">ساعات کاری</h2>
            <?php if ($branch): ?>
                <div class="flex justify-between border-b py-2 text-sm">
                    <span>شنبه تا پنجشنبه</span>
                    <span class="mr-num font-medium">
                        <?= fa(substr((string)$branch['opening_time'], 0, 5)) ?> تا <?= fa(substr((string)$branch['closing_time'], 0, 5)) ?>
                    </span>
                </div>
                <div class="flex justify-between border-b py-2 text-sm"><span>جمعه</span><span class="text-muted">تعطیل</span></div>
                <p class="text-sm text-muted mt-4"><?= e($branch['address']) ?></p>
                <p class="text-sm mr-num" dir="ltr"><?= fa($branch['phone']) ?></p>
            <?php else: ?>
                <p class="text-sm text-muted">اطلاعات شعبه هنوز ثبت نشده است.</p>
            <?php endif; ?>
            <a class="mr-btn mr-btn--soft mr-btn--block mt-4" href="<?= url('/contact') ?>">مسیریابی و تماس</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="site-container">
        <div class="section__head">
            <h2>دسته‌بندی خدمات</h2>
            <p>هر آنچه برای مراقبت و زیبایی نیاز دارید، زیر یک سقف.</p>
        </div>
        <?php if ($categories === []): ?>
            <?= component('empty', ['icon' => '✂️', 'title' => 'خدمتی ثبت نشده است']) ?>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($categories as $c): ?>
                    <a class="svc-card" href="<?= url('/services?category=' . urlencode((string)$c['slug'])) ?>">
                        <div class="svc-card__media"><?= $c['icon'] ?: '✨' ?></div>
                        <div class="svc-card__body">
                            <h3 class="text-lg mb-1"><?= e($c['name']) ?></h3>
                            <p class="text-sm text-muted"><?= e($c['description'] ?? '') ?></p>
                            <div class="svc-card__meta">
                                <span><?= fa((int)$c['services_count']) ?> خدمت</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($featured !== []): ?>
<section class="section section--muted">
    <div class="site-container">
        <div class="section__head"><h2>خدمات ویژه</h2><p>پرطرفدارترین خدمات سالن</p></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach (array_slice($featured, 0, 6) as $s): ?>
                <a class="svc-card" href="<?= url('/services/' . $s['slug']) ?>">
                    <div class="svc-card__media">
                        <?php if (!empty($s['image'])): ?>
                            <img src="<?= e(url($s['image'])) ?>" alt="<?= e($s['name']) ?>" loading="lazy">
                        <?php else: ?>💫<?php endif; ?>
                    </div>
                    <div class="svc-card__body">
                        <span class="mr-badge mr-badge--gold mb-2" style="align-self:flex-start"><?= e($s['category_name']) ?></span>
                        <h3 class="text-lg mb-1"><?= e($s['name']) ?></h3>
                        <p class="text-sm text-muted"><?= e($s['short_description'] ?? '') ?></p>
                        <div class="svc-card__meta">
                            <span class="svc-card__price mr-num"><?= money($s['price']) ?></span>
                            <span class="mr-num">⏱ <?= fa((int)$s['duration_minutes']) ?> دقیقه</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($team !== []): ?>
<section class="section">
    <div class="site-container">
        <div class="section__head"><h2>تیم متخصصان ما</h2><p>هر کار را به دست متخصص همان حوزه بسپارید.</p></div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <?php foreach ($team as $m): ?>
                <div class="team-card">
                    <?php if (!empty($m['avatar'])): ?>
                        <img class="team-card__avatar" src="<?= e(url($m['avatar'])) ?>" alt="<?= e($m['first_name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="team-card__avatar"><?= e(mb_substr((string)$m['first_name'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <h3 class="text-md mb-1"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></h3>
                    <p class="text-sm text-muted mb-2"><?= e($m['job_title'] ?? '') ?></p>
                    <?php if ((float)$m['rating'] > 0): ?>
                        <span class="stars">★</span> <span class="mr-num text-sm"><?= fa(number_format((float)$m['rating'], 1)) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5"><a class="mr-btn mr-btn--ghost" href="<?= url('/team') ?>">مشاهده همه متخصصان</a></div>
    </div>
</section>
<?php endif; ?>

<section class="section section--muted">
    <div class="site-container">
        <div class="section__head"><h2>نظر مشتریان</h2><p>تجربه واقعی کسانی که مهمان ما بوده‌اند.</p></div>
        <?php if ($reviews === []): ?>
            <?= component('empty', ['icon' => '💬', 'title' => 'هنوز نظری ثبت نشده', 'text' => 'اولین نفری باشید که تجربه‌اش را ثبت می‌کند.']) ?>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($reviews as $r): ?>
                    <div class="review-card">
                        <div class="stars mb-2"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
                        <p class="text-sm mb-3"><?= e($r['comment'] ?? '') ?></p>
                        <div class="text-xs text-muted">
                            <?= e($r['first_name'] . ' ' . mb_substr((string)$r['last_name'], 0, 1) . '.') ?>
                            <?php if (!empty($r['service_name'])): ?> — <?= e($r['service_name']) ?><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($posts !== []): ?>
<section class="section">
    <div class="site-container">
        <div class="section__head"><h2>از مجله زیبایی</h2><p>نکته‌های کاربردی مراقبت از مو، پوست و ناخن.</p></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($posts as $p): ?>
                <a class="svc-card" href="<?= url('/blog/' . $p['slug']) ?>">
                    <div class="svc-card__media">
                        <?php if (!empty($p['cover'])): ?>
                            <img src="<?= e(url($p['cover'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
                        <?php else: ?>📖<?php endif; ?>
                    </div>
                    <div class="svc-card__body">
                        <h3 class="text-md mb-1"><?= e($p['title']) ?></h3>
                        <p class="text-sm text-muted"><?= e($p['excerpt'] ?? '') ?></p>
                        <div class="svc-card__meta"><span><?= jdate($p['published_at'], 'j F Y') ?></span></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section section--muted">
    <div class="site-container text-center">
        <h2 class="mb-3">آماده‌اید نوبت خود را رزرو کنید؟</h2>
        <p class="text-muted mb-5">انتخاب خدمت، متخصص و ساعت — همه در یک صفحه.</p>
        <a class="mr-btn mr-btn--primary mr-btn--lg" href="<?= url('/booking') ?>">شروع رزرو آنلاین</a>
    </div>
</section>
<?php View::endSection();
