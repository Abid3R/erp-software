<?php

namespace App\Filament\Resources;

use App\Actions\Purchasing\CreateRfqFromRequisition;
use App\Enums\RequisitionStatus;
use App\Exceptions\SourcingException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\PurchaseRequisitionResource\Pages;
use App\Models\PurchaseRequisition;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseRequisitionResource extends Resource
{
    protected static ?string $model = PurchaseRequisition::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Requisitions';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->required()->maxLength(32)
                ->default(fn (): string => 'REQ-'.str_pad((string) (PurchaseRequisition::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\Select::make('department_id')->relationship('department', 'name')->searchable()->preload(),
            Forms\Components\DatePicker::make('needed_by'),
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
                Tables\Columns\TextColumn::make('department.name')->placeholder('—'),
                Tables\Columns\TextColumn::make('needed_by')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('lines_count')->counts('lines')->label('Lines'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (RequisitionStatus $state): string => $state->label())
                    ->color(fn (RequisitionStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(RequisitionStatus::options()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (PurchaseRequisition $record): bool => $record->status === RequisitionStatus::Draft),
                Tables\Actions\Action::make('submit')->icon('heroicon-o-paper-airplane')->color('info')
                    ->visible(fn (PurchaseRequisition $record): bool => $record->status === RequisitionStatus::Draft)
                    ->action(fn (PurchaseRequisition $record) => $record->update(['status' => RequisitionStatus::Submitted])),
                Tables\Actions\Action::make('approve')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (PurchaseRequisition $record): bool => $record->status === RequisitionStatus::Submitted)
                    ->requiresConfirmation()
                    ->action(fn (PurchaseRequisition $record) => $record->update(['status' => RequisitionStatus::Approved])),
                Tables\Actions\Action::make('reject')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (PurchaseRequisition $record): bool => $record->status === RequisitionStatus::Submitted)
                    ->requiresConfirmation()
                    ->action(fn (PurchaseRequisition $record) => $record->update(['status' => RequisitionStatus::Rejected])),
                Tables\Actions\Action::make('createRfq')->label('Create RFQ')->icon('heroicon-o-document-plus')->color('warning')
                    ->visible(fn (PurchaseRequisition $record): bool => $record->status === RequisitionStatus::Approved)
                    ->form([
                        Forms\Components\Select::make('warehouse_id')->label('Deliver to warehouse')
                            ->options(fn (): array => Warehouse::query()->pluck('name', 'id')->all())->required(),
                    ])
                    ->action(function (PurchaseRequisition $record, array $data): void {
                        try {
                            $rfq = app(CreateRfqFromRequisition::class)->handle($record, (int) $data['warehouse_id']);
                            Notification::make()->title('RFQ '.$rfq->number.' created')->success()->send();
                        } catch (SourcingException $e) {
                            Notification::make()->title('Cannot create RFQ')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseRequisitions::route('/'),
            'create' => Pages\CreatePurchaseRequisition::route('/create'),
            'edit' => Pages\EditPurchaseRequisition::route('/{record}/edit'),
        ];
    }
}
