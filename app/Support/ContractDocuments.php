<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class ContractDocuments
{
    public const DIRECTORY = 'contract-documents';

    /**
     * @var list<string>
     */
    public const MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * @return array{document_path: string, document_mime: string, document_name: string}
     */
    public static function put(TemporaryUploadedFile $file, string $errorKey): array
    {
        $checked = self::assertValid($file, $errorKey);
        $path = self::DIRECTORY.'/'.Str::uuid()->toString().'.'.$checked['extension'];
        Storage::disk('local')->put($path, $checked['contents']);

        return [
            'document_path' => $path,
            'document_mime' => $checked['mime'],
            'document_name' => self::originalName($file),
        ];
    }

    /**
     * @return array{extension: string, mime: string, contents: string}
     */
    public static function assertValid(TemporaryUploadedFile $file, string $errorKey): array
    {
        $size = $file->getSize();

        if ($size === false || $size < 1 || $size > VaultFiles::MAX_KILOBYTES * 1024) {
            throw ValidationException::withMessages([
                $errorKey => $size !== false && $size > VaultFiles::MAX_KILOBYTES * 1024
                    ? __('Файл больше 10 МБ.')
                    : __('Нужен файл PDF, JPG, PNG или WEBP.'),
            ]);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $contents = $file->get();
        $mime = is_string($contents) ? VaultFiles::mimeFor($extension, $contents) : null;

        if (! is_string($contents) || ! in_array($mime, self::MIMES, true)) {
            throw ValidationException::withMessages([$errorKey => __('Нужен файл PDF, JPG, PNG или WEBP.')]);
        }

        return [
            'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
            'mime' => $mime,
            'contents' => $contents,
        ];
    }

    public static function delete(?string $path): void
    {
        if (! self::kept($path)) {
            return;
        }

        Storage::disk('local')->delete($path);
    }

    public static function kept(?string $path): bool
    {
        return is_string($path)
            && $path !== ''
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && str_starts_with($path, self::DIRECTORY.'/')
            && substr_count($path, '/') === 1;
    }

    private static function originalName(TemporaryUploadedFile $file): string
    {
        $name = str_replace(["\0", '/', '\\', '"', "\r", "\n"], '', (string) $file->getClientOriginalName());
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            return 'document';
        }

        return mb_substr($name, 0, 120);
    }
}
