<?php
use App\Core\View;
View::extend('public');
View::section('content');
/** @var array $categories @var array $services @var string|null $active */
?>
<section class="hero" style="padding-block:var(--mr-s7)">
    <div class="site-container">
        <h1 class="hero__title" style="font-size:clamp(1.6rem,3.4vw,2.5rem)">خدمات و تعرفه‌ها</h1>
        <p class="hero__lead">قیمت‌ها شفاف و به‌روز است. مدت زمان هر خدمت تقریبی و بسته به شرایط مو یا پوست متغیر است.</p>
    </div>
</section>

<section class="section">
    <div class="site-container">
        <div class="flex gap-2 flex-wrap mb-5">
            <a class="mr-btn <?= $active === null ? 'mr-btn--primary' : 'mr-btn--ghost' ?> mr-btn--sm" href="<?= url('/services') ?>">همه</a>
            <?php foreach ($categories as $c): ?>
                <a class="mr-btn <?= $active === $c['slug'] ? 'mr-btn--primary' : 'mr-btn--ghost' ?> mr-btn--sm"
                   href="<?= url('/services?category=' . urlencode((string)$c['slug'])) ?>">
                    <?= $c['icon'] ?: '' ?> <?= e($c['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($services === []): ?>
            <?= component('empty', [
                'icon' => '🔍', 'title' => 'خدمتی در این دسته یافت نشد',
                'text' => 'دسته دیگری را انتخاب کنید یا همه خدمات را ببینید.',
                'actionHref' => '/services', 'actionLabel' => 'همه خدمات',
            ]) ?>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($services as $s): ?>
                    <article class="svc-card">
                        <a class="svc-card__media" href="<?= url('/services/' . $s['slug']) ?>">
                            <?php if (!empty($s['image'])): ?>
                                <img src="<?= e(url($s['image'])) ?>" alt="<?= e($s['name']) ?>" loading="lazy">
                            <?php else: ?>💫<?php endif; ?>
                        </a>
                        <div class="svc-card__body">
                            <span class="mr-badge mb-2" style="align-self:flex-start"><?= e($s['category_name']) ?></span>
                            <h2 class="text-lg mb-1"><a href="<?= url('/services/' . $s['slug']) ?>"><?= e($s['name']) ?></a></h2>
                            <p class="text-sm text-muted"><?= e($s['short_description'] ?? '') ?></p>
                            <div class="svc-card__meta">
                                <span class="svc-card__price mr-num"><?= money($s['price']) ?></span>
                                <span class="mr-num">⏱ <?= fa((int)$s['duration_minutes']) ?> دقیقه</span>
                            </div>
                            <a class="mr-btn mr-btn--soft mr-btn--sm mt-3" href="<?= url('/booking?service=' . urlencode((string)$s['slug'])) ?>">رزرو این خدمت</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php View::endSection();
