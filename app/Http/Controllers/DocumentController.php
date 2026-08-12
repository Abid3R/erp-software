<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves DMS files (spec: DMS). Files live on the private disk and are never
 * publicly reachable; each download is authorised against the caller's companies
 * so one tenant can never pull another tenant's documents.
 */
class DocumentController extends Controller
{
    public function __construct(private CompanyContext $context) {}

    public function download(Document $document): StreamedResponse
    {
        $user = Auth::user();
        $companyId = (int) $document->company_id;

        abort_unless($user !== null && $user->companies()->whereKey($companyId)->exists(), 403);

        $this->context->set($companyId);

        $disk = Storage::disk(Document::DISK);
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->original_name ?? basename($document->file_path));
    }
}
