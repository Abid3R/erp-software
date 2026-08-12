<?php

namespace App\Filament\Resources;

use App\Actions\Purchasing\AwardRfq;
use App\Domain\Purchasing\ComparativeStatement;
use App\Enums\RfqStatus;
use App\Exceptions\SourcingException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\RfqResource\Pages;
use App\Filament\Resources\RfqResource\RelationManagers\QuotesRelationManager;
use App\Models\Rfq;
use App\Models\RfqQuote;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RfqResource extends Resource
{
    protected static ?string $model = Rfq::class;

    protected static ?string $slug = 'rfqs';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'RFQs';

    protected static ?string $modelLabel = 'RFQ';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->required()->maxLength(32)
                ->default(fn (): string => 'RFQ-'.str_pad((string) (Rfq::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\Select::make('warehouse_id')->relationship('warehouse', 'name')->searchable()->preload()->required(),
            Forms\Components\DatePicker::make('due_date'),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            Forms\Components\Repeater::make('lines')->relationship()->schema([
                Forms\Components\Select::make('product_id')->label('Product')->relationship('product', 'name')->searchable()->preload()->required(),
                Forms\Components\TextInput::make('quantity')->numeric()->minValue(0.0001)->required(),
            ])->columns(2)->minItems(1)->addActionLabel('Add line')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name'),
                Tables\Columns\TextColumn::make('lines_count')->counts('lines')->label('Lines'),
                Tables\Columns\TextColumn::make('quotes_count')->counts('quotes')->label('Quotes'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (RfqStatus $state): string => $state->label())
                    ->color(fn (RfqStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(RfqStatus::options()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Rfq $record): bool => $record->status->isAwardable() || $record->status === RfqStatus::Draft),
                Tables\Actions\Action::make('award')->label('Compare & award')->icon('heroicon-o-trophy')->color('success')
                    ->visible(fn (Rfq $record): bool => $record->status->isAwardable())
                    ->modalHeading('Comparative statement')
                    ->modalContent(fn (Rfq $record) => view('filament.rfq.comparative', ['data' => ComparativeStatement::for($record)]))
                    ->form([
                        Forms\Components\Select::make('supplier_id')->label('Award to supplier')
                            ->options(fn (Rfq $record): array => Supplier::query()
                                ->whereIn('id', RfqQuote::query()->where('rfq_id', $record->getKey())->pluck('supplier_id'))
                                ->pluck('name', 'id')->all())
                            ->required(),
                    ])
                    ->action(function (Rfq $record, array $data): void {
                        try {
                            $po = app(AwardRfq::class)->handle($record, (int) $data['supplier_id']);
                            Notification::make()->title('Awarded — '.$po->po_number.' created')->success()->send();
                        } catch (SourcingException $e) {
                            Notification::make()->title('Cannot award')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [QuotesRelationManager::class, DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRfqs::route('/'),
            'create' => Pages\CreateRfq::route('/create'),
            'edit' => Pages\EditRfq::route('/{record}/edit'),
        ];
    }
}
