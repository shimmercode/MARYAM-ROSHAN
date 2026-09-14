<?php
declare(strict_types=1);

namespace App\Services\Sms;

use App\Core\Config;

final class MelipayamakProvider implements SmsProviderInterface
{
    public function send(string $to, string $message): array
    {
        $user = (string)Config::get('services.sms.username', '');
        $pass = (string)Config::get('services.sms.password', '');
        $from = (string)Config::get('services.sms.sender', '');
        if ($user === '' || $pass === '') {
            return ['success' => false, 'reference' => null, 'error' => 'اطلاعات حساب ملی‌پیامک تنظیم نشده است.'];
        }

        $payload = json_encode([
            'username' => $user,
            'password' => $pass,
            'to'       => $to,
            'from'     => $from,
            'text'     => $message,
            'isflash'  => false,
        ], JSON_UNESCAPED_UNICODE);

        $result = HttpClient::post(
            'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
            (string)$payload,
            (int)Config::get('services.sms.timeout', 10),
            ['Content-Type: application/json']
        );
        if (!$result['ok']) {
            return ['success' => false, 'reference' => null, 'error' => $result['error']];
        }
        $data  = json_decode((string)$result['body'], true);
        $value = (string)($data['Value'] ?? '');
        $code  = (int)($data['RetStatus'] ?? 0);
        if ($code !== 1) {
            return ['success' => false, 'reference' => null, 'error' => (string)($data['StrRetStatus'] ?? 'ارسال ناموفق')];
        }
        return ['success' => true, 'reference' => $value, 'error' => null];
    }

    public function name(): string
    {
        return 'melipayamak';
    }
}
