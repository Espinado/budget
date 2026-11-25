<?php

$roots = ['app', 'resources/views', 'routes'];
$strings = [];

foreach ($roots as $root) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $f) {
        if (! $f->isFile()) {
            continue;
        }
        $name = $f->getFilename();
        if (! str_ends_with($name, '.php')) {
            continue;
        }
        $c = file_get_contents($f->getPathname());
        preg_match_all("/'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'/u", $c, $m1);
        preg_match_all('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/u', $c, $m2);
        foreach (array_merge($m1[1], $m2[1]) as $s) {
            $s = stripcslashes($s);
            if (preg_match('/[А-Яа-яЁё]/u', $s) && mb_strlen($s) >= 2) {
                $strings[$s] = true;
            }
        }
        if (str_ends_with($name, '.blade.php')) {
            preg_match_all('/>([^<]*[А-Яа-яЁё][^<]*)</u', $c, $m3);
            foreach ($m3[1] as $s) {
                $s = trim(html_entity_decode($s));
                if ($s !== '' && ! str_contains($s, '{{') && ! str_contains($s, '@')) {
                    $strings[$s] = true;
                }
            }
            preg_match_all('/(?:title|aria-label|placeholder|alt)="([^"]*[А-Яа-яЁё][^"]*)"/u', $c, $m4);
            foreach ($m4[1] as $s) {
                $strings[$s] = true;
            }
        }
    }
}

ksort($strings, SORT_STRING);
$path = dirname(__DIR__).'/storage/i18n-ru-strings.txt';
file_put_contents($path, implode("\n", array_keys($strings)));
echo count($strings)." unique strings -> {$path}\n";
