<?php

declare(strict_types=1);

function format_date(string $format, int $timestamp): string
{
    return date($format, $timestamp);
}

function str_slug(string $text): string
{
    return strtolower(str_replace(' ', '-', $text));
}
