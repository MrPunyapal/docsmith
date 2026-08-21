<?php

declare(strict_types=1);

namespace Docsmith\Hub\Git;

/**
 * Parsed result of `info/refs?service=git-upload-pack` ref discovery.
 */
final readonly class RefAdvertisement
{
    /**
     * @param  array<string, string>  $refs    Full ref name => SHA (e.g. refs/heads/main => abc...).
     * @param  array<string, string>  $peeled  Ref name => peeled SHA for annotated tags.
     * @param  list<string>           $capabilities
     */
    public function __construct(
        public array $refs,
        public array $peeled,
        public array $capabilities,
    ) {
    }

    public function shaFor(string $refName): ?string
    {
        return $this->refs[$refName] ?? null;
    }

    /**
     * Resolve a user-facing ref using Git's own candidate ordering.
     *
     * Order: HEAD, exact name, refs/<ref>, refs/tags/<ref>, refs/heads/<ref>,
     * refs/remotes/<ref>, refs/remotes/<ref>/HEAD. Raw 40-hex SHAs are accepted
     * when they match any advertised tip.
     */
    public function resolve(string $ref): ResolvedRef
    {
        $ref = trim($ref);

        if ($ref === '') {
            throw new RefNotFoundException('The requested ref is empty.');
        }

        if ($this->isRawSha($ref)) {
            $sha = strtolower($ref);

            foreach ($this->refs as $advertisedSha) {
                if (strtolower($advertisedSha) === $sha) {
                    return new ResolvedRef($ref, $sha);
                }
            }

            foreach ($this->peeled as $peeledSha) {
                if (strtolower($peeledSha) === $sha) {
                    return new ResolvedRef($ref, $sha);
                }
            }

            throw new RefNotFoundException(sprintf(
                'Commit [%s] is not a branch or tag tip on the remote. Pinning arbitrary commits is not supported; reference an existing branch or tag instead.',
                $ref,
            ));
        }

        if ($ref === 'HEAD') {
            $candidates = ['HEAD'];
        } elseif (str_starts_with($ref, 'refs/')) {
            $candidates = [$ref];
        } else {
            $candidates = [
                'refs/' . $ref,
                'refs/tags/' . $ref,
                'refs/heads/' . $ref,
                'refs/remotes/' . $ref,
                'refs/remotes/' . $ref . '/HEAD',
            ];
        }

        foreach ($candidates as $candidate) {
            $sha = $this->shaFor($candidate);

            if ($sha === null) {
                continue;
            }

            // Prefer the peeled commit of annotated tags so we fetch history
            // directly instead of the tag wrapper object.
            $effective = strtolower($this->peeled[$candidate] ?? $sha);

            return new ResolvedRef($candidate, $effective);
        }

        throw new RefNotFoundException(sprintf(
            'Ref [%s] was not found on the remote repository.',
            $ref,
        ));
    }

    private function isRawSha(string $ref): bool
    {
        return preg_match('/^[0-9a-fA-F]{40}$/', $ref) === 1
            || preg_match('/^[0-9a-fA-F]{64}$/', $ref) === 1;
    }
}
