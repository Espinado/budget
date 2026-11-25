<?php

/**
 * Wrap plain Cyrillic text and common attributes in Blade views with {{ __('...') }}.
 * Skips already-translated nodes and Blade directives / echoes.
 */

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views'));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
        $path = str_replace('\\', '/', $f->getPathname());
        if (str_contains($path, 'vendor/mail')) {
            continue;
        }
        $files[] = $f->getPathname();
    }
}

function bladeEscape(string $s): string
{
    return str_replace("'", "\\'", $s);
}

function wrapAttr(string $html): string
{
    return preg_replace_callback(
        '/\b(title|aria-label|placeholder|alt)="([^"]*[А-Яа-яЁё][^"]*)"/u',
        function ($m) {
            if (str_contains($m[2], '{{') || str_contains($m[2], '__(')) {
                return $m[0];
            }

            return $m[1].'="{{ __(\''.bladeEscape($m[2]).'\') }}"';
        },
        $html
    );
}

function wrapTextNodes(string $html): string
{
    return preg_replace_callback(
        '/>([^<]*[А-Яа-яЁё][^<]*)</u',
        function ($m) {
            $inner = $m[1];
            if (str_contains($inner, '{{') || str_contains($inner, '{!!') || str_contains($inner, '@')) {
                return $m[0];
            }
            // Preserve leading/trailing whitespace around the phrase.
            if (! preg_match('/^(\s*)(.+?)(\s*)$/us', $inner, $p)) {
                return $m[0];
            }
            $text = $p[2];
            if ($text === '' || str_contains($text, '__(')) {
                return $m[0];
            }

            return '>'.$p[1]."{{ __('".bladeEscape($text)."') }}".$p[3].'<';
        },
        $html
    );
}

function wrapPhpStringsInBlade(string $html): string
{
    // @php blocks and inline {{ '...' }} rare; wrap single-quoted Cyrillic in @php assignments.
    return preg_replace_callback(
        "/(\\\$[A-Za-z_][A-Za-z0-9_]*\s*=\s*)'([^']*[А-Яа-яЁё][^']*)'/u",
        function ($m) {
            if (str_contains($m[0], '__(')) {
                return $m[0];
            }

            return $m[1]."__('".bladeEscape($m[2])."')";
        },
        $html
    );
}

$changed = 0;
foreach ($files as $path) {
    $src = file_get_contents($path);
    $out = wrapAttr($src);
    $out = wrapTextNodes($out);
    $out = wrapPhpStringsInBlade($out);
    if ($out !== $src) {
        file_put_contents($path, $out);
        $changed++;
        echo "wrapped: {$path}\n";
    }
}

echo "Blade files changed: {$changed}\n";
