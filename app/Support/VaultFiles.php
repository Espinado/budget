<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class VaultFiles
{
    public const DISK = 'vault';

    public const MAX_KILOBYTES = 10240;

    /**
     * @var array<string, string>
     */
    public const MIME_BY_EXTENSION = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'heic' => 'image/heic',
        'heif' => 'image/heif',
    ];

    public static function store(int $userId, string $plaintext): string
    {
        $path = $userId.'/'.Str::uuid()->toString();
        Storage::disk(self::DISK)->put($path, Crypt::encryptString($plaintext));

        return $path;
    }

    public static function read(string $path): string
    {
        $stored = Storage::disk(self::DISK)->get($path);

        if (! is_string($stored) || $stored === '') {
            abort(404);
        }

        return Crypt::decryptString($stored);
    }

    public static function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    public static function mimeFor(string $extension, string $contents): ?string
    {
        $extension = strtolower($extension);

        if (! isset(self::MIME_BY_EXTENSION[$extension]) || $contents === '') {
            return null;
        }

        $expected = self::MIME_BY_EXTENSION[$extension];
        $matches = match ($expected) {
            'application/pdf' => str_starts_with($contents, '%PDF'),
            'image/jpeg' => str_starts_with($contents, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($contents, "\x89PNG\x0D\x0A\x1A\x0A"),
            'image/webp' => str_starts_with($contents, 'RIFF') && substr($contents, 8, 4) === 'WEBP',
            'image/heic', 'image/heif' => self::isHeif($contents),
            default => false,
        };

        return $matches ? $expected : null;
    }

    private static function isHeif(string $contents): bool
    {
        if (strlen($contents) < 12 || substr($contents, 4, 4) !== 'ftyp') {
            return false;
        }

        return in_array(substr($contents, 8, 4), ['heic', 'heix', 'heif', 'mif1', 'msf1'], true);
    }
}
