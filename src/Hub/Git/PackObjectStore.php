<?php

declare(strict_types=1);

namespace Docsmith\Hub\Git;

/**
 * A temporary local object database populated from a fetched packfile.
 *
 * The parser streams the pack sequentially, inflating each zlib-compressed
 * entry with an exact byte-position tracking strategy so delta chains can be
 * resolved without loading the whole pack into memory. Reconstructed objects
 * are written to a loose-object directory keyed by SHA-1.
 *
 * @internal
 */
final class PackObjectStore
{
    private const string PACK_SIGNATURE = 'PACK';

    /** @var resource|null */
    private $packStream;

    /** @var array<string, GitObjectType> */
    private array $types = [];

    /** @var array<int, string> */
    private array $offsetToSha = [];

    /** @var array<int, array{GitObjectType, string}> */
    private array $cache = [];

    private int $cacheBytes = 0;

    private bool $cleaned = false;

    private function __construct(
        private readonly string $storeDir,
        private readonly string $packPath,
        private readonly int $memoryBudgetBytes,
    ) {
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * Import a packfile into a fresh loose-object store.
     */
    public static function import(
        string $packPath,
        ?string $storeDir = null,
        int $memoryBudgetBytes = 64 * 1024 * 1024,
    ): self {
        if (! is_file($packPath)) {
            throw new GitException(sprintf('Packfile [%s] does not exist.', $packPath));
        }

        $storeDir ??= (sys_get_temp_dir() . '/docsmith-objects-' . uniqid('', true));

        if (! is_dir($storeDir) && ! mkdir($storeDir, 0777, true)) {
            throw new GitException(sprintf('Unable to create object store directory [%s].', $storeDir));
        }

        $store = new self($storeDir, $packPath, $memoryBudgetBytes);
        $store->parse();

        return $store;
    }

    /**
     * Seed a store directly from in-memory objects (used by tests).
     *
     * @param  array<string, GitObject>  $objects  Map of sha => object.
     */
    public static function seeded(array $objects): self
    {
        $storeDir = sys_get_temp_dir() . '/docsmith-objects-' . uniqid('', true);

        if (! is_dir($storeDir) && ! mkdir($storeDir, 0777, true)) {
            throw new GitException(sprintf('Unable to create object store directory [%s].', $storeDir));
        }

        $store = new self($storeDir, '', 0);
        $store->packStream = null;

        foreach ($objects as $object) {
            $store->types[$object->sha] = $object->type;

            $directory = dirname($store->loosePath($object->sha));

            if (! is_dir($directory) && ! mkdir($directory, 0777, true)) {
                throw new GitException(sprintf('Unable to seed object directory [%s].', $directory));
            }

            if (file_put_contents($store->loosePath($object->sha), $object->data) === false) {
                throw new GitException(sprintf('Unable to seed object [%s].', $object->sha));
            }
        }

        return $store;
    }

    public function storeDir(): string
    {
        return $this->storeDir;
    }

    public function has(string $sha): bool
    {
        return isset($this->types[strtolower($sha)]);
    }

    public function type(string $sha): ?GitObjectType
    {
        return $this->types[strtolower($sha)] ?? null;
    }

    public function object(string $sha): GitObject
    {
        $sha = strtolower($sha);

        $type = $this->types[$sha] ?? null;

        if (in_array($type, [null, GitObjectType::OfsDelta, GitObjectType::RefDelta], true)) {
            throw new GitException(sprintf('Object [%s] is not present in the fetched pack.', $sha));
        }

        return new GitObject($type, $sha, $this->looseContents($sha));
    }

    /**
     * Resolve a commit (or annotated tag pointing at one) to its root tree SHA.
     */
    public function commitTreeSha(string $commitOrTagSha): string
    {
        $sha = $this->peelToCommit($commitOrTagSha);
        $data = $this->object($sha)->data;

        if (preg_match('/^tree ([0-9a-f]{40})$/m', $data, $matches) !== 1) {
            throw new ProtocolException(sprintf('Commit [%s] has no tree header.', $sha));
        }

        return $matches[1];
    }

    /**
     * Follow tag objects until a commit is reached.
     */
    public function peelToCommit(string $sha): string
    {
        $seen = [];

        while (true) {
            $sha = strtolower($sha);

            if (isset($seen[$sha])) {
                throw new ProtocolException('Tag chain contains a cycle.');
            }

            $seen[$sha] = true;
            $type = $this->types[$sha] ?? null;

            if ($type === GitObjectType::Commit) {
                return $sha;
            }

            if ($type !== GitObjectType::Tag) {
                throw new GitException(sprintf(
                    'Object [%s] is neither a commit nor a tag; cannot resolve documentation tip.',
                    $sha,
                ));
            }

            if (preg_match('/^object ([0-9a-f]{40})$/m', $this->looseContents($sha), $matches) !== 1) {
                throw new ProtocolException(sprintf('Tag object [%s] has no target header.', $sha));
            }

            $sha = $matches[1];
        }
    }

    /**
     * @return list<TreeEntry>
     */
    public function treeEntries(string $treeSha): array
    {
        $object = $this->object($treeSha);

        if ($object->type !== GitObjectType::Tree) {
            throw new GitException(sprintf('Object [%s] is not a tree.', strtolower($treeSha)));
        }

        return TreeParser::parse($object->data);
    }

    /**
     * Walk a slash-separated path below a tree and return the destination entry.
     *
     * @throws GitException when any segment is missing or not traversable.
     */
    public function resolveTreePath(string $rootTreeSha, string $path): TreeEntry
    {
        $segments = array_values(array_filter(
            explode('/', trim(str_replace('\\', '/', $path), '/')),
            fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return new TreeEntry(TreeEntry::MODE_DIRECTORY, '', strtolower($rootTreeSha));
        }

        $currentSha = strtolower($rootTreeSha);
        $walked = '';

        foreach ($segments as $index => $segment) {
            $match = null;

            foreach ($this->treeEntries($currentSha) as $candidate) {
                if ($candidate->name === $segment) {
                    $match = $candidate;

                    break;
                }
            }

            if ($match === null) {
                throw new GitException(sprintf(
                    'Path [%s] was not found in the repository%s.',
                    $walked === '' ? $segment : $walked . '/' . $segment,
                    trim($path, '/ ') === '' ? '' : sprintf(' (requested path: %s)', $path),
                ));
            }

            $walked = $walked === '' ? $segment : $walked . '/' . $segment;
            $isLast = $index === count($segments) - 1;

            if ($isLast) {
                return $match;
            }

            if (! $match->isDirectory()) {
                throw new GitException(sprintf('Path segment [%s] is not a directory.', $walked));
            }

            $currentSha = strtolower($match->sha);
        }

        throw new GitException(sprintf('Path [%s] could not be resolved.', $path));
    }

    /**
     * Recursively flatten a tree into path/mode/sha tuples, directories included.
     *
     * @return list<array{path: string, mode: int, sha: string}>
     */
    public function flattenTree(string $treeSha, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($this->treeEntries($treeSha) as $entry) {
            $path = $prefix === '' ? $entry->name : $prefix . '/' . $entry->name;

            if ($entry->isDirectory()) {
                $flattened[] = ['path' => $path, 'mode' => $entry->mode, 'sha' => $entry->sha];
                $flattened = [...$flattened, ...$this->flattenTree($entry->sha, $path)];

                continue;
            }

            $flattened[] = ['path' => $path, 'mode' => $entry->mode, 'sha' => $entry->sha];
        }

        return $flattened;
    }

    /**
     * Absolute filesystem path of the loose object file for a SHA.
     */
    public function loosePath(string $sha): string
    {
        $sha = strtolower($sha);

        return $this->storeDir . '/' . substr($sha, 0, 2) . '/' . substr($sha, 2);
    }

    public function looseSize(string $sha): int
    {
        $size = filesize($this->loosePath($sha));

        if ($size === false) {
            throw new GitException(sprintf('Object [%s] disappeared from the temporary store.', strtolower($sha)));
        }

        return $size;
    }

    /**
     * Stream a loose object's contents to a destination file without buffering
     * the whole blob in memory.
     */
    public function copyLooseTo(string $sha, string $destination): void
    {
        $source = fopen($this->loosePath($sha), 'rb');
        $target = fopen($destination, 'wb');

        if ($source === false || $target === false) {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_resource($target)) {
                fclose($target);
            }

            throw new GitException(sprintf('Unable to stage object [%s].', strtolower($sha)));
        }

        try {
            while (! feof($source)) {
                $chunk = fread($source, 256 * 1024);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                fwrite($target, $chunk);
            }
        } finally {
            fclose($source);
            fclose($target);
        }
    }

    public function looseContents(string $sha): string
    {
        $contents = file_get_contents($this->loosePath($sha));

        if ($contents === false) {
            throw new GitException(sprintf('Unable to read object [%s] from the temporary store.', strtolower($sha)));
        }

        return $contents;
    }

    /**
     * Delete the temporary store directory.
     */
    public function cleanup(): void
    {
        if ($this->cleaned) {
            return;
        }

        $this->cleaned = true;

        if (is_resource($this->packStream)) {
            fclose($this->packStream);
        }

        if (is_dir($this->storeDir)) {
            self::removeDirectory($this->storeDir);
        }

        if ($this->packPath !== '' && is_file($this->packPath)) {
            @unlink($this->packPath);
        }
    }

    private static function removeDirectory(string $directory): void
    {
        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path) && ! is_link($path)) {
                self::removeDirectory($path);

                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }

