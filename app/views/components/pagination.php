<?php
/** @var int $page @var int $lastPage @var array $query */
$page     = max(1, (int)($page ?? 1));
$lastPage = max(1, (int)($lastPage ?? 1));
if ($lastPage <= 1) {
    return;
}
$query = $query ?? [];
$link  = static function (int $p) use ($query): string {
    $query['page'] = $p;
    return '?' . http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
};
$from = max(1, $page - 2);
$to   = min($lastPage, $page + 2);
?>
<nav class="mr-pagination" aria-label="صفحه‌بندی">
    <a class="<?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $link(max(1, $page - 1)) ?>" rel="prev">›</a>
    <?php if ($from > 1): ?>
        <a href="<?= $link(1) ?>"><?= fa(1) ?></a><span class="is-disabled">…</span>
    <?php endif; ?>
    <?php for ($i = $from; $i <= $to; $i++): ?>
        <a class="<?= $i === $page ? 'is-active' : '' ?>" href="<?= $link($i) ?>"><?= fa($i) ?></a>
    <?php endfor; ?>
    <?php if ($to < $lastPage): ?>
        <span class="is-disabled">…</span><a href="<?= $link($lastPage) ?>"><?= fa($lastPage) ?></a>
    <?php endif; ?>
    <a class="<?= $page >= $lastPage ? 'is-disabled' : '' ?>" href="<?= $link(min($lastPage, $page + 1)) ?>" rel="next">‹</a>
</nav>
