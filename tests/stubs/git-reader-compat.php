<?php

declare(strict_types=1);

/*
 * Compatibility shim for git-reader < 0.2, which does not ship authentication
 * support yet. It defines a faithful stub of the upcoming `GitReader\Credentials`
 * API so credential resolution can be exercised against the currently pinned
 * release — and so static analysis can reason about the real signatures.
 *
 * The definition is skipped automatically once git-reader 0.2+ provides the
 * real class, so this shim never collides with (or masks) the genuine code.
 * Delete this file once the package requires mrpunyapal/git-reader ^0.2.
 *
 * Also referenced from phpstan.neon `bootstrapFiles`.
 */

if (! class_exists(GitReader\Credentials::class)) {
    eval(<<<'PHP'
namespace GitReader;

final readonly class Credentials
{
    public function __construct(
        public readonly string $username,
        public readonly string $token,
    ) {
    }

    public static function make(string $token, string $username = 'x-access-token'): self
    {
        return new self($username, $token);
    }

    public function authorizationHeader(): string
    {
        return 'Basic ' . base64_encode($this->username . ':' . $this->token);
    }
}
PHP);
}