    private function writeLoose(string $sha, GitObjectType $type, string $rawData): void
    {
        $directory = dirname($this->loosePath($sha));

        if (! is_dir($directory) && ! mkdir($directory, 0777, true)) {
            throw new GitException(sprintf('Unable to create object directory [%s].', $directory));
        }

        if (file_put_contents($this->loosePath($sha), $rawData) === false) {
            throw new GitException(sprintf('Unable to write object [%s] to the temporary store.', $sha));
        }

        $this->types[$sha] = $type;
    }

    // ---------------------------------------------------------------- parsing

    private function parse(): void
    {
        $stream = fopen($this->packPath, 'rb');

        if ($stream === false) {
            throw new GitException(sprintf('Unable to open packfile [%s].', $this->packPath));
        }

        $this->packStream = $stream;

        $signature = fread($stream, 4);

        if ($signature !== self::PACK_SIGNATURE) {
            throw new ProtocolException('Downloaded packfile has an invalid signature.');
        }

        /** @var array{1: int} $versionParts */
        $versionParts = unpack('N', (string) fread($stream, 4));
        $version = $versionParts[1];
        /** @var array{1: int} $countParts */
        $countParts = unpack('N', (string) fread($stream, 4));
        $count = $countParts[1];

        if ($version !== 2 && $version !== 3) {
            throw new ProtocolException(sprintf('Unsupported packfile version %d.', $version));
        }

        /** @var list<int> $deferred */
        $deferred = [];

        for ($index = 0; $index < $count; $index++) {
            $offset = ftell($stream);

            if ($offset === false) {
                throw new ProtocolException('Corrupt packfile: unable to determine entry offset.');
            }

            [$type, $baseReference] = $this->readEntryHeader($stream);

            if ($type === GitObjectType::RefDelta && is_string($baseReference) && ! $this->has($baseReference)) {
                // Base appears later in the pack; retry after the sequential pass.
                $this->skipEntryData($stream);
                $deferred[] = $offset;

                continue;
            }

            $this->materializeAt($stream, $offset);
        }

        $rounds = 0;

        while ($deferred !== []) {
            $remaining = [];

            foreach ($deferred as $offset) {
                fseek($stream, $offset);

                [$type, $baseReference] = $this->readEntryHeader($stream);

                if ($type === GitObjectType::RefDelta && is_string($baseReference) && ! $this->has($baseReference)) {
                    $remaining[] = $offset;

                    continue;
                }

                fseek($stream, $offset);
                $this->materializeAt($stream, $offset);
            }

            if (count($remaining) === count($deferred) || ++$rounds > 32) {
                throw new ProtocolException('Corrupt packfile: unresolvable ref-delta base.');
            }

            $deferred = $remaining;
        }

        $this->verifyTrailer($stream);
    }

