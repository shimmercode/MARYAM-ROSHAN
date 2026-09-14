<?php
/** Minimal centred layout used by the installer, auth screens and error pages. */
use App\Core\View;

$title = $title ?? '';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> | سالن زیبایی مریم روشن</title>
<link rel="icon" href="<?= asset('images/favicon.svg') ?>">
<link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
<link rel="stylesheet" href="<?= asset('css/utilities.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/mockup.css') ?>">
<style>
  .mr-auth { min-height:100vh; display:grid; place-items:center; padding:var(--mr-s5);
    position:relative; overflow:hidden;
    background:linear-gradient(135deg,var(--mr-rose-600),var(--mr-gold-500) 60%,var(--mr-dusty-rose)); }
  .mr-auth::before, .mr-auth::after {
    content:''; position:absolute; border-radius:50%; opacity:.15; background:#fff;
    animation:mr-auth-float 20s infinite ease-in-out; pointer-events:none; }
  .mr-auth::before { width:400px; height:400px; top:-120px; inset-inline-end:-120px; }
  .mr-auth::after  { width:300px; height:300px; bottom:-90px; inset-inline-start:-90px; animation-delay:6s; }
  @keyframes mr-auth-float {
    0%,100% { transform:translate(0,0) scale(1); }
    33% { transform:translate(24px,-24px) scale(1.05); }
    66% { transform:translate(-16px,16px) scale(.96); } }
  .mr-auth__card { position:relative; width:min(560px,100%); background:rgba(255,255,255,.97);
    backdrop-filter:blur(16px);
    border-radius:24px; box-shadow:0 30px 60px rgba(23,27,55,.28); overflow:hidden; animation:mr-auth-up .5s ease; }
  @keyframes mr-auth-up { from { opacity:0; transform:translateY(24px);} to { opacity:1; transform:translateY(0);} }
  .mr-auth__head { padding:var(--mr-s6) var(--mr-s6) var(--mr-s4); text-align:center; }
  .mr-auth__mark { width:60px;height:60px;border-radius:18px;margin:0 auto var(--mr-s3);
    display:grid;place-items:center;font-size:1.6rem;font-weight:700;color:#fff;
    background:linear-gradient(135deg,var(--mr-primary),var(--mr-accent)); }
  .mr-auth__body { padding:0 var(--mr-s6) var(--mr-s6); }
  .mr-steps { display:flex; gap:var(--mr-s2); justify-content:center; margin-bottom:var(--mr-s5); }
  .mr-steps span { width:34px;height:4px;border-radius:99px;background:var(--mr-line); }
  .mr-steps span.done { background:var(--mr-primary); }

  /* Split-screen variant: brand panel + form, opt-in via $split in the view. */
  .mr-auth--split { padding: var(--mr-s5); }
  .mr-auth--split .mr-auth__card { width:min(1040px,100%); display:grid; grid-template-columns:1.05fr 1fr; border-radius:28px; }
  .mr-auth__brand {
    position:relative; overflow:hidden; color:#fff; padding:var(--mr-s7) var(--mr-s6);
    display:flex; flex-direction:column; justify-content:center; gap:var(--mr-s5);
    background:linear-gradient(150deg,var(--mr-rose-700),var(--mr-primary) 55%,var(--mr-gold-500));
  }
  .mr-auth__brand::after {
    content:''; position:absolute; inset-inline-end:-70px; inset-block-end:-70px;
    width:220px; height:220px; border-radius:50%; background:rgba(255,255,255,.08);
  }
  .mr-auth__brand-mark { display:flex; align-items:center; gap:var(--mr-s3); position:relative; }
  .mr-auth__brand-mark .mark {
    width:48px; height:48px; border-radius:14px; background:rgba(255,255,255,.16);
    display:grid; place-items:center; font-weight:700; font-size:1.25rem;
  }
  .mr-auth__brand h2 { font-size:var(--mr-fs-xl); margin:0; color:#fff; }
  .mr-auth__brand p.lead { color:rgba(255,255,255,.85); font-size:var(--mr-fs-sm); margin:2px 0 0; }
  .lb-feat { display:flex; align-items:flex-start; gap:var(--mr-s3); position:relative; }
  .lb-feat .ic {
    flex:0 0 auto; width:34px; height:34px; border-radius:10px; background:rgba(255,255,255,.14);
    display:grid; place-items:center; font-size:.95rem;
  }
  .lb-feat .tx b { display:block; font-size:var(--mr-fs-sm); font-weight:600; }
  .lb-feat .tx span { display:block; font-size:var(--mr-fs-xs); color:rgba(255,255,255,.78); margin-top:1px; }
  .lb-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:var(--mr-s2); position:relative; }
  .lb-stats .stat {
    background:rgba(255,255,255,.12); border-radius:var(--mr-radius-sm); padding:var(--mr-s3) var(--mr-s2);
    text-align:center; backdrop-filter:blur(2px);
  }
  .lb-stats .stat b { display:block; font-size:var(--mr-fs-lg); font-weight:700; }
  .lb-stats .stat span { display:block; font-size:10px; color:rgba(255,255,255,.78); margin-top:2px; }
  @media (max-width: 900px) {
    .mr-auth--split .mr-auth__card { grid-template-columns:1fr; }
    .mr-auth__brand { display:none; }
  }
</style>
<?= View::yield('head') ?>
</head>
<body>
<div class="mr-auth <?= !empty($split) ? 'mr-auth--split login-page' : '' ?>">
  <div class="mr-auth__card <?= !empty($split) ? 'login-box' : '' ?>">
    <?= View::yield('content') ?>
  </div>
</div>
<script type="module" src="<?= asset('js/core.js') ?>"></script>
<?= View::yield('scripts') ?>
</body>
</html>
