<?php

namespace App\Filament\Resources;

use App\Enums\LetterOfCreditStatus;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\LetterOfCreditResource\Pages;
use App\Filament\Resources\LetterOfCreditResource\RelationManagers\ProformaInvoicesRelationManager;
use App\Models\LetterOfCredit;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Letter of Credit (spec: Export). A financial control document covering one or
 * more Proforma Invoices. No ledger impact — utilisation (allocated / remaining)
 * is derived live from the linked PIs. One LC → many PIs.
 */
class LetterOfCreditResource extends Resource
{
    protected static ?string $model = LetterOfCredit::class;

    protected static ?string $slug = 'letters-of-credit';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Export';

    protected static ?string $navigationLabel = 'Letters of Credit';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->whereIn('status', ['applied', 'received', 'confirmed', 'partially_utilized'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Letter of credit')->columns(2)->schema([
                Forms\Components\TextInput::make('number')->label('LC No.')->maxLength(40)
                    ->placeholder('Auto (LC-0001) if left blank')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
                Forms\Components\DatePicker::make('lc_date')->label('LC date')->default(now())->required(),
                Forms\Components\Select::make('customer_id')->label('Applicant / customer')
                    ->relationship('customer', 'name')->searchable()->preload()->required(),
                Forms\Components\TextInput::make('beneficiary')->maxLength(191)
                    ->helperText('Usually your own company (the exporter).'),
                Forms\Components\TextInput::make('issuing_bank')->maxLength(191),
                Forms\Components\TextInput::make('advising_bank')->maxLength(191),
            ]),
            Forms\Components\Section::make('Value & terms')->columns(3)->schema([
                Forms\Components\TextInput::make('amount')->numeric()->minValue(0)->required()->default(0),
                Forms\Components\Select::make('currency_code')->label('Currency')
                    ->options(fn (): array => self::currencyOptions())
                    ->default(config('erp.export.default_currency'))->required(),
                Forms\Components\TextInput::make('exchange_rate')->label('Exchange rate → base')
                    ->numeric()->minValue(0)->default(1)->required()
                    ->helperText('Rate to '.config('erp.currency.code').' for reporting.'),
                Forms\Components\Select::make('payment_terms')
                    ->options(fn (): array => self::paymentTermOptions())->searchable(),
                Forms\Components\DatePicker::make('issue_date'),
                Forms\Components\DatePicker::make('expiry_date'),
                Forms\Components\DatePicker::make('latest_shipment_date'),
                Forms\Components\TextInput::make('port_of_loading')->maxLength(128),
                Forms\Components\TextInput::make('port_of_discharge')->maxLength(128),
            ]),
            Forms\Components\Section::make('Other')->columns(1)->schema([
                Forms\Components\Select::make('status')->options(LetterOfCreditStatus::options())
                    ->default(LetterOfCreditStatus::Draft->value)->required(),
                Forms\Components\Textarea::make('description')->rows(2)->maxLength(500),
                Forms\Components\Textarea::make('notes')->rows(2)->maxLength(500),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('LC No.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Applicant')->searchable(),
                Tables\Columns\TextColumn::make('issuing_bank')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('currency_code')->label('Ccy'),
                Tables\Columns\TextColumn::make('amount')->numeric(decimalPlaces: 2)->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->numeric(decimalPlaces: 2)),
                Tables\Columns\TextColumn::make('allocated')->label('Allocated')
                    ->state(fn (LetterOfCredit $record): string => number_format((float) (string) $record->allocated(), 2))
                    ->color('info'),
                Tables\Columns\TextColumn::make('remaining')->label('Remaining')
                    ->state(fn (LetterOfCredit $record): string => number_format((float) (string) $record->remaining(), 2))
                    ->color(fn (LetterOfCredit $record): string => $record->remaining()->isNegative() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('expiry_date')->date()->sortable()
                    ->color(fn (LetterOfCredit $record): string => $record->isPastExpiry() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (LetterOfCreditStatus $state): string => $state->label())
                    ->color(fn (LetterOfCreditStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(LetterOfCreditStatus::options()),
                Tables\Filters\SelectFilter::make('customer_id')->relationship('customer', 'name')->searchable()->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('lc_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ProformaInvoicesRelationManager::class,
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLettersOfCredit::route('/'),
            'create' => Pages\CreateLetterOfCredit::route('/create'),
            'view' => Pages\ViewLetterOfCredit::route('/{record}'),
            'edit' => Pages\EditLetterOfCredit::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    public static function currencyOptions(): array
    {
        $codes = (array) config('erp.export.currencies', []);
        $codes[] = config('erp.currency.code');

        return collect($codes)->filter()->unique()->mapWithKeys(fn ($c): array => [$c => $c])->all();
    }

    /** @return array<string, string> */
    public static function paymentTermOptions(): array
    {
        return collect((array) config('erp.export.payment_terms', []))
            ->mapWithKeys(fn ($t): array => [$t => $t])->all();
    }

    /** @return array<string, string> */
    public static function incotermOptions(): array
    {
        return collect((array) config('erp.export.incoterms', []))
            ->mapWithKeys(fn ($t): array => [$t => $t])->all();
    }
}
