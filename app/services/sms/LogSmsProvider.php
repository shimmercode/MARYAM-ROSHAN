<?php
declare(strict_types=1);

namespace App\Services\Sms;

use App\Core\Logger;

/**
 * Default provider: writes messages to the log instead of sending them.
 * This lets the entire system work without any SMS credentials.
 */
final class LogSmsProvider implements SmsProviderInterface
{
    public function send(string $to, string $message): array
    {
        Logger::info('SMS (log driver)', ['to' => $to, 'message' => $message]);
        return ['success' => true, 'reference' => 'LOG-' . bin2hex(random_bytes(6)), 'error' => null];
    }

    public function name(): string
    {
        return 'log';
    }
}
