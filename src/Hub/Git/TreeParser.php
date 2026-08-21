<?php

declare(strict_types=1);

namespace Docsmith\Hub\Git;

/**
 * Parses raw Git tree object payloads into entries.
 *
 * Tree format: repeated `<mode> <name>\0<20-byte binary sha>` groups.
 * Note the directory mode is spelled "40000" (no leading zero).
 */
final class TreeParser
{
    /**
     * @return list<TreeEntry>
     */
    public static function parse(string $data): array
    {
        $entries = [];
        $position = 0;
        $length = strlen($data);

        while ($position < $length) {
            $space = strpos($data, ' ', $position);

            if ($space === false) {
                throw new ProtocolException('Corrupt tree object: mode separator not found.');
            }

            $modeText = substr($data, $position, $space - $position);
            $mode = (int) octdec($modeText);

            if ($mode === 0) {
                throw new ProtocolException(sprintf('Corrupt tree object: invalid mode "%s".', $modeText));
            }

            $nul = strpos($data, "\0", $space + 1);

            if ($nul === false || $nul + 21 > $length) {
                throw new ProtocolException('Corrupt tree object: entry name or hash truncated.');
            }

            $name = substr($data, $space + 1, $nul - $space - 1);
            $sha = bin2hex(substr($data, $nul + 1, 20));

            $entries[] = new TreeEntry($mode, $name, $sha);
            $position = $nul + 21;
        }

        return $entries;
    }
}
