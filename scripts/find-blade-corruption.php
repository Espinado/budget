<?php

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views')) as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
        continue;
    }
    $c = file_get_contents($f->getPathname());
    $bad = false;
    if (preg_match("/=>\\s*\\{\\{\\s*__\\(/", $c)) {
        $bad = '=> {{ __(';
    } elseif (preg_match("/__\\('(?:\\\\)?'?[a-zA-Z]/", $c) && preg_match("/__\\('\\\\?'[a-z]/", $c)) {
        $bad = 'wrapped english literal';
    } elseif (preg_match('/__\\(\'[^\']{0,40}(wire:|function )/s', $c)) {
        $bad = 'wrapped js/wire';
    } elseif (substr_count($c, "{{ __('") !== substr_count($c, "') }}") && substr_count($c, '__(\'') > 20) {
        // loose check — skip
    }
    // Unbalanced blade from bad wraps: look for ') }}</ without matching open cleanly via id pattern
    if (str_contains($c, "__('id")) {
        $bad = "still __('id";
    }
    if ($bad) {
        echo $f->getPathname().' :: '.$bad.PHP_EOL;
    }
}
echo "done\n";
