<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * Domain/business rule violation (booking conflict, insufficient stock, ...).
 */
class BusinessException extends HttpException
{
    public function __construct(string $message, private string $errorCode = 'BUSINESS_RULE', int $status = 409, array $details = [])
    {
        parent::__construct($status, $message, $details);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
