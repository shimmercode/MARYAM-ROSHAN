<?php
declare(strict_types=1);

namespace App\Services\Sms;

/**
 * Minimal HTTP POST helper (cURL with a stream fallback).
 */
final class HttpClient
{
    /** @return array{ok:bool, body:?string, error:?string, status:int} */
    public static function post(string $url, string $body, int $timeout = 10, array $headers = []): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => $headers ?: ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $resp   = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err    = curl_error($ch);
            curl_close($ch);
            if ($resp === false) {
                return ['ok' => false, 'body' => null, 'error' => $err ?: 'خطای ارتباط با سرویس', 'status' => $status];
            }
            return ['ok' => $status >= 200 && $status < 300, 'body' => (string)$resp, 'error' => null, 'status' => $status];
        }

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers ?: ['Content-Type: application/x-www-form-urlencoded']),
                'content'       => $body,
                'timeout'       => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return ['ok' => false, 'body' => null, 'error' => 'خطای ارتباط با سرویس', 'status' => 0];
        }
        return ['ok' => true, 'body' => $resp, 'error' => null, 'status' => 200];
    }
}
