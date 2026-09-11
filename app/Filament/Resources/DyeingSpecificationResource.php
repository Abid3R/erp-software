<?php

namespace App\Filament\Resources;

use App\Enums\ConsumptionBasis;
use App\Filament\Resources\DyeingSpecificationResource\Pages;
use App\Models\DyeingSpecification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Dyeing specification master — one standard dyeing program per dyed-fabric product
 * (process, liquor ratio, temperature, shade, fastness + the dyes/chemicals recipe).
 * Selecting the product on a dyeing process order auto-fills these, and it prints as
 * an operator recipe sheet. The dyeing parallel of Fabric Specifications; the lab dip
 * remains the shade-approval gate and can override it.
 */
class DyeingSpecificationResource extends Resource
{
    protected static ?string $model = DyeingSpecification::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Textile';

    protected static ?string $navigationLabel = 'Dyeing Specifications';

    protected static ?string $modelLabel = 'dyeing specification';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product & recipe')->columns(3)->schema([
                Forms\Components\Select::make('product_id')->label('Product (dyed fabric)')
                    ->relationship('product', 'name')->searchable()->preload()->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('The dyed fabric this dyeing program describes.'),
                Forms\Components\TextInput::make('recipe_number')->label('Recipe no')->maxLength(64),
                Forms\Components\TextInput::make('version')->numeric()->minValue(1)->default(1),
            ]),
            Forms\Components\Section::make('Dyeing parameters')->columns(3)->schema([
                Forms\Components\Select::make('dyeing_process')->label('Dyeing process')->native(false)
                    ->options([
                        'reactive' => 'Reactive', 'disperse' => 'Disperse', 'pigment' => 'Pigment',
                        'direct' => 'Direct', 'vat' => 'Vat', 'acid' => 'Acid',
                    ])->placeholder('—'),
                Forms\Components\TextInput::make('substrate')->maxLength(255)->placeholder('e.g. Single Jersey Cotton'),
                Forms\Components\TextInput::make('gsm')->label('GSM')->maxLength(32),
                Forms\Components\TextInput::make('colour')->maxLength(255),
                Forms\Components\TextInput::make('colour_ref')->label('Colour ref')->maxLength(255),
                Forms\Components\TextInput::make('liquor_ratio')->label('Liquor ratio')->maxLength(32)->placeholder('e.g. 1:8'),
                Forms\Components\TextInput::make('temperature')->label('Temperature (°C)')->numeric()->minValue(0),
                Forms\Components\TextInput::make('dyeing_time')->label('Time (min)')->numeric()->minValue(0),
                Forms\Components\TextInput::make('ph')->label('pH')->numeric()->minValue(0)->maxValue(14),
                Forms\Components\TextInput::make('shade_percentage')->label('Shade %')->numeric()->minValue(0),
                Forms\Components\TextInput::make('fastness_wash')->label('Fastness — wash')->maxLength(16)->placeholder('e.g. 4-5'),
                Forms\Components\TextInput::make('fastness_rubbing')->label('Fastness — rubbing')->maxLength(16),
                Forms\Components\TextInput::make('fastness_light')->label('Fastness — light')->maxLength(16),
                Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
            ]),
            Forms\Components\Section::make('Dyeing recipe (dyes & chemicals)')
                ->description('Dyes as % on weight of fabric; salt/soda/auxiliaries as g/L of the dye bath (fabric weight × liquor ratio). A dyeing order multiplies these by its quantity.')
                ->schema([
                    Forms\Components\Repeater::make('consumptions')->relationship()->hiddenLabel()
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
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('recipe_number')->label('Recipe')->placeholder('—'),
                Tables\Columns\TextColumn::make('version')->label('v')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('approval_status')->label('Approval')->badge()
                    ->color(fn (?string $state): string => $state === 'approved' ? 'success' : 'gray')
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?: 'draft')),
                Tables\Columns\TextColumn::make('dyeing_process')->label('Process')->badge()->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : '—'),
                Tables\Columns\TextColumn::make('liquor_ratio')->label('Liquor')->placeholder('—'),
                Tables\Columns\TextColumn::make('shade_percentage')->label('Shade %')->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')->label('Approve')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (DyeingSpecification $r): bool => $r->approval_status !== 'approved')
                    ->requiresConfirmation()
                    ->action(function (DyeingSpecification $r): void {
                        $r->update(['approval_status' => 'approved', 'approved_by' => \Illuminate\Support\Facades\Auth::id(), 'approved_at' => now()]);
                        \Filament\Notifications\Notification::make()->title('Recipe approved')->success()->send();
                    }),
                Tables\Actions\Action::make('print')->label('Recipe sheet')
                    ->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (DyeingSpecification $record): string => route('print.dyeing-specification', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('product_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDyeingSpecifications::route('/'),
        ];
    }
}