    /**
     * Reconstruct the object stored at `$offset`, caching it in the loose store.
     *
     * @param resource $stream
     */
    private function materializeAt($stream, int $offset): void
    {
        fseek($stream, $offset);

        [$type, $baseReference] = $this->readEntryHeader($stream);
        $raw = $this->inflateCurrentEntry($stream, $this->pendingEntrySize);

        if ($type === GitObjectType::OfsDelta) {
            $baseOffset = $offset - (is_int($baseReference) ? $baseReference : 0);
            $base = $this->loadCached($baseOffset);
            $data = self::applyDelta($base->data, $raw);
            $sha = $this->hashObject($base->type, $data);
            $resolvedType = $base->type;
        } elseif ($type === GitObjectType::RefDelta) {
            $baseSha = is_string($baseReference) ? strtolower($baseReference) : '';
            $base = $this->object($baseSha);
            $data = self::applyDelta($base->data, $raw);
            $sha = $this->hashObject($base->type, $data);
            $resolvedType = $base->type;
        } else {
            $data = $raw;
            $sha = $this->hashObject($type, $data);
            $resolvedType = $type;
        }

        $this->writeLoose($sha, $resolvedType, $data);
        $this->offsetToSha[$offset] = $sha;
        $this->cachePut($offset, $resolvedType, $data);
    }

