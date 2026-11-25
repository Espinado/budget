<?php

namespace App\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AppRelease
{
    public static function current(): string
    {
        $latest = 0;
        $bytes = 0;
        $count = 0;

        foreach ([app_path(), resource_path('views'), base_path('routes')] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $count++;
                $bytes += $file->getSize();
                $latest = max($latest, $file->getMTime());
            }
        }

        foreach ([public_path('sw.js'), public_path('pwa.js'), public_path('build/manifest.json')] as $path) {
            if (! is_file($path)) {
                continue;
            }

            $count++;
            $bytes += filesize($path);
            $latest = max($latest, (int) filemtime($path));
        }

        return $latest.'-'.$count.'-'.$bytes;
    }
}

