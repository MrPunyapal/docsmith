<?php

declare(strict_types=1);

namespace Docsmith\Hub\Git;

/**
 * Encodes pkt-line frames and wraps string payloads in a stream.
 *
 * @internal
 */
final class PktLine
{
    public static function encode(string $payload): string
    {
        $length = strlen($payload) + 4;

        if ($length > 65520) {
            throw new ProtocolException('pkt-line payload exceeds the maximum size of 65516 bytes.');
        }

        return sprintf('%04x%s', $length, $payload);
    }

    public static function flush(): string
    {
        return "0000";
    }

    /**
     * Wrap a complete in-memory body in a temp stream so it can be read
     * with PktLineReader like any other response.
     *
     * @return resource
     */
    public static function bodyStream(string $body): mixed
    {
        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            throw new ProtocolException('Unable to open a temporary stream.');
        }

        fwrite($stream, $body);
        rewind($stream);

        return $stream;
    }
}
