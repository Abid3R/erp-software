<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Auditable, BelongsToCompany, HasDocuments;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'name', 'code', 'phone', 'email', 'address', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<ProformaInvoice, $this> */
    public function proformaInvoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class);
    }

    /** @return HasMany<CommercialInvoice, $this> */
    public function commercialInvoices(): HasMany
    {
        return $this->hasMany(CommercialInvoice::class);
    }

    /** @return HasMany<LetterOfCredit, $this> */
    public function lettersOfCredit(): HasMany
    {
        return $this->hasMany(LetterOfCredit::class);
    }

    /** @return HasMany<ExportShipment, $this> */
    public function exportShipments(): HasMany
    {
        return $this->hasMany(ExportShipment::class);
    }
}
