<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * A stored file (spec: DMS). Standalone in the library or attached to any record
 * via the polymorphic `documentable`. Physical file lives on the private disk;
 * size/mime are captured on save and the file is removed when the row is deleted.
 *
 * @property DocumentCategory $category
 * @property int|null $size
 * @property string $file_path
 * @property string|null $original_name
 * @property string|null $documentable_type
 * @property int|null $documentable_id
 * @property int|null $uploaded_by
 */
class Document extends Model
{
    use BelongsToCompany;

    /** Private disk that backs every document. */
    public const DISK = 'local';

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'documentable_type', 'documentable_id', 'category',
        'title', 'file_path', 'original_name', 'mime_type', 'size', 'notes', 'uploaded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['category' => DocumentCategory::class, 'size' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Document $doc): void {
            if ($doc->uploaded_by === null && Auth::id() !== null) {
                $doc->uploaded_by = Auth::id();
            }

            // Capture size/mime from the freshly stored file.
            if ($doc->isDirty('file_path')) {
                $disk = Storage::disk(self::DISK);
                if ($disk->exists($doc->file_path)) {
                    $doc->size = $disk->size($doc->file_path);
                    $doc->mime_type = $disk->mimeType($doc->file_path) ?: null;
                }
            }
        });

        // Remove the backing file when the record is deleted.
        static::deleting(function (Document $doc): void {
            Storage::disk(self::DISK)->delete($doc->file_path);
        });
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Human label for what this document is attached to, or "Library" if standalone. */
    public function attachedTo(): string
    {
        if ($this->documentable_type === null) {
            return 'Library';
        }

        return class_basename($this->documentable_type).' #'.$this->documentable_id;
    }

    /** File size formatted for display (KB/MB). */
    public function humanSize(): string
    {
        $bytes = $this->size ?? 0;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024).' KB';
        }

        return $bytes.' B';
    }
}
