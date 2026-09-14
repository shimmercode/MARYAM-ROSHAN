<?php
use App\Core\View;
View::extend('admin');
View::section('content');
/** @var array $users @var array $roles */
?>
<div class="mr-page-head">
    <div>
        <div class="mr-breadcrumb"><a href="<?= url('/admin/settings') ?>">تنظیمات</a> / کاربران</div>
        <h1>کاربران و دسترسی‌ها</h1>
        <p>نقش هر کاربر تعیین می‌کند به کدام بخش‌های پنل دسترسی دارد</p>
    </div>
</div>

<div class="mr-card">
    <div class="mr-card__body">
        <?php if ($users === []): ?>
            <?= component('empty', ['icon' => '👥', 'title' => 'کاربری یافت نشد']) ?>
        <?php else: ?>
            <div class="mr-table__wrap">
                <table class="mr-table">
                    <thead><tr><th>کاربر</th><th>موبایل</th><th>شعبه</th><th>نقش‌ها</th><th>آخرین ورود</th><th>وضعیت</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong>
                                <?php if (!empty($u['email'])): ?>
                                    <div class="text-xs text-muted" dir="ltr"><?= e($u['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="mr-num" dir="ltr"><?= fa($u['mobile']) ?></td>
                            <td class="text-xs"><?= e($u['branch_name'] ?? '—') ?></td>
                            <td>
                                <?php foreach (array_filter(explode(',', (string)$u['roles'])) as $role): ?>
                                    <span class="mr-badge mr-badge--gold"><?= e($role) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-xs"><?= $u['last_login_at'] ? jdate($u['last_login_at'], 'j F — H:i') : 'هرگز' ?></td>
                            <td><?= component('status_badge', ['status' => $u['status']]) ?></td>
                            <td>
                                <button class="mr-btn mr-btn--soft mr-btn--sm" type="button"
                                        data-roles-for="<?= (int)$u['id'] ?>"
                                        data-name="<?= e($u['first_name'] . ' ' . $u['last_name']) ?>"
                                        data-current="<?= e((string)$u['roles']) ?>">تغییر نقش</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mr-modal" id="rolesModal" hidden>
    <div class="mr-modal__panel">
        <form method="post" id="rolesForm" action="<?= url('/admin/settings/users/0/roles') ?>">
            <?= csrf_field() ?>
            <div class="mr-card__head">
                <h2 class="mr-card__title">نقش‌های <span id="rolesUser"></span></h2>
                <button class="mr-iconbtn" type="button" data-modal-close="rolesModal">✕</button>
            </div>
            <div class="mr-card__body">
                <?php foreach ($roles as $r): ?>
                    <label class="flex items-center gap-2 border-b py-2">
                        <input type="checkbox" name="role_ids[]" value="<?= (int)$r['id'] ?>" data-role-name="<?= e($r['name']) ?>">
                        <span class="text-sm"><?= e($r['name']) ?></span>
                        <code class="text-xs text-muted" dir="ltr"><?= e($r['slug']) ?></code>
                    </label>
                <?php endforeach; ?>
                <p class="mr-help mt-2">حذف همه نقش‌ها دسترسی کاربر به پنل را قطع می‌کند.</p>
            </div>
            <div class="mr-card__foot flex gap-2 justify-end">
                <button class="mr-btn mr-btn--ghost" type="button" data-modal-close="rolesModal">انصراف</button>
                <button class="mr-btn mr-btn--primary" type="submit">ذخیره نقش‌ها</button>
            </div>
        </form>
    </div>
</div>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { openModal } from '<?= asset('js/core.js') ?>';
const form = document.getElementById('rolesForm');
const base = '<?= url('/admin/settings/users') ?>';

document.querySelectorAll('[data-roles-for]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const current = (btn.dataset.current || '').split(',').map((s) => s.trim());
        document.getElementById('rolesUser').textContent = btn.dataset.name;
        form.action = base + '/' + btn.dataset.rolesFor + '/roles';
        form.querySelectorAll('[data-role-name]').forEach((cb) => {
            cb.checked = current.includes(cb.dataset.roleName);
        });
        openModal('rolesModal');
    });
});
</script>
<?php View::endSection();
