<?php

declare(strict_types=1);

namespace Docsmith\Assets;

use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use RuntimeException;
use Throwable;

final class AssetMinifier
{
    public function minifyCss(string $css): string
    {
        try {
            $minifier = new CSS($css);

            return $minifier->minify();
        } catch (Throwable $throwable) {
            throw new RuntimeException('CSS minification failed: ' . $throwable->getMessage(), 0, $throwable);
        }
    }

    public function minifyJs(string $js): string
    {
        try {
            $minifier = new JS($js);

            return $minifier->minify();
        } catch (Throwable $throwable) {
            throw new RuntimeException('JS minification failed: ' . $throwable->getMessage(), 0, $throwable);
        }
    }
}
