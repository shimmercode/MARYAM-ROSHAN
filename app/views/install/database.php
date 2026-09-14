<?php
use App\Core\View;
View::extend('blank');
View::section('content');
?>
<div class="mr-auth__head">
    <div class="mr-auth__mark">۳</div>
    <h1 class="text-xl mb-1">اتصال به پایگاه داده</h1>
    <p class="text-sm text-muted m-0">اطلاعات MySQL هاست خود را وارد کنید.</p>
</div>
<div class="mr-auth__body">
    <div class="mr-steps"><span class="done"></span><span class="done"></span><span class="done"></span><span></span></div>
    <?= partial('flash') ?>

    <form method="post" action="<?= url('/install/database') ?>" id="dbForm">
        <?= csrf_field() ?>
        <div class="grid grid-cols-2 gap-3">
            <div class="mr-field">
                <label class="mr-label" for="db_host">میزبان <span class="req">*</span></label>
                <input class="mr-input" id="db_host" name="db_host" value="<?= e(old('db_host', 'localhost')) ?>" required>
                <div class="mr-error" data-error-for="db_host"><?= e(error_for('db_host')) ?></div>
            </div>
            <div class="mr-field">
                <label class="mr-label" for="db_port">پورت <span class="req">*</span></label>
                <input class="mr-input mr-num" id="db_port" name="db_port" value="<?= e(old('db_port', '3306')) ?>" required>
                <div class="mr-error" data-error-for="db_port"><?= e(error_for('db_port')) ?></div>
            </div>
        </div>
        <div class="mr-field">
            <label class="mr-label" for="db_name">نام پایگاه داده <span class="req">*</span></label>
            <input class="mr-input" id="db_name" name="db_name" value="<?= e(old('db_name', 'maryam_roshan')) ?>" required>
            <div class="mr-help">در صورت نبود، به‌صورت خودکار ساخته می‌شود.</div>
            <div class="mr-error" data-error-for="db_name"><?= e(error_for('db_name')) ?></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="mr-field">
                <label class="mr-label" for="db_user">نام کاربری <span class="req">*</span></label>
                <input class="mr-input" id="db_user" name="db_user" value="<?= e(old('db_user', 'root')) ?>" required>
                <div class="mr-error" data-error-for="db_user"><?= e(error_for('db_user')) ?></div>
            </div>
            <div class="mr-field">
                <label class="mr-label" for="db_pass">رمز عبور</label>
                <input class="mr-input" id="db_pass" name="db_pass" type="password" autocomplete="new-password">
            </div>
        </div>
        <div class="mr-field">
            <label class="mr-label" for="app_url">آدرس سایت <span class="req">*</span></label>
            <input class="mr-input" id="app_url" name="app_url" dir="ltr"
                   value="<?= e(old('app_url', (isset($_SERVER['HTTP_HOST']) ? ((($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) : 'http://localhost'))) ?>" required>
            <div class="mr-error" data-error-for="app_url"><?= e(error_for('app_url')) ?></div>
        </div>

        <div class="mr-alert mr-alert--warning">
            <span aria-hidden="true">⏳</span>
            <span>با ثبت این فرم، جداول و داده‌های پایه ساخته می‌شوند. این کار ممکن است تا یک دقیقه طول بکشد.</span>
        </div>

        <button class="mr-btn mr-btn--primary mr-btn--block mr-btn--lg" type="submit" id="dbSubmit">
            ساخت پایگاه داده و ادامه
        </button>
    </form>
</div>
<?php View::endSection();

View::section('scripts'); ?>
<script type="module">
import { busy } from '<?= asset('js/core.js') ?>';
document.getElementById('dbForm')?.addEventListener('submit', () => {
    busy(document.getElementById('dbSubmit'), true, 'در حال ساخت جداول…');
});
</script>
<?php View::endSection();
