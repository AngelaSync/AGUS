<?php

declare(strict_types=1);

namespace Agus\Exceptions;

/**
 * Throw this anywhere to stop and show an error page:
 *   throw new HttpException(404, 'Listing not found');
 * Or use the helper: abort(404).
 */
class HttpException extends \RuntimeException
{
    public function __construct(private int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Please sign in to continue',
            403 => 'You are not allowed to do that',
            404 => 'Page not found',
            405 => 'Method not allowed',
            419 => 'Your session expired. Please refresh the page and try again',
            422 => 'Some fields need fixing',
            429 => 'Too many requests. Please slow down',
            default => 'Something went wrong',
        };
    }
}
