<?php
use App\Core\View;
View::extend('portal');
View::section('content');
/** @var int $balance @var float $pointValue @var array $tiers @var array|null $tier @var array|null $next @var int $toNext @var array $history @var string $referral */
$pct = ($next !== null && (int)$next['min_points'] > 0)
    ? min(100, (int)round($balance / (int)$next['min_points'] * 100))
    : 100;
?>
<section class="mr-card mb-4">
    <div class="mr-card__body text-center">
        <div class="tier-ring mb-3" style="--tier-pct:<?= $pct ?>%;--tier-color:<?= e($tier['color'] ?? '#C9A227') ?>">
            <div class="tier-ring__inner">
                <div>
                    <div class="tier-ring__value mr-num"><?= fa($balance) ?></div>
                    <div class="tier-ring__label">امتیاز</div>
                </div>
            </div>
        </div>
        <h2 class="text-lg mb-1">سطح <?= e($tier['name'] ?? 'برنزی') ?></h2>
        <?php if ($next !== null): ?>
            <p class="text-sm text-muted">
                <span class="mr-num"><?= fa($toNext) ?></span> امتیاز تا سطح <?= e($next['name']) ?>
            </p>
        <?php else: ?>
            <p class="text-sm text-muted">شما در بالاترین سطح باشگاه مشتریان هستید. 👑</p>
        <?php endif; ?>
        <p class="text-xs text-muted mt-2">
            ارزش هر امتیاز: <span class="mr-num"><?= money($pointValue) ?></span>
        </p>
    </div>
</section>

<section class="mr-card mb-4">
    <div class="mr-card__head"><h2 class="mr-card__title">سطوح باشگاه</h2></div>
    <div class="mr-card__body">
        <?php foreach ($tiers as $t): $isMine = ($tier['id'] ?? 0) === $t['id']; ?>
            <div class="slot-row <?= $isMine ? 'font-bold' : '' ?>">
                <span class="slot-row__time" style="color:<?= e($t['color']) ?>">●</span>
                <div class="slot-row__body">
                    <strong><?= e($t['name']) ?> <?= $isMine ? '(سطح شما)' : '' ?></strong>
                    <small>
                        از <span class="mr-num"><?= fa((int)$t['min_points']) ?></span> امتیاز ·
                        <span class="mr-num"><?= fa((float)$t['discount_percent']) ?></span>٪ تخفیف
                    </small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="mr-card mb-4">
    <div class="mr-card__head"><h2 class="mr-card__title">معرفی به دوستان</h2></div>
    <div class="mr-card__body">
        <p class="text-sm text-muted mb-3">کد زیر را به دوستان خود بدهید؛ با اولین مراجعه آن‌ها امتیاز هدیه می‌گیرید.</p>
        <div class="flex gap-2">
            <input class="mr-input mr-num text-center font-bold" id="refCode" dir="ltr" value="<?= e($referral) ?>" readonly>
            <button class="mr-btn mr-btn--soft" type="button" id="copyRef">کپی</button>
        </div>
    </div>
</section>

<section class="mr-card">
    <div class="mr-card__head"><h2 class="mr-card__title">تاریخچه امتیازها</h2></div>
    <div class="mr-card__body">
        <?php if ($history === []): ?>
            <?= component('empty', ['icon' => '🎁', 'title' => 'هنوز امتیازی ثبت نشده است']) ?>
        <?php else: ?>
            <?php foreach ($history as $h): $plus = (int)$h['points'] >= 0; ?>
                <div class="slot-row">
                    <div class="slot-row__body">
                        <strong><?= e($h['description'] ?? ($plus ? 'کسب امتیاز' : 'استفاده از امتیاز')) ?></strong>
                        <small><?= jdate($h['created_at'], 'j F Y') ?></small>
                    </div>
                    <div class="mr-num font-bold <?= $plus ? 'text-success' : 'text-danger' ?>">
                        <?= $plus ? '+' : '−' ?><?= fa(abs((int)$h['points'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { toast } from '<?= asset('js/core.js') ?>';
document.getElementById('copyRef')?.addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(document.getElementById('refCode').value);
        toast('کد معرف کپی شد.', 'success');
    } catch {
        toast('کپی نشد؛ کد را دستی انتخاب کنید.', 'error');
    }
});
</script>
<?php View::endSection();
