<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')->icon('heroicon-o-printer')
                ->url(fn (): string => route('print.purchase-order', $this->record))->openUrlInNewTab(),
        ];
    }

    public function getTitle(): string
    {
        /** @var PurchaseOrder $po */
        $po = $this->record;

        return 'PO '.$po->po_number;
    }
}
