<?php

namespace App\Filament\Resources;

use App\Enums\LabDipStatus;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\LabDipResource\Pages;
use App\Models\LabDip;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Lab dips — colour-development requests. Pure workflow, no inventory/accounting
 * effect. Status advances only through the manual approval actions; an approved
 * lab dip becomes selectable on a dyeing process order.
 */
class LabDipResource extends Resource
{
    protected static ?string $model = LabDip::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?string $navigationLabel = 'Lab Dips';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Colour development')->columns(2)->schema([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')->searchable()->preload(),
                Forms\Components\DatePicker::make('request_date')->default(now()),
                Forms\Components\TextInput::make('colour')->required()->maxLength(255)
                    ->helperText('Colour name, e.g. Navy Blue.'),
                Forms\Components\TextInput::make('colour_ref')->label('Colour reference')->maxLength(255)
                    ->helperText('Pantone / customer reference.'),
                Forms\Components\TextInput::make('sample_ref')->label('Sample reference')->maxLength(255),
                Forms\Components\Textarea::make('recipe')->label('Recipe / specification')->rows(3)->columnSpanFull(),
                Forms\Components\Textarea::make('remarks')->rows(2)->columnSpanFull(),
                // Status advances only via the workflow actions.
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('colour')->searchable(),
                Tables\Columns\TextColumn::make('colour_ref')->label('Ref')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (LabDipStatus $state): string => $state->label())
                    ->color(fn (LabDipStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('request_date')->date()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(LabDipStatus::options()),
            ])
            ->actions([
                self::transition('submit', 'Submit', LabDipStatus::Draft, LabDipStatus::Submitted, 'heroicon-o-paper-airplane', 'warning'),
                self::transition('toLab', 'Send to lab', LabDipStatus::Submitted, LabDipStatus::InLab, 'heroicon-o-beaker', 'warning'),
                self::transition('internalApprove', 'Internal approve', LabDipStatus::InLab, LabDipStatus::InternalApproved, 'heroicon-o-check', 'info'),
                self::transition('sendToCustomer', 'Send to customer', LabDipStatus::InternalApproved, LabDipStatus::SentToCustomer, 'heroicon-o-envelope', 'warning'),
                self::transition('customerApprove', 'Customer approved', LabDipStatus::SentToCustomer, LabDipStatus::CustomerApproved, 'heroicon-o-check-badge', 'success'),
                self::rejectAction(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (LabDip $record): bool => ! $record->status->isTerminal()),
            ])
            ->defaultSort('id', 'desc');
    }

    /** A guarded, manual status transition that carries no side effects. */
    private static function transition(string $name, string $label, LabDipStatus $from, LabDipStatus $to, string $icon, string $color): Tables\Actions\Action
    {
        return Tables\Actions\Action::make($name)->label($label)->icon($icon)->color($color)
            ->visible(fn (LabDip $record): bool => $record->status === $from)
            ->requiresConfirmation()
            ->action(function (LabDip $record) use ($to, $label): void {
                $record->update(['status' => $to]);
                Notification::make()->title($label)->success()->send();
            });
    }

    private static function rejectAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('reject')->label('Reject')->icon('heroicon-o-x-mark')->color('danger')
            ->visible(fn (LabDip $record): bool => ! $record->status->isTerminal() && $record->status !== LabDipStatus::Draft)
            ->form([
                Forms\Components\Textarea::make('reason')->label('Reason for rejection')->required()->maxLength(255),
            ])
            ->action(function (LabDip $record, array $data): void {
                $record->update([
                    'status' => LabDipStatus::Rejected,
                    'remarks' => trim(($record->remarks ? $record->remarks."\n" : '').'Rejected: '.$data['reason']),
                ]);
                Notification::make()->title('Lab dip rejected')->danger()->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLabDips::route('/'),
            'create' => Pages\CreateLabDip::route('/create'),
            'edit' => Pages\EditLabDip::route('/{record}/edit'),
        ];
    }
}
