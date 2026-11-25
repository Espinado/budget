<?php

namespace App\Http\Controllers;

use App\Models\SecretDocument;
use App\Support\PersonalVault;
use App\Support\VaultFiles;
use Illuminate\Http\Response;

class VaultDocumentController extends Controller
{
    public function show(int $document): Response
    {
        return $this->respond($document, inline: true);
    }

    public function download(int $document): Response
    {
        return $this->respond($document, inline: false);
    }

    private function respond(int $document, bool $inline): Response
    {
        abort_unless(PersonalVault::isUnlocked(), 403);

        $record = SecretDocument::query()
            ->where('user_id', auth()->id())
            ->whereKey($document)
            ->firstOrFail();

        abort_unless(in_array($record->mime, VaultFiles::MIME_BY_EXTENSION, true), 404);

        $bytes = VaultFiles::read($record->path);
        $filename = str_replace(['"', '\\', "\r", "\n"], '', $record->downloadFilename());
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'document';
        $disposition = ($inline ? 'inline' : 'attachment')
            .'; filename="'.$fallback.'"'
            ."; filename*=UTF-8''".rawurlencode($filename);

        return response($bytes, 200, [
            'Content-Type' => $record->mime,
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) strlen($bytes),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
