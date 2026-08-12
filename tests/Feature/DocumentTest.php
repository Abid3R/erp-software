<?php

use App\Enums\DocumentCategory;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Quotation;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: Quotation} */
function documentSetup(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $customer = Customer::create(['company_id' => $company->getKey(), 'name' => 'Buyer', 'code' => 'B1']);
    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $quotation = Quotation::create([
        'number' => 'QT-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'quote_date' => '2026-06-01', 'status' => 'draft',
    ]);

    return [$company, $quotation];
}

function storeDocumentFile(): string
{
    // Real bytes on the (faked) private disk — UploadedFile::fake()->create() only
    // reports a size for validation and writes a 0-byte file, so we write directly.
    $path = 'documents/contract-'.uniqid().'.pdf';
    Storage::disk(Document::DISK)->put($path, str_repeat('PDF-CONTENT ', 300)); // ~3.5 KB

    return $path;
}

it('attaches a document to a business record and captures size/uploader', function () {
    Storage::fake(Document::DISK);
    [$company, $quotation] = documentSetup();
    $uploader = superAdminFor($company);
    $this->actingAs($uploader);

    $path = storeDocumentFile();
    $doc = $quotation->documents()->create([
        'category' => DocumentCategory::Contract->value,
        'title' => 'Signed contract',
        'file_path' => $path,
        'original_name' => 'contract.pdf',
    ]);

    expect($doc->fresh())
        ->documentable_id->toBe($quotation->getKey())
        ->documentable_type->toBe(Quotation::class)
        ->company_id->toBe($company->getKey())
        ->and($doc->fresh()->size)->toBeGreaterThan(0)
        ->and($doc->fresh()->uploaded_by)->toBe($uploader->getKey())
        ->and($quotation->documents()->count())->toBe(1);
});

it('lets an authorised company member download the file', function () {
    Storage::fake(Document::DISK);
    [$company, $quotation] = documentSetup();
    $member = superAdminFor($company);

    $doc = $quotation->documents()->create([
        'category' => DocumentCategory::Contract->value, 'title' => 'C',
        'file_path' => storeDocumentFile(), 'original_name' => 'contract.pdf',
    ]);

    $this->actingAs($member)->get(route('documents.download', $doc))
        ->assertOk()
        ->assertDownload('contract.pdf');
});

it('forbids a member of another company from downloading', function () {
    Storage::fake(Document::DISK);
    [$company, $quotation] = documentSetup();
    $doc = $quotation->documents()->create([
        'category' => DocumentCategory::Contract->value, 'title' => 'C',
        'file_path' => storeDocumentFile(), 'original_name' => 'contract.pdf',
    ]);

    $otherCompany = Company::factory()->create();
    $intruder = superAdminFor($otherCompany);

    $this->actingAs($intruder)->get(route('documents.download', $doc))->assertForbidden();
});

it('removes the backing file when the document is deleted', function () {
    Storage::fake(Document::DISK);
    [$company, $quotation] = documentSetup();
    $path = storeDocumentFile();
    $doc = $quotation->documents()->create([
        'category' => DocumentCategory::Other->value, 'title' => 'temp', 'file_path' => $path,
    ]);
    Storage::disk(Document::DISK)->assertExists($path);

    $doc->delete();

    Storage::disk(Document::DISK)->assertMissing($path);
});
