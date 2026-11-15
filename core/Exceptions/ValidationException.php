<?php

declare(strict_types=1);

namespace Agus\Exceptions;

/**
 * Thrown by $this->validate() in a controller. App turns it into
 * a redirect back with errors (forms) or a 422 JSON response (API).
 */
class ValidationException extends HttpException
{
    public function __construct(private array $errors, private array $input = [])
    {
        parent::__construct(422);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function input(): array
    {
        return $this->input;
    }
}
