<?php
use App\Core\View;
View::extend('public');

View::section('jsonld'); ?>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'BlogPosting',
    'headline' => $post['title'],
    'description' => $post['excerpt'] ?? '',
    'datePublished' => $post['published_at'],
    'inLanguage' => 'fa-IR',
    'publisher' => ['@type' => 'Organization', 'name' => setting('salon_name', 'سالن زیبایی مریم روشن')],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<?php View::endSection();

View::section('content');
/** @var array $post @var array $related */
?>
<article class="section">
    <div class="site-container">
        <nav class="mr-breadcrumb mb-4"><a href="<?= url('/') ?>">خانه</a> / <a href="<?= url('/blog') ?>">مجله</a></nav>
        <h1 class="text-2xl mb-2"><?= e($post['title']) ?></h1>
        <div class="text-sm text-muted mb-5">
            <?= jdate($post['published_at'], 'j F Y') ?> · <span class="mr-num"><?= fa((int)$post['views']) ?></span> بازدید
        </div>
        <?php if (!empty($post['cover'])): ?>
            <img class="w-full rounded-xl mb-5" src="<?= e(url($post['cover'])) ?>" alt="<?= e($post['title']) ?>">
        <?php endif; ?>
        <div class="prose"><?= $post['body'] ?></div>

        <?php if (!empty($post['tags'])): ?>
            <div class="flex gap-2 flex-wrap mt-5">
                <?php foreach (explode(',', (string)$post['tags']) as $tag): ?>
                    <span class="mr-badge"><?= e(trim($tag)) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</article>

<?php if ($related !== []): ?>
<section class="section section--muted">
    <div class="site-container">
        <h2 class="text-xl mb-4">مطالب مرتبط</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($related as $p): ?>
                <a class="svc-card" href="<?= url('/blog/' . $p['slug']) ?>">
                    <div class="svc-card__media">📖</div>
                    <div class="svc-card__body">
                        <h3 class="text-md mb-1"><?= e($p['title']) ?></h3>
                        <p class="text-sm text-muted"><?= e($p['excerpt'] ?? '') ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php View::endSection();
