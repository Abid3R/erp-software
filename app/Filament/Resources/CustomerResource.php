<?php

namespace App\Filament\Resources;

use App\Domain\Accounting\PartyLedger;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Support\CsvActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Sales';

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
                Tables\Columns\TextColumn::make('receivable')->label('Receivable')
                    ->money(config('erp.currency.code'))
                    ->state(fn (Customer $record): string => (string) PartyLedger::receivable($record)),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label('Active')])
            ->headerActions([
                CsvActions::export(Customer::class, 'customers.csv', self::csvColumns()),
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
            'Is active' => fn (Customer $r): string => $r->is_active ? 'yes' : 'no',
        ];
    }

    /** @param array<string, string> $row */
    public static function importRow(array $row): string
    {
        $get = fn (string ...$aliases): string => CsvActions::value($row, ...$aliases);
        $code = $get('Code', 'Customer code');
        $name = $get('Name', 'Customer name');
        if ($name === '') {
            return 'skipped';
        }

        $existing = $code !== '' ? Customer::query()->where('code', $code)->first() : Customer::query()->where('name', $name)->first();
        $customer = $existing ?? new Customer;
        $active = $get('Active');
        $customer->fill([
            'code' => $code !== '' ? $code : ($existing->code ?? null),
            'name' => $name,
            'phone' => $get('Phone', 'Mobile') ?: null,
            'email' => $get('Email') ?: null,
            'address' => $get('Address') ?: null,
            'is_active' => $active !== '' ? CsvActions::bool($active) : true,
        ]);
        $customer->save();

        return $existing !== null ? 'updated' : 'created';
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
