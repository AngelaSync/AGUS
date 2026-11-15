<?php

declare(strict_types=1);

namespace Agus\Http;

use Agus\Support\Session;

/**
 * What a controller returns. Nothing is printed until App calls send(),
 * so middleware can still change the status, headers or body.
 */
class Response
{
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return new self($json === false ? '{}' : $json, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($text, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Redirect to a path inside the app ('/login') or a full URL. */
    public static function redirect(string $to, int $status = 302): self
    {
        // route() already returns a full link (with the XAMPP folder), so only
        // plain paths like '/login' get the folder added here.
        $base = rtrim((string) (config('app.url', '') ?: (request()?->attribute('base_path') ?? '')), '/');
        $already = preg_match('#^https?://#i', $to) || ($base !== '' && ($to === $base || str_starts_with($to, $base . '/')));
        $location = $already ? $to : url($to);
        return new self('', $status, ['Location' => $location]);
    }

    /** Go back to the page the user came from (used after a failed form). */
    public static function back(string $fallback = '/'): self
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        return $referer ? new self('', 302, ['Location' => $referer]) : self::redirect($fallback);
    }

    /** Flash a one-time message for the next page: ->with('success', 'Saved!') */
    public function with(string $key, mixed $value): self
    {
        Session::flash($key, $value);
        return $this;
    }

    /** Keep the submitted form values so the view can refill the inputs. */
    public function withInput(array $input): self
    {
        unset($input['password'], $input['password_confirmation'], $input['_token']);
        Session::flash('_old_input', $input);
        return $this;
    }

    public function withErrors(array $errors): self
    {
        Session::flash('_errors', $errors);
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}
