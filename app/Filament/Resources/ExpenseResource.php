<?php

namespace App\Filament\Resources;

use App\Actions\Accounting\PostExpense;
use App\Enums\AccountType;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseStatus;
use App\Exceptions\ExpenseException;
use App\Exceptions\PostingException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Account;
use App\Models\Expense;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Accounts';

    protected static ?string $navigationLabel = 'Expenses';

    protected static ?int $navigationSort = 8;

    /** @return array<int|string, string> Postable expense accounts for the current company. */
    private static function expenseAccountOptions(): array
    {
        return Account::query()
            ->where('type', AccountType::Expense)
            ->where('is_postable', true)->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $a): array => [$a->getKey() => "{$a->code} · {$a->name}"])
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->required()->maxLength(32)
                ->default(fn (): string => 'EXP-'.str_pad((string) (Expense::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\DatePicker::make('expense_date')->default(now())->required(),
            Forms\Components\Select::make('payment_method')->options(ExpensePaymentMethod::options())
                ->default(ExpensePaymentMethod::Cash->value)->required()->live(),
            Forms\Components\Select::make('supplier_id')->label('Supplier (payee)')
                ->relationship('supplier', 'name')->searchable()->preload()
                ->visible(fn (Forms\Get $get): bool => $get('payment_method') === ExpensePaymentMethod::Credit->value)
                ->required(fn (Forms\Get $get): bool => $get('payment_method') === ExpensePaymentMethod::Credit->value),
            Forms\Components\TextInput::make('reference')->maxLength(64),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            Forms\Components\Repeater::make('lines')->relationship()->schema([
                Forms\Components\Select::make('account_id')->label('Expense account')
                    ->options(fn (): array => self::expenseAccountOptions())->searchable()->required(),
                Forms\Components\TextInput::make('amount')->numeric()->minValue(0.01)->required()
                    ->prefix(config('erp.currency.symbol')),
                Forms\Components\TextInput::make('description')->maxLength(255),
            ])->columns(3)->minItems(1)->addActionLabel('Add line')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('expense_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('payment_method')->badge()
                    ->formatStateUsing(fn (ExpensePaymentMethod $state): string => $state->label()),
                Tables\Columns\TextColumn::make('supplier.name')->label('Payee')->placeholder('—'),
                Tables\Columns\TextColumn::make('lines_sum_amount')->label('Total')
                    ->sum('lines', 'amount')->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ExpenseStatus $state): string => $state->label())
                    ->color(fn (ExpenseStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ExpenseStatus::options()),
                Tables\Filters\SelectFilter::make('payment_method')->options(ExpensePaymentMethod::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Expense $record): bool => $record->status === ExpenseStatus::Draft),
                Tables\Actions\Action::make('post')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (Expense $record): bool => $record->status === ExpenseStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('Posting books Dr Expense / Cr Cash·Bank·Payable. It cannot be undone.')
                    ->action(function (Expense $record): void {
                        try {
                            app(PostExpense::class)->handle($record);
                            Notification::make()->title('Expense posted')->body('Accounting updated.')->success()->send();
                        } catch (ExpenseException|PostingException $e) {
                            Notification::make()->title('Cannot post expense')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (Expense $record): string => route('print.expense', $record))->openUrlInNewTab(),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (Expense $record): bool => $record->status === ExpenseStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (Expense $record) => $record->update(['status' => ExpenseStatus::Cancelled])),
            ])
            ->defaultSort('expense_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view' => Pages\ViewExpense::route('/{record}'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
