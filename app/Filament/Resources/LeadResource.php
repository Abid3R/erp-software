<?php

namespace App\Filament\Resources;

use App\Actions\Crm\ConvertLead;
use App\Enums\LeadStatus;
use App\Filament\Resources\LeadResource\Pages;
use App\Models\Lead;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->whereIn('status', ['new', 'contacted', 'qualified'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Contact name')->required()->maxLength(255),
            Forms\Components\TextInput::make('company_name')->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->maxLength(255),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
            Forms\Components\TextInput::make('source')->maxLength(255)->helperText('e.g. Referral, Website, Trade show'),
            Forms\Components\Select::make('status')->options(LeadStatus::options())->default('new')->required(),
            Forms\Components\Select::make('owner_id')->relationship('owner', 'name')->searchable()->preload()->label('Owner'),
            Forms\Components\Textarea::make('notes')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('company_name')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('email')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('source')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (LeadStatus $state): string => $state->label())
                    ->color(fn (LeadStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('owner.name')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(LeadStatus::options()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('convert')->label('Convert to customer')->icon('heroicon-o-user-plus')->color('success')
                    ->visible(fn (Lead $record): bool => $record->status !== LeadStatus::Converted && $record->status !== LeadStatus::Lost)
                    ->requiresConfirmation()
                    ->action(function (Lead $record): void {
                        $customer = app(ConvertLead::class)->handle($record);
                        Notification::make()->title('Converted — customer '.$customer->name.' created')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
