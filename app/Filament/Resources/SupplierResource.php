<?php

namespace App\Filament\Resources;

use App\Domain\Accounting\PartyLedger;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use App\Support\CsvActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('code')->maxLength(255),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->maxLength(255),
            Forms\Components\Textarea::make('address')->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('phone')->toggleable(),
                Tables\Columns\TextColumn::make('payable')->label('Payable')
                    ->money(config('erp.currency.code'))
                    ->state(fn (Supplier $record): string => (string) PartyLedger::payable($record)),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label('Active')])
            ->headerActions([
                CsvActions::export(Supplier::class, 'suppliers.csv', self::csvColumns()),
                CsvActions::import([self::class, 'importRow']),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('name');
    }

    /** @return array<string, string|callable> */
    public static function csvColumns(): array
    {
        return [
            'Code' => 'code',
            'Name' => 'name',
            'Phone' => 'phone',
            'Email' => 'email',
            'Address' => 'address',
            'Active' => fn (Supplier $r): string => $r->is_active ? 'yes' : 'no',
        ];
    }

    /** @param array<string, string> $row */
    public static function importRow(array $row): string
    {
        $get = fn (string ...$aliases): string => CsvActions::value($row, ...$aliases);
        $code = $get('Code', 'Supplier code');
        $name = $get('Name', 'Supplier name');
        if ($name === '') {
            return 'skipped';
        }

        $existing = $code !== '' ? Supplier::query()->where('code', $code)->first() : Supplier::query()->where('name', $name)->first();
        $supplier = $existing ?? new Supplier;
        $active = $get('Active');
        $supplier->fill([
            'code' => $code !== '' ? $code : ($existing->code ?? null),
            'name' => $name,
            'phone' => $get('Phone', 'Mobile') ?: null,
            'email' => $get('Email') ?: null,
            'address' => $get('Address') ?: null,
            'is_active' => $active !== '' ? CsvActions::bool($active) : true,
        ]);
        $supplier->save();

        return $existing !== null ? 'updated' : 'created';
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
