<?php
declare(strict_types=1);

namespace App\Services\Sms;

use App\Core\Config;

final class KavenegarProvider implements SmsProviderInterface
{
    public function send(string $to, string $message): array
    {
        $apiKey = (string)Config::get('services.sms.api_key', '');
        $sender = (string)Config::get('services.sms.sender', '');
        if ($apiKey === '') {
            return ['success' => false, 'reference' => null, 'error' => 'SMS_API_KEY تنظیم نشده است.'];
        }

        $url  = 'https://api.kavenegar.com/v1/' . rawurlencode($apiKey) . '/sms/send.json';
        $post = http_build_query(['receptor' => $to, 'message' => $message, 'sender' => $sender]);

        $result = HttpClient::post($url, $post, (int)Config::get('services.sms.timeout', 10));
        if (!$result['ok']) {
            return ['success' => false, 'reference' => null, 'error' => $result['error']];
        }
        $data   = json_decode((string)$result['body'], true);
        $status = (int)($data['return']['status'] ?? 0);
        if ($status !== 200) {
            return ['success' => false, 'reference' => null, 'error' => (string)($data['return']['message'] ?? 'خطای نامشخص در ارسال پیامک')];
        }
        return ['success' => true, 'reference' => (string)($data['entries'][0]['messageid'] ?? ''), 'error' => null];
    }

    public function name(): string
    {
        return 'kavenegar';
    }
}
