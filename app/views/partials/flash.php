<?php
/**
 * @var array $flashes  List of ['type' => string, 'message' => string] pairs,
 *                       exactly as Session::pullFlash() returns them.
 */
$flashes = $flashes ?? [];
$map = ['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'];
foreach ($flashes as $item):
    $type    = (string)($item['type'] ?? 'info');
    $message = (string)($item['message'] ?? '');
    if ($message === '') {
        continue;
    }
    $cls = $map[$type] ?? 'info'; ?>
        <div class="mr-alert mr-alert--<?= e($cls) ?>" data-flash="<?= e($cls) ?>" role="alert">
            <span aria-hidden="true"><?= $cls === 'error' ? '⚠️' : ($cls === 'success' ? '✅' : 'ℹ️') ?></span>
            <span><?= e($message) ?></span>
        </div>
    <?php
endforeach;
