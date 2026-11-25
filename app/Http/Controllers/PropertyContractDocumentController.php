<?php

namespace App\Http\Controllers;

use App\Models\PropertyContract;
use App\Support\ContractDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PropertyContractDocumentController extends Controller
{
    public function show(Request $request, PropertyContract $contract): BinaryFileResponse
    {
        $object = $contract->propertyObject;
        abort_unless($object !== null && (int) $object->household_id === (int) $request->user()->currentHousehold()->id, 404);
        abort_unless(ContractDocuments::kept($contract->document_path), 404);
        abort_unless(in_array($contract->document_mime, ContractDocuments::MIMES, true), 404);

        $root = realpath(Storage::disk('local')->path(''));
        $absolute = realpath(Storage::disk('local')->path($contract->document_path));
        $prefix = is_string($root) ? rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR : '';
        abort_unless(is_string($absolute) && $prefix !== '' && str_starts_with($absolute, $prefix), 404);

        $filename = str_replace(['"', '\\', "\r", "\n"], '', (string) ($contract->document_name ?: 'document'));
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'document';
        $inline = ! $request->boolean('download');
        $disposition = ($inline ? 'inline' : 'attachment')
            .'; filename="'.$fallback.'"'
            ."; filename*=UTF-8''".rawurlencode($filename);

        return response()->file($absolute, [
            'Content-Type' => $contract->document_mime,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
