<?php

declare(strict_types=1);

namespace Docsmith\Hub\Git;

/**
 * Minimal HTTPS transport for Git smart-HTTP built on native PHP streams.
 *
 * No curl or PSR-18 dependency is required; TLS verification is enabled by
 * default. Redirects are followed manually so the effective URL is known to
 * callers (needed because upload-pack POSTs must target the redirected base).
 *
 * @internal
 */
final class SmartHttpTransport
{
    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function __construct(
        private readonly float $timeout = 60.0,
        private readonly bool $verifyTls = true,
        private readonly int $maxRedirects = 5,
        private readonly string $userAgent = 'docsmith-hub/0.1',
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): TransportResponse
    {
        return $this->request('GET', $url, null, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function post(string $url, string $body, array $headers = []): TransportResponse
    {
        return $this->request('POST', $url, $body, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method, string $url, ?string $body, array $headers): TransportResponse
    {
        $currentUrl = $url;
        $currentMethod = $method;
        $currentBody = $body;

        for ($redirect = 0; $redirect <= $this->maxRedirects; $redirect++) {
            [$stream, $rawHeaders] = $this->open($currentUrl, $currentMethod, $currentBody, $headers);

            if (! is_resource($stream)) {
                $error = error_get_last()['message'] ?? 'unknown error';

                throw new GitException(sprintf(
                    'Unable to connect to %s (%s).',
                    $currentUrl,
                    preg_replace('/^.*?:\s*/', '', (string) $error) ?: 'unknown error',
                ));
            }

            /** @var list<string> $rawHeaders */
            [$status, $headerMap] = $this->parseHeaders($rawHeaders);
            $location = $headerMap['location'] ?? null;

            if (in_array($status, self::REDIRECT_STATUSES, true) && is_string($location) && $location !== '') {
                fclose($stream);

                if ($status === 303) {
                    $currentMethod = 'GET';
                    $currentBody = null;
                }

                $currentUrl = $this->resolveLocation($currentUrl, $location);

                continue;
            }

            return new TransportResponse(
                status: $status,
                headers: $headerMap,
                bodyStream: $stream,
                effectiveUrl: $currentUrl,
            );
        }

        throw new ProtocolException(sprintf('Too many redirects while requesting %s.', $url));
    }

    /**
     * @param  array<string, string>  $headers
     */
    /**
     * @param  array<string, string>  $headers
     * @return array{resource|false, list<string>}
     */
    private function open(string $url, string $method, ?string $body, array $headers): array
    {
        $lines = [
            'User-Agent: ' . $this->userAgent,
            'Accept: */*',
        ];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $lines),
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => $this->timeout,
            ],
            'ssl' => [
                'verify_peer' => $this->verifyTls,
                'verify_peer_name' => $this->verifyTls,
            ],
        ];

        if ($body !== null) {
            $options['http']['content'] = $body;
        }

        $previous = error_reporting(0);
        /** @var resource|false $stream */
        $stream = fopen($url, 'rb', false, stream_context_create($options));
        error_reporting($previous);

        // The stream wrapper populates $http_response_header in this scope.
        $responseHeaders = [];

        foreach ((array) $http_response_header as $line) {
            $responseHeaders[] = (string) $line;
        }

        return [$stream, $responseHeaders];
    }

    /**
     * @param  list<string> $rawHeaders
     * @return array{int, array<string, string>}
     */
    private function parseHeaders(array $rawHeaders): array
    {
        $statusLine = null;
        $map = [];

        foreach ($rawHeaders as $line) {
            if (str_starts_with($line, 'HTTP/')) {
                $statusLine = $line;
                $map = [];

                continue;
            }

            $separator = strpos($line, ':');

            if ($separator === false) {
                continue;
            }

            $map[strtolower(trim(substr($line, 0, $separator)))] = trim(substr($line, $separator + 1));
        }

        $status = 0;

        if ($statusLine !== null && preg_match('#^HTTP/\S+\s+(\d{3})#', $statusLine, $matches) === 1) {
            $status = (int) $matches[1];
        }

        if ($status === 0) {
            throw new ProtocolException('Malformed HTTP response: no status line received.');
        }

        return [$status, $map];
    }

    private function resolveLocation(string $baseUrl, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($baseUrl);
        $host = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        if (($parts['port'] ?? null) !== null) {
            $host .= ':' . $parts['port'];
        }

        if (str_starts_with($location, '/')) {
            return $host . $location;
        }

        $path = isset($parts['path']) ? (string) preg_replace('#/[^/]*$#', '/', $parts['path']) : '/';

        return $host . $path . $location;
    }
}
