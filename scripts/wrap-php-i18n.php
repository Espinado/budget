<?php

/**
 * Wrap Cyrillic string literals in __() using the PHP tokenizer.
 * Skips files under vendor, storage, scripts; skips already-wrapped calls.
 */

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app'));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $files[] = $f->getPathname();
    }
}
$files[] = 'routes/web.php';

$changed = 0;
foreach ($files as $path) {
    $src = file_get_contents($path);
    $tokens = token_get_all($src);
    $out = '';
    $count = count($tokens);
    $fileChanged = false;

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (! is_array($token)) {
            $out .= $token;
            continue;
        }

        [$id, $text] = $token;

        if ($id === T_CONSTANT_ENCAPSED_STRING && preg_match('/[А-Яа-яЁё]/u', $text)) {
            $prev = null;
            for ($j = $i - 1; $j >= 0; $j--) {
                $t = $tokens[$j];
                if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $prev = $t;
                break;
            }

            $already = false;
            if (is_array($prev) && $prev[0] === T_STRING && in_array($prev[1], ['__', 'trans', '@lang'], true)) {
                $already = true;
            } elseif ($prev === '(') {
                for ($j = $i - 1; $j >= 0; $j--) {
                    $t = $tokens[$j];
                    if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    if ($t === '(') {
                        continue;
                    }
                    if (is_array($t) && $t[0] === T_STRING && in_array($t[1], ['__', 'trans'], true)) {
                        $already = true;
                    }
                    break;
                }
            }

            // Skip attribute array keys that look technical? Keep wrapping user-facing values.
            if (! $already) {
                $out .= '__('.$text.')';
                $fileChanged = true;
                continue;
            }
        }

        $out .= $text;
    }

    if ($fileChanged && $out !== $src) {
        file_put_contents($path, $out);
        $changed++;
        echo "wrapped: {$path}\n";
    }
}

echo "Files changed: {$changed}\n";
