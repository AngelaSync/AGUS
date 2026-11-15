<?php

declare(strict_types=1);

namespace Agus\Http;

use Agus\Support\Logger;

/**
 * Calls external APIs with cURL. Built for the "API Integration" part:
 * timeouts, JSON in/out, API-key headers, retries with back-off on
 * 429 (rate limited) and 5xx errors, and logging of failures.
 *
 *   $res = HttpClient::make('https://api.example.com')
 *       ->withHeaders(['Authorization' => 'Bearer ' . env('EXAMPLE_KEY')])
 *       ->timeout(8)
 *       ->retry(2)
 *       ->get('/v1/things', ['q' => 'bread']);
 *
 *   if ($res->ok()) { $data = $res->json(); }
 */
class HttpClient
{
    private array $headers = ['Accept' => 'application/json'];
    private int $timeout = 10;
    private int $retries = 0;
    private int $retryDelayMs = 500;

    public function __construct(private string $baseUrl = '')
    {
        $this->headers['User-Agent'] = (string) config('app.name', 'AGUS') . '/1.0 (student project)';
    }

    public static function make(string $baseUrl = ''): self
    {
        return new self($baseUrl);
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function withToken(string $token): self
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $token]);
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    /** Retry up to $times more on network errors, 429 and 5xx. */
    public function retry(int $times, int $delayMs = 500): self
    {
        $this->retries = max(0, $times);
        $this->retryDelayMs = max(0, $delayMs);
        return $this;
    }

    public function get(string $path, array $query = []): HttpResponse
    {
        return $this->send('GET', $path, $query);
    }

    public function post(string $path, array $data = [], bool $asJson = true): HttpResponse
    {
        return $this->send('POST', $path, [], $data, $asJson);
    }

    public function put(string $path, array $data = []): HttpResponse
    {
        return $this->send('PUT', $path, [], $data);
    }

    public function delete(string $path, array $query = []): HttpResponse
    {
        return $this->send('DELETE', $path, $query);
    }

    public function send(string $method, string $path, array $query = [], array $data = [], bool $asJson = true): HttpResponse
    {
        $url = preg_match('#^https?://#i', $path) ? $path : rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = $this->headers;
        $body = null;
        if ($data && $method !== 'GET') {
            if ($asJson) {
                $headers['Content-Type'] = 'application/json';
                $body = json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
                $body = http_build_query($data);
            }
        }

        $attempt = 0;
        do {
            $response = $this->execute($method, $url, $headers, $body);
            $retryable = $response->networkError() || $response->status() === 429 || $response->status() >= 500;
            if (!$retryable || $attempt >= $this->retries) {
                break;
            }
            // Respect Retry-After when the API sends it, otherwise back off 0.5s, 1s, 2s...
            $wait = (int) ($response->header('retry-after') ?? 0) * 1000 ?: $this->retryDelayMs * (2 ** $attempt);
            usleep(min($wait, 5000) * 1000);
            $attempt++;
        } while (true);

        if (!$response->ok()) {
            Logger::warning('External API call failed', [
                'method' => $method,
                'url' => preg_replace('/(key|token|apikey|appid)=[^&]+/i', '$1=***', $url),
                'status' => $response->status(),
                'error' => $response->error(),
            ]);
        }
        return $response;
    }

    private function execute(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        if (!function_exists('curl_init')) {
            return new HttpResponse(0, '', [], 'The PHP cURL extension is not enabled (enable extension=curl in php.ini).');
        }

        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => array_map(fn ($k, $v) => "$k: $v", array_keys($headers), $headers),
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $result === false ? curl_error($ch) : null;
        curl_close($ch);

        return new HttpResponse($status, $result === false ? '' : (string) $result, $responseHeaders, $error);
    }
}
