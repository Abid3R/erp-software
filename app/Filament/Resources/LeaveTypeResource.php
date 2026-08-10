<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveTypeResource\Pages;
use App\Models\LeaveType;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeaveTypeResource extends Resource
{
    protected static ?string $model = LeaveType::class;

    protected static ?string $navigationIcon = 'heroicon-o-sun';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('code')->required()->maxLength(32)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\TextInput::make('annual_quota')->numeric()->default(0)->minValue(0)->suffix('days/year'),
            Forms\Components\Toggle::make('is_paid')->default(true),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('annual_quota')->suffix(' days'),
                Tables\Columns\IconColumn::make('is_paid')->boolean()->label('Paid'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveTypes::route('/'),
            'create' => Pages\CreateLeaveType::route('/create'),
            'edit' => Pages\EditLeaveType::route('/{record}/edit'),
        ];
    }
}
