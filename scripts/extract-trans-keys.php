<?php

$keys = [];
$roots = ['app', 'resources/views', 'routes'];

foreach ($roots as $root) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $f) {
        if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.php')) {
            continue;
        }
        $c = file_get_contents($f->getPathname());
        preg_match_all("/__\(\s*'((?:\\\\'|[^'])*)'\s*[\),]/u", $c, $m);
        foreach ($m[1] as $raw) {
            $key = stripcslashes($raw);
            if (preg_match('/[А-Яа-яЁё]/u', $key)) {
                $keys[$key] = true;
            }
        }
        preg_match_all('/__\(\s*"((?:\\\\"|[^"])*)"\s*[\),]/u', $c, $m2);
        foreach ($m2[1] as $raw) {
            $key = stripcslashes($raw);
            if (preg_match('/[А-Яа-яЁё]/u', $key)) {
                $keys[$key] = true;
            }
        }
    }
}

ksort($keys, SORT_STRING);
file_put_contents('storage/i18n-keys.json', json_encode(array_keys($keys), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo count($keys)." keys\n";
