<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property string|null $logo_path
 * @property bool $show_logo
 */
class ReportSetting extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'show_logo', 'logo_path', 'header_note', 'footer_note',
        'signatory_left', 'signatory_right', 'terms',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['show_logo' => 'boolean'];
    }

    public function signatoryLeft(): string
    {
        return $this->signatory_left ?: 'Prepared by';
    }

    public function signatoryRight(): string
    {
        return $this->signatory_right ?: 'Authorised signature';
    }

    /** Public URL for the logo (browser print). Null when unset or absent. */
    public function logoUrl(): ?string
    {
        if (! $this->show_logo || $this->logo_path === null || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /** Base64 data URI for the logo (dompdf, which can't fetch URLs). Null when unset. */
    public function logoDataUri(): ?string
    {
        if (! $this->show_logo || $this->logo_path === null || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($this->logo_path);
        $mime = Storage::disk('public')->mimeType($this->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $contents);
    }
}
