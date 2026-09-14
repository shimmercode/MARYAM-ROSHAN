<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

class ValidationException extends HttpException
{
    public function __construct(array $errors, string $message = 'اطلاعات وارد شده صحیح نیست.')
    {
        parent::__construct(422, $message, $errors);
    }

    public function errors(): array
    {
        return $this->details();
    }
}
