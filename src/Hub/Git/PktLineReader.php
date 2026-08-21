<?php

declare(strict_types=1);

namespace Docsmith\Hub\Git;

/**
 * Reads pkt-line framed packets from a PHP stream.
 *
 * @internal
 */
final class PktLineReader
{
    private const FLUSH_PREFIX = '0000';

    /**
     * @param  resource  $stream
     */
    public function __construct(private readonly mixed $stream)
    {
    }

    /**
     * Read the next packet payload.
     *
     * @return string|null the payload; an empty string when a flush-pkt was
     *                     consumed; null when the stream ended cleanly.
     *
     * @throws ProtocolException on malformed framing or ERR packets.
     */
    public function read(): ?string
    {
        $prefix = $this->readExact(4);

        if ($prefix === null) {
            return null;
        }

        if ($prefix === self::FLUSH_PREFIX) {
            return '';
        }

        if (! ctype_xdigit($prefix)) {
            throw new ProtocolException(sprintf('Malformed pkt-line length prefix "%s".', $prefix));
        }

        $length = (int) hexdec($prefix);

        if ($length < 4) {
            throw new ProtocolException(sprintf('Malformed pkt-line length prefix "%s".', $prefix));
        }

        $payload = $this->readPayload($length - 4);
        $payload = rtrim($payload, "\n");

        if (str_starts_with($payload, 'ERR ')) {
            throw new ProtocolException('Remote Git server reported an error: ' . substr($payload, 4));
        }

        return $payload;
    }

    /**
     * @return resource The underlying stream, positioned after the last packet.
     */
    public function stream(): mixed
    {
        return $this->stream;
    }

    /**
     * @return string|null null only when the stream ends exactly at a boundary.
     */
    private function readExact(int $length): ?string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread($this->stream, max(1, $length - strlen($buffer)));

            if ($chunk === false || $chunk === '') {
                if ($buffer === '') {
                    return null;
                }

                throw new ProtocolException('Unexpected end of stream inside a pkt-line.');
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function readPayload(int $length): string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread($this->stream, max(1, $length - strlen($buffer)));

            if ($chunk === false || $chunk === '') {
                throw new ProtocolException('Unexpected end of stream while reading a pkt-line payload.');
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }
}
