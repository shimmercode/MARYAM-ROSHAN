<?php
/** @var array|null $currentUser @var int $unreadCount */
$user = $currentUser ?? null;
$initials = $user ? mb_substr((string)$user['first_name'], 0, 1) : '؟';
$fullName = $user ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : '';
$roleName = $user['role_name'] ?? '';
?>
<header class="header mr-topbar mr-no-print">
    <div class="h-r">
        <button class="ib mr-sidebar__toggle" type="button" data-sidebar-toggle aria-label="باز کردن منو"><i class="bi bi-list"></i></button>

        <div class="pt d-none d-md-block">
            <h2><?= e($title ?? 'پنل مدیریت') ?></h2>
            <span><?= e(setting('salon_name', 'سالن زیبایی مریم روشن')) ?></span>
        </div>

        <div class="sb">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label class="sr-only" for="globalSearch">جستجوی مشتری</label>
            <input id="globalSearch" type="search" placeholder="جستجوی مشتری با نام یا شماره موبایل…"
                   autocomplete="off" data-global-search>
            <div class="mr-card hidden" id="globalSearchResults"
                 style="position:absolute;inset-inline:0;top:calc(100% + 6px);z-index:50;max-height:320px;overflow:auto"></div>
        </div>
    </div>

    <div class="h-l">
        <a class="mr-btn mr-btn--primary mr-btn--sm d-none d-sm-inline-flex" href="<?= url('/admin/appointments/create') ?>">+ نوبت جدید</a>

        <a class="ib" href="<?= url('/admin/notifications') ?>" aria-label="اعلان‌ها">
            <i class="bi bi-bell"></i><?php if (($unreadCount ?? 0) > 0): ?><span class="bg"><?= fa(min(99, (int)$unreadCount)) ?></span><?php endif; ?>
        </a>

        <div class="dropdown">
            <button class="ub" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="حساب کاربری">
                <span class="av"><?= e($initials) ?></span>
                <span class="ui d-none d-lg-flex">
                    <span class="un"><?= e($fullName) ?></span>
                    <span class="ur"><?= e($roleName) ?></span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-start shadow">
                <li class="px-3 py-2 text-sm">
                    <div class="font-medium"><?= e($fullName) ?></div>
                    <div class="text-xs text-muted"><?= e($roleName) ?></div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= url('/profile') ?>">پروفایل و رمز عبور</a></li>
                <li>
                    <form method="post" action="<?= url('/logout') ?>" class="px-3 py-1">
                        <?= csrf_field() ?>
                        <button class="mr-btn mr-btn--ghost mr-btn--sm mr-btn--block" type="submit">خروج از حساب</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<script type="module">
import { get, debounce, escapeHtml, showLoading, showEmpty, showError } from '<?= asset('js/core.js') ?>';

const input = document.querySelector('[data-global-search]');
const box   = document.getElementById('globalSearchResults');

const run = debounce(async (term) => {
    if (term.length < 2) { box.classList.add('hidden'); return; }
    box.classList.remove('hidden');
    showLoading(box, 3);
    try {
        const rows = await get('<?= url('/admin/customers/search') ?>', { q: term });
        if (!rows.length) { showEmpty(box, 'مشتری یافت نشد', 'نام یا شماره دیگری را امتحان کنید.'); return; }
        box.innerHTML = rows.map((c) => `
            <a class="flex items-center gap-3 px-4 py-3 border-b" href="<?= url('/admin/customers/') ?>${c.id}">
              <span class="mr-avatar">${escapeHtml((c.first_name || '؟').slice(0, 1))}</span>
              <span class="flex-1 min-w-0">
                <span class="block font-medium truncate">${escapeHtml(c.first_name + ' ' + c.last_name)}</span>
                <span class="block text-xs text-muted mr-num">${escapeHtml(c.mobile)}</span>
              </span>
            </a>`).join('');
    } catch (e) {
        showError(box, e.message, () => run(term));
    }
}, 320);

input?.addEventListener('input', (e) => run(e.target.value.trim()));
document.addEventListener('click', (e) => {
    if (!e.target.closest('.sb')) box.classList.add('hidden');
});
</script>