    /**
     * Return a previously materialized object, re-reading it from disk on cache miss.
     */
    private function loadCached(int $offset): GitObject
    {
        if (isset($this->cache[$offset])) {
            [$type, $data] = $this->cache[$offset];

            return new GitObject($type, $this->offsetToSha[$offset], $data);
        }

        $sha = $this->offsetToSha[$offset] ?? null;

        if ($sha === null) {
            throw new ProtocolException('Corrupt packfile: ofs-delta points before the pack start.');
        }

        $type = $this->types[$sha] ?? GitObjectType::Blob;
        $data = $this->looseContents($sha);
        $this->cachePut($offset, $type, $data);

        return new GitObject($type, $sha, $data);
    }

    private function cachePut(int $offset, GitObjectType $type, string $data): void
    {
        $this->cache[$offset] = [$type, $data];
        $this->cacheBytes += strlen($data);

        while ($this->cacheBytes > $this->memoryBudgetBytes && count($this->cache) > 1) {
            $oldest = array_key_first($this->cache);
            [, $oldData] = $this->cache[$oldest];
            $this->cacheBytes -= strlen($oldData);
            unset($this->cache[$oldest]);
        }
    }

    /** Size of the entry whose header was parsed last. */
    private int $pendingEntrySize = 0;

    /**
     * @param  resource $stream
     * @return array{GitObjectType, int|string|null}
     */
    private function readEntryHeader($stream): array
    {
        $byte = $this->readByte($stream);
        $typeValue = ($byte >> 4) & 0x07;
        $size = $byte & 0x0F;
        $shift = 4;

        while (($byte & 0x80) !== 0) {
            $byte = $this->readByte($stream);
            $size |= ($byte & 0x7F) << $shift;
            $shift += 7;
        }

        $type = GitObjectType::tryFrom($typeValue);

        if ($type === null) {
            throw new ProtocolException(sprintf('Corrupt packfile: unknown object type %d.', $typeValue));
        }

        $this->pendingEntrySize = $size;

        if ($type === GitObjectType::OfsDelta) {
            return [$type, $this->readBaseOffsetVarint($stream)];
        }

        if ($type === GitObjectType::RefDelta) {
            $bytes = fread($stream, 20);

            if ($bytes === false || strlen($bytes) !== 20) {
                throw new ProtocolException('Corrupt packfile: truncated ref-delta base hash.');
            }

            return [$type, bin2hex($bytes)];
        }

        return [$type, null];
    }

