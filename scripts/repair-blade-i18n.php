<?php

/**
 * Repair damage from wrap-blade-i18n matching Blade "=>" as an HTML tag closer.
 *
 * Broken forms:
 *   {{ $x->{{ __('id }})" attrs>Label') }}
 *   ['period' => {{ __('$period->id]) }}" attrs>Label') }}
 *   wire:confirm / function bodies accidentally wrapped
 */

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views'));
$changed = 0;

foreach ($files as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
        continue;
    }

    $path = $f->getPathname();
    $src = file_get_contents($path);
    $out = $src;

    // {{ $var->{{ __('id }})" ... >Label') }}  OR with trailing stuff before quote
    $out = preg_replace_callback(
        '/\{\{\s*(\$[A-Za-z_][A-Za-z0-9_]*)\-\>\{\{\s*__\(\'id\s*\}\)"([^>]*)>([^\'{]+?)\'\)\s*\}\}/u',
        function ($m) {
            $label = trim($m[3]);

            return '{{ '.$m[1].'->id }})"'.$m[2].'>{{ __(\''.addcslashes($label, "'").'\') }}';
        },
        $out
    );

    // settings period link
    $out = preg_replace(
        '/\[\'period\'\s*=>\s*\{\{\s*__\(\'\$period->id\]\)\s*\}\}"([^>]*)>\s*← К периоду\'\)\s*\}\}/u',
        '[\'period\' => $period->id]) }}"$1>{{ __(\'← К периоду\') }}',
        $out
    );

    // groups delete with wire:confirm inside broken wrap
    $out = preg_replace(
        '/wire:click="deleteTransaction\(\{\{\s*\$transaction\-\>\{\{\s*__\(\'id\s*\}\)"\s*wire:confirm="Удалить эту операцию\?"\s*class="([^"]*)">Удалить\'\)\s*\}\}/u',
        'wire:click="deleteTransaction({{ $transaction->id }})" wire:confirm="{{ __(\'Удалить эту операцию?\') }}" class="$1">{{ __(\'Удалить\') }}',
        $out
    );

    if ($out !== $src) {
        file_put_contents($path, $out);
        $changed++;
        echo "repaired: {$path}\n";
    }
}

echo "Repaired files: {$changed}\n";
