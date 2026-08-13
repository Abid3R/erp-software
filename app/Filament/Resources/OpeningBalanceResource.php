<?php

namespace App\Filament\Resources;

use App\Actions\Accounting\PostOpeningBalances;
use App\Enums\OpeningBalanceLineType;
use App\Enums\OpeningBalanceStatus;
use App\Exceptions\OpeningBalanceException;
use App\Exceptions\PostingException;
use App\Filament\Resources\OpeningBalanceResource\Pages;
use App\Models\Account;
use App\Models\OpeningBalance;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OpeningBalanceResource extends Resource
{
    protected static ?string $model = OpeningBalance::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Accounts';

    protected static ?string $navigationLabel = 'Opening Balances';

    protected static ?int $navigationSort = 8;

    /** @return array<int|string, string> */
    private static function accountOptions(): array
    {
        return Account::query()->where('is_postable', true)->where('is_active', true)->orderBy('code')
            ->get()->mapWithKeys(fn (Account $a): array => [$a->getKey() => "{$a->code} · {$a->name}"])->all();
    }

    private static function isType(Forms\Get $get, OpeningBalanceLineType $type): bool
    {
        return $get('type') === $type->value;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->required()->maxLength(32)
                ->default(fn (): string => 'OB-'.str_pad((string) (OpeningBalance::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\DatePicker::make('as_of_date')->label('As of (cutover date)')->default(now())->required(),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            Forms\Components\Repeater::make('lines')->relationship()->schema([
                Forms\Components\Select::make('type')->options(OpeningBalanceLineType::options())
                    ->default(OpeningBalanceLineType::Gl->value)->required()->live(),

                // GL
                Forms\Components\Select::make('account_id')->label('Account')
                    ->options(fn (): array => self::accountOptions())->searchable()
                    ->visible(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Gl))
                    ->required(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Gl)),
                Forms\Components\Select::make('side')->options(['debit' => 'Debit', 'credit' => 'Credit'])
                    ->default('debit')
                    ->visible(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Gl))
                    ->required(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Gl)),

                // AR / AP
                Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()
                    ->visible(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Ar))
                    ->required(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Ar)),
                Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload()
                    ->visible(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Ap))
                    ->required(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Ap)),

                // Stock
                Forms\Components\Select::make('warehouse_id')->relationship('warehouse', 'name')->searchable()->preload()
                    ->visible(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Stock))
                    ->required(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Stock)),
                Forms\Components\Select::make('product_id')->relationship('product', 'name')->searchable()->preload()
                    ->visible(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Stock))
                    ->required(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Stock)),
                Forms\Components\TextInput::make('quantity')->numeric()->minValue(0.0001)
                    ->visible(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Stock))
                    ->required(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Stock)),
                Forms\Components\TextInput::make('unit_cost')->numeric()->minValue(0)
                    ->visible(fn (Forms\Get $get): bool => self::isType($get, OpeningBalanceLineType::Stock)),

                // GL / AR / AP amount
                Forms\Components\TextInput::make('amount')->numeric()->minValue(0.01)
                    ->prefix(config('erp.currency.symbol'))
                    ->visible(fn (Forms\Get $get): bool => ! self::isType($get, OpeningBalanceLineType::Stock))
                    ->required(fn (Forms\Get $get): bool => ! self::isType($get, OpeningBalanceLineType::Stock)),
            ])->columns(3)->minItems(1)->addActionLabel('Add opening balance')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('as_of_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('lines_count')->counts('lines')->label('Lines'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (OpeningBalanceStatus $state): string => $state->label())
                    ->color(fn (OpeningBalanceStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(OpeningBalanceStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (OpeningBalance $record): bool => $record->status === OpeningBalanceStatus::Draft),
                Tables\Actions\Action::make('post')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (OpeningBalance $record): bool => $record->status === OpeningBalanceStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('Posting books all opening balances against Opening Balance Equity and cannot be undone.')
                    ->action(function (OpeningBalance $record): void {
                        try {
                            app(PostOpeningBalances::class)->handle($record);
                            Notification::make()->title('Opening balances posted')->success()->send();
                        } catch (OpeningBalanceException|PostingException $e) {
                            Notification::make()->title('Cannot post')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (OpeningBalance $record): bool => $record->status === OpeningBalanceStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (OpeningBalance $record) => $record->update(['status' => OpeningBalanceStatus::Cancelled])),
            ])
            ->defaultSort('as_of_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpeningBalances::route('/'),
            'create' => Pages\CreateOpeningBalance::route('/create'),
            'view' => Pages\ViewOpeningBalance::route('/{record}'),
            'edit' => Pages\EditOpeningBalance::route('/{record}/edit'),
        ];
    }
}
