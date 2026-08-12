<?php

namespace App\Filament\Resources\FixedAssetResource\Pages;

use App\Actions\Accounting\DepreciateAssets;
use App\Exceptions\PostingException;
use App\Filament\Resources\FixedAssetResource;
use App\Models\Company;
use App\Support\CompanyContext;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListFixedAssets extends ListRecords
{
    protected static string $resource = FixedAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('runDepreciation')->label('Run depreciation')
                ->icon('heroicon-o-arrow-trending-down')->color('warning')
                ->form([
                    Forms\Components\Select::make('month')
                        ->options(collect(range(1, 12))->mapWithKeys(fn (int $m): array => [$m => Carbon::create(2000, $m, 1)->format('F')])->all())
                        ->default((int) now()->month)->required(),
                    Forms\Components\TextInput::make('year')->numeric()->default((int) now()->year)->required(),
                ])
                ->action(function (array $data): void {
                    $company = app(CompanyContext::class)->current();
                    if (! $company instanceof Company) {
                        return;
                    }
                    try {
                        $result = app(DepreciateAssets::class)->handle($company, (int) $data['year'], (int) $data['month']);
                        Notification::make()
                            ->title('Depreciation posted')
                            ->body("{$result['count']} asset(s) depreciated · total ".config('erp.currency.symbol').$result['total'])
                            ->success()->send();
                    } catch (PostingException $e) {
                        Notification::make()->title('Cannot post depreciation')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
