<?php

declare(strict_types=1);

namespace Agus\Http;

/** The result of an HttpClient call. */
class HttpResponse
{
    public function __construct(
        private int $status,
        private string $body,
        private array $headers = [],
        private ?string $error = null,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function ok(): bool
    {
        return $this->error === null && $this->status >= 200 && $this->status < 300;
    }

    public function failed(): bool
    {
        return !$this->ok();
    }

    /** True when the request never reached the server (DNS, timeout, no internet). */
    public function networkError(): bool
    {
        return $this->error !== null;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** Decoded JSON, or $default when the body is not valid JSON. */
    public function json(mixed $default = null): mixed
    {
        $data = json_decode($this->body, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
