<?php

namespace App\Filament\Resources;

use App\Enums\DocumentCategory;
use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Central document library (spec: DMS). Lists every uploaded file — standalone or
 * attached to a record — and lets users add general documents. Per-record uploads
 * happen through the DocumentsRelationManager on each business resource.
 */
class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationGroup = 'Documents';

    protected static ?string $navigationLabel = 'Document Library';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required()->maxLength(255),
            Forms\Components\Select::make('category')->options(DocumentCategory::options())
                ->default(DocumentCategory::Other->value)->required(),
            Forms\Components\FileUpload::make('file_path')->label('File')
                ->disk(Document::DISK)->directory('documents')->visibility('private')
                ->storeFileNamesIn('original_name')->maxSize(10240)->required()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('category')->badge()
                    ->formatStateUsing(fn (DocumentCategory $state): string => $state->label())
                    ->color(fn (DocumentCategory $state): string => $state->color()),
                Tables\Columns\TextColumn::make('attached')->label('Attached to')
                    ->state(fn (Document $record): string => $record->attachedTo())
                    ->badge()->color(fn (Document $record): string => $record->documentable_type === null ? 'gray' : 'info'),
                Tables\Columns\TextColumn::make('size')->label('Size')
                    ->formatStateUsing(fn (Document $record): string => $record->humanSize()),
                Tables\Columns\TextColumn::make('uploader.name')->label('Uploaded by')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')->options(DocumentCategory::options()),
            ])
            ->actions([
                Tables\Actions\Action::make('download')->icon('heroicon-o-arrow-down-tray')->color('gray')
                    ->url(fn (Document $record): string => route('documents.download', $record))->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
