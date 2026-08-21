<?php

declare(strict_types=1);

use Docsmith\Hub\Git\PackObjectStore;
use Docsmith\Hub\Git\ProtocolException;

it('applies insert and copy delta instructions', function (): void {
    // Insert "Hello " then copy 5 bytes at offset 6 ("World").
    $delta = "\x06Hello " . "\x91\x06\x05";
    $base = 'Hello World!';

    expect(PackObjectStore::applyDelta($base, $delta))->toBe('Hello World');
});

it('expands zero-size copy instructions to 64KB', function (): void {
    $base = str_repeat('x', 0x10000);

    expect(PackObjectStore::applyDelta($base, "\x80"))->toBe($base);
});

it('rejects reserved opcode zero', function (): void {
    PackObjectStore::applyDelta('abc', "\x00");
})->throws(ProtocolException::class);

it('rejects copies outside the base object', function (): void {
    PackObjectStore::applyDelta('short', "\x91\x63\x05");
})->throws(ProtocolException::class);
