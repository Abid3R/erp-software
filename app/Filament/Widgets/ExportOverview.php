<?php

namespace App\Filament\Widgets;

use App\Enums\CommercialInvoiceStatus;
use App\Filament\Resources\CommercialInvoiceResource;
use App\Filament\Resources\ExportShipmentResource;
use App\Filament\Resources\LetterOfCreditResource;
use App\Filament\Resources\ProformaInvoiceResource;
use App\Models\CommercialInvoice;
use App\Models\ExportShipment;
use App\Models\LetterOfCredit;
use App\Models\ProformaInvoice;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Brick\Math\BigDecimal;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Export KPIs for the dashboard — live figures for the PI → LC → shipment →
 * receivable pipeline. LC values are shown in the base currency (foreign amount ×
 * exchange rate) so mixed-currency LCs are comparable. Real numbers only; the
 * whole widget is hidden when there is no export activity yet.
 */
class ExportOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }

        $symbol = config('erp.currency.symbol');
        $precision = (int) config('erp.currency.precision', 2);
        $money = fn (BigDecimal $v): string => $symbol.number_format((float) (string) $v, $precision);

        $activePis = ProformaInvoice::query()->where('company_id', $companyId)
            ->whereIn('status', ['draft', 'sent', 'approved'])->count();

        $activeLcs = LetterOfCredit::query()->where('company_id', $companyId)
            ->whereNotIn('status', ['expired', 'cancelled', 'fully_utilized'])
            ->with(['proformaInvoices.lines', 'proformaInvoices.taxRate'])
            ->get();

        $totalLc = BigDecimal::zero();
        $utilisedLc = BigDecimal::zero();
        foreach ($activeLcs as $lc) {
            $rate = BigDecimal::of($lc->exchange_rate);
            $totalLc = $totalLc->plus(BigDecimal::of($lc->amount)->multipliedBy($rate));
            $utilisedLc = $utilisedLc->plus($lc->allocated()->multipliedBy($rate));
        }
        $remainingLc = $totalLc->minus($utilisedLc);

        $pendingShipments = ExportShipment::query()->where('company_id', $companyId)
            ->whereIn('status', ['draft', 'ready_for_shipment', 'stuffing'])->count();

        $postedCis = CommercialInvoice::query()->where('company_id', $companyId)
            ->where('status', CommercialInvoiceStatus::Posted->value)
            ->with(['lines', 'taxRate', 'shipment'])
            ->get();

        $billed = BigDecimal::zero();
        $shipped = BigDecimal::zero();
        foreach ($postedCis as $ci) {
            $base = $ci->toBase($ci->total());
            $billed = $billed->plus($base);
            if ($ci->shipment !== null && $ci->shipment->status->hasShipped()) {
                $shipped = $shipped->plus($base);
            }
        }

        return [
            Stat::make('Active PIs', (string) $activePis)
                ->color('info')->description('Draft, sent or approved')
                ->icon('heroicon-o-document-text')->url(ProformaInvoiceResource::getUrl()),

            Stat::make('Active LCs', (string) $activeLcs->count())
                ->color('info')->description('Open letters of credit')
                ->icon('heroicon-o-banknotes')->url(LetterOfCreditResource::getUrl()),

            Stat::make('LC value (total)', $money($totalLc))
                ->color('gray')->description('Base value of active LCs')
                ->icon('heroicon-o-scale'),

            Stat::make('LC utilised', $money($utilisedLc))
                ->color('warning')->description('Allocated to proforma invoices')
                ->icon('heroicon-o-chart-pie'),

            Stat::make('LC remaining', $money($remainingLc))
                ->color('success')->description('Still available to allocate')
                ->icon('heroicon-o-clock')->url(LetterOfCreditResource::getUrl()),

            Stat::make('Pending shipments', (string) $pendingShipments)
                ->color($pendingShipments > 0 ? 'warning' : 'success')
                ->description('Not yet shipped')
                ->icon('heroicon-o-globe-asia-australia')->url(ExportShipmentResource::getUrl()),

            Stat::make('Shipped value', $money($shipped))
                ->color('gray')->description('Posted invoices already shipped')
                ->icon('heroicon-o-paper-airplane'),

            Stat::make('Export billed', $money($billed))
                ->color('info')->description('Posted export invoices')
                ->icon('heroicon-o-currency-dollar')->url(CommercialInvoiceResource::getUrl()),
        ];
    }

    /**
     * Respect the Shield permission (as HasWidgetShield does), then additionally
     * hide the whole widget until there is export activity to show.
     */
    public static function canView(): bool
    {
        if (! Filament::auth()->user()?->can(static::getPermissionName())) {
            return false;
        }

        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return false;
        }

        return ProformaInvoice::query()->where('company_id', $companyId)->exists()
            || LetterOfCredit::query()->where('company_id', $companyId)->exists();
    }
}