    /**
     * Skip an entry whose delta base is not yet available; its bytes are still
     * consumed so the sequential cursor stays correct.
     *
     * @param  resource  $stream
     */
    private function skipEntryData($stream): void
    {
        $this->inflateCurrentEntry($stream, $this->pendingEntrySize);
    }

    /**
     * Inflate exactly one zlib stream from the current position and leave the
     * file cursor immediately after it.
     *
     * @param  resource $stream
     */
    private function inflateCurrentEntry($stream, int $expectedSize): string
    {
        $context = inflate_init(ZLIB_ENCODING_DEFLATE);

        if ($context === false) {
            throw new GitException('Unable to initialise zlib inflation context.');
        }

        $startPosition = ftell($stream);

        if ($startPosition === false) {
            throw new ProtocolException('Corrupt packfile: unable to determine stream position.');
        }

        $output = '';
        $fed = 0;

        while (inflate_get_status($context) !== $this->streamEndStatus()) {
            $chunk = fread($stream, 128 * 1024);

            if ($chunk === false || $chunk === '') {
                throw new ProtocolException('Corrupt packfile: unexpected EOF inside compressed entry.');
            }

            $fed += strlen($chunk);
            $inflated = inflate_add($context, $chunk);

            if ($inflated === false) {
                throw new ProtocolException('Corrupt packfile: invalid compressed data.');
            }

            $output .= $inflated;

            if (strlen($output) > max($expectedSize * 2, 1024 * 1024) + 4096) {
                throw new ProtocolException('Compressed entry expands beyond expected size; refusing to continue.');
            }
        }

        if (strlen($output) !== $expectedSize) {
            throw new ProtocolException('Corrupt packfile: inflated size does not match entry header.');
        }

        // Reposition past bytes that belonged to this entry's zlib stream only.
        $consumed = inflate_get_read_len($context);
        fseek($stream, $startPosition + $consumed);

        return $output;
    }

    private function streamEndStatus(): int
    {
        return defined('ZLIB_STREAM_END') ? constant('ZLIB_STREAM_END') : 1;
    }

    /**
     * @param  resource $stream
     */
    private function readByte($stream): int
    {
        $byte = fread($stream, 1);

        if ($byte === false || $byte === '') {
            throw new ProtocolException('Corrupt packfile: unexpected EOF in entry header.');
        }

        return ord($byte);
    }

    /**
     * Negative-offset varint used by ofs-delta entries.
     *
     * @param resource $stream
     */
    private function readBaseOffsetVarint($stream): int
    {
        $byte = $this->readByte($stream);
        $offset = $byte & 0x7F;

        while (($byte & 0x80) !== 0) {
            $byte = $this->readByte($stream);
            $offset = (($offset + 1) << 7) | ($byte & 0x7F);
        }

        return $offset;
    }

    /**
     * @param resource $stream
     */
    private function verifyTrailer($stream): void
    {
        $size = filesize($this->packPath);

        if ($size === false || $size < 52) {
            throw new ProtocolException('Corrupt packfile: too small.');
        }

        if (fseek($stream, $size - 20) !== 0) {
            throw new ProtocolException('Corrupt packfile: unable to seek to trailer.');
        }

        $trailer = fread($stream, 20);

        if ($trailer === false || strlen($trailer) !== 20) {
            throw new ProtocolException('Corrupt packfile: truncated trailer.');
        }

        $context = hash_init('sha1');

        $handle = fopen($this->packPath, 'rb');

        if ($handle === false) {
            throw new GitException(sprintf('Unable to reopen packfile [%s].', $this->packPath));
        }

        try {
            $remaining = $size - 20;

            while ($remaining > 0) {
                $chunk = fread($handle, min(256 * 1024, $remaining));

                if ($chunk === false || $chunk === '') {
                    throw new ProtocolException('Corrupt packfile: read failure during verification.');
                }

                hash_update($context, $chunk);
                $remaining -= strlen($chunk);
            }
        } finally {
            fclose($handle);
        }

        if (hash_final($context, true) !== $trailer) {
            throw new ProtocolException('Packfile checksum mismatch; download is corrupt.');
        }
    }

