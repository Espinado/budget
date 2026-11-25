<?php

$replacements = [
    [
        '{{ $account->{{ __(\'id }})" class="text-base font-medium text-sky-800">Изменить\') }}',
        '{{ $account->id }})" class="text-base font-medium text-sky-800">{{ __(\'Изменить\') }}',
    ],
    [
        '{{ $group->{{ __(\'id }})" class="text-base font-medium text-sky-800">Изменить\') }}',
        '{{ $group->id }})" class="text-base font-medium text-sky-800">{{ __(\'Изменить\') }}',
    ],
    [
        '{{ $group->{{ __(\'id }})" data-focus-open class="text-base font-medium text-rose-700">Удалить\') }}',
        '{{ $group->id }})" data-focus-open class="text-base font-medium text-rose-700">{{ __(\'Удалить\') }}',
    ],
    [
        '{{ $entry->{{ __(\'id }})" class="text-base font-medium text-sky-800">Изменить\') }}',
        '{{ $entry->id }})" class="text-base font-medium text-sky-800">{{ __(\'Изменить\') }}',
    ],
    [
        '{{ $entry->{{ __(\'id }})" data-focus-open class="text-base font-medium text-rose-700">Удалить\') }}',
        '{{ $entry->id }})" data-focus-open class="text-base font-medium text-rose-700">{{ __(\'Удалить\') }}',
    ],
    [
        '{{ $line->{{ __(\'id }})" class="rounded-full bg-ink px-2.5 py-1 text-xs font-medium text-white">Факт\') }}',
        '{{ $line->id }})" class="rounded-full bg-ink px-2.5 py-1 text-xs font-medium text-white">{{ __(\'Факт\') }}',
    ],
    [
        '{{ $transaction->{{ __(\'id }})" class="text-base font-medium text-sky-800">Изменить\') }}',
        '{{ $transaction->id }})" class="text-base font-medium text-sky-800">{{ __(\'Изменить\') }}',
    ],
    [
        '{{ $category->{{ __(\'id }})" class="text-base font-medium text-sky-800">Изменить\') }}',
        '{{ $category->id }})" class="text-base font-medium text-sky-800">{{ __(\'Изменить\') }}',
    ],
    [
        '{{ $transaction->{{ __(\'id }})" wire:confirm="Удалить эту операцию?" class="text-stone-400 hover:text-rose-700">Удалить\') }}',
        '{{ $transaction->id }})" wire:confirm="{{ __(\'Удалить эту операцию?\') }}" class="text-stone-400 hover:text-rose-700">{{ __(\'Удалить\') }}',
    ],
];

$n = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views')) as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
        continue;
    }
    $c = file_get_contents($f->getPathname());
    $o = $c;
    foreach ($replacements as [$from, $to]) {
        $o = str_replace($from, $to, $o);
    }
    if ($o !== $c) {
        file_put_contents($f->getPathname(), $o);
        echo 'fixed '.$f->getPathname()."\n";
        $n++;
    }
}
echo "files {$n}\n";

// dump exact broken snippet bytes for debugging if still present
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views')) as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
        continue;
    }
    $c = file_get_contents($f->getPathname());
    if (str_contains($c, "__('id")) {
        if (preg_match("/.{{0,40}}__\\('id.{{0,80}}/u", $c, $m)) {
            echo 'STILL: '.$f->getPathname()."\n  ".$m[0]."\n";
            echo 'HEX: '.bin2hex(mb_substr($m[0], 0, 60))."\n";
        }
    }
}
