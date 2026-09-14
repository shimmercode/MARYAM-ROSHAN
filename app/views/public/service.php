<?php
use App\Core\View;
View::extend('public');

View::section('jsonld'); ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Service',
    'name'     => $service['name'],
    'description' => $service['short_description'] ?? '',
    'serviceType' => $service['category_name'],
    'provider' => ['@type' => 'BeautySalon', 'name' => setting('salon_name', 'سالن زیبایی مریم روشن')],
    'areaServed' => 'تهران',
    'offers'   => [
        '@type' => 'Offer',
        'price' => (string)(int)$service['price'],
        'priceCurrency' => 'IRR',
        'availability' => 'https://schema.org/InStock',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<?php View::endSection();

View::section('content');
/** @var array $service @var array $staff @var array $related @var array $reviews */
?>
<section class="section">
    <div class="site-container">
        <nav class="mr-breadcrumb mb-4">
            <a href="<?= url('/') ?>">خانه</a> / <a href="<?= url('/services') ?>">خدمات</a> /
            <a href="<?= url('/services?category=' . urlencode((string)$service['category_slug'])) ?>"><?= e($service['category_name']) ?></a>
        </nav>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="col-span-2">
                <h1 class="text-2xl mb-2"><?= e($service['name']) ?></h1>
                <p class="text-lg text-muted mb-5"><?= e($service['short_description'] ?? '') ?></p>

                <?php if (!empty($service['image'])): ?>
                    <img class="w-full rounded-xl mb-5" src="<?= e(url($service['image'])) ?>" alt="<?= e($service['name']) ?>">
                <?php endif; ?>

                <?php if (!empty($service['description'])): ?>
                    <div class="prose"><?= nl2br(e($service['description'])) ?></div>
                <?php endif; ?>

                <?php if ($staff !== []): ?>
                    <h2 class="text-xl mt-6 mb-3">متخصصان این خدمت</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <?php foreach ($staff as $m): ?>
                            <div class="team-card">
                                <?php if (!empty($m['avatar'])): ?>
                                    <img class="team-card__avatar" src="<?= e(url($m['avatar'])) ?>" alt="<?= e($m['first_name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="team-card__avatar"><?= e(mb_substr((string)$m['first_name'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <div class="font-medium"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></div>
                                <?php if ((float)$m['rating'] > 0): ?>
                                    <div class="text-sm"><span class="stars">★</span> <span class="mr-num"><?= fa(number_format((float)$m['rating'], 1)) ?></span></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h2 class="text-xl mt-6 mb-3">نظر مشتریان</h2>
                <?php if ($reviews === []): ?>
                    <?= component('empty', ['icon' => '💬', 'title' => 'هنوز نظری برای این خدمت ثبت نشده است']) ?>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <div class="review-card mb-3">
                            <div class="stars mb-2"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
                            <p class="text-sm mb-2"><?= e($r['comment'] ?? '') ?></p>
                            <div class="text-xs text-muted">
                                <?= e($r['first_name'] . ' ' . mb_substr((string)$r['last_name'], 0, 1) . '.') ?> — <?= jdate($r['created_at'], 'j F Y') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <aside>
                <div class="mr-card sticky" style="top:90px">
                    <div class="mr-card__body">
                        <div class="text-sm text-muted">قیمت این خدمت</div>
                        <div class="text-2xl font-bold text-primary mr-num mb-3"><?= money($service['price']) ?></div>

                        <div class="flex justify-between border-b py-2 text-sm">
                            <span class="text-muted">مدت زمان</span>
                            <span class="mr-num"><?= fa((int)$service['duration_minutes']) ?> دقیقه</span>
                        </div>
                        <div class="flex justify-between border-b py-2 text-sm">
                            <span class="text-muted">دسته‌بندی</span><span><?= e($service['category_name']) ?></span>
                        </div>
                        <?php if ((int)$service['loyalty_points'] > 0): ?>
                            <div class="flex justify-between border-b py-2 text-sm">
                                <span class="text-muted">امتیاز وفاداری</span>
                                <span class="mr-num"><?= fa((int)$service['loyalty_points']) ?> امتیاز</span>
                            </div>
                        <?php endif; ?>
                        <?php if ((int)$service['requires_deposit'] === 1): ?>
                            <div class="mr-alert mr-alert--info mt-3 text-xs">
                                <span>برای این خدمت پرداخت بیعانه لازم است.</span>
                            </div>
                        <?php endif; ?>

                        <?php if ((int)$service['online_booking'] === 1): ?>
                            <a class="mr-btn mr-btn--primary mr-btn--block mr-btn--lg mt-4"
                               href="<?= url('/booking?service=' . urlencode((string)$service['slug'])) ?>">رزرو نوبت</a>
                        <?php else: ?>
                            <a class="mr-btn mr-btn--soft mr-btn--block mt-4" href="<?= url('/contact') ?>">تماس برای رزرو</a>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>

        <?php if ($related !== []): ?>
            <h2 class="text-xl mt-6 mb-3">خدمات مرتبط</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($related as $s): ?>
                    <a class="svc-card" href="<?= url('/services/' . $s['slug']) ?>">
                        <div class="svc-card__media">💫</div>
                        <div class="svc-card__body">
                            <h3 class="text-md mb-1"><?= e($s['name']) ?></h3>
                            <div class="svc-card__meta">
                                <span class="svc-card__price mr-num"><?= money($s['price']) ?></span>
                                <span class="mr-num">⏱ <?= fa((int)$s['duration_minutes']) ?> دقیقه</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php View::endSection();
