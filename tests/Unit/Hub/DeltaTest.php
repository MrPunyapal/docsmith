<?php

declare(strict_types=1);

use Docsmith\Hub\Git\PackObjectStore;
use Docsmith\Hub\Git\ProtocolException;

it('applies insert and copy delta instructions after the size header', function (): void {
    // Base "Hello World!" (12 bytes) -> target "Hello World" (11 bytes).
    $header = "\x0c\x0b";
    $delta = $header . "\x06Hello " . "\x91\x06\x05";

    expect(PackObjectStore::applyDelta('Hello World!', $delta))->toBe('Hello World');
});

it('expands zero-size copy instructions to 64KB', function (): void {
    $base = str_repeat('x', 0x10000);

    // varints for 65536 on both sides, then copy(offset 0, size 0 => 65536).
    $delta = "\x80\x80\x04\x80\x80\x04\x80";

    expect(PackObjectStore::applyDelta($base, $delta))->toBe($base);
});

it('rejects reserved opcode zero', function (): void {
    PackObjectStore::applyDelta('abc', "\x03\x03\x00");
})->throws(ProtocolException::class);

it('rejects copies outside the base object', function (): void {
    PackObjectStore::applyDelta('short', "\x05\x05\x91\x63\x05");
})->throws(ProtocolException::class);

it('rejects a delta whose declared source size mismatches the base', function (): void {
    PackObjectStore::applyDelta('abc', "\x09\x09");
})->throws(ProtocolException::class, 'expects 9');

it('rejects truncated size headers', function (): void {
    PackObjectStore::applyDelta('abc', "\x03\x93");
})->throws(ProtocolException::class, 'truncated');
