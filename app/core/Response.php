<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private int $status = 200;
    private array $headers = [];
    private string $body = '';

    public static function make(string $body = '', int $status = 200, array $headers = []): self
    {
        $r = new self();
        $r->body = $body;
        $r->status = $status;
        $r->headers = $headers;
        return $r;
    }

    public static function html(string $html, int $status = 200): self
    {
        return self::make($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(array $data, int $status = 200): self
    {
        return self::make(
            (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function success(mixed $data = [], array $meta = []): self
    {
        $payload = ['success' => true, 'data' => $data];
        if ($meta) {
            $payload['meta'] = $meta;
        }
        return self::json($payload);
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): self
    {
        $err = ['code' => $code, 'message' => $message];
        if ($details) {
            $err['details'] = $details;
        }
        return self::json(['success' => false, 'error' => $err], $status);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return self::make('', $status, ['Location' => $to]);
    }

    public static function download(string $content, string $filename, string $mime = 'application/octet-stream'): self
    {
        return self::make($content, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^\w.\-]/', '_', $filename) . '"',
            'Content-Length'      => (string)strlen($content),
        ]);
    }

    public function withHeader(string $k, string $v): self
    {
        $this->headers[$k] = $v;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach (Config::get('app.security_headers', []) as $k => $v) {
                header($k . ': ' . $v);
            }
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        echo $this->body;
    }
}
