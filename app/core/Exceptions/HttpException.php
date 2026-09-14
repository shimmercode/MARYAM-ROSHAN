<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(private int $status, string $message = '', private array $details = [])
    {
        parent::__construct($message !== '' ? $message : 'خطا رخ داد.');
    }

    public function status(): int
    {
        return $this->status;
    }

    public function details(): array
    {
        return $this->details;
    }
}
