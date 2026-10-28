<?php

namespace App\Support;

/** Inline SVG icons from resources/icons (Heroicons outline, MIT). */
class Icon
{
    protected static array $cache = [];

    public static function render(string $name, string $class = 'h-5 w-5'): string
    {
        $svg = self::$cache[$name] ??= (string) @file_get_contents(resource_path("icons/{$name}.svg"));

        return str_replace('<svg ', '<svg class="' . e($class) . '" ', $svg);
    }
}
