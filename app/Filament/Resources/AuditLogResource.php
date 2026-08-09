<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use App\Support\CompanyContext;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only audit trail viewer (spec #30). No create/edit/delete — the log is
 * append-only. Scoped to the active company so trails never leak across tenants.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Audit Log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', app(CompanyContext::class)->currentId());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('User')->default('system')->searchable(),
                Tables\Columns\TextColumn::make('event')->badge()->colors([
                    'success' => 'created',
                    'warning' => 'updated',
                    'danger' => 'deleted',
                ]),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Entity')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('auditable_id')->label('ID'),
                Tables\Columns\TextColumn::make('ip_address')->toggleable()->toggledHiddenByDefault(true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')->options([
                    'created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted',
                ]),
            ])
            ->actions([Tables\Actions\ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('user.name')->label('User')->default('system'),
            TextEntry::make('event')->badge(),
            TextEntry::make('auditable_type')->label('Entity')
                ->formatStateUsing(fn (string $state): string => class_basename($state)),
            TextEntry::make('auditable_id')->label('Entity ID'),
            TextEntry::make('ip_address')->label('IP address'),
            KeyValueEntry::make('old_values')->label('Before'),
            KeyValueEntry::make('new_values')->label('After'),
        ])->columns(2);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
