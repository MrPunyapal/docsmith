<?php

declare(strict_types=1);

namespace Docsmith\Hub\Git;

/**
 * A completed smart-HTTP response.
 *
 * @internal
 */
final class TransportResponse
{
    /**
     * @param resource             $bodyStream
     * @param array<string, string> $headers   Header map with lowercase names.
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly mixed $bodyStream,
        public readonly string $effectiveUrl,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function close(): void
    {
        if (is_resource($this->bodyStream)) {
            fclose($this->bodyStream);
        }
    }

    /**
     * Read the remaining body into a string and close the stream.
     */
    public function body(): string
    {
        $contents = stream_get_contents($this->bodyStream);
        $this->close();

        return is_string($contents) ? $contents : '';
    }
}
