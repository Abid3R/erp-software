<?php

namespace App\Filament\RelationManagers;

use App\Enums\DocumentCategory;
use App\Models\Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Reusable DMS panel (spec: DMS). Dropped onto any resource whose model uses the
 * HasDocuments trait — upload, list, download and remove attachments in place.
 * Files store on the private disk; downloads go through the authorised route.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    protected static ?string $icon = 'heroicon-o-paper-clip';

    public function form(Form $form): Form
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('category')->badge()
                    ->formatStateUsing(fn (DocumentCategory $state): string => $state->label())
                    ->color(fn (DocumentCategory $state): string => $state->color()),
                Tables\Columns\TextColumn::make('size')->label('Size')
                    ->formatStateUsing(fn (Document $record): string => $record->humanSize()),
                Tables\Columns\TextColumn::make('uploader.name')->label('Uploaded by')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')->options(DocumentCategory::options()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Upload document')->icon('heroicon-o-arrow-up-tray'),
            ])
            ->actions([
                Tables\Actions\Action::make('download')->icon('heroicon-o-arrow-down-tray')->color('gray')
                    ->url(fn (Document $record): string => route('documents.download', $record))->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
