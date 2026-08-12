<?php

namespace App\Models\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Attaches DMS documents to any business record (spec: DMS). Host models expose a
 * `documents` morphMany so the shared DocumentsRelationManager can upload, list,
 * download and remove attachments uniformly.
 */
trait HasDocuments
{
    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
