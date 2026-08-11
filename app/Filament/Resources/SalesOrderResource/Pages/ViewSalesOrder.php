<?php

namespace App\Filament\Resources\SalesOrderResource\Pages;

use App\Filament\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')->icon('heroicon-o-printer')
                ->url(fn (): string => route('print.sales-order', $this->record))->openUrlInNewTab(),
        ];
    }

    public function getTitle(): string
    {
        /** @var SalesOrder $so */
        $so = $this->record;

        return 'SO '.$so->so_number;
    }
}