    private function hashObject(GitObjectType $type, string $data): string
    {
        return sha1($type->label() . ' ' . strlen($data) . "\0" . $data);
    }

    /**
     * Apply a git delta to a base buffer.
     *
     * Delta payload = varint(source size) + varint(target size) + instruction
     * stream of copy/insert opcodes.
     */
    public static function applyDelta(string $base, string $delta): string
    {
        $position = 0;
        $length = strlen($delta);

        $sourceSize = self::readDeltaVarint($delta, $position);
        $targetSize = self::readDeltaVarint($delta, $position);

        if ($sourceSize !== strlen($base)) {
            throw new ProtocolException(sprintf(
                'Corrupt delta: base is %d bytes but delta expects %d.',
                strlen($base),
                $sourceSize,
            ));
        }

        $output = '';

        while ($position < $length) {
            $opcode = ord($delta[$position++]);

            if (($opcode & 0x80) !== 0) {
                $copyOffset = 0;
                $copySize = 0;

                if (($opcode & 0x01) !== 0) {
                    $copyOffset |= ord($delta[$position++] ?? throw new ProtocolException('Corrupt delta: truncated copy offset.'));
                }

                if (($opcode & 0x02) !== 0) {
                    $copyOffset |= (ord($delta[$position++] ?? throw new ProtocolException('Corrupt delta: truncated copy offset.')) << 8);
                }

                if (($opcode & 0x04) !== 0) {
                    $copyOffset |= (ord($delta[$position++] ?? throw new ProtocolException('Corrupt delta: truncated copy offset.')) << 16);
                }

                if (($opcode & 0x08) !== 0) {
                    $copyOffset |= (ord($delta[$position++] ?? throw new ProtocolException('Corrupt delta: truncated copy offset.')) << 24);
                }

                if (($opcode & 0x10) !== 0) {
                    $copySize |= ord($delta[$position++] ?? throw new ProtocolException('Corrupt delta: truncated copy size.'));
                }

                if (($opcode & 0x20) !== 0) {
                    $copySize |= (ord($delta[$position++] ?? throw new ProtocolException('Corrupt delta: truncated copy size.')) << 8);
                }

                if (($opcode & 0x40) !== 0) {
                    $copySize |= (ord($delta[$position++] ?? throw new ProtocolException('Corrupt delta: truncated copy size.')) << 16);
                }

                if ($copySize === 0) {
                    $copySize = 0x10000;
                }

                if ($copyOffset + $copySize > strlen($base)) {
                    throw new ProtocolException('Corrupt delta: copy range outside base object.');
                }

                $output .= substr($base, $copyOffset, $copySize);

                continue;
            }

            if ($opcode === 0) {
                throw new ProtocolException('Corrupt delta: reserved opcode 0.');
            }

            if ($position + $opcode > $length) {
                throw new ProtocolException('Corrupt delta: insert length exceeds payload.');
            }

            $output .= substr($delta, $position, $opcode);
            $position += $opcode;
        }

        if (strlen($output) !== $targetSize) {
            throw new ProtocolException(sprintf(
                'Corrupt delta: reconstructed %d bytes but header declares %d.',
                strlen($output),
                $targetSize,
            ));
        }

        return $output;
    }

    /**
     * Little-endian base-128 varint used in delta size headers.
     */
    private static function readDeltaVarint(string $delta, int &$position): int
    {
        $value = 0;
        $shift = 0;

        while (true) {
            if ($position >= strlen($delta)) {
                throw new ProtocolException('Corrupt delta: truncated size header.');
            }

            $byte = ord($delta[$position++]);
            $value |= ($byte & 0x7F) << $shift;
            $shift += 7;

            if (($byte & 0x80) === 0) {
                return $value;
            }
        }
    }
}
