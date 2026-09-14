<?php
declare(strict_types=1);

namespace App\Services\Sms;

interface SmsProviderInterface
{
    /**
     * @return array{success:bool, reference:?string, error:?string}
     */
    public function send(string $to, string $message): array;

    public function name(): string;
}
