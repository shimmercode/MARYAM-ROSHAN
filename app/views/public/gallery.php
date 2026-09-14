<?php
use App\Core\View;
View::extend('public');
View::section('content');
/** @var array $items */
$categories = array_values(array_unique(array_filter(array_column($items, 'category'))));
?>
<section class="hero" style="padding-block:var(--mr-s7)">
    <div class="site-container">
        <h1 class="hero__title" style="font-size:clamp(1.6rem,3.4vw,2.5rem)">گالری نمونه‌کارها</h1>
        <p class="hero__lead">نمونه‌هایی از کارهای انجام‌شده توسط تیم سالن.</p>
    </div>
</section>

<section class="section">
    <div class="site-container">
        <?php if ($items === []): ?>
            <?= component('empty', ['icon' => '🖼', 'title' => 'گالری هنوز خالی است', 'text' => 'به‌زودی نمونه‌کارها منتشر می‌شود.']) ?>
        <?php else: ?>
            <?php if ($categories !== []): ?>
                <div class="flex gap-2 flex-wrap mb-5" data-gallery-filters>
                    <button class="mr-btn mr-btn--primary mr-btn--sm" data-filter="">همه</button>
                    <?php foreach ($categories as $c): ?>
                        <button class="mr-btn mr-btn--ghost mr-btn--sm" data-filter="<?= e($c) ?>"><?= e($c) ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="gallery-grid" id="galleryGrid">
                <?php foreach ($items as $g): ?>
                    <figure data-category="<?= e($g['category'] ?? '') ?>">
                        <img src="<?= e(url($g['image'])) ?>" alt="<?= e($g['title'] ?? $g['service_name'] ?? 'نمونه‌کار') ?>" loading="lazy">
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php View::endSection();

View::section('scripts'); ?>
<script>
document.querySelector('[data-gallery-filters]')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-filter]');
    if (!btn) return;
    document.querySelectorAll('[data-filter]').forEach((b) => {
        b.classList.remove('mr-btn--primary'); b.classList.add('mr-btn--ghost');
    });
    btn.classList.add('mr-btn--primary'); btn.classList.remove('mr-btn--ghost');
    const want = btn.dataset.filter;
    document.querySelectorAll('#galleryGrid figure').forEach((f) => {
        f.style.display = (!want || f.dataset.category === want) ? '' : 'none';
    });
});
</script>
<?php View::endSection();
