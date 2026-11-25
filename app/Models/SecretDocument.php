<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecretDocument extends Model
{
    protected $fillable = [
        'user_id',
        'secret_document_section_id',
        'name',
        'original_name',
        'mime',
        'size',
        'path',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SecretDocumentSection::class, 'secret_document_section_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function fileTitle(): string
    {
        $stored = trim($this->name);

        if ($stored !== '' && ! self::looksLikeStorageHash($stored)) {
            return $stored;
        }

        $ids = self::query()
            ->where('user_id', $this->user_id)
            ->where('secret_document_section_id', $this->secret_document_section_id)
            ->when(
                $this->isImage(),
                fn ($query) => $query->where('mime', 'like', 'image/%'),
                fn ($query) => $query->where('mime', 'not like', 'image/%'),
            )
            ->orderBy('id')
            ->pluck('id');

        $place = $ids->search($this->id);
        $number = $place === false ? 1 : $place + 1;

        return $this->isImage()
            ? __('Фото :n', ['n' => $number])
            : __('Документ :n', ['n' => $number]);
    }

    public function downloadFilename(): string
    {
        $title = $this->fileTitle();
        $extension = strtolower(pathinfo((string) $this->original_name, PATHINFO_EXTENSION));

        if ($extension === '') {
            $extension = match ($this->mime) {
                'application/pdf' => 'pdf',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/heic' => 'heic',
                'image/heif' => 'heif',
                default => 'jpg',
            };
        }

        if (str_ends_with(strtolower($title), '.'.$extension)) {
            return $title;
        }

        return $title.'.'.$extension;
    }

    public static function looksLikeStorageHash(string $name): bool
    {
        $base = pathinfo($name, PATHINFO_FILENAME);

        return (bool) preg_match('/^[a-f0-9]{16,}(?:_\d+)?$/i', $base);
    }
}
