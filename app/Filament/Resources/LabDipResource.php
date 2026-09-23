<?php

namespace App\Filament\Resources;

use App\Enums\ConsumptionBasis;
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

    protected static ?string $navigationGroup = 'Textile';

    protected static ?string $navigationLabel = 'Lab Dips';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Colour development')->columns(2)->schema([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')->searchable()->preload(),
                Forms\Components\DatePicker::make('request_date')->default(now()),
                Forms\Components\Select::make('sales_order_id')->label('Sales order (optional)')
                    ->relationship('salesOrder', 'so_number')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => (string) ($record->so_number ?? '#'.$record->getKey()))
                    ->searchable()->preload(),
                Forms\Components\Select::make('proforma_invoice_id')->label('Proforma invoice (optional)')
                    ->relationship('proformaInvoice', 'number')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => (string) ($record->number ?? '#'.$record->getKey()))
                    ->searchable()->preload(),
                Forms\Components\TextInput::make('colour')->required()->maxLength(255)
                    ->helperText('Colour name, e.g. Navy Blue.'),
                Forms\Components\TextInput::make('colour_ref')->label('Colour reference')->maxLength(255)
                    ->helperText('Pantone / customer reference.'),
                Forms\Components\TextInput::make('sample_ref')->label('Sample reference')->maxLength(255),
                Forms\Components\TextInput::make('recipe_version')->label('Recipe version')->maxLength(32)->placeholder('e.g. v1'),
                Forms\Components\Textarea::make('recipe')->label('Recipe / specification')->rows(3)->columnSpanFull(),
                Forms\Components\Textarea::make('remarks')->rows(2)->columnSpanFull(),
                // Status advances only via the workflow actions.
            ]),
            Forms\Components\Section::make('Dye-house parameters')->columns(3)->collapsible()
                ->description('The recipe bulk dyeing must reproduce.')
                ->schema([
                    Forms\Components\Select::make('dyeing_process')->label('Dyeing process')->native(false)
                        ->options([
                            'reactive' => 'Reactive', 'disperse' => 'Disperse', 'pigment' => 'Pigment',
                            'direct' => 'Direct', 'vat' => 'Vat', 'acid' => 'Acid',
                        ])->placeholder('—'),
                    Forms\Components\Select::make('dyeing_type')->label('Dyeing type')->native(false)
                        ->options(\App\Enums\DyeingType::options())
                        ->helperText('Per the approved recipe. One-Part: Pretreat → Dye → Wash-off. Two-Part: Pretreat → Dye 1 → Inter. Wash → Dye 2 → Wash-off.'),
                    Forms\Components\TextInput::make('substrate')->label('Substrate / fabric')->maxLength(255)
                        ->placeholder('e.g. Single Jersey Cotton'),
                    Forms\Components\TextInput::make('gsm')->label('GSM')->maxLength(32),
                    Forms\Components\TextInput::make('liquor_ratio')->label('Liquor ratio')->maxLength(32)
                        ->placeholder('e.g. 1:8'),
                    Forms\Components\TextInput::make('temperature')->label('Temperature (°C)')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('dyeing_time')->label('Time (min)')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('ph')->label('pH')->numeric()->minValue(0)->maxValue(14),
                    Forms\Components\TextInput::make('shade_percentage')->label('Shade %')->numeric()->minValue(0),
                    Forms\Components\Group::make()->schema([]),
                    Forms\Components\TextInput::make('fastness_wash')->label('Fastness — wash')->maxLength(16)
                        ->placeholder('e.g. 4-5'),
                    Forms\Components\TextInput::make('fastness_rubbing')->label('Fastness — rubbing')->maxLength(16),
                    Forms\Components\TextInput::make('fastness_light')->label('Fastness — light')->maxLength(16),
                ]),
            Forms\Components\Section::make('Dyeing recipe (dyes & chemicals)')->collapsible()
                ->description('Dyes are dosed as % on weight of fabric; salt/soda/auxiliaries as g/L of the dye bath (fabric weight × liquor ratio). A dyeing order multiplies these by its quantity.')
                ->schema([
                    Forms\Components\Repeater::make('consumptions')->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Forms\Components\Select::make('product_id')->label('Dye / chemical')
                                ->relationship('product', 'name')->searchable()->preload()->required(),
                            Forms\Components\Select::make('basis')->label('Dosing')->native(false)
                                ->options([
                                    ConsumptionBasis::PercentOwf->value => '% owf (dye)',
                                    ConsumptionBasis::GramsPerLitre->value => 'g/L (chemical)',
                                    ConsumptionBasis::PerUnit->value => 'per unit',
                                ])->default(ConsumptionBasis::PercentOwf->value)->required(),
                            Forms\Components\TextInput::make('rate')->label('Rate')->numeric()->minValue(0)->required()
                                ->helperText('e.g. 3 (% owf) or 40 (g/L)'),
                            Forms\Components\TextInput::make('wastage_percent')->label('Wastage %')->numeric()->minValue(0)->default(0),
                        ])->columns(4)->addActionLabel('Add dye / chemical')->columnSpanFull(),
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
                Tables\Actions\Action::make('recipe')->label('Recipe sheet')
                    ->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (LabDip $record): string => route('print.lab-dip', $record))
                    ->openUrlInNewTab(),
                self::transition('submit', 'Submit', LabDipStatus::Draft, LabDipStatus::Submitted, 'heroicon-o-paper-airplane', 'warning'),
                self::transition('toLab', 'Send to lab', LabDipStatus::Submitted, LabDipStatus::InLab, 'heroicon-o-beaker', 'warning'),
                self::transition('internalApprove', 'Internal approve', LabDipStatus::InLab, LabDipStatus::InternalApproved, 'heroicon-o-check', 'info'),
                self::transition('sendToCustomer', 'Send to customer', LabDipStatus::InternalApproved, LabDipStatus::SentToCustomer, 'heroicon-o-envelope', 'warning'),
                self::transition('customerApprove', 'Customer approved', LabDipStatus::SentToCustomer, LabDipStatus::CustomerApproved, 'heroicon-o-check-badge', 'success'),
                self::rejectAction(),
                self::cancelAction(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (LabDip $record): bool => ! $record->status->isTerminal()),
            ])
            ->defaultSort('id', 'desc');
    }

    /** A guarded, manual status transition. Approval states also stamp who/when. */
    private static function transition(string $name, string $label, LabDipStatus $from, LabDipStatus $to, string $icon, string $color): Tables\Actions\Action
    {
        return Tables\Actions\Action::make($name)->label($label)->icon($icon)->color($color)
            ->visible(fn (LabDip $record): bool => $record->status === $from)
            ->requiresConfirmation()
            ->action(function (LabDip $record) use ($to, $label): void {
                $data = ['status' => $to];
                if ($to->isApproved() && $record->approved_at === null) {
                    $data['approved_by'] = \Illuminate\Support\Facades\Auth::id();
                    $data['approved_at'] = now();
                }
                $record->update($data);
                Notification::make()->title($label)->success()->send();
            });
    }

    private static function cancelAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('cancel')->label('Cancel')->icon('heroicon-o-no-symbol')->color('danger')
            ->visible(fn (LabDip $record): bool => ! $record->status->isTerminal())
            ->requiresConfirmation()
            ->action(function (LabDip $record): void {
                $record->update(['status' => LabDipStatus::Cancelled]);
                Notification::make()->title('Lab dip cancelled')->danger()->send();
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
