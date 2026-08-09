<?php

namespace App\Filament\Resources;

use App\Enums\AccountType;
use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?string $navigationLabel = 'Chart of Accounts';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()->maxLength(32)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where(
                    'company_id',
                    app(CompanyContext::class)->currentId(),
                )),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Select::make('type')
                ->options(collect(AccountType::cases())->mapWithKeys(fn (AccountType $t) => [$t->value => $t->label()]))
                ->required(),
            Forms\Components\Select::make('parent_id')
                ->relationship('parent', 'name')->searchable()->preload(),
            Forms\Components\Toggle::make('is_postable')->default(true)
                ->helperText('Only postable (leaf) accounts can receive journal lines.'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (AccountType $state): string => $state->label()),
                Tables\Columns\IconColumn::make('is_postable')->boolean()->label('Postable'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(collect(AccountType::cases())->mapWithKeys(fn (AccountType $t) => [$t->value => $t->label()])),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
