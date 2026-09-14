<?php
use App\Core\View;
View::extend('public');
View::section('content');
/** @var array $posts @var int $page @var int $lastPage */
?>
<section class="hero" style="padding-block:var(--mr-s7)">
    <div class="site-container">
        <h1 class="hero__title" style="font-size:clamp(1.6rem,3.4vw,2.5rem)">مجله زیبایی</h1>
        <p class="hero__lead">نکته‌های کاربردی مراقبت از مو، پوست و ناخن از زبان متخصصان ما.</p>
    </div>
</section>

<section class="section">
    <div class="site-container">
        <?php if ($posts === []): ?>
            <?= component('empty', ['icon' => '📖', 'title' => 'هنوز مطلبی منتشر نشده است']) ?>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($posts as $p): ?>
                    <article class="svc-card">
                        <a class="svc-card__media" href="<?= url('/blog/' . $p['slug']) ?>">
                            <?php if (!empty($p['cover'])): ?>
                                <img src="<?= e(url($p['cover'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
                            <?php else: ?>📖<?php endif; ?>
                        </a>
                        <div class="svc-card__body">
                            <h2 class="text-md mb-1"><a href="<?= url('/blog/' . $p['slug']) ?>"><?= e($p['title']) ?></a></h2>
                            <p class="text-sm text-muted"><?= e($p['excerpt'] ?? '') ?></p>
                            <div class="svc-card__meta"><span><?= jdate($p['published_at'], 'j F Y') ?></span></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?= component('pagination', ['page' => $page, 'lastPage' => $lastPage, 'query' => []]) ?>
        <?php endif; ?>
    </div>
</section>
<?php View::endSection();
